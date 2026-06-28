<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\Stock\StockReportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{service: StockReportService} $this */
    $this->service = new StockReportService;
});

function createProductWithPurchaseItem(array $productAttrs = [], array $purchaseItemAttrs = []): Product
{
    $product = Product::factory()->create(array_merge(['stock' => 10], $productAttrs));
    $purchase = Purchase::factory()->create(
        $purchaseItemAttrs['purchase_attrs'] ?? []
    );
    PurchaseItem::factory()->create(array_merge([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'qty' => 5,
    ], collect($purchaseItemAttrs)->except('purchase_attrs')->toArray()));

    return $product;
}

it('gets paginated filtered stocks', function () {
    /** @var object{service: StockReportService} $this */
    createProductWithPurchaseItem();
    createProductWithPurchaseItem();
    createProductWithPurchaseItem();

    $result = $this->service->getPaginatedFilteredStocks([], 10);

    expect($result->total())->toBe(3);
});

it('filters stocks by category', function () {
    /** @var object{service: StockReportService} $this */
    $category = Category::factory()->create();
    createProductWithPurchaseItem(['category_id' => $category->id]);
    createProductWithPurchaseItem(); // other category

    $result = $this->service->getPaginatedFilteredStocks(['category' => $category->id]);

    expect($result->total())->toBe(1);
});

it('skips category filter when value is semua-kategori', function () {
    /** @var object{service: StockReportService} $this */
    createProductWithPurchaseItem();
    createProductWithPurchaseItem();

    $result = $this->service->getPaginatedFilteredStocks(['category' => 'semua-kategori']);

    expect($result->total())->toBe(2);
});

it('filters stocks by supplier', function () {
    /** @var object{service: StockReportService} $this */
    $supplier = Supplier::factory()->create();
    createProductWithPurchaseItem([], ['purchase_attrs' => ['supplier_id' => $supplier->id]]);
    createProductWithPurchaseItem();

    $result = $this->service->getPaginatedFilteredStocks(['supplier' => $supplier->id]);

    expect($result->total())->toBe(1);
});

it('skips supplier filter when value is semua-supplier', function () {
    /** @var object{service: StockReportService} $this */
    createProductWithPurchaseItem();
    createProductWithPurchaseItem();

    $result = $this->service->getPaginatedFilteredStocks(['supplier' => 'semua-supplier']);

    expect($result->total())->toBe(2);
});

it('transforms product data through paginator', function () {
    /** @var object{service: StockReportService} $this */
    $category = Category::factory()->create(['name' => 'Alat Tulis']);
    $supplier = Supplier::factory()->create(['name' => 'PT Alat']);
    $product = Product::factory()->create([
        'name' => 'Pensil',
        'category_id' => $category->id,
        'stock' => 15,
        'alert_stock' => 5,
    ]);
    $purchase = Purchase::factory()->create(['supplier_id' => $supplier->id]);
    PurchaseItem::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'qty' => 5,
    ]);

    $result = $this->service->getPaginatedFilteredStocks([]);

    $item = $result->first();
    expect($item)->toBeArray();
    expect($item['name'])->toBe('Pensil');
    expect($item['category'])->toBe('Alat Tulis');
    expect($item['stock'])->toBe(15);
    expect($item['alert_stock'])->toBe(5);
    expect($item['supplier'])->toBe('PT Alat');
});

it('excludes products with soft-deleted purchase items', function () {
    /** @var object{service: StockReportService} $this */
    $product = Product::factory()->create(['stock' => 10]);
    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'qty' => 5,
    ]);
    PurchaseItem::first()->delete();

    $result = $this->service->getPaginatedFilteredStocks([]);

    expect($result->total())->toBe(0);
});

it('gets filtered stocks for export', function () {
    /** @var object{service: StockReportService} $this */
    $category = Category::factory()->create(['name' => 'Export Category']);
    $product = createProductWithPurchaseItem(['category_id' => $category->id]);

    $stocks = $this->service->getFilteredStocksForExport([]);

    expect($stocks)->toHaveCount(1);
    $firstStock = $stocks->first();
    expect($firstStock->category)->toBe('Export Category');
    expect($firstStock->supplier)->not->toBe('-');
});

it('prepares export filters with resolved names', function () {
    /** @var object{service: StockReportService} $this */
    $category = Category::factory()->create(['name' => 'Makanan']);
    $supplier = Supplier::factory()->create(['name' => 'PT Indo']);

    $filters = $this->service->prepareExportFilters([
        'category' => $category->id,
        'supplier' => $supplier->id,
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
    ]);

    expect($filters['category'])->toBe('Makanan');
    expect($filters['supplier'])->toBe('PT Indo');
});

it('prepares export filters with default labels', function () {
    /** @var object{service: StockReportService} $this */
    $filters = $this->service->prepareExportFilters([
        'category' => 'semua-kategori',
        'supplier' => 'semua-supplier',
    ]);

    expect($filters['category'])->toBe('Semua Kategori');
    expect($filters['supplier'])->toBe('Semua Supplier');
});

it('includes only products that have purchase items', function () {
    /** @var object{service: StockReportService} $this */
    $productWithPurchase = createProductWithPurchaseItem(['name' => 'Has Purchase']);
    Product::factory()->create(['name' => 'No Purchase', 'stock' => 10]);

    $result = $this->service->getPaginatedFilteredStocks([]);

    expect($result->total())->toBe(1);
    expect($result->first()['name'])->toBe('Has Purchase');
});
