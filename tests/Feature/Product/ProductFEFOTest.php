<?php

use App\Actions\Product\ReduceProductStock;
use App\Actions\Product\UpdateProductExpiryDate;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('updates product expiry date correctly based on FEFO after multiple purchases and sales', function () {
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    // Create product
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'stock' => 0,
        'expiry_date' => null,
    ]);

    // Supplier
    $supplier = Supplier::factory()->create();

    // Purchase 1 (Expired 10 Jan) = Qty 10
    $purchase1 = Purchase::create([
        'supplier_id' => $supplier->id,
        'user_id' => User::factory()->create()->id,
        'total' => 1000,
        'date' => now()->format('Y-m-d'),
    ]);
    PurchaseItem::create([
        'purchase_id' => $purchase1->id,
        'product_id' => $product->id,
        'qty' => 10,
        'remaining_qty' => 10,
        'price' => 100,
        'expiry_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
    ]);
    $product->increment('stock', 10);
    resolve(UpdateProductExpiryDate::class)->execute($product);

    $product->refresh();
    expect($product->expiry_date->format('Y-m-d'))->toBe(Carbon::now()->addDays(10)->format('Y-m-d'));

    // Purchase 2 (Expired 20 Jan) = Qty 10
    $purchase2 = Purchase::create([
        'supplier_id' => $supplier->id,
        'user_id' => User::factory()->create()->id,
        'total' => 1000,
        'date' => now()->format('Y-m-d'),
    ]);
    PurchaseItem::create([
        'purchase_id' => $purchase2->id,
        'product_id' => $product->id,
        'qty' => 10,
        'remaining_qty' => 10,
        'price' => 100,
        'expiry_date' => Carbon::now()->addDays(20)->format('Y-m-d'),
    ]);
    $product->increment('stock', 10);
    resolve(UpdateProductExpiryDate::class)->execute($product);

    // Product expiry should still be 10 Jan because it's the earliest batch physically available
    $product->refresh();
    expect($product->expiry_date->format('Y-m-d'))->toBe(Carbon::now()->addDays(10)->format('Y-m-d'));

    // Sale of 10 items (This should consume the whole Purchase 1)
    $sale = Sale::factory()->create();
    $saleItem = new SaleItem([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 10,
        'price' => 200,
    ]);
    // The logic inside Product::reduceStockFromSaleItems saves the items, wait, we need to pass a collection
    resolve(ReduceProductStock::class)->execute(collect([$saleItem]));

    $product->refresh();
    // Stock should be 10, and tracking the second purchase expiry (20 Jan)
    expect($product->stock)->toBe(10);
    expect($product->expiry_date->format('Y-m-d'))->toBe(Carbon::now()->addDays(20)->format('Y-m-d'));

    // Verify the remaining_qty of purchase items
    $pItem1 = PurchaseItem::where('purchase_id', $purchase1->id)->first();
    $pItem2 = PurchaseItem::where('purchase_id', $purchase2->id)->first();

    expect($pItem1->remaining_qty)->toBe(0);
    expect($pItem2->remaining_qty)->toBe(10);
});
