<?php

use App\Models\Product;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use App\Services\Stock\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->service = new StockAdjustmentService;
    $this->actingAs($this->admin);
});

it('triggers notification when stock adjusted below alert threshold via service', function () {
    Notification::fake();

    $product = Product::factory()->create(['stock' => 100, 'alert_stock' => 20]);

    $this->service->storeAdjustment([
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
    Notification::fake();

    $product = Product::factory()->create(['stock' => 100, 'alert_stock' => 10]);

    $this->service->storeAdjustment([
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
    Notification::fake();

    $product = Product::factory()->create(['stock' => 100, 'alert_stock' => 10]);

    $this->service->storeAdjustment([
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
    Notification::fake();

    $product = Product::factory()->create(['stock' => 5, 'alert_stock' => 10]);

    $this->service->storeAdjustment([
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
    $product = Product::factory()->create(['stock' => 100, 'alert_stock' => 20]);

    $this->service->storeAdjustment([
        'product_id' => $product->id,
        'new_stock' => 80,
        'reason' => 'Rusak',
        'date' => now()->toDateString(),
    ]);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'type' => 'adjust',
        'qty' => 20,
        'reference_type' => 'StockAdjustment',
    ]);
});

it('creates stock adjustment record with correct data', function () {
    $product = Product::factory()->create(['stock' => 100]);

    $this->service->storeAdjustment([
        'product_id' => $product->id,
        'new_stock' => 60,
        'reason' => 'Stock opname bulanan',
        'date' => '2026-05-01',
    ]);

    $this->assertDatabaseHas('stock_adjustments', [
        'product_id' => $product->id,
        'old_stock' => 100,
        'new_stock' => 60,
        'reason' => 'Stock opname bulanan',
        'date' => '2026-05-01',
    ]);
});

it('includes notification data with correct stock and alert values', function () {
    Notification::fake();

    $product = Product::factory()->create([
        'name' => 'Beras Premium',
        'stock' => 50,
        'alert_stock' => 20,
    ]);

    $this->service->storeAdjustment([
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
