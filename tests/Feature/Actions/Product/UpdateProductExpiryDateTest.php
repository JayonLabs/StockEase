<?php

use App\Actions\Product\UpdateProductExpiryDate;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('sets expiry_date to the earliest expiring purchase item', function () {
    $product = Product::factory()->create(['expiry_date' => null]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'remaining_qty' => 5,
        'expiry_date' => '2026-12-01',
    ]);
    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'remaining_qty' => 5,
        'expiry_date' => '2026-06-15',
    ]);
    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'remaining_qty' => 5,
        'expiry_date' => '2027-01-10',
    ]);

    (new UpdateProductExpiryDate)->execute($product);

    expect($product->fresh()->expiry_date->toDateString())->toBe('2026-06-15');
});

it('sets expiry_date to null when no purchase items have remaining stock', function () {
    $product = Product::factory()->create(['expiry_date' => '2026-06-01']);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'remaining_qty' => 0,
        'expiry_date' => '2026-06-01',
    ]);

    (new UpdateProductExpiryDate)->execute($product);

    expect($product->fresh()->expiry_date)->toBeNull();
});

it('sets expiry_date to null when product has no purchase items', function () {
    $product = Product::factory()->create(['expiry_date' => '2026-06-01']);

    (new UpdateProductExpiryDate)->execute($product);

    expect($product->fresh()->expiry_date)->toBeNull();
});

it('places batches with null expiry_date AFTER dated batches (NULLs last ordering)', function () {
    $product = Product::factory()->create(['expiry_date' => null]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'remaining_qty' => 5,
        'expiry_date' => '2026-06-15',
    ]);
    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'remaining_qty' => 5,
        'expiry_date' => null,
    ]);

    (new UpdateProductExpiryDate)->execute($product);

    // orderByRaw('expiry_date IS NULL, expiry_date ASC') → IS NULL=0 first, IS NULL=1 last
    // So earliest dated batch wins over a null-expiry batch
    expect($product->fresh()->expiry_date->toDateString())->toBe('2026-06-15');
});

it('skips purchase items with zero remaining_qty when selecting earliest expiry', function () {
    $product = Product::factory()->create(['expiry_date' => null]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'remaining_qty' => 0,
        'expiry_date' => '2025-01-01',
    ]);
    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'remaining_qty' => 3,
        'expiry_date' => '2026-09-01',
    ]);

    (new UpdateProductExpiryDate)->execute($product);

    expect($product->fresh()->expiry_date->toDateString())->toBe('2026-09-01');
});

it('restricts to warehouse purchase items when warehouseId is provided', function () {
    $warehouse = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $product = Product::factory()->create(['expiry_date' => null]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $other->id,
        'remaining_qty' => 5,
        'expiry_date' => '2026-01-01',
    ]);
    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'remaining_qty' => 5,
        'expiry_date' => '2026-11-01',
    ]);

    (new UpdateProductExpiryDate)->execute($product, $warehouse->id);

    expect($product->fresh()->expiry_date->toDateString())->toBe('2026-11-01');
});

it('sets expiry_date to null when warehouse has no items with remaining stock', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['expiry_date' => '2026-06-01']);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'remaining_qty' => 0,
        'expiry_date' => '2026-06-01',
    ]);

    (new UpdateProductExpiryDate)->execute($product, $warehouse->id);

    expect($product->fresh()->expiry_date)->toBeNull();
});
