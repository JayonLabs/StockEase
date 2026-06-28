<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Report\ProductMovementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{service: ProductMovementService} $this */
    $this->service = new ProductMovementService;
});

it('returns fast moving products sorted by qty sold', function () {
    /** @var object{service: ProductMovementService} $this */
    $product1 = Product::factory()->create(['name' => 'Fast Item', 'sku' => 'FAST', 'stock' => 20]);
    $product2 = Product::factory()->create(['name' => 'Slow Item', 'sku' => 'SLOW', 'stock' => 30]);
    $sale = Sale::factory()->create([
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product1->id,
        'qty' => 10,
        'price' => 5000,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product2->id,
        'qty' => 2,
        'price' => 5000,
    ]);

    $fastMoving = $this->service->getFastMovingProducts(now()->toDateString(), now()->toDateString(), 10);

    expect($fastMoving)->toHaveCount(2);
    expect($fastMoving->first()['product_name'])->toBe('Fast Item');
    expect($fastMoving->first()['total_qty_sold'])->toBe(10);
});

it('returns fast moving products with revenue', function () {
    /** @var object{service: ProductMovementService} $this */
    $product = Product::factory()->create(['name' => 'Revenue Product', 'sku' => 'REV']);
    $sale = Sale::factory()->create([
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 5,
        'price' => 2000,
    ]);

    $fastMoving = $this->service->getFastMovingProducts(now()->toDateString(), now()->toDateString());

    expect($fastMoving->first()['total_revenue'])->toBe(10000.0);
});

it('respects limit parameter for fast moving products', function () {
    /** @var object{service: ProductMovementService} $this */
    Product::factory()->count(5)->create();
    $sale = Sale::factory()->create([
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);

    $fastMoving = $this->service->getFastMovingProducts(now()->toDateString(), now()->toDateString(), 3);

    expect($fastMoving->count())->toBeLessThanOrEqual(3);
});

it('returns slow moving products sorted by least sales', function () {
    /** @var object{service: ProductMovementService} $this */
    $product1 = Product::factory()->create(['name' => 'Unsold Item', 'stock' => 15]);
    $product2 = Product::factory()->create(['name' => 'Rare Item', 'stock' => 10]);
    $sale = Sale::factory()->create([
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product2->id,
        'qty' => 1,
        'price' => 10000,
    ]);

    $slowMoving = $this->service->getSlowMovingProducts(now()->toDateString(), now()->toDateString(), 10);

    $firstUnsold = $slowMoving->firstWhere('product_name', 'Unsold Item');
    expect($firstUnsold)->not->toBeNull();
    expect($firstUnsold['total_qty_sold'])->toBe(0);
});

it('excludes products with zero stock from slow moving', function () {
    /** @var object{service: ProductMovementService} $this */
    Product::factory()->create(['name' => 'No Stock', 'stock' => 0]);
    Product::factory()->create(['name' => 'Has Stock', 'stock' => 10]);

    $slowMoving = $this->service->getSlowMovingProducts(now()->toDateString(), now()->toDateString());

    $noStockItem = $slowMoving->firstWhere('product_name', 'No Stock');
    expect($noStockItem)->toBeNull();
});

it('builds chart data from fast and slow moving collections', function () {
    /** @var object{service: ProductMovementService} $this */
    $fastMoving = collect([
        ['product_name' => 'Fast A', 'total_qty_sold' => 50],
    ]);

    $slowMoving = collect([
        ['product_name' => 'Slow A', 'total_qty_sold' => 2, 'current_stock' => 30],
    ]);

    $chartData = $this->service->buildChartData($fastMoving, $slowMoving);

    expect($chartData['fast'])->toHaveCount(1);
    expect($chartData['fast'][0]['name'])->toBe('Fast A');
    expect($chartData['fast'][0]['qty'])->toBe(50);
    expect($chartData['slow'])->toHaveCount(1);
    expect($chartData['slow'][0]['name'])->toBe('Slow A');
    expect($chartData['slow'][0]['stock'])->toBe(30);
});

it('gets summary statistics', function () {
    /** @var object{service: ProductMovementService} $this */
    $product1 = Product::factory()->create(['name' => 'Product 1', 'stock' => 10]);
    $product2 = Product::factory()->create(['name' => 'Product 2', 'stock' => 5]);
    Product::factory()->create(['name' => 'Product 3', 'stock' => 0]);
    $sale = Sale::factory()->create([
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product1->id,
        'qty' => 3,
        'price' => 5000,
    ]);

    $stats = $this->service->getSummaryStats(now()->toDateString(), now()->toDateString());

    expect($stats['total_products_analyzed'])->toBe(3);
    expect($stats['total_qty_sold'])->toBe(3);
    expect($stats['fast_moving_count'])->toBe(1);
    expect($stats['unsold_products_count'])->toBe(1); // product2 has stock > 0 but not sold
});

it('returns zero stats when no data exists', function () {
    /** @var object{service: ProductMovementService} $this */
    $stats = $this->service->getSummaryStats(now()->toDateString(), now()->toDateString());

    expect($stats['total_qty_sold'])->toBe(0);
    expect($stats['fast_moving_count'])->toBe(0);
});
