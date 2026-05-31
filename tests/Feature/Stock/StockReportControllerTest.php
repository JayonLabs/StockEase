<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    /** @var TestCase&object{admin:User, warehouse:User, cashier:User, category:Category, supplier:Supplier, product:Product} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);

    $this->category = Category::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $unit = Unit::factory()->create();

    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'unit_id' => $unit->id,
    ]);
});

// Helper — buat product dengan purchase item agar lolos whereHas('purchaseItems')
function stockProduct(Product $product, Supplier $supplier, array $purchaseAttributes = []): Product
{
    $purchase = Purchase::factory()->create(array_merge([
        'supplier_id' => $supplier->id,
        'date' => Carbon::today()->toDateString(),
    ], $purchaseAttributes));

    PurchaseItem::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'qty' => 10,
        'price' => 5000,
    ]);

    return $product;
}

// ============================================================
// Authorization
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user from all stock report routes', function (string $route) {
        get(route($route))->assertRedirect(route('login'));
    })->with([
        'reports.stock.index',
        'reports.stock.searchCategory',
        'reports.stock.searchSupplier',
        'reports.stock.export-to-pdf',
        'reports.stock.export-to-excel',
    ]);

    it('forbids cashier from all stock report routes', function (string $route) {
        /** @var TestCase&object{cashier:User} $this */
        actingAs($this->cashier)
            ->get(route($route))
            ->assertForbidden();
    })->with([
        'reports.stock.index',
        'reports.stock.searchCategory',
        'reports.stock.searchSupplier',
        'reports.stock.export-to-pdf',
        'reports.stock.export-to-excel',
    ]);

    it('allows admin and warehouse to access stock report index', function (string $role) {
        /** @var TestCase&object{admin:User, warehouse:User} $this */
        actingAs($this->{$role})
            ->get(route('reports.stock.index'))
            ->assertSuccessful();
    })->with(['admin', 'warehouse']);
});

// ============================================================
// Index
// ============================================================

describe('Index', function () {
    it('renders the Reports/Stock/Index component', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('reports.stock.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Reports/Stock/Index'));
    });

    it('returns empty filteredStocks when no filter params provided', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        stockProduct($this->product, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.index'))
            ->assertInertia(fn ($page) => $page->where('filteredStocks', []));
    });

    it('returns paginated stocks when filters are provided', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        stockProduct($this->product, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('filteredStocks.data', 1)
                    ->has('filteredStocks.current_page')
                    ->has('filteredStocks.total')
            );
    });

    it('returned stock data contains expected fields', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        stockProduct($this->product, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('filteredStocks.data.0.id')
                    ->has('filteredStocks.data.0.name')
                    ->has('filteredStocks.data.0.category')
                    ->has('filteredStocks.data.0.stock')
                    ->has('filteredStocks.data.0.alert_stock')
                    ->has('filteredStocks.data.0.supplier')
            );
    });

    it('excludes products without purchase items', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier, category:Category} $this */
        // Product tanpa purchaseItem — tidak boleh muncul
        $unit = Unit::factory()->create();
        Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $unit->id,
        ]);

        // Product dengan purchaseItem — harus muncul
        stockProduct($this->product, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertInertia(fn ($page) => $page->has('filteredStocks.data', 1));
    });

    it('paginates with default 10 per page', function () {
        /** @var TestCase&object{admin:User, category:Category, supplier:Supplier} $this */
        $unit = Unit::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            $product = Product::factory()->create([
                'category_id' => $this->category->id,
                'unit_id' => $unit->id,
            ]);
            stockProduct($product, $this->supplier);
        }

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('filteredStocks.data', 10)
                    ->where('filteredStocks.total', 12)
            );
    });
});

// ============================================================
// Index — date filter
// ============================================================

describe('Date filter', function () {
    it('filters stocks by purchase date range', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier, category:Category} $this */
        stockProduct($this->product, $this->supplier, ['date' => Carbon::today()->toDateString()]);

        $unit = Unit::factory()->create();
        $oldProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $unit->id,
        ]);
        stockProduct($oldProduct, $this->supplier, ['date' => Carbon::now()->subMonth()->toDateString()]);

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertInertia(fn ($page) => $page->has('filteredStocks.data', 1));
    });

    it('excludes stocks purchased before start_date', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        stockProduct($this->product, $this->supplier, ['date' => Carbon::now()->subDays(5)->toDateString()]);

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertInertia(fn ($page) => $page->has('filteredStocks.data', 0));
    });

    it('excludes stocks purchased after end_date', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        stockProduct($this->product, $this->supplier, ['date' => Carbon::tomorrow()->toDateString()]);

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertInertia(fn ($page) => $page->has('filteredStocks.data', 0));
    });
});

// ============================================================
// Index — category & supplier filter
// ============================================================

describe('Category and supplier filter', function () {
    it('filters stocks by specific category', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier, category:Category} $this */
        $otherCategory = Category::factory()->create();
        $unit = Unit::factory()->create();
        $otherProduct = Product::factory()->create([
            'category_id' => $otherCategory->id,
            'unit_id' => $unit->id,
        ]);

        stockProduct($this->product, $this->supplier);   // category yang kita filter
        stockProduct($otherProduct, $this->supplier);    // kategori lain

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => $this->category->id,
                'supplier' => 'semua-supplier',
            ]))
            ->assertInertia(fn ($page) => $page->has('filteredStocks.data', 1));
    });

    it('shows all categories when category is semua-kategori', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier, category:Category} $this */
        $otherCategory = Category::factory()->create();
        $unit = Unit::factory()->create();
        $otherProduct = Product::factory()->create([
            'category_id' => $otherCategory->id,
            'unit_id' => $unit->id,
        ]);

        stockProduct($this->product, $this->supplier);
        stockProduct($otherProduct, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertInertia(fn ($page) => $page->has('filteredStocks.data', 2));
    });

    it('filters stocks by specific supplier', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier, category:Category} $this */
        $otherSupplier = Supplier::factory()->create();
        $unit = Unit::factory()->create();
        $otherProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $unit->id,
        ]);

        stockProduct($this->product, $this->supplier);
        stockProduct($otherProduct, $otherSupplier);

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => $this->supplier->id,
            ]))
            ->assertInertia(fn ($page) => $page->has('filteredStocks.data', 1));
    });

    it('shows all suppliers when supplier is semua-supplier', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier, category:Category} $this */
        $otherSupplier = Supplier::factory()->create();
        $unit = Unit::factory()->create();
        $otherProduct = Product::factory()->create([
            'category_id' => $this->category->id,
            'unit_id' => $unit->id,
        ]);

        stockProduct($this->product, $this->supplier);
        stockProduct($otherProduct, $otherSupplier);

        actingAs($this->admin)
            ->get(route('reports.stock.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertInertia(fn ($page) => $page->has('filteredStocks.data', 2));
    });
});

// ============================================================
// Search Category
// ============================================================

describe('Search category', function () {
    it('returns category matching search query', function () {
        /** @var TestCase&object{admin:User} $this */
        Category::factory()->create(['name' => 'Electronics']);

        actingAs($this->admin)
            ->getJson(route('reports.stock.searchCategory', ['search' => 'Elect']))
            ->assertOk()
            ->assertJsonPath('message', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Electronics');
    });

    it('returns category by numeric ID', function () {
        /** @var TestCase&object{admin:User, category:Category} $this */
        actingAs($this->admin)
            ->getJson(route('reports.stock.searchCategory', ['search' => $this->category->id]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->category->id);
    });

    it('returns 404 when no category matches', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.stock.searchCategory', ['search' => 'xyznonexistent']))
            ->assertStatus(404);
    });

    it('limits results to 5 categories', function () {
        /** @var TestCase&object{admin:User} $this */
        Category::factory()->count(8)->create(['name' => 'Kategori Test']);

        actingAs($this->admin)
            ->getJson(route('reports.stock.searchCategory', ['search' => 'Kategori Test']))
            ->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('warehouse can search category', function () {
        /** @var TestCase&object{warehouse:User, category:Category} $this */
        actingAs($this->warehouse)
            ->getJson(route('reports.stock.searchCategory', ['search' => $this->category->name]))
            ->assertOk();
    });
});

// ============================================================
// Search Supplier
// ============================================================

describe('Search supplier', function () {
    it('returns supplier matching search query', function () {
        /** @var TestCase&object{admin:User} $this */
        Supplier::factory()->create(['name' => 'Tech Supplier Inc']);

        actingAs($this->admin)
            ->getJson(route('reports.stock.searchSupplier', ['search' => 'Tech']))
            ->assertOk()
            ->assertJsonPath('message', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Tech Supplier Inc');
    });

    it('returns supplier by numeric ID', function () {
        /** @var TestCase&object{admin:User, supplier:Supplier} $this */
        actingAs($this->admin)
            ->getJson(route('reports.stock.searchSupplier', ['search' => $this->supplier->id]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->supplier->id);
    });

    it('returns 404 when no supplier matches', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.stock.searchSupplier', ['search' => 'xyznonexistent']))
            ->assertStatus(404);
    });

    it('limits results to 5 suppliers', function () {
        /** @var TestCase&object{admin:User} $this */
        Supplier::factory()->count(8)->create(['name' => 'Supplier Test']);

        actingAs($this->admin)
            ->getJson(route('reports.stock.searchSupplier', ['search' => 'Supplier Test']))
            ->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('warehouse can search supplier', function () {
        /** @var TestCase&object{warehouse:User, supplier:Supplier} $this */
        actingAs($this->warehouse)
            ->getJson(route('reports.stock.searchSupplier', ['search' => $this->supplier->name]))
            ->assertOk();
    });
});

// ============================================================
// Export to PDF
// ============================================================

describe('Export to PDF', function () {
    it('downloads a PDF file', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        Storage::fake('local');
        stockProduct($this->product, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.export-to-pdf', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('warehouse can export to PDF', function () {
        /** @var TestCase&object{warehouse:User, product:Product, supplier:Supplier} $this */
        Storage::fake('local');
        stockProduct($this->product, $this->supplier);

        actingAs($this->warehouse)
            ->get(route('reports.stock.export-to-pdf', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertSuccessful();
    });

    it('stores PDF to local storage', function () {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::fake('local');

        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        stockProduct($this->product, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.export-to-pdf', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]));

        $year = Carbon::now('Asia/Shanghai')->format('Y');
        $month = Carbon::now('Asia/Shanghai')->translatedFormat('F');

        $storage->assertExists("reports/stock/{$year}/{$month}");
    });

    it('validates required fields for PDF export', function (array $data, array $errors) {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('reports.stock.export-to-pdf', $data))
            ->assertSessionHasErrors($errors);
    })->with([
        'missing start_date' => [
            ['end_date' => Carbon::today()->toDateString(), 'category' => 'semua-kategori', 'supplier' => 'semua-supplier'],
            ['start_date'],
        ],
        'missing end_date' => [
            ['start_date' => Carbon::today()->toDateString(), 'category' => 'semua-kategori', 'supplier' => 'semua-supplier'],
            ['end_date'],
        ],
        'invalid start_date' => [
            ['start_date' => 'bukan-tanggal', 'end_date' => Carbon::today()->toDateString(), 'category' => 'semua-kategori', 'supplier' => 'semua-supplier'],
            ['start_date'],
        ],
        'missing category' => [
            ['start_date' => Carbon::today()->toDateString(), 'end_date' => Carbon::today()->toDateString(), 'supplier' => 'semua-supplier'],
            ['category'],
        ],
        'missing supplier' => [
            ['start_date' => Carbon::today()->toDateString(), 'end_date' => Carbon::today()->toDateString(), 'category' => 'semua-kategori'],
            ['supplier'],
        ],
    ]);

    it('resolves specific category name in export filters', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier, category:Category} $this */
        Storage::fake('local');
        stockProduct($this->product, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.export-to-pdf', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => (string) $this->category->id,
                'supplier' => 'semua-supplier',
            ]))
            ->assertSuccessful();
    });

    it('resolves specific supplier name in export filters', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        Storage::fake('local');
        stockProduct($this->product, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.export-to-pdf', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => (string) $this->supplier->id,
            ]))
            ->assertSuccessful();
    });
});

// ============================================================
// Export to Excel
// ============================================================

describe('Export to Excel', function () {
    it('downloads an Excel file', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        Storage::fake('local');
        stockProduct($this->product, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.export-to-excel', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertSuccessful()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
    });

    it('warehouse can export to Excel', function () {
        /** @var TestCase&object{warehouse:User, product:Product, supplier:Supplier} $this */
        Storage::fake('local');
        stockProduct($this->product, $this->supplier);

        actingAs($this->warehouse)
            ->get(route('reports.stock.export-to-excel', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertSuccessful();
    });

    it('stores Excel to local storage', function () {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::fake('local');
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        stockProduct($this->product, $this->supplier);

        actingAs($this->admin)
            ->get(route('reports.stock.export-to-excel', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]));

        $year = Carbon::now('Asia/Shanghai')->format('Y');
        $month = Carbon::now('Asia/Shanghai')->translatedFormat('F');

        $storage->assertExists("reports/stock/{$year}/{$month}");
    });

    it('validates required fields for Excel export', function (array $data, array $errors) {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('reports.stock.export-to-excel', $data))
            ->assertSessionHasErrors($errors);
    })->with([
        'missing start_date' => [
            ['end_date' => Carbon::today()->toDateString(), 'category' => 'semua-kategori', 'supplier' => 'semua-supplier'],
            ['start_date'],
        ],
        'missing end_date' => [
            ['start_date' => Carbon::today()->toDateString(), 'category' => 'semua-kategori', 'supplier' => 'semua-supplier'],
            ['end_date'],
        ],
        'missing category' => [
            ['start_date' => Carbon::today()->toDateString(), 'end_date' => Carbon::today()->toDateString(), 'supplier' => 'semua-supplier'],
            ['category'],
        ],
        'missing supplier' => [
            ['start_date' => Carbon::today()->toDateString(), 'end_date' => Carbon::today()->toDateString(), 'category' => 'semua-kategori'],
            ['supplier'],
        ],
    ]);

    it('stores and downloads Excel exactly once per export', function () {
        /** @var TestCase&object{admin:User, product:Product, supplier:Supplier} $this */
        Excel::fake();
        stockProduct($this->product, $this->supplier);

        $today = Carbon::today()->toDateString();
        $year = Carbon::now('Asia/Shanghai')->format('Y');
        $month = Carbon::now('Asia/Shanghai')->translatedFormat('F');
        $fileName = 'Laporan Stock '
            .Carbon::parse($today)->translatedFormat('d F Y').' - '
            .Carbon::parse($today)->translatedFormat('d F Y').' StockEase.xlsx';

        actingAs($this->admin)
            ->get(route('reports.stock.export-to-excel', [
                'start_date' => $today,
                'end_date' => $today,
                'category' => 'semua-kategori',
                'supplier' => 'semua-supplier',
            ]))
            ->assertSuccessful();

        Excel::assertStored("reports/stock/{$year}/{$month}/{$fileName}", 'local');
        Excel::assertDownloaded($fileName);
    });
});
