<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
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
    /** @var TestCase&object{admin:User, cashier:User, warehouse:User, product:Product} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);

    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $this->product = Product::factory()->create([
        'category_id' => $category->id,
        'unit_id' => $unit->id,
    ]);
});

// Helper — buat completed sale dengan SaleItem
function saleReport(User $cashier, Product $product, array $attributes = []): Sale
{
    $sale = Sale::factory()->create(array_merge([
        'user_id' => $cashier->id,
        'date' => Carbon::today()->toDateString(),
        'status' => 'completed',
        'payment_method' => 'cash',
        'total' => 10000,
    ], $attributes));

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => $sale->total,
    ]);

    return $sale;
}

// ============================================================
// Authorization
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user from all sale report routes', function (string $route) {
        get(route($route))->assertRedirect(route('login'));
    })->with([
        'reports.sale.index',
        'reports.sale.search-cashier',
        'reports.sale.export-to-pdf',
        'reports.sale.export-to-excel',
    ]);

    it('forbids warehouse from all sale report routes', function (string $route) {
        /** @var TestCase&object{warehouse:User} $this */
        actingAs($this->warehouse)
            ->get(route($route))
            ->assertForbidden();
    })->with([
        'reports.sale.index',
        'reports.sale.search-cashier',
        'reports.sale.export-to-pdf',
        'reports.sale.export-to-excel',
    ]);

    it('allows admin and cashier to access sale report index', function (string $role) {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        actingAs($this->{$role})
            ->get(route('reports.sale.index'))
            ->assertSuccessful();
    })->with(['admin', 'cashier']);
});

// ============================================================
// Index
// ============================================================

describe('Index', function () {
    it('renders the Reports/Sale/Index component', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('reports.sale.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Reports/Sale/Index'));
    });

    it('returns empty sales when no filter is provided', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.sale.index'))
            ->assertInertia(fn ($page) => $page->where('sales', []));
    });

    it('returns sales data when filters are provided', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product, ['date' => Carbon::today()->toDateString()]);

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('sales.sales', 1)
                    ->has('sales.sumTotalSale')
                    ->has('sales.transactionCount')
                    ->has('sales.countProductSale')
                    ->has('sales.bestSellingProduct')
                    ->has('sales.salesTrend')
                    ->has('sales.productSalesShare')
            );
    });

    it('calculates sumTotalSale correctly', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product, ['total' => 25000]);
        saleReport($this->cashier, $this->product, ['total' => 15000]);

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('sales.sumTotalSale', 40000)
                    ->where('sales.transactionCount', 2)
            );
    });

    it('excludes draft sales from report', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product, ['status' => 'completed']);
        Sale::factory()->create([
            'user_id' => $this->cashier->id,
            'date' => Carbon::today()->toDateString(),
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => 99999,
        ]);

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('sales.sales', 1)
                    ->where('sales.sumTotalSale', fn ($val) => $val != 99999)
            );
    });
});

// ============================================================
// Index — date filter
// ============================================================

describe('Date filter', function () {
    it('filters sales within date range', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product, ['date' => Carbon::today()->toDateString()]);
        saleReport($this->cashier, $this->product, ['date' => Carbon::yesterday()->toDateString()]);
        saleReport($this->cashier, $this->product, ['date' => Carbon::now()->subDays(5)->toDateString()]); // outside

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::yesterday()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]))
            ->assertInertia(fn ($page) => $page->has('sales.sales', 2));
    });

    it('excludes sales before start date', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product, ['date' => Carbon::now()->subDays(2)->toDateString()]);
        saleReport($this->cashier, $this->product, ['date' => Carbon::today()->toDateString()]);

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]))
            ->assertInertia(fn ($page) => $page->has('sales.sales', 1));
    });

    it('excludes sales after end date', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product, ['date' => Carbon::today()->toDateString()]);
        saleReport($this->cashier, $this->product, ['date' => Carbon::tomorrow()->toDateString()]);

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]))
            ->assertInertia(fn ($page) => $page->has('sales.sales', 1));
    });

    it('returns empty when no sales in date range', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product, ['date' => Carbon::now()->subMonth()->toDateString()]);

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]))
            ->assertInertia(fn ($page) => $page->where('sales', []));
    });
});

// ============================================================
// Index — cashier & payment filter
// ============================================================

describe('Cashier and payment filter', function () {
    it('filters by specific cashier', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        $otherCashier = User::factory()->create(['role' => 'cashier']);

        saleReport($this->cashier, $this->product);
        saleReport($otherCashier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => $this->cashier->id,
                'payment' => 'semua-metode',
            ]))
            ->assertInertia(fn ($page) => $page->has('sales.sales', 1));
    });

    it('shows all cashiers when cashier is semua-cashier', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        $otherCashier = User::factory()->create(['role' => 'cashier']);

        saleReport($this->cashier, $this->product);
        saleReport($otherCashier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]))
            ->assertInertia(fn ($page) => $page->has('sales.sales', 2));
    });

    it('filters by specific payment method', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product, ['payment_method' => 'cash']);
        saleReport($this->cashier, $this->product, ['payment_method' => 'qris']);

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'cash',
            ]))
            ->assertInertia(fn ($page) => $page->has('sales.sales', 1));
    });

    it('shows all payment methods when payment is semua-metode', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product, ['payment_method' => 'cash']);
        saleReport($this->cashier, $this->product, ['payment_method' => 'qris']);

        actingAs($this->admin)
            ->get(route('reports.sale.index', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]))
            ->assertInertia(fn ($page) => $page->has('sales.sales', 2));
    });
});

// ============================================================
// Search Cashier
// ============================================================

describe('Search cashier', function () {
    it('returns cashier matching search query', function () {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.sale.search-cashier', ['search' => $this->cashier->name]))
            ->assertSuccessful()
            ->assertJsonPath('data.0.value', $this->cashier->id);
    });

    it('returns admin in cashier search results', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.sale.search-cashier', ['search' => $this->admin->name]))
            ->assertSuccessful()
            ->assertJsonPath('data.0.value', $this->admin->id);
    });

    it('does not return warehouse role in cashier search', function () {
        /** @var TestCase&object{admin:User, warehouse:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.sale.search-cashier', ['search' => $this->warehouse->name]))
            ->assertStatus(404)
            ->assertJsonPath('data', null);
    });

    it('returns empty data when search is blank', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.sale.search-cashier', ['search' => '']))
            ->assertSuccessful()
            ->assertJsonPath('data', []);
    });

    it('returns 404 when no cashier matches', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.sale.search-cashier', ['search' => 'xyznonexistent']))
            ->assertStatus(404);
    });

    it('returns label and value structure', function () {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.sale.search-cashier', ['search' => $this->cashier->name]))
            ->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    ['value', 'label'],
                ],
            ]);
    });

    it('searches cashier by exact id match only', function () {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        $cashier11 = User::factory()->create(['role' => 'cashier']);

        actingAs($this->admin)
            ->getJson(route('reports.sale.search-cashier', ['search' => (string) $this->cashier->id]))
            ->assertSuccessful()
            ->assertJsonPath('data.0.value', $this->cashier->id);

        // Searching for partial ID should not match longer IDs
        $response = actingAs($this->admin)
            ->getJson(route('reports.sale.search-cashier', ['search' => (string) $cashier11->id]));

        $response->assertSuccessful();
        $data = $response->json('data');
        $ids = collect($data)->pluck('value')->all();
        expect($ids)->toContain($cashier11->id)
            ->and($ids)->not->toContain($this->cashier->id);
    });
});

// ============================================================
// Export to PDF
// ============================================================

describe('Export to PDF', function () {
    it('downloads a PDF file', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        Storage::fake('local');
        saleReport($this->cashier, $this->product);

        $response = actingAs($this->admin)
            ->get(route('reports.sale.export-to-pdf', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/pdf');
    });

    it('cashier can also export to PDF', function () {
        /** @var TestCase&object{cashier:User, product:Product} $this */
        Storage::fake('local');
        saleReport($this->cashier, $this->product);

        $response = actingAs($this->cashier)
            ->get(route('reports.sale.export-to-pdf', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]));

        $response->assertSuccessful();
    });

    it('stores PDF to local storage', function () {

        /** @var FilesystemAdapter $storage */
        $storage = Storage::fake('local');

        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.sale.export-to-pdf', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]));

        $year = Carbon::now('Asia/Shanghai')->format('Y');
        $month = Carbon::now('Asia/Shanghai')->translatedFormat('F');

        $storage->assertExists("reports/sales/{$year}/{$month}");
    });

    it('validates required fields for PDF export', function (array $data, array $errors) {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('reports.sale.export-to-pdf', $data))
            ->assertSessionHasErrors($errors);
    })->with([
        'missing start' => [
            ['end' => Carbon::today()->toDateString(), 'cashier' => 'semua-cashier', 'payment' => 'semua-metode'],
            ['start'],
        ],
        'missing end' => [
            ['start' => Carbon::today()->toDateString(), 'cashier' => 'semua-cashier', 'payment' => 'semua-metode'],
            ['end'],
        ],
        'invalid start date' => [
            ['start' => 'bukan-tanggal', 'end' => Carbon::today()->toDateString(), 'cashier' => 'semua-cashier', 'payment' => 'semua-metode'],
            ['start'],
        ],
        'missing cashier' => [
            ['start' => Carbon::today()->toDateString(), 'end' => Carbon::today()->toDateString(), 'payment' => 'semua-metode'],
            ['cashier'],
        ],
        'missing payment' => [
            ['start' => Carbon::today()->toDateString(), 'end' => Carbon::today()->toDateString(), 'cashier' => 'semua-cashier'],
            ['payment'],
        ],
    ]);
});

// ============================================================
// Export to Excel
// ============================================================

describe('Export to Excel', function () {
    it('downloads an Excel file', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        Storage::fake('local');
        saleReport($this->cashier, $this->product);

        $response = actingAs($this->admin)
            ->get(route('reports.sale.export-to-excel', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]));

        $response->assertSuccessful();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    });

    it('stores Excel to local storage', function () {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::fake('local');

        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        saleReport($this->cashier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.sale.export-to-excel', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]));

        $year = Carbon::now('Asia/Shanghai')->format('Y');
        $month = Carbon::now('Asia/Shanghai')->translatedFormat('F');

        $storage->assertExists("reports/sales/{$year}/{$month}");
    });

    it('validates required fields for Excel export', function (array $data, array $errors) {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('reports.sale.export-to-excel', $data))
            ->assertSessionHasErrors($errors);
    })->with([
        'missing start' => [
            ['end' => Carbon::today()->toDateString(), 'cashier' => 'semua-cashier', 'payment' => 'semua-metode'],
            ['start'],
        ],
        'missing end' => [
            ['start' => Carbon::today()->toDateString(), 'cashier' => 'semua-cashier', 'payment' => 'semua-metode'],
            ['end'],
        ],
        'missing cashier' => [
            ['start' => Carbon::today()->toDateString(), 'end' => Carbon::today()->toDateString(), 'payment' => 'semua-metode'],
            ['cashier'],
        ],
        'missing payment' => [
            ['start' => Carbon::today()->toDateString(), 'end' => Carbon::today()->toDateString(), 'cashier' => 'semua-cashier'],
            ['payment'],
        ],
    ]);

    it('resolves cashier name correctly in prepareExcelFilters', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        Storage::fake('local');
        saleReport($this->cashier, $this->product);

        // Tidak throw — cashier ID valid di-resolve ke nama
        $response = actingAs($this->admin)
            ->get(route('reports.sale.export-to-excel', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'cashier' => (string) $this->cashier->id,
                'payment' => 'semua-metode',
            ]));

        $response->assertSuccessful();
    });

    it('stores and downloads Excel exactly once per export', function () {
        /** @var TestCase&object{admin:User, cashier:User, product:Product} $this */
        Excel::fake();
        saleReport($this->cashier, $this->product);

        $today = Carbon::today()->toDateString();
        $year = Carbon::now('Asia/Shanghai')->format('Y');
        $month = Carbon::now('Asia/Shanghai')->translatedFormat('F');
        $fileName = 'Laporan Penjualan '
            .Carbon::parse($today)->translatedFormat('d F Y').' - '
            .Carbon::parse($today)->translatedFormat('d F Y').' StockEase.xlsx';

        actingAs($this->admin)
            ->get(route('reports.sale.export-to-excel', [
                'start' => $today,
                'end' => $today,
                'cashier' => 'semua-cashier',
                'payment' => 'semua-metode',
            ]))
            ->assertSuccessful();

        Excel::assertStored("reports/sales/{$year}/{$month}/{$fileName}", 'local');
        Excel::assertDownloaded($fileName);
    });
});
