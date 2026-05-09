<?php

use App\Models\Product;
use App\Models\StockLog;
use App\Services\Stock\StockLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new StockLogService;
});

it('gets paginated stock logs', function () {
    StockLog::factory()->count(15)->create();

    $result = $this->service->getPaginatedStockLogs([], 10);

    expect($result->total())->toBe(15);
    expect($result->count())->toBe(10);
});

it('filters stock logs by search query on product name', function () {
    $product = Product::factory()->create(['name' => 'Beras']);
    StockLog::factory()->create([
        'product_id' => $product->id,
        'type' => 'in',
    ]);
    StockLog::factory()->create(['type' => 'out']);

    $result = $this->service->getPaginatedStockLogs(['search' => 'Beras']);

    expect($result->total())->toBe(1);
});

it('filters stock logs by search on product sku', function () {
    $product = Product::factory()->create(['sku' => 'BRG-001']);
    StockLog::factory()->create(['product_id' => $product->id]);
    StockLog::factory()->create();

    $result = $this->service->getPaginatedStockLogs(['search' => 'BRG-001']);

    expect($result->total())->toBe(1);
});

it('filters stock logs by search on product barcode', function () {
    $product = Product::factory()->create(['barcode' => '899123456']);
    StockLog::factory()->create(['product_id' => $product->id]);
    StockLog::factory()->create();

    $result = $this->service->getPaginatedStockLogs(['search' => '899123456']);

    expect($result->total())->toBe(1);
});

it('filters stock logs by search on type', function () {
    $product = Product::factory()->create(['name' => 'ItemX', 'sku' => '11111111', 'barcode' => '0000000000000']);
    StockLog::factory()->create([
        'product_id' => $product->id,
        'type' => 'in',
        'reference_type' => 'abc',
        'note' => '',
    ]);
    StockLog::factory()->create([
        'product_id' => $product->id,
        'type' => 'out',
        'reference_type' => 'abc',
        'note' => '',
    ]);

    $result = $this->service->getPaginatedStockLogs(['search' => 'out']);

    expect($result->total())->toBe(1);
});

it('filters stock logs by search on reference type', function () {
    $product = Product::factory()->create(['name' => 'ItemY', 'sku' => '22222222', 'barcode' => '1111111111111']);
    StockLog::factory()->create([
        'product_id' => $product->id,
        'type' => 'in',
        'reference_type' => 'XYZ_PURCHASE',
        'note' => '',
    ]);
    StockLog::factory()->create([
        'product_id' => $product->id,
        'type' => 'in',
        'reference_type' => 'XYZ_SALE',
        'note' => '',
    ]);

    $result = $this->service->getPaginatedStockLogs(['search' => 'PURCHASE']);

    expect($result->total())->toBe(1);
});

it('filters stock logs by search on note', function () {
    $product = Product::factory()->create(['name' => 'ItemZ', 'sku' => '33333333', 'barcode' => '2222222222222']);
    StockLog::factory()->create([
        'product_id' => $product->id,
        'type' => 'in',
        'reference_type' => 'abc',
        'note' => 'CatatanPenambahan123',
    ]);
    StockLog::factory()->create([
        'product_id' => $product->id,
        'type' => 'in',
        'reference_type' => 'abc',
        'note' => 'CatatanPengurangan456',
    ]);

    $result = $this->service->getPaginatedStockLogs(['search' => 'Penambahan']);

    expect($result->total())->toBe(1);
});

it('filters stock logs by date range', function () {
    StockLog::factory()->create(['created_at' => now()->subDays(10)]);
    StockLog::factory()->create(['created_at' => now()]);

    $result = $this->service->getPaginatedStockLogs([
        'start_date' => now()->subDays(2)->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    expect($result->total())->toBe(1);
});

it('only applies date filter when both start and end are provided', function () {
    StockLog::factory()->count(5)->create();

    $result = $this->service->getPaginatedStockLogs(['start_date' => now()->toDateString()]);

    expect($result->total())->toBe(5);
});

it('returns empty when no logs match filters', function () {
    StockLog::factory()->create(['type' => 'in']);

    $result = $this->service->getPaginatedStockLogs(['search' => 'NotFound']);

    expect($result->total())->toBe(0);
});

it('loads product relationship on stock logs', function () {
    $product = Product::factory()->create(['name' => 'Loaded Product']);
    StockLog::factory()->create(['product_id' => $product->id]);

    $result = $this->service->getPaginatedStockLogs([]);

    $log = $result->first();
    expect($log->relationLoaded('product'))->toBeTrue();
    expect($log->product->name)->toBe('Loaded Product');
});

it('orders stock logs by latest first', function () {
    $old = StockLog::factory()->create(['created_at' => now()->subDays(5)]);
    $new = StockLog::factory()->create(['created_at' => now()]);

    $result = $this->service->getPaginatedStockLogs([]);

    expect($result->first()->id)->toBe($new->id);
});
