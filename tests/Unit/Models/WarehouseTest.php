<?php

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

uses(TestCase::class, RefreshDatabase::class);

it('belongs to many products with pivot stock', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $warehouse->products()->attach($product->id, ['stock' => 50]);

    expect($warehouse->products)->toHaveCount(1);
    expect($warehouse->products->first()->pivot->stock)->toBe(50);
});

it('syncs products without detaching existing relations', function () {
    $warehouse = Warehouse::factory()->create();
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();

    $warehouse->products()->attach($productA->id, ['stock' => 10]);
    $warehouse->products()->syncWithoutDetaching([$productB->id => ['stock' => 20]]);

    expect($warehouse->products)->toHaveCount(2);

    assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $productA->id,
        'stock' => 10,
    ]);
    assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $productB->id,
        'stock' => 20,
    ]);
});

it('has many stock transfers from', function () {
    $warehouse = Warehouse::factory()->create();
    $otherWarehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    StockTransfer::factory()->count(3)->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $otherWarehouse->id,
        'product_id' => $product->id,
    ]);

    expect($warehouse->stockTransfersFrom)->toHaveCount(3);
});

it('has many stock transfers to', function () {
    $warehouse = Warehouse::factory()->create();
    $otherWarehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    StockTransfer::factory()->count(2)->create([
        'from_warehouse_id' => $otherWarehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
    ]);

    expect($warehouse->stockTransfersTo)->toHaveCount(2);
});

it('generates slug from name', function () {
    $warehouse = Warehouse::factory()->create(['name' => 'Gudang Pusat']);

    expect($warehouse->slug)->toBe('gudang-pusat');
});

it('can soft delete', function () {
    $warehouse = Warehouse::factory()->create();
    $warehouse->delete();

    assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
});
