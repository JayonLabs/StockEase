<?php

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Services\Stock\StockAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create(['role' => 'admin']);
    Auth::login($user);
});

it('can get paginated adjustments', function () {
    $product = Product::factory()->create();
    StockAdjustment::factory()->count(15)->create([
        'product_id' => $product->id,
        'user_id' => Auth::id(),
    ]);
    $service = new StockAdjustmentService;

    $results = $service->getPaginatedAdjustments([], 10);

    expect($results->total())->toBe(15);
    expect($results->count())->toBe(10);
});

it('can store a new stock adjustment', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $data = [
        'product_id' => $product->id,
        'new_stock' => 15,
        'reason' => 'Penambahan stok fisik',
        'date' => now()->toDateString(),
    ];
    $service = new StockAdjustmentService;

    $adjustment = $service->storeAdjustment($data);

    expect($adjustment->old_stock)->toBe(10);
    expect($adjustment->new_stock)->toBe(15);
    expect($adjustment->reason)->toBe('Penambahan stok fisik');

    $product->refresh();
    expect($product->stock)->toBe(15);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => 5, // diff
        'type' => 'adjust',
        'reference_type' => 'StockAdjustment',
        'reference_id' => $adjustment->id,
    ]);
});

it('can handle negative stock adjustment', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $data = [
        'product_id' => $product->id,
        'new_stock' => 7,
        'reason' => 'Barang rusak',
        'date' => now()->toDateString(),
    ];
    $service = new StockAdjustmentService;

    $adjustment = $service->storeAdjustment($data);

    expect($product->refresh()->stock)->toBe(7);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => 3,
        'type' => 'adjust',
    ]);
});
