<?php

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Warehouse\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create(['role' => 'admin']);
    Auth::login($user);
});

it('can get paginated transfers', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    StockTransfer::factory()->count(15)->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
    ]);
    $service = new StockTransferService;

    $results = $service->getPaginatedTransfers([], 10);

    expect($results->total())->toBe(15);
    expect($results->count())->toBe(10);
});

it('can filter transfers by warehouse', function () {
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $product = Product::factory()->create();

    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
    ]);
    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouseB->id,
        'to_warehouse_id' => $warehouseA->id,
        'product_id' => $product->id,
    ]);
    $service = new StockTransferService;

    $results = $service->getPaginatedTransfers(['warehouse_id' => $warehouseA->id], 10);

    expect($results->total())->toBe(2);
});

it('can filter transfers by search', function () {
    $warehouse = Warehouse::factory()->create();
    $productA = Product::factory()->create(['name' => 'Keripik']);
    $productB = Product::factory()->create(['name' => 'Minuman']);

    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'product_id' => $productA->id,
    ]);
    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'product_id' => $productB->id,
    ]);
    $service = new StockTransferService;

    $results = $service->getPaginatedTransfers(['search' => 'Keripik'], 10);

    expect($results->total())->toBe(1);
});

it('can store a stock transfer', function () {
    $warehouseA = Warehouse::factory()->create(['name' => 'Gudang A']);
    $warehouseB = Warehouse::factory()->create(['name' => 'Gudang B']);
    $product = Product::factory()->create(['stock' => 100]);

    $warehouseA->products()->attach($product->id, ['stock' => 50]);
    $service = new StockTransferService;

    $transfer = $service->storeTransfer([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'qty' => 20,
        'note' => 'Test transfer',
        'date' => now()->toDateString(),
    ]);

    expect($transfer->from_warehouse_id)->toBe($warehouseA->id);
    expect($transfer->to_warehouse_id)->toBe($warehouseB->id);
    expect($transfer->qty)->toBe(20);
    expect($transfer->status)->toBe('completed');

    $this->assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouseA->id,
        'product_id' => $product->id,
        'stock' => 30,
    ]);

    $this->assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'stock' => 20,
    ]);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => 20,
        'type' => 'transfer',
        'reference_type' => 'StockTransfer',
        'reference_id' => $transfer->id,
    ]);
});

it('creates stock log when transferring', function () {
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $warehouseA->products()->attach($product->id, ['stock' => 30]);
    $service = new StockTransferService;

    $transfer = $service->storeTransfer([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'qty' => 15,
        'note' => 'Transfer test',
        'date' => now()->toDateString(),
    ]);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => 15,
        'type' => 'transfer',
    ]);
});

it('handles transfer to warehouse that already has the product', function () {
    $warehouseA = Warehouse::factory()->create(['name' => 'Toko A']);
    $warehouseB = Warehouse::factory()->create(['name' => 'Toko B']);
    $product = Product::factory()->create();

    $warehouseA->products()->attach($product->id, ['stock' => 50]);
    $warehouseB->products()->attach($product->id, ['stock' => 30]);
    $service = new StockTransferService;

    $service->storeTransfer([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'qty' => 10,
        'date' => now()->toDateString(),
    ]);

    $this->assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouseA->id,
        'product_id' => $product->id,
        'stock' => 40,
    ]);

    $this->assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'stock' => 40,
    ]);
});
