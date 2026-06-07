<?php

use App\Actions\Product\RestoreProductStock;
use App\Enums\StockLogType;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockLog;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

// ============================================================
// Helpers
// ============================================================

function makeReturnItem(Product $product, int $qty, ?int $saleReturnId = null, ?int $saleItemId = null): SaleReturnItem
{
    $saleReturn = $saleReturnId
        ? SaleReturn::find($saleReturnId)
        : SaleReturn::factory()->create();

    $saleItem = $saleItemId
        ? SaleItem::find($saleItemId)
        : SaleItem::factory()->create(['product_id' => $product->id, 'qty' => $qty, 'price' => 1000]);

    return SaleReturnItem::factory()->create([
        'sale_return_id' => $saleReturn->id,
        'sale_item_id' => $saleItem->id,
        'product_id' => $product->id,
        'qty' => $qty,
        'price' => $saleItem->price,
        'total' => $saleItem->price * $qty,
    ]);
}

// ============================================================
// Global stock (no warehouseId) tests
// ============================================================

it('restores global stock when no warehouse is specified', function () {
    $product = Product::factory()->create(['stock' => 5]);

    $purchaseItem = PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'qty' => 10,
        'remaining_qty' => 5, // 5 already consumed
        'expiry_date' => '2026-12-01',
    ]);

    $returnItem = makeReturnItem($product, 3);

    (new RestoreProductStock)->execute(collect([$returnItem]));

    expect($product->fresh()->stock)->toBe(8);
    expect($purchaseItem->fresh()->remaining_qty)->toBe(8);
});

it('creates a StockLog with type In after restore', function () {
    $product = Product::factory()->create(['stock' => 5]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'qty' => 10,
        'remaining_qty' => 5,
        'expiry_date' => '2026-12-01',
    ]);

    $returnItem = makeReturnItem($product, 2);

    (new RestoreProductStock)->execute(collect([$returnItem]));

    expect(
        StockLog::where('product_id', $product->id)
            ->where('type', StockLogType::In->value)
            ->where('qty', 2)
            ->exists()
    )->toBeTrue();
});

it('restores stock across multiple purchase items in FEFO order', function () {
    $product = Product::factory()->create(['stock' => 0]);

    $batchA = PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'qty' => 5,
        'remaining_qty' => 0,
        'expiry_date' => '2026-06-01',
    ]);
    $batchB = PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'qty' => 5,
        'remaining_qty' => 0,
        'expiry_date' => '2026-09-01',
    ]);

    $returnItem = makeReturnItem($product, 7);

    (new RestoreProductStock)->execute(collect([$returnItem]));

    expect($batchA->fresh()->remaining_qty)->toBe(5);
    expect($batchB->fresh()->remaining_qty)->toBe(2);
    expect($product->fresh()->stock)->toBe(7);
});

it('handles excess qty gracefully by incrementing last purchase item', function () {
    $product = Product::factory()->create(['stock' => 0]);

    $purchaseItem = PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'qty' => 3,
        'remaining_qty' => 0,
        'expiry_date' => '2026-06-01',
    ]);

    // Return more than what was originally consumed — unusual but should not throw
    $returnItem = makeReturnItem($product, 5);

    (new RestoreProductStock)->execute(collect([$returnItem]));

    // batchA can absorb 3, remaining 2 goes to lastItem increment
    expect($purchaseItem->fresh()->remaining_qty)->toBe(5);
    expect($product->fresh()->stock)->toBe(5);
});

it('restores stock for multiple return items at once', function () {
    $product = Product::factory()->create(['stock' => 2]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'qty' => 10,
        'remaining_qty' => 2,
        'expiry_date' => '2026-12-01',
    ]);

    $returnItemA = makeReturnItem($product, 2);
    $returnItemB = makeReturnItem($product, 3);

    (new RestoreProductStock)->execute(collect([$returnItemA, $returnItemB]));

    expect($product->fresh()->stock)->toBe(7);
});

// ============================================================
// Warehouse-specific tests
// ============================================================

it('restores warehouse pivot stock when warehouseId is provided', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 5]);
    $warehouse->products()->syncWithoutDetaching([$product->id => ['stock' => 5]]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty' => 10,
        'remaining_qty' => 5,
        'expiry_date' => '2026-12-01',
    ]);

    $returnItem = makeReturnItem($product, 3);

    (new RestoreProductStock)->execute(collect([$returnItem]), $warehouse->id);

    $warehouseStock = DB::table('warehouse_product')
        ->where('warehouse_id', $warehouse->id)
        ->where('product_id', $product->id)
        ->value('stock');

    expect($warehouseStock)->toBe(8);
    expect($product->fresh()->stock)->toBe(8);
});

it('only restores purchase items belonging to the specified warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 5]);
    $warehouse->products()->syncWithoutDetaching([$product->id => ['stock' => 5]]);
    $other->products()->syncWithoutDetaching([$product->id => ['stock' => 0]]);

    $warehouseBatch = PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty' => 10,
        'remaining_qty' => 5,
        'expiry_date' => '2026-06-01',
    ]);
    $otherBatch = PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $other->id,
        'qty' => 10,
        'remaining_qty' => 2,
        'expiry_date' => '2026-06-01',
    ]);

    $returnItem = makeReturnItem($product, 3);

    (new RestoreProductStock)->execute(collect([$returnItem]), $warehouse->id);

    expect($warehouseBatch->fresh()->remaining_qty)->toBe(8);
    expect($otherBatch->fresh()->remaining_qty)->toBe(2); // untouched
});

it('syncs global stock from all warehouses after warehouse restore', function () {
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);
    $warehouseA->products()->syncWithoutDetaching([$product->id => ['stock' => 5]]);
    $warehouseB->products()->syncWithoutDetaching([$product->id => ['stock' => 5]]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouseA->id,
        'qty' => 10,
        'remaining_qty' => 5,
        'expiry_date' => '2026-12-01',
    ]);

    $returnItem = makeReturnItem($product, 2);

    (new RestoreProductStock)->execute(collect([$returnItem]), $warehouseA->id);

    // warehouseA=7, warehouseB=5 → global=12
    expect($product->fresh()->stock)->toBe(12);
});

it('updates product expiry_date after restoring stock', function () {
    $product = Product::factory()->create(['expiry_date' => null]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'qty' => 10,
        'remaining_qty' => 5,
        'expiry_date' => '2026-06-15',
    ]);

    $returnItem = makeReturnItem($product, 2);

    (new RestoreProductStock)->execute(collect([$returnItem]));

    expect($product->fresh()->expiry_date->toDateString())->toBe('2026-06-15');
});
