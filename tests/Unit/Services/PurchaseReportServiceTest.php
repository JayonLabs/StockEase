<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchase\PurchaseReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PurchaseReportService;
});

it('gets filtered purchases by date range via created_at', function () {
    Purchase::factory()->create(['created_at' => now()]);
    Purchase::factory()->create(['created_at' => now()->subDays(10)]);

    $purchases = $this->service->getFilteredPurchases([
        'start_date' => now()->subDays(5)->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    expect($purchases)->toHaveCount(1);
});

it('gets filtered purchases by supplier', function () {
    $supplier = Supplier::factory()->create();
    Purchase::factory()->create(['supplier_id' => $supplier->id]);
    Purchase::factory()->create();

    $purchases = $this->service->getFilteredPurchases(['supplier' => $supplier->id]);

    expect($purchases)->toHaveCount(1);
    expect($purchases->first()->supplier_id)->toBe($supplier->id);
});

it('skips supplier filter when value is semua-supplier', function () {
    Purchase::factory()->count(2)->create();

    $purchases = $this->service->getFilteredPurchases(['supplier' => 'semua-supplier']);

    expect($purchases)->toHaveCount(2);
});

it('gets filtered purchases by user', function () {
    $user = User::factory()->create();
    Purchase::factory()->create(['user_id' => $user->id]);
    Purchase::factory()->create();

    $purchases = $this->service->getFilteredPurchases(['user' => $user->id]);

    expect($purchases)->toHaveCount(1);
    expect($purchases->first()->user_id)->toBe($user->id);
});

it('skips user filter when value is semua-user', function () {
    Purchase::factory()->count(2)->create();

    $purchases = $this->service->getFilteredPurchases(['user' => 'semua-user']);

    expect($purchases)->toHaveCount(2);
});

it('returns empty index report data when purchases collection is empty', function () {
    $data = $this->service->getIndexReportData(collect());

    expect($data)->toBe([]);
});

it('generates index report data from purchases', function () {
    $supplier = Supplier::factory()->create(['name' => 'Supplier A']);
    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'total' => 50000,
    ]);
    $product = Product::factory()->create();
    PurchaseItem::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'qty' => 10,
        'price' => 5000,
    ]);

    $purchases = Purchase::with('supplier', 'user', 'purchaseItems', 'purchaseItems.product')->get();

    $data = $this->service->getIndexReportData($purchases);

    expect($data)->toHaveKeys([
        'filters', 'sumTotalPurchase', 'totalItemsPurchased',
        'totalTransaction', 'purchaseTrends', 'topSupplier',
    ]);
    expect($data['sumTotalPurchase'])->toBe(50000.0);
    expect($data['totalItemsPurchased'])->toBe(10);
    expect($data['totalTransaction'])->toBe(1);
});

it('identifies top supplier in index report', function () {
    $supplierA = Supplier::factory()->create(['name' => 'Top Supplier']);
    $supplierB = Supplier::factory()->create(['name' => 'Other Supplier']);
    Purchase::factory()->create([
        'supplier_id' => $supplierA->id,
        'total' => 100000,
    ]);
    Purchase::factory()->create([
        'supplier_id' => $supplierB->id,
        'total' => 20000,
    ]);

    $purchases = Purchase::with('supplier', 'user', 'purchaseItems', 'purchaseItems.product')->get();

    $data = $this->service->getIndexReportData($purchases);

    expect($data['topSupplier'])->not->toBeEmpty();
    expect($data['topSupplier']->first()['supplier_name'])->toBe('Top Supplier');
});

it('generates pdf report data from purchases', function () {
    $product = Product::factory()->create(['name' => 'PDF Product']);
    $purchase = Purchase::factory()->create(['total' => 75000]);
    PurchaseItem::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'qty' => 5,
        'price' => 15000,
    ]);

    $purchases = Purchase::with('supplier', 'user', 'purchaseItems', 'purchaseItems.product')->get();
    $filters = [
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
        'supplier' => 'semua-supplier',
        'user' => 'semua-user',
    ];

    $data = $this->service->getPdfReportData($purchases, $filters);

    expect($data)->toHaveKeys([
        'startDate', 'endDate', 'purchases', 'sumTotalPurchase',
        'totalItemsPurchased', 'totalTransaction', 'user', 'supplier',
    ]);
    expect($data['sumTotalPurchase'])->toBe(75000.0);
});

it('resolves supplier name in pdf report data', function () {
    $supplier = Supplier::factory()->create(['name' => 'PT Jaya']);
    $purchase = Purchase::factory()->create(['supplier_id' => $supplier->id]);
    PurchaseItem::factory()->create(['purchase_id' => $purchase->id]);

    $purchases = Purchase::with('supplier', 'user', 'purchaseItems', 'purchaseItems.product')->get();

    $data = $this->service->getPdfReportData($purchases, [
        'supplier' => $supplier->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ]);

    expect($data['supplier'])->toBe('PT Jaya');
});

it('generates excel report summary', function () {
    $supplier = Supplier::factory()->create(['name' => 'Supplier Excel']);
    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'total' => 50000,
    ]);
    PurchaseItem::factory()->create([
        'purchase_id' => $purchase->id,
        'qty' => 10,
        'price' => 5000,
    ]);

    $purchases = Purchase::with('supplier', 'user', 'purchaseItems', 'purchaseItems.product')->get();

    $summary = $this->service->getExcelReportSummary($purchases);

    expect($summary)->toHaveKeys([
        'sumTotalPurchase', 'totalItemsPurchased', 'totalTransaction', 'suppliers',
    ]);
    expect($summary['suppliers'])->not->toBeEmpty();
    expect($summary['suppliers']->first()->name)->toBe('Supplier Excel');
});

it('prepares excel filters with resolved names', function () {
    $user = User::factory()->create(['name' => 'Purchase User']);
    $supplier = Supplier::factory()->create(['name' => 'Purchase Supplier']);

    $filters = $this->service->prepareExcelFilters([
        'user' => $user->id,
        'supplier' => $supplier->id,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
    ]);

    expect($filters['user'])->toBe('Purchase User');
    expect($filters['supplier'])->toBe('Purchase Supplier');
});

it('prepares excel filters with default labels', function () {
    $filters = $this->service->prepareExcelFilters([
        'user' => 'semua-user',
        'supplier' => 'semua-supplier',
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
    ]);

    expect($filters['user'])->toBe('Semua User');
    expect($filters['supplier'])->toBe('Semua Supplier');
});
