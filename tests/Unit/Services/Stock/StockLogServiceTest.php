<?php

use App\Models\Product;
use App\Models\StockLog;
use App\Services\Stock\StockLogService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{service: StockLogService} $this */
    $this->service = new StockLogService;
});

it('gets paginated stock logs', function () {
    /** @var object{service: StockLogService} $this */
    StockLog::factory()->count(15)->create();

    $result = $this->service->getPaginatedStockLogs([], 10);

    expect($result->total())->toBe(15);
    expect($result->count())->toBe(10);
});

it('filters stock logs by search query on product name', function () {
    /** @var object{service: StockLogService} $this */
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
    /** @var object{service: StockLogService} $this */
    $product = Product::factory()->create(['sku' => 'BRG-001']);
    StockLog::factory()->create(['product_id' => $product->id]);
    StockLog::factory()->create();

    $result = $this->service->getPaginatedStockLogs(['search' => 'BRG-001']);

    expect($result->total())->toBe(1);
});

it('filters stock logs by search on product barcode', function () {
    /** @var object{service: StockLogService} $this */
    $product = Product::factory()->create(['barcode' => '899123456']);
    StockLog::factory()->create(['product_id' => $product->id]);
    StockLog::factory()->create();

    $result = $this->service->getPaginatedStockLogs(['search' => '899123456']);

    expect($result->total())->toBe(1);
});

it('filters stock logs by search on type', function () {
    /** @var object{service: StockLogService} $this */
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
    /** @var object{service: StockLogService} $this */
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
    /** @var object{service: StockLogService} $this */
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
    /** @var object{service: StockLogService} $this */
    StockLog::factory()->create(['created_at' => now()->subDays(10)]);
    StockLog::factory()->create(['created_at' => now()]);

    $result = $this->service->getPaginatedStockLogs([
        'start_date' => now()->subDays(2)->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    expect($result->total())->toBe(1);
});

it('only applies date filter when both start and end are provided', function () {
    /** @var object{service: StockLogService} $this */
    StockLog::factory()->count(5)->create();

    $result = $this->service->getPaginatedStockLogs(['start_date' => now()->toDateString()]);

    expect($result->total())->toBe(5);
});

it('returns empty when no logs match filters', function () {
    /** @var object{service: StockLogService} $this */
    StockLog::factory()->create(['type' => 'in']);

    $result = $this->service->getPaginatedStockLogs(['search' => 'NotFound']);

    expect($result->total())->toBe(0);
});

it('loads product relationship on stock logs', function () {
    /** @var object{service: StockLogService} $this */
    $product = Product::factory()->create(['name' => 'Loaded Product']);
    StockLog::factory()->create(['product_id' => $product->id]);

    $result = $this->service->getPaginatedStockLogs([]);

    $log = $result->first();
    expect($log->relationLoaded('product'))->toBeTrue();
    expect($log->product->name)->toBe('Loaded Product');
});

it('orders stock logs by latest first', function () {
    /** @var object{service: StockLogService} $this */
    $old = StockLog::factory()->create(['created_at' => now()->subDays(5)]);
    $new = StockLog::factory()->create(['created_at' => now()]);

    $result = $this->service->getPaginatedStockLogs([]);

    expect($result->first()->id)->toBe($new->id);
});

it('filters stock logs by exact numeric reference_id', function () {
    /** @var object{service: StockLogService} $this */
    $product = Product::factory()->create(['name' => 'Item', 'sku' => '44444444', 'barcode' => '3333333333333']);
    $log1 = StockLog::factory()->create([
        'product_id' => $product->id,
        'reference_id' => 123,
        'reference_type' => 'purchase',
    ]);
    StockLog::factory()->create([
        'product_id' => $product->id,
        'reference_id' => 1234,
        'reference_type' => 'purchase',
    ]);

    $result = $this->service->getPaginatedStockLogs(['search' => '123']);

    expect($result->total())->toBe(1);
    expect($result->first()->id)->toBe($log1->id);
});
