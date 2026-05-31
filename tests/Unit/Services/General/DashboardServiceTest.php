<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\StockLog;
use App\Services\General\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can get cashier dashboard data', function () {
    Sale::factory()->create(['total' => 1000, 'payment_method' => 'cash', 'status' => 'completed']);
    $dashboardService = new DashboardService;

    $data = $dashboardService->getDashboardData('cashier');

    expect($data)->toHaveKeys(['cashierSalesSummary', 'recentTransaction', 'weeklySalesChart']);
    expect((int) $data['cashierSalesSummary']['todaysIncome'])->toBe(1000);
});

it('can get warehouse dashboard data', function () {
    Product::query()->delete();
    $products = Product::factory()->count(5)->create();
    StockLog::factory()->create(['product_id' => $products->first()->id, 'type' => 'in', 'qty' => 10]);
    $dashboardService = new DashboardService;

    $data = $dashboardService->getDashboardData('warehouse');

    expect($data)->toHaveKeys(['warehouseSummary', 'activityLogWarehouse', 'warehouseChart']);
    expect($data['warehouseSummary']['totalProduct'])->toBe(5);
});

it('generates weekly sales chart data', function () {
    Sale::factory()->create(['total' => 1000, 'payment_method' => 'cash', 'status' => 'completed']);
    $dashboardService = new DashboardService;

    $chart = $dashboardService->getWeeklySalesChart();

    expect($chart)->toHaveKeys(['categories', 'data']);
    expect($chart['categories'])->toHaveCount(7);
});

it('generates warehouse chart data', function () {
    Product::factory()->create();
    StockLog::factory()->create();
    $dashboardService = new DashboardService;

    $chart = $dashboardService->getWarehouseChart();

    expect($chart)->toHaveKeys(['stockMovement', 'categoryDistribution']);
});
