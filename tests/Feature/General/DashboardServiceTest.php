<?php

namespace Tests\Feature\General;

use App\Enums\SaleStatus;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\User;
use App\Services\General\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, warehouse:User, unknown:User, supplier:Supplier} $this */
    $this->service = new DashboardService;
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
    $this->unknown = User::factory()->create(['role' => 'customer']);
    $this->supplier = Supplier::factory()->create();
});

// ─── adminData() — today sales ────────────────────────────────────────────────

it('calculates today sales using date column, not created_at', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 50000,
        'date' => Carbon::today()->toDateString(),
        'created_at' => Carbon::now()->subDays(5), // different from date
    ]);

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['today'])->toBe(50000.0);
});

it('returns zero for today sales when sale date is not today even if created_at is today', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => Carbon::today()->subDay()->toDateString(), // yesterday
        'created_at' => Carbon::now(), // today
    ]);

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['today'])->toBe(0.0);
});

it('excludes non-completed sales from today sales', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
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

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['today'])->toBe(50000.0);
});

it('sums multiple completed sales for today', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
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

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['today'])->toBe(35000.0);
});

it('returns zero for today sales when no completed sales exist', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['today'])->toBe(0.0);
});

// ─── adminData() — month sales ───────────────────────────────────────────────

it('calculates month sales using date column, not created_at', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 75000,
        'date' => Carbon::now()->toDateString(),
        'created_at' => Carbon::now()->subMonth(), // different month
    ]);

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['month'])->toBe(75000.0);
});

it('excludes sales from previous month even if created_at is this month', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => Carbon::now()->subMonthNoOverflow()->toDateString(), // last month
        'created_at' => Carbon::now(), // this month
    ]);

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['month'])->toBe(0.0);
});

it('excludes draft and canceled sales from month sales', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
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

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['month'])->toBe(100000.0);
});

it('returns zero for month sales when no completed sales this month', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => Carbon::now()->subMonths(2)->toDateString(),
    ]);

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['month'])->toBe(0.0);
});

// ─── adminData() — month purchases ───────────────────────────────────────────

it('calculates month purchases using date column, not created_at', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Purchase::factory()->create([
        'supplier_id' => $this->supplier->id,
        'user_id' => $this->admin->id,
        'total' => 200000,
        'date' => Carbon::now()->toDateString(),
        'created_at' => Carbon::now()->subMonth(), // different month
    ]);

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['monthPurchases'])->toBe(200000.0);
});

it('excludes purchases from previous month even if created_at is this month', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Purchase::factory()->create([
        'supplier_id' => $this->supplier->id,
        'user_id' => $this->admin->id,
        'total' => 99999,
        'date' => Carbon::now()->subMonthNoOverflow()->toDateString(),
        'created_at' => Carbon::now(),
    ]);

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['monthPurchases'])->toBe(0.0);
});

it('sums multiple purchases for this month', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
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

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['monthPurchases'])->toBe(500000.0);
});

it('returns zero for month purchases when no purchases this month', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Purchase::factory()->create([
        'supplier_id' => $this->supplier->id,
        'user_id' => $this->admin->id,
        'total' => 99999,
        'date' => Carbon::now()->subMonths(2)->toDateString(),
    ]);

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['monthPurchases'])->toBe(0.0);
});

// ─── adminData() — structure ─────────────────────────────────────────────────

it('returns all expected admin data keys', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $data = $this->service->getDashboardData($this->admin);

    expect($data)->toHaveKeys([
        'salesSummary',
        'lowStock',
        'activities',
        'weeklySalesChart',
        'priceUpdateChart',
    ]);
});

it('returns salesSummary with correct keys', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary'])->toHaveKeys([
        'today',
        'month',
        'activeProducts',
        'monthPurchases',
    ]);
});

// ─── cashierData() — today income ────────────────────────────────────────────

it('calculates cashier today income using date column', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Sale::factory()->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'total' => 45000,
        'date' => Carbon::today()->toDateString(),
        'created_at' => Carbon::now()->subDays(3),
    ]);

    $data = $this->service->getDashboardData($this->cashier);

    expect($data['cashierSalesSummary']['todaysIncome'])->toBe(45000.0);
});

it('cashier today income is zero when sale date is not today', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Sale::factory()->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => Carbon::today()->subDay()->toDateString(),
        'created_at' => Carbon::now(),
    ]);

    $data = $this->service->getDashboardData($this->cashier);

    expect($data['cashierSalesSummary']['todaysIncome'])->toBe(0.0);
});

// ─── cashierData() — weekly transaction count ────────────────────────────────

it('counts weekly transactions using date column', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    // This week
    Sale::factory()->count(4)->create([
        'user_id' => $this->cashier->id,
        'status' => SaleStatus::Completed->value,
        'date' => Carbon::now()->toDateString(),
        'created_at' => Carbon::now()->subMonth(),
    ]);

    $data = $this->service->getDashboardData($this->cashier);

    expect($data['cashierSalesSummary']['totalTransactionPerWeek'])->toBe(4);
});

it('excludes transactions outside current week by date column', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
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

    $data = $this->service->getDashboardData($this->cashier);

    expect($data['cashierSalesSummary']['totalTransactionPerWeek'])->toBe(1);
});

// ─── cashierData() — best selling product ────────────────────────────────────

it('finds best selling product using date column in sale relation', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
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

    $data = $this->service->getDashboardData($this->cashier);

    expect($data['cashierSalesSummary']['bestSellingProduct'])->toBe($product->name);
});

it('returns fallback message when no transactions today for cashier', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $data = $this->service->getDashboardData($this->cashier);

    expect($data['cashierSalesSummary']['bestSellingProduct'])->toBe('Tidak ada transaksi hari ini');
});

// ─── cashierData() — average per customer ────────────────────────────────────

it('calculates average per customer using date column', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
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

    $data = $this->service->getDashboardData($this->cashier);

    expect($data['cashierSalesSummary']['averagePerCustomer'])->toBe(75000.0);
});

// ─── cashierData() — structure ───────────────────────────────────────────────

it('returns all expected cashier data keys', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $data = $this->service->getDashboardData($this->cashier);

    expect($data)->toHaveKeys([
        'cashierSalesSummary',
        'recentTransaction',
        'weeklySalesChart',
    ]);
});

// ─── priceUpdateChart ────────────────────────────────────────────────────────

it('provides price update chart data for the last 7 days', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
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
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
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
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
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
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $data = $this->service->getDashboardData($this->warehouse);

    expect($data)->toHaveKeys([
        'warehouseSummary',
        'activityLogWarehouse',
        'warehouseChart',
    ]);
});

it('counts total products for warehouse', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Product::factory()->count(5)->create();

    $data = $this->service->getDashboardData($this->warehouse);

    expect($data['warehouseSummary']['totalProduct'])->toBe(5);
});

it('counts low stock products for warehouse', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Product::factory()->create(['stock' => 2, 'alert_stock' => 5]);
    Product::factory()->create(['stock' => 1, 'alert_stock' => 5]);
    Product::factory()->create(['stock' => 20, 'alert_stock' => 5]);

    $data = $this->service->getDashboardData($this->warehouse);

    expect($data['warehouseSummary']['lowStock'])->toBe(2);
});

// ─── warehouseData() — newProductThisMonth (year-aware) ───────────────────────

it('newProductThisMonth excludes products from same month last year', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Product::factory()->create([
        'created_at' => Carbon::now()->subYear(),
    ]);

    Product::factory()->create([
        'created_at' => Carbon::now()->subYears(2),
    ]);

    $data = $this->service->getDashboardData($this->warehouse);

    expect($data['warehouseSummary']['newProductThisMonth'])->toBe(0);
});

it('newProductThisMonth includes products from this month and year', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Product::factory()->count(3)->create([
        'created_at' => Carbon::now(),
    ]);

    Product::factory()->create([
        'created_at' => Carbon::now()->subYear(),
    ]);

    $data = $this->service->getDashboardData($this->warehouse);

    expect($data['warehouseSummary']['newProductThisMonth'])->toBe(3);
});

it('monthSales and monthPurchases already exclude cross-year data', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 99999,
        'date' => Carbon::now()->subYear()->toDateString(),
    ]);

    Purchase::factory()->create([
        'supplier_id' => $this->supplier->id,
        'user_id' => $this->admin->id,
        'total' => 99999,
        'date' => Carbon::now()->subYear()->toDateString(),
    ]);

    $data = $this->service->getDashboardData($this->admin);

    expect($data['salesSummary']['month'])->toBe(0.0);
    expect($data['salesSummary']['monthPurchases'])->toBe(0.0);
});

// ─── getDashboardData role routing ───────────────────────────────────────────

it('returns admin data for admin role', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $data = $this->service->getDashboardData($this->admin);

    expect($data)->toHaveKey('salesSummary');
});

it('returns cashier data for cashier role', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $data = $this->service->getDashboardData($this->cashier);

    expect($data)->toHaveKey('cashierSalesSummary');
});

it('returns warehouse data for warehouse role', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $data = $this->service->getDashboardData($this->warehouse);

    expect($data)->toHaveKey('warehouseSummary');
});

it('returns cashier data for user with cashier role', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, warehouse:User, unknown:User, supplier:Supplier} $this */
    $data = $this->service->getDashboardData($this->cashier);

    expect($data)->toHaveKey('cashierSalesSummary');
});

it('returns empty array for user without recognized role', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, warehouse:User, unknown:User, supplier:Supplier} $this */
    $user = User::factory()->create();
    $user->syncRoles([]);

    $data = $this->service->getDashboardData($user);

    expect($data)->toBe([]);
});

// ─── Carbon locale — diffForHumans ───────────────────────────────────────────

it('activity history uses indonesian diffForHumans', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $product = Product::factory()->create();

    $sale = Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 50000,
        'created_at' => Carbon::now()->subHours(3),
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 2,
        'price' => 10000,
    ]);

    $data = $this->service->getDashboardData($this->admin);

    expect($data['activities'])->not->toBeEmpty();

    $saleActivity = collect($data['activities'])->firstWhere('type', 'sale');
    expect($saleActivity)->not->toBeNull();
    expect($saleActivity['time'])->toContain('jam');
});

it('activity history diffForHumans is in indonesian not english', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $product = Product::factory()->create();

    $sale = Sale::factory()->create([
        'user_id' => $this->admin->id,
        'status' => SaleStatus::Completed->value,
        'total' => 50000,
        'created_at' => Carbon::now()->subHour(),
    ]);

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 10000,
    ]);

    $data = $this->service->getDashboardData($this->admin);

    $saleActivity = collect($data['activities'])->firstWhere('type', 'sale');

    $englishWords = ['hour', 'day', 'week', 'month', 'year', 'ago'];
    foreach ($englishWords as $word) {
        expect($saleActivity['time'])->not->toContain($word);
    }
});

// ─── Carbon locale — weekly chart day names ──────────────────────────────────

it('weekly sales chart uses indonesian day abbreviations', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $chart = $this->service->getWeeklySalesChart();

    expect($chart['categories'])->toHaveCount(7);

    $indonesianDays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    foreach ($chart['categories'] as $day) {
        expect($day)->toBeIn($indonesianDays);
    }
});

it('weekly sales chart day names are not english', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    $chart = $this->service->getWeeklySalesChart();

    $englishDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    foreach ($englishDays as $day) {
        expect($chart['categories'])->not->toContain($day);
    }
});

// ─── Carbon locale — warehouse chart day names ───────────────────────────────

it('warehouse chart uses indonesian day abbreviations', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    Product::factory()->create();
    StockLog::factory()->create();

    $chart = $this->service->getWarehouseChart();

    expect($chart['stockMovement'])->not->toBeEmpty();

    $indonesianDays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    foreach ($chart['stockMovement'] as $entry) {
        expect($entry['date'])->toBeIn($indonesianDays);
    }
});

// ─── Carbon locale — price update chart ──────────────────────────────────────

it('price update chart uses indonesian month names in categories', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, warehouse:User, unknown:User, supplier:Supplier} $this */
    $product = Product::factory()->create();

    PriceHistory::factory()->create([
        'product_id' => $product->id,
        'created_at' => Carbon::now(),
    ]);

    $chart = $this->service->getPriceUpdateChartData();

    expect($chart['categories'])->toHaveCount(7);

    foreach ($chart['categories'] as $category) {
        expect($category)->toMatch('/^\d{2}\s[A-Za-z]+$/');
    }
});

// ─── cashierData() — user isolation (BUG-6) ────────────────────────────────

it('cashierData filters transactions by user_id', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, warehouse:User, unknown:User, supplier:Supplier} $this */
    $cashierA = User::factory()->create(['role' => 'cashier']);
    $cashierB = User::factory()->create(['role' => 'cashier']);

    // Sales for cashier A
    Sale::factory()->create([
        'user_id' => $cashierA->id,
        'status' => SaleStatus::Completed->value,
        'total' => 10000,
        'date' => Carbon::today()->toDateString(),
    ]);

    // Sales for cashier B
    Sale::factory()->create([
        'user_id' => $cashierB->id,
        'status' => SaleStatus::Completed->value,
        'total' => 50000,
        'date' => Carbon::today()->toDateString(),
    ]);

    $data = $this->service->getDashboardData($cashierA);

    expect($data['cashierSalesSummary']['todaysIncome'])->toBe(10000.0);
    expect($data['cashierSalesSummary']['totalTransactionPerWeek'])->toBe(1);
});

it('cashierData does not include other cashier transactions in recent transactions', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, warehouse:User, unknown:User, supplier:Supplier} $this */
    $cashierA = User::factory()->create(['role' => 'cashier']);
    $cashierB = User::factory()->create(['role' => 'cashier']);

    Sale::factory()->create([
        'user_id' => $cashierA->id,
        'customer_name' => 'Customer A',
        'status' => SaleStatus::Completed->value,
        'total' => 10000,
        'date' => Carbon::today()->toDateString(),
    ]);

    Sale::factory()->create([
        'user_id' => $cashierB->id,
        'customer_name' => 'Customer B',
        'status' => SaleStatus::Completed->value,
        'total' => 50000,
        'date' => Carbon::today()->toDateString(),
    ]);

    $data = $this->service->getDashboardData($cashierA);
    $customers = collect($data['recentTransaction'])->pluck('customer')->toArray();

    expect($customers)->toContain('Customer A');
    expect($customers)->not->toContain('Customer B');
    expect($data['recentTransaction'])->toHaveCount(1);
});

it('cashierData excludes other users sales from best selling product', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, warehouse:User, unknown:User, supplier:Supplier} $this */
    $cashierA = User::factory()->create(['role' => 'cashier']);
    $cashierB = User::factory()->create(['role' => 'cashier']);

    $productA = Product::factory()->create(['name' => 'Produk A']);
    $productB = Product::factory()->create(['name' => 'Produk B']);

    $saleA = Sale::factory()->create([
        'user_id' => $cashierA->id,
        'status' => SaleStatus::Completed->value,
        'date' => Carbon::today()->toDateString(),
    ]);

    SaleItem::factory()->create([
        'sale_id' => $saleA->id,
        'product_id' => $productA->id,
        'qty' => 5,
        'price' => 10000,
    ]);

    $saleB = Sale::factory()->create([
        'user_id' => $cashierB->id,
        'status' => SaleStatus::Completed->value,
        'date' => Carbon::today()->toDateString(),
    ]);

    SaleItem::factory()->create([
        'sale_id' => $saleB->id,
        'product_id' => $productB->id,
        'qty' => 100,
        'price' => 10000,
    ]);

    $data = $this->service->getDashboardData($cashierA);

    expect($data['cashierSalesSummary']['bestSellingProduct'])->toBe('Produk A');
});

it('cashierData excludes other users sales from average per customer', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, warehouse:User, unknown:User, supplier:Supplier} $this */
    $cashierA = User::factory()->create(['role' => 'cashier']);
    $cashierB = User::factory()->create(['role' => 'cashier']);

    Sale::factory()->create([
        'user_id' => $cashierA->id,
        'status' => SaleStatus::Completed->value,
        'total' => 100000,
        'date' => Carbon::today()->toDateString(),
    ]);

    Sale::factory()->create([
        'user_id' => $cashierB->id,
        'status' => SaleStatus::Completed->value,
        'total' => 900000,
        'date' => Carbon::today()->toDateString(),
    ]);

    $data = $this->service->getDashboardData($cashierA);

    expect($data['cashierSalesSummary']['averagePerCustomer'])->toBe(100000.0);
});

it('cashierData excludes other users sales from weekly transaction count', function () {
    /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, warehouse:User, unknown:User, supplier:Supplier} $this */
    $cashierA = User::factory()->create(['role' => 'cashier']);
    $cashierB = User::factory()->create(['role' => 'cashier']);

    Sale::factory()->count(3)->create([
        'user_id' => $cashierA->id,
        'status' => SaleStatus::Completed->value,
        'date' => Carbon::today()->toDateString(),
    ]);

    Sale::factory()->count(7)->create([
        'user_id' => $cashierB->id,
        'status' => SaleStatus::Completed->value,
        'date' => Carbon::today()->toDateString(),
    ]);

    $data = $this->service->getDashboardData($cashierA);

    expect($data['cashierSalesSummary']['totalTransactionPerWeek'])->toBe(3);
});

// ─── Activity History Caching ────────────────────────────────────────────────

describe('Activity History Caching', function () {
    beforeEach(function () {
        /** @var TestCase&object{service:DashboardService, admin:User, cashier:User, supplier:Supplier} $this */
    });

    it('caches activity history results', function () {
        /** @var TestCase&object{service:DashboardService, admin:User, supplier:Supplier} $this */
        $product = Product::factory()->create(['name' => 'Test Product']);

        $sale = Sale::factory()->create([
            'user_id' => $this->admin->id,
            'status' => SaleStatus::Completed->value,
            'total' => 50000,
            'created_at' => Carbon::now()->subHour(),
        ]);

        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
        ]);

        Cache::flush();

        $result1 = $this->service->getActivityHistory();

        $cached = Cache::get('dashboard_activity_history');
        expect($cached)->not->toBeNull();
        expect($cached)->toBe($result1);

        $result2 = $this->service->getActivityHistory();
        expect($result2)->toBe($result1);
    });

    it('skips queries on subsequent calls when cache is warm', function () {
        /** @var TestCase&object{service:DashboardService, admin:User, supplier:Supplier} $this */
        $product = Product::factory()->create(['name' => 'Test Product']);

        $sale = Sale::factory()->create([
            'user_id' => $this->admin->id,
            'status' => SaleStatus::Completed->value,
            'total' => 50000,
            'created_at' => Carbon::now()->subHour(),
        ]);

        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
        ]);

        Cache::flush();
        $this->service->getActivityHistory();

        DB::enableQueryLog();
        $result = $this->service->getActivityHistory();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($result)->not->toBeEmpty();

        $dataQueries = collect($queries)->filter(
            fn ($q) => ! str_contains(strtolower($q['query']), 'cache')
                && ! str_contains($q['query'], 'sqlite_master')
        );

        expect($dataQueries)->toHaveCount(0);
    });

    it('returns activities sorted by newest first', function () {
        /** @var TestCase&object{service:DashboardService, admin:User, supplier:Supplier} $this */
        Cache::flush();

        $product = Product::factory()->create(['name' => 'Sort Product']);

        $older = Sale::factory()->create([
            'user_id' => $this->admin->id,
            'status' => SaleStatus::Completed->value,
            'total' => 10000,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        SaleItem::factory()->create([
            'sale_id' => $older->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 10000,
        ]);

        $newer = Sale::factory()->create([
            'user_id' => $this->admin->id,
            'status' => SaleStatus::Completed->value,
            'total' => 20000,
            'created_at' => Carbon::now()->subHour(),
        ]);

        SaleItem::factory()->create([
            'sale_id' => $newer->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
        ]);

        $activities = $this->service->getActivityHistory();

        expect($activities)->toHaveCount(2);
        expect($activities[0]['id'])->toBe('sale_'.$newer->id);
        expect($activities[1]['id'])->toBe('sale_'.$older->id);
    });

    it('limits activity history to 10 items', function () {
        /** @var TestCase&object{service:DashboardService, admin:User, supplier:Supplier} $this */
        Cache::flush();

        $product = Product::factory()->create(['name' => 'Limit Product']);

        for ($i = 0; $i < 15; $i++) {
            $sale = Sale::factory()->create([
                'user_id' => $this->admin->id,
                'status' => SaleStatus::Completed->value,
                'total' => 10000 + $i,
                'created_at' => Carbon::now()->subMinutes($i),
            ]);

            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 1,
                'price' => 10000,
            ]);
        }

        $activities = $this->service->getActivityHistory();
        expect($activities)->toHaveCount(10);
    });

    it('includes all activity types in result', function () {
        /** @var TestCase&object{service:DashboardService, admin:User, supplier:Supplier} $this */
        Cache::flush();

        $product = Product::factory()->create(['name' => 'Type Test Product']);

        $sale = Sale::factory()->create([
            'user_id' => $this->admin->id,
            'status' => SaleStatus::Completed->value,
            'total' => 10000,
            'created_at' => Carbon::now()->subHour(),
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 10000,
        ]);

        Purchase::factory()->create([
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->admin->id,
            'total' => 50000,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        StockLog::factory()->create([
            'product_id' => $product->id,
            'type' => 'in',
            'qty' => 10,
            'created_at' => Carbon::now()->subHours(3),
        ]);

        PriceHistory::factory()->create([
            'product_id' => $product->id,
            'user_id' => $this->admin->id,
            'created_at' => Carbon::now()->subHours(4),
        ]);

        $activities = $this->service->getActivityHistory();

        $types = collect($activities)->pluck('type')->toArray();
        expect($types)->toContain('sale');
        expect($types)->toContain('purchase');
        expect($types)->toContain('stock');
        expect($types)->toContain('price');
    });

    it('includes activity history in admin dashboard data', function () {
        /** @var TestCase&object{service:DashboardService, admin:User, supplier:Supplier} $this */
        Cache::flush();

        $data = $this->service->getDashboardData($this->admin);

        expect($data)->toHaveKey('activities');
        expect($data['activities'])->toBeArray();
    });

    it('cache isolates dashboard data correctly', function () {
        /** @var TestCase&object{service:DashboardService, admin:User, supplier:Supplier} $this */
        Cache::flush();

        $product = Product::factory()->create(['name' => 'Cache Isolation']);

        $sale = Sale::factory()->create([
            'user_id' => $this->admin->id,
            'status' => SaleStatus::Completed->value,
            'total' => 50000,
            'created_at' => Carbon::now(),
        ]);

        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 10000,
        ]);

        $adminData = $this->service->getDashboardData($this->admin);
        expect($adminData['activities'])->not->toBeEmpty();
        expect(Cache::has('dashboard_activity_history'))->toBeTrue();
    });
});
