<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Report\ProfitLossReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ProfitLossReportService;
});

it('returns zero summary when no completed sales exist in date range', function () {
    $summary = $this->service->getProfitLossSummary(now()->toDateString(), now()->toDateString());

    expect($summary['total_revenue'])->toEqual(0.0);
    expect($summary['total_cost'])->toEqual(0.0);
    expect($summary['gross_profit'])->toEqual(0.0);
    expect($summary['profit_margin'])->toEqual(0.0);
});

it('calculates profit loss summary correctly', function () {
    $product = Product::factory()->create();
    Sale::factory()->create([
        'total' => 100000,
        'total_cost' => 60000,
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);

    $summary = $this->service->getProfitLossSummary(now()->toDateString(), now()->toDateString());

    expect($summary['total_revenue'])->toBe(100000.0);
    expect($summary['total_cost'])->toBe(60000.0);
    expect($summary['gross_profit'])->toBe(40000.0);
    expect($summary['profit_margin'])->toBe(40.0);
});

it('excludes non-completed sales from summary', function () {
    Sale::factory()->create([
        'total' => 100000,
        'total_cost' => 60000,
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);
    Sale::factory()->create([
        'total' => 50000,
        'total_cost' => 30000,
        'status' => 'draft',
        'date' => now()->toDateString(),
    ]);

    $summary = $this->service->getProfitLossSummary(now()->toDateString(), now()->toDateString());

    expect($summary['total_revenue'])->toBe(100000.0);
    expect($summary['gross_profit'])->toBe(40000.0);
});

it('only includes sales within date range for summary', function () {
    Sale::factory()->create([
        'total' => 100000,
        'total_cost' => 60000,
        'status' => 'completed',
        'date' => now()->subDays(10)->toDateString(),
    ]);

    $summary = $this->service->getProfitLossSummary(now()->toDateString(), now()->toDateString());

    expect($summary['total_revenue'])->toBe(0.0);
});

it('calculates profit margin correctly when revenue is zero', function () {
    $summary = $this->service->getProfitLossSummary(now()->toDateString(), now()->toDateString());

    expect($summary['profit_margin'])->toEqual(0.0);
});

it('returns paginated product breakdown', function () {
    $product1 = Product::factory()->create(['name' => 'Product A', 'sku' => 'SKU-A']);
    $product2 = Product::factory()->create(['name' => 'Product B', 'sku' => 'SKU-B']);
    $sale = Sale::factory()->create([
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product1->id,
        'qty' => 3,
        'price' => 10000,
        'cost_price' => 6000,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product2->id,
        'qty' => 2,
        'price' => 20000,
        'cost_price' => 12000,
    ]);

    $breakdown = $this->service->getProductBreakdown(now()->toDateString(), now()->toDateString());

    expect($breakdown->total())->toBe(2);
});

it('orders product breakdown by profit descending', function () {
    $product1 = Product::factory()->create(['name' => 'Low Profit']);
    $product2 = Product::factory()->create(['name' => 'High Profit']);
    $sale = Sale::factory()->create([
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product1->id,
        'qty' => 1,
        'price' => 1000,
        'cost_price' => 900,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product2->id,
        'qty' => 1,
        'price' => 5000,
        'cost_price' => 1000,
    ]);

    $breakdown = $this->service->getProductBreakdown(now()->toDateString(), now()->toDateString());

    expect($breakdown->first()->product_name)->toBe('High Profit');
});

it('excludes sales outside date range in product breakdown', function () {
    $product = Product::factory()->create();
    $oldSale = Sale::factory()->create([
        'status' => 'completed',
        'date' => now()->subDays(5)->toDateString(),
    ]);
    SaleItem::factory()->create([
        'sale_id' => $oldSale->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 1000,
        'cost_price' => 500,
    ]);

    $breakdown = $this->service->getProductBreakdown(now()->toDateString(), now()->toDateString());

    expect($breakdown->total())->toBe(0);
});

it('returns chart data grouped by day', function () {
    $sale1 = Sale::factory()->create([
        'total' => 10000,
        'total_cost' => 6000,
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);
    $sale2 = Sale::factory()->create([
        'total' => 20000,
        'total_cost' => 10000,
        'status' => 'completed',
        'date' => now()->subDay()->toDateString(),
    ]);

    $chartData = $this->service->getChartData(now()->subDays(2)->toDateString(), now()->toDateString());

    expect($chartData)->toHaveCount(2);
    expect($chartData->first())->toHaveKeys(['day', 'revenue', 'cost', 'profit']);
});

it('returns empty chart data when no sales exist', function () {
    $chartData = $this->service->getChartData(now()->toDateString(), now()->toDateString());

    expect($chartData)->toBeEmpty();
});

it('sums multiple sales on the same day in chart data', function () {
    Sale::factory()->create([
        'total' => 10000,
        'total_cost' => 5000,
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);
    Sale::factory()->create([
        'total' => 5000,
        'total_cost' => 2000,
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);

    $chartData = $this->service->getChartData(now()->toDateString(), now()->toDateString());

    expect($chartData)->toHaveCount(1);
    expect($chartData->first()['revenue'])->toBe(15000.0);
    expect($chartData->first()['cost'])->toBe(7000.0);
    expect($chartData->first()['profit'])->toBe(8000.0);
});
