<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\StockAlertNotification;
use App\Services\Stock\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{admin: User, service: StockAdjustmentService, warehouseModel: Warehouse} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->service = new StockAdjustmentService;
    $this->warehouseModel = Warehouse::factory()->create();
    $this->actingAs($this->admin);
});

it('triggers notification when stock adjusted below alert threshold via service', function () {
    /** @var TestCase&object{admin: User, service: StockAdjustmentService, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create(['alert_stock' => 20]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $this->service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 10,
        'reason' => 'Stock opname — mismatch found',
        'date' => now()->toDateString(),
    ]);

    $product->refresh();
    expect($product->stock)->toBe(10);

    Notification::assertSentTo(
        [$this->admin],
        StockAlertNotification::class,
        function ($notification) use ($product) {
            $data = $notification->toArray($this->admin);

            return $data['product_id'] === $product->id
                && $data['current_stock'] === 10;
        }
    );
});

it('triggers notification when stock adjusted to exactly alert stock', function () {
    /** @var TestCase&object{admin: User, service: StockAdjustmentService, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create(['alert_stock' => 10]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $this->service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 10,
        'reason' => 'Adjusted to exactly alert level',
        'date' => now()->toDateString(),
    ]);

    $product->refresh();
    expect($product->stock)->toBe(10);

    Notification::assertSentTo(
        [$this->admin],
        StockAlertNotification::class
    );
});

it('does not trigger notification when stock adjusted down but still above alert', function () {
    /** @var TestCase&object{service: StockAdjustmentService, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create(['alert_stock' => 10]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $this->service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 50,
        'reason' => 'Reduced but still safe',
        'date' => now()->toDateString(),
    ]);

    $product->refresh();
    expect($product->stock)->toBe(50);

    Notification::assertNothingSent();
});

it('does not trigger notification when stock adjusted up', function () {
    /** @var TestCase&object{service: StockAdjustmentService, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create(['alert_stock' => 10]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 5]);
    $product->syncStockFromWarehouses();

    $this->service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 200,
        'reason' => 'Found extra stock during opname',
        'date' => now()->toDateString(),
    ]);

    $product->refresh();
    expect($product->stock)->toBe(200);

    Notification::assertNothingSent();
});

it('creates stock log with correct type for adjustment', function () {
    /** @var TestCase&object{service: StockAdjustmentService, warehouseModel: Warehouse} $this */
    $product = Product::factory()->create(['alert_stock' => 20]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $this->service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 80,
        'reason' => 'Rusak',
        'date' => now()->toDateString(),
    ]);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouseModel->id,
        'type' => 'adjust',
        'qty' => 20,
        'reference_type' => 'StockAdjustment',
    ]);
});

it('creates stock adjustment record with correct data', function () {
    /** @var TestCase&object{service: StockAdjustmentService, warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $this->service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 60,
        'reason' => 'Stock opname bulanan',
        'date' => '2026-05-01',
    ]);

    $this->assertDatabaseHas('stock_adjustments', [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'old_stock' => 100,
        'new_stock' => 60,
        'reason' => 'Stock opname bulanan',
        'date' => '2026-05-01',
    ]);
});

it('includes notification data with correct stock and alert values', function () {
    /** @var TestCase&object{admin: User, service: StockAdjustmentService, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create([
        'name' => 'Beras Premium',
        'alert_stock' => 20,
    ]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 50]);
    $product->syncStockFromWarehouses();

    $this->service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 5,
        'reason' => 'Missing items after audit',
        'date' => now()->toDateString(),
    ]);

    $product->refresh();

    Notification::assertSentTo(
        [$this->admin],
        StockAlertNotification::class,
        function ($notification) use ($product) {
            $data = $notification->toArray($this->admin);

            return $data['product_id'] === $product->id
                && $data['product_name'] === 'Beras Premium'
                && $data['current_stock'] === 5
                && $data['alert_level'] === 20
                && str_contains($data['message'], 'Beras Premium');
        }
    );
});
