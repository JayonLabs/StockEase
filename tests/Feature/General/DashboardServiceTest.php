<?php

namespace Tests\Feature\General;

use App\Enums\SaleStatus;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\General\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DashboardService;
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->supplier = Supplier::factory()->create();
});

// ─── adminData() — today sales ────────────────────────────────────────────────

it('calculates today sales using date column, not created_at', function () {
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 50000,
        'date' => Carbon::today()->toDateString(),
        'created_at' => Carbon::now()->subDays(5), // different from date
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['today'])->toBe(50000.0);
});

it('returns zero for today sales when sale date is not today even if created_at is today', function () {
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => Carbon::today()->subDay()->toDateString(), // yesterday
        'created_at' => Carbon::now(), // today
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['today'])->toBe(0.0);
});

it('excludes non-completed sales from today sales', function () {
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 50000,
        'date' => Carbon::today()->toDateString(),
    ]);

    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Draft->value,
        'total' => 99999,
        'date' => Carbon::today()->toDateString(),
    ]);

    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Canceled->value,
        'total' => 88888,
        'date' => Carbon::today()->toDateString(),
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['today'])->toBe(50000.0);
});

it('sums multiple completed sales for today', function () {
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 10000,
        'date' => Carbon::today()->toDateString(),
    ]);

    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 25000,
        'date' => Carbon::today()->toDateString(),
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['today'])->toBe(35000.0);
});

it('returns zero for today sales when no completed sales exist', function () {
    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['today'])->toBe(0.0);
});

// ─── adminData() — month sales ───────────────────────────────────────────────

it('calculates month sales using date column, not created_at', function () {
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 75000,
        'date' => Carbon::now()->toDateString(),
        'created_at' => Carbon::now()->subMonth(), // different month
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['month'])->toBe(75000.0);
});

it('excludes sales from previous month even if created_at is this month', function () {
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => Carbon::now()->subMonth()->toDateString(), // last month
        'created_at' => Carbon::now(), // this month
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['month'])->toBe(0.0);
});

it('excludes draft and canceled sales from month sales', function () {
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 100000,
        'date' => Carbon::now()->toDateString(),
    ]);

    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Draft->value,
        'total' => 500000,
        'date' => Carbon::now()->toDateString(),
    ]);

    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Canceled->value,
        'total' => 300000,
        'date' => Carbon::now()->toDateString(),
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['month'])->toBe(100000.0);
});

it('returns zero for month sales when no completed sales this month', function () {
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => Carbon::now()->subMonths(2)->toDateString(),
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['month'])->toBe(0.0);
});

// ─── adminData() — month purchases ───────────────────────────────────────────

it('calculates month purchases using date column, not created_at', function () {
    Purchase::factory()->create([
        'supplier_id' => $this->supplier->id,
        'user_id' => $this->admin->id,
        'total' => 200000,
        'date' => Carbon::now()->toDateString(),
        'created_at' => Carbon::now()->subMonth(), // different month
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['monthPurchases'])->toBe(200000.0);
});

it('excludes purchases from previous month even if created_at is this month', function () {
    Purchase::factory()->create([
        'supplier_id' => $this->supplier->id,
        'user_id' => $this->admin->id,
        'total' => 99999,
        'date' => Carbon::now()->subMonth()->toDateString(),
        'created_at' => Carbon::now(),
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['monthPurchases'])->toBe(0.0);
});

it('sums multiple purchases for this month', function () {
    Purchase::factory()->create([
        'supplier_id' => $this->supplier->id,
        'user_id' => $this->admin->id,
        'total' => 150000,
        'date' => Carbon::now()->toDateString(),
    ]);

    Purchase::factory()->create([
        'supplier_id' => $this->supplier->id,
        'user_id' => $this->admin->id,
        'total' => 350000,
        'date' => Carbon::now()->toDateString(),
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['monthPurchases'])->toBe(500000.0);
});

it('returns zero for month purchases when no purchases this month', function () {
    Purchase::factory()->create([
        'supplier_id' => $this->supplier->id,
        'user_id' => $this->admin->id,
        'total' => 99999,
        'date' => Carbon::now()->subMonths(2)->toDateString(),
    ]);

    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary']['monthPurchases'])->toBe(0.0);
});

// ─── adminData() — structure ─────────────────────────────────────────────────

it('returns all expected admin data keys', function () {
    $data = $this->service->getDashboardData('admin');

    expect($data)->toHaveKeys([
        'salesSummary',
        'lowStock',
        'activities',
        'weeklySalesChart',
        'priceUpdateChart',
    ]);
});

it('returns salesSummary with correct keys', function () {
    $data = $this->service->getDashboardData('admin');

    expect($data['salesSummary'])->toHaveKeys([
        'today',
        'month',
        'activeProducts',
        'monthPurchases',
    ]);
});

// ─── cashierData() — today income ────────────────────────────────────────────

it('calculates cashier today income using date column', function () {
    Sale::factory()->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'total' => 45000,
        'date' => Carbon::today()->toDateString(),
        'created_at' => Carbon::now()->subDays(3),
    ]);

    $data = $this->service->getDashboardData('cashier');

    expect($data['cashierSalesSummary']['todaysIncome'])->toBe(45000.0);
});

it('cashier today income is zero when sale date is not today', function () {
    Sale::factory()->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => Carbon::today()->subDay()->toDateString(),
        'created_at' => Carbon::now(),
    ]);

    $data = $this->service->getDashboardData('cashier');

    expect($data['cashierSalesSummary']['todaysIncome'])->toBe(0.0);
});

// ─── cashierData() — weekly transaction count ────────────────────────────────

it('counts weekly transactions using date column', function () {
    // This week
    Sale::factory()->count(4)->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'date' => Carbon::now()->toDateString(),
        'created_at' => Carbon::now()->subMonth(),
    ]);

    $data = $this->service->getDashboardData('cashier');

    expect($data['cashierSalesSummary']['totalTransactionPerWeek'])->toBe(4);
});

it('excludes transactions outside current week by date column', function () {
    // This week
    Sale::factory()->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'total' => 10000,
        'date' => Carbon::now()->toDateString(),
    ]);

    // Last week by date
    Sale::factory()->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => Carbon::now()->subWeek()->toDateString(),
        'created_at' => Carbon::now(), // today by created_at, but last week by date
    ]);

    $data = $this->service->getDashboardData('cashier');

    expect($data['cashierSalesSummary']['totalTransactionPerWeek'])->toBe(1);
});

// ─── cashierData() — best selling product ────────────────────────────────────

it('finds best selling product using date column in sale relation', function () {
    $product = Product::factory()->create();

    $sale = Sale::factory()->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'date' => Carbon::today()->toDateString(),
        'created_at' => Carbon::now()->subDays(10),
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 5,
        'price' => 10000,
    ]);

    $data = $this->service->getDashboardData('cashier');

    expect($data['cashierSalesSummary']['bestSellingProduct'])->toBe($product->name);
});

it('returns fallback message when no transactions today for cashier', function () {
    $data = $this->service->getDashboardData('cashier');

    expect($data['cashierSalesSummary']['bestSellingProduct'])->toBe('Tidak ada transaksi hari ini');
});

// ─── cashierData() — average per customer ────────────────────────────────────

it('calculates average per customer using date column', function () {
    Sale::factory()->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'total' => 100000,
        'date' => Carbon::today()->toDateString(),
        'created_at' => Carbon::now()->subDays(2),
    ]);

    Sale::factory()->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'total' => 50000,
        'date' => Carbon::today()->toDateString(),
        'created_at' => Carbon::now()->subDays(5),
    ]);

    $data = $this->service->getDashboardData('cashier');

    expect($data['cashierSalesSummary']['averagePerCustomer'])->toBe(75000.0);
});

// ─── cashierData() — structure ───────────────────────────────────────────────

it('returns all expected cashier data keys', function () {
    $data = $this->service->getDashboardData('cashier');

    expect($data)->toHaveKeys([
        'cashierSalesSummary',
        'recentTransaction',
        'weeklySalesChart',
    ]);
});

// ─── priceUpdateChart ────────────────────────────────────────────────────────

it('provides price update chart data for the last 7 days', function () {
    $product = Product::factory()->create();

    PriceHistory::factory()->count(3)->create([
        'product_id' => $product->id,
        'created_at' => Carbon::now(),
    ]);

    PriceHistory::factory()->count(2)->create([
        'product_id' => $product->id,
        'created_at' => Carbon::now()->subDay(),
    ]);

    $chartData = $this->service->getPriceUpdateChartData();

    expect($chartData['categories'])->toHaveCount(7);
    expect($chartData['data'])->toHaveCount(7);
    expect($chartData['data'][6])->toBe(3); // today
    expect($chartData['data'][5])->toBe(2); // yesterday
});

// ─── getWeeklySalesChart ─────────────────────────────────────────────────────

it('weekly chart uses date column for filtering', function () {
    $today = Carbon::now()->startOfWeek();

    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 15000,
        'date' => $today->toDateString(),
        'created_at' => Carbon::now()->subMonths(2),
    ]);

    $chartData = $this->service->getWeeklySalesChart();

    expect($chartData['data'][0])->toBe(15000.0);
});

it('weekly chart excludes sales where date is outside current week', function () {
    $lastWeek = Carbon::now()->subWeek()->startOfWeek();

    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => $lastWeek->toDateString(),
        'created_at' => Carbon::now(),
    ]);

    $chartData = $this->service->getWeeklySalesChart();

    expect(array_sum($chartData['data']))->toBe(0.0);
});

// ─── warehouseData ───────────────────────────────────────────────────────────

it('returns all expected warehouse data keys', function () {
    $data = $this->service->getDashboardData('warehouse');

    expect($data)->toHaveKeys([
        'warehouseSummary',
        'activityLogWarehouse',
        'warehouseChart',
    ]);
});

it('counts total products for warehouse', function () {
    Product::factory()->count(5)->create();

    $data = $this->service->getDashboardData('warehouse');

    expect($data['warehouseSummary']['totalProduct'])->toBe(5);
});

it('counts low stock products for warehouse', function () {
    Product::factory()->create(['stock' => 2, 'alert_stock' => 5]);
    Product::factory()->create(['stock' => 1, 'alert_stock' => 5]);
    Product::factory()->create(['stock' => 20, 'alert_stock' => 5]);

    $data = $this->service->getDashboardData('warehouse');

    expect($data['warehouseSummary']['lowStock'])->toBe(2);
});

// ─── getDashboardData role routing ───────────────────────────────────────────

it('returns admin data for admin role', function () {
    $data = $this->service->getDashboardData('admin');

    expect($data)->toHaveKey('salesSummary');
});

it('returns cashier data for cashier role', function () {
    $data = $this->service->getDashboardData('cashier');

    expect($data)->toHaveKey('cashierSalesSummary');
});

it('returns warehouse data for warehouse role', function () {
    $data = $this->service->getDashboardData('warehouse');

    expect($data)->toHaveKey('warehouseSummary');
});

it('returns empty array for unknown role', function () {
    $data = $this->service->getDashboardData('unknown');

    expect($data)->toBe([]);
});
