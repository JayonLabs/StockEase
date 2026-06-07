<?php

use App\Actions\Product\ReduceProductStock;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

function makeProductWithPurchaseItems(Warehouse $warehouse, int $stock, int $purchaseItemQty, float $price = 1000): Product
{
    $product = Product::factory()->create(['stock' => $stock]);
    $warehouse->products()->syncWithoutDetaching([$product->id => ['stock' => $stock]]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty' => $purchaseItemQty,
        'remaining_qty' => $purchaseItemQty,
        'price' => $price,
    ]);

    return $product;
}

// ============================================================
// Correctness Tests
// ============================================================

it('reduces warehouse stock for a single sale item', function () {
    $warehouse = Warehouse::factory()->create();
    $product = makeProductWithPurchaseItems($warehouse, 10, 10);
    $sale = Sale::factory()->create(['warehouse_id' => $warehouse->id, 'status' => 'completed']);
    $saleItem = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty' => 3,
        'price' => 1000,
    ]);

    (new ReduceProductStock)->execute(collect([$saleItem]), $warehouse->id);

    expect(
        DB::table('warehouse_product')
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->value('stock')
    )->toBe(7);

    expect($product->fresh()->stock)->toBe(7);
});

it('reduces warehouse stock for multiple sale items', function () {
    $warehouse = Warehouse::factory()->create();
    $productA = makeProductWithPurchaseItems($warehouse, 10, 10);
    $productB = makeProductWithPurchaseItems($warehouse, 8, 8);

    $sale = Sale::factory()->create(['warehouse_id' => $warehouse->id, 'status' => 'completed']);
    $itemA = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $productA->id, 'warehouse_id' => $warehouse->id, 'qty' => 4, 'price' => 1000]);
    $itemB = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $productB->id, 'warehouse_id' => $warehouse->id, 'qty' => 2, 'price' => 1000]);

    (new ReduceProductStock)->execute(collect([$itemA, $itemB]), $warehouse->id);

    expect($productA->fresh()->stock)->toBe(6);
    expect($productB->fresh()->stock)->toBe(6);
});

it('throws exception when warehouse stock is insufficient', function () {
    $warehouse = Warehouse::factory()->create();
    $product = makeProductWithPurchaseItems($warehouse, 2, 2);
    $sale = Sale::factory()->create(['warehouse_id' => $warehouse->id, 'status' => 'completed']);
    $saleItem = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'qty' => 5, 'price' => 1000]);

    expect(fn () => (new ReduceProductStock)->execute(collect([$saleItem]), $warehouse->id))
        ->toThrow(Exception::class);
});

// ============================================================
// N+1 Regression — PERF-02
// ============================================================

it('issues no per-product stockInWarehouse queries when reducing stock for multiple items', function () {
    $warehouse = Warehouse::factory()->create();
    $productA = makeProductWithPurchaseItems($warehouse, 10, 10);
    $productB = makeProductWithPurchaseItems($warehouse, 8, 8);
    $productC = makeProductWithPurchaseItems($warehouse, 6, 6);

    $sale = Sale::factory()->create(['warehouse_id' => $warehouse->id, 'status' => 'completed']);
    $itemA = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $productA->id, 'warehouse_id' => $warehouse->id, 'qty' => 2, 'price' => 1000]);
    $itemB = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $productB->id, 'warehouse_id' => $warehouse->id, 'qty' => 1, 'price' => 1000]);
    $itemC = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $productC->id, 'warehouse_id' => $warehouse->id, 'qty' => 1, 'price' => 1000]);

    DB::enableQueryLog();

    (new ReduceProductStock)->execute(collect([$itemA, $itemB, $itemC]), $warehouse->id);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $stockInWarehouseQueries = collect($queries)->filter(
        fn ($q) => str_contains($q['query'], 'pivot_product_id')
    );

    expect($stockInWarehouseQueries)->toHaveCount(0);

    expect($productA->fresh()->stock)->toBe(8);
    expect($productB->fresh()->stock)->toBe(7);
    expect($productC->fresh()->stock)->toBe(5);
});
