<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Sale\SaleReportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->service = new SaleReportService;
});

it('gets filtered sales by date range', function () {
    Sale::factory()->create([
        'payment_method' => 'cash',
        'status' => 'completed',
        'date' => now()->toDateString(),
    ]);
    Sale::factory()->create([
        'payment_method' => 'cash',
        'status' => 'completed',
        'date' => now()->subDays(10)->toDateString(),
    ]);

    $sales = $this->service->getFilteredSales([
        'start' => now()->subDays(5)->toDateString(),
        'end' => now()->toDateString(),
    ]);

    expect($sales)->toHaveCount(1);
});

it('gets filtered sales by cashier', function () {
    $user = User::factory()->create();
    Sale::factory()->create([
        'user_id' => $user->id,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);
    Sale::factory()->create([
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    $sales = $this->service->getFilteredSales(['cashier' => $user->id]);

    expect($sales)->toHaveCount(1);
    expect($sales->first()->user_id)->toBe($user->id);
});

it('skips cashier filter when value is semua-cashier', function () {
    Sale::factory()->count(3)->create([
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    $sales = $this->service->getFilteredSales(['cashier' => 'semua-cashier']);

    expect($sales)->toHaveCount(3);
});

it('gets filtered sales by payment method', function () {
    Sale::factory()->create([
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);
    Sale::factory()->create([
        'payment_method' => 'qris',
        'status' => 'completed',
    ]);

    $sales = $this->service->getFilteredSales(['payment' => 'qris']);

    expect($sales)->toHaveCount(1);
    expect($sales->first()->payment_method)->toBe('qris');
});

it('skips payment method filter when value is semua-metode', function () {
    Sale::factory()->create(['payment_method' => 'cash', 'status' => 'completed']);
    Sale::factory()->create(['payment_method' => 'qris', 'status' => 'completed']);

    $sales = $this->service->getFilteredSales(['payment' => 'semua-metode']);

    expect($sales)->toHaveCount(2);
});

it('excludes draft sales from filtered results', function () {
    Sale::factory()->create(['payment_method' => 'pending', 'status' => 'draft']);
    Sale::factory()->create(['payment_method' => 'cash', 'status' => 'completed']);

    $sales = $this->service->getFilteredSales([]);

    expect($sales)->toHaveCount(1);
});

it('returns empty index report data when sales collection is empty', function () {
    $data = $this->service->getIndexReportData(collect());

    expect($data)->toBe([]);
});

it('generates index report data from sales collection', function () {
    $product = Product::factory()->create(['name' => 'Best Product']);
    $user = User::factory()->create(['name' => 'Cashier A']);
    Sale::factory()->count(3)->create([
        'payment_method' => 'cash',
        'status' => 'completed',
        'total' => 50000,
        'user_id' => $user->id,
    ]);

    $sales = Sale::query()
        ->with('user', 'saleItems', 'saleItems.product', 'paymentTransaction')
        ->where('status', '!=', 'draft')
        ->get();

    $data = $this->service->getIndexReportData($sales);

    expect($data)->toHaveKeys([
        'sales', 'sumTotalSale', 'transactionCount',
        'countProductSale', 'bestSellingProduct', 'salesTrend', 'productSalesShare',
    ]);
    expect($data['sumTotalSale'])->toBe(150000.0);
    expect($data['transactionCount'])->toBe(3);
});

it('calculates best selling product in index report', function () {
    $productA = Product::factory()->create(['name' => 'Product A']);
    $productB = Product::factory()->create(['name' => 'Product B']);
    $sale = Sale::factory()->create([
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $productA->id,
        'qty' => 5,
        'price' => 1000,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $productB->id,
        'qty' => 2,
        'price' => 1000,
    ]);

    $sales = Sale::query()
        ->with('user', 'saleItems', 'saleItems.product', 'paymentTransaction')
        ->where('status', '!=', 'draft')
        ->get();

    $data = $this->service->getIndexReportData($sales);

    expect($data['bestSellingProduct']['product_name'])->toBe('Product A');
    expect($data['bestSellingProduct']['total_sold'])->toBe(5);
});

it('generates pdf report data with filters', function () {
    $product = Product::factory()->create(['name' => 'PDF Product']);
    $sale = Sale::factory()->create([
        'payment_method' => 'cash',
        'status' => 'completed',
        'total' => 25000,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 2,
        'price' => 12500,
    ]);

    $sales = Sale::query()
        ->with('user', 'saleItems', 'saleItems.product', 'paymentTransaction')
        ->where('status', '!=', 'draft')
        ->get();

    $filters = [
        'start' => now()->toDateString(),
        'end' => now()->toDateString(),
        'cashier' => null,
        'payment' => 'cash',
    ];

    $data = $this->service->getPdfReportData($sales, $filters);

    expect($data)->toHaveKeys([
        'start_date', 'end_date', 'cashier_name', 'payment',
        'total_sales', 'transaction_count', 'product_sold',
        'best_selling_product', 'sales',
    ]);
});

it('resolves cashier name in pdf report data', function () {
    $user = User::factory()->create(['name' => 'John Cashier']);
    Sale::factory()->create([
        'user_id' => $user->id,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    $sales = Sale::query()
        ->with('user', 'saleItems', 'saleItems.product', 'paymentTransaction')
        ->where('status', '!=', 'draft')
        ->get();

    $data = $this->service->getPdfReportData($sales, [
        'cashier' => $user->id,
        'start' => now()->toDateString(),
        'end' => now()->toDateString(),
        'payment' => 'cash',
    ]);

    expect($data['cashier_name'])->toBe('John Cashier');
});

it('generates excel report summary', function () {
    Sale::factory()->create([
        'total' => 50000,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);
    Sale::factory()->create([
        'total' => 25000,
        'payment_method' => 'cash',
        'status' => 'completed',
    ]);

    $sales = Sale::query()
        ->with('user', 'saleItems', 'saleItems.product', 'paymentTransaction')
        ->where('status', '!=', 'draft')
        ->get();

    $summary = $this->service->getExcelReportSummary($sales);

    expect($summary)->toHaveKeys([
        'total_sales', 'transaction_count', 'product_count', 'best_product',
    ]);
    expect($summary['transaction_count'])->toBe(2);
});

it('prepares excel filters with cashier name', function () {
    $user = User::factory()->create(['name' => 'Excel Cashier']);

    $filters = $this->service->prepareExcelFilters([
        'cashier' => $user->id,
        'start' => now()->toDateString(),
        'end' => now()->toDateString(),
    ]);

    expect($filters['cashier'])->toBe('Excel Cashier');
});

it('prepares excel filters with default cashier label', function () {
    $filters = $this->service->prepareExcelFilters([
        'cashier' => 'semua-cashier',
        'start' => now()->toDateString(),
        'end' => now()->toDateString(),
    ]);

    expect($filters['cashier'])->toBe('Semua Cashier');
});
