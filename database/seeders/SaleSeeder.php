<?php

namespace Database\Seeders;

use App\Actions\Product\ReduceProductStock;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $products = Product::where('stock', '>', 0)->get();

        if ($users->isEmpty() || $products->isEmpty()) {
            return;
        }

        $reduceProductStock = resolve(ReduceProductStock::class);

        // Create sales for the last 60 days
        for ($i = 60; $i >= 0; $i -= rand(1, 3)) {
            $salesPerDay = rand(3, 10);

            for ($j = 0; $j < $salesPerDay; $j++) {
                DB::transaction(function () use ($i, $users, $products, $reduceProductStock) {
                    $date = Carbon::now()->subtract('days', $i)->addHours(rand(8, 20))->addMinutes(rand(0, 59));

                    $sale = Sale::create([
                        'user_id' => $users->random()->id,
                        'customer_name' => fake()->name(),
                        'total' => 0,
                        'total_cost' => 0,
                        'payment_method' => fake()->randomElement([PaymentMethod::Cash->value, PaymentMethod::Qris->value]),
                        'paid' => 0,
                        'change' => 0,
                        'date' => $date->format('Y-m-d'),
                        'status' => SaleStatus::Completed->value,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    $itemsCount = rand(1, 4);
                    $selectedProducts = $products->random($itemsCount);
                    $saleItems = collect();

                    foreach ($selectedProducts as $product) {
                        $qty = rand(1, 3);

                        // Ensure enough stock
                        if ($product->stock < $qty) {
                            continue;
                        }

                        $saleItem = SaleItem::create([
                            'sale_id' => $sale->id,
                            'product_id' => $product->id,
                            'qty' => $qty,
                            'price' => $product->selling_price,
                            'cost_price' => 0, // Will be updated by ReduceProductStock
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);

                        $saleItems->push($saleItem);
                        $product->stock -= $qty; // Update local object without saving to DB, Action handles DB
                    }

                    if ($saleItems->isNotEmpty()) {
                        // We use the action to handle real stock reduction from PurchaseItems and cost calculation
                        // But wait, the action decrements stock AGAIN.
                        // Let's NOT decrement manually above, just check.

                        // Actually, let's just create the items and let the Action do its work.
                        // I need to reload items to be sure
                        $reduceProductStock->execute($saleItems);

                        $sale->refresh();
                        $sale->update([
                            'paid' => ceil($sale->total / 1000) * 1000, // Round up to nearest 1000 for cash
                            'change' => (ceil($sale->total / 1000) * 1000) - $sale->total,
                            'created_at' => $date,
                            'updated_at' => $date,
                        ]);
                    } else {
                        $sale->delete();
                    }
                });

                // Refresh products list to get updated stock
                $products = Product::where('stock', '>', 0)->get();
            }
        }
    }
}
