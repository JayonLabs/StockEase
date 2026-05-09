<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleReturnType;
use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Services\Sale\SaleReturnService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class SaleReturnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $completedSales = Sale::where('status', SaleStatus::Completed->value)
            ->has('saleItems')
            ->get();

        if ($completedSales->isEmpty()) {
            $this->command?->info('No completed sales found. Skipping SaleReturnSeeder.');

            return;
        }

        $reasons = [
            'Produk rusak / kemasan bocor',
            'Produk sudah kadaluarsa',
            'Barang tidak sesuai dengan pesanan',
            'Barang cacat produksi',
            'Kesalahan pengambilan barang oleh kasir',
            'Pelanggan membatalkan pembelian',
            'Kualitas produk tidak sesuai ekspektasi',
            'Ukuran / varian tidak sesuai',
            'Produk tidak berfungsi dengan baik',
            'Jumlah barang yang diterima kurang',
        ];

        // Pick ~30% of completed sales for returns
        $selectedSales = $completedSales->random(
            max(1, (int) ceil($completedSales->count() * 0.30))
        );

        $service = new SaleReturnService;
        $createdCount = 0;

        foreach ($selectedSales as $sale) {
            $sale->load('saleItems', 'saleItems.product');

            if ($sale->saleItems->isEmpty()) {
                continue;
            }

            // Pick 1-2 random sale items to return
            $itemsToReturn = $sale->saleItems->random(rand(1, min(2, $sale->saleItems->count())));

            // Already returned items check
            $alreadyReturnedQty = 0;
            $saleItemId = $itemsToReturn->first()->id;

            $alreadyReturnedQty = SaleReturnItem::where('sale_item_id', $saleItemId)
                ->whereHas('saleReturn', fn ($q) => $q->where('status', SaleReturnStatus::Completed->value))
                ->sum('qty');

            $maxReturnable = $itemsToReturn->first()->qty - $alreadyReturnedQty;

            if ($maxReturnable <= 0) {
                continue;
            }

            $returnQty = rand(1, $maxReturnable);

            // Login as a random admin or cashier
            $user = User::whereIn('role', [Role::Admin->value, Role::Cashier->value])->inRandomOrder()->first();
            if (! $user) {
                continue;
            }
            Auth::loginUsingId($user->id);

            // Determine return type (70% refund, 30% exchange)
            $returnType = fake()->randomElement([SaleReturnType::Refund->value, SaleReturnType::Refund->value, SaleReturnType::Exchange->value, SaleReturnType::Refund->value]);

            // Return date: 1-10 days after sale date
            $returnDate = Carbon::parse($sale->date)->addDays(rand(1, 10));

            // Don't create future returns
            if ($returnDate->isAfter(now())) {
                $returnDate = now()->subDays(rand(0, 3));
            }

            try {
                $return = $service->processReturn($sale, [
                    'return_type' => $returnType,
                    'reason' => fake()->randomElement($reasons),
                    'notes' => fake()->boolean(30) ? fake()->sentence() : null,
                    'items' => [
                        [
                            'sale_item_id' => $itemsToReturn->first()->id,
                            'qty' => $returnQty,
                        ],
                    ],
                ]);

                // Set realistic return_date and timestamps
                $return->update([
                    'return_date' => $returnDate,
                    'created_at' => $returnDate,
                    'updated_at' => $returnDate,
                ]);

                // Also update the related stock log timestamp
                StockLog::where('reference_type', 'SaleReturn')
                    ->where('reference_id', $return->id)
                    ->update([
                        'created_at' => $returnDate,
                        'updated_at' => $returnDate,
                    ]);

                $createdCount++;

            } catch (\Exception $e) {
                $this->command?->warn("Gagal membuat retur untuk sale #{$sale->id}: {$e->getMessage()}");
            }
        }

        $this->command?->info("SaleReturnSeeder: {$createdCount} retur berhasil dibuat.");
    }
}
