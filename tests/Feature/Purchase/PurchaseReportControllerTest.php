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
    /** @var TestCase&object{admin:User, warehouse:User, cashier:User, supplier:Supplier, product:Product} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);

    $this->supplier = Supplier::factory()->create();

    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $this->product = Product::factory()->create([
        'category_id' => $category->id,
        'unit_id' => $unit->id,
    ]);
});

// Helper — buat purchase dengan PurchaseItem
function purchaseReport(User $user, Supplier $supplier, Product $product, array $attributes = []): Purchase
{
    $purchase = Purchase::factory()->create(array_merge([
        'user_id' => $user->id,
        'supplier_id' => $supplier->id,
        'created_at' => Carbon::today(),
        'total' => 5000,
    ], $attributes));

    PurchaseItem::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'qty' => 5,
        'price' => 1000,
    ]);

    return $purchase;
}

// ============================================================
// Authorization
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user from all purchase report routes', function (string $route) {
        get(route($route))->assertRedirect(route('login'));
    })->with([
        'reports.purchase.index',
        'reports.purchase.search-supplier',
        'reports.purchase.search-user',
        'reports.purchase.export-to-pdf',
        'reports.purchase.export-to-excel',
    ]);

    it('forbids cashier from all purchase report routes', function (string $route) {
        /** @var TestCase&object{cashier:User} $this */
        actingAs($this->cashier)
            ->get(route($route))
            ->assertForbidden();
    })->with([
        'reports.purchase.index',
        'reports.purchase.search-supplier',
        'reports.purchase.search-user',
        'reports.purchase.export-to-pdf',
        'reports.purchase.export-to-excel',
    ]);

    it('allows admin and warehouse to access purchase report index', function (string $role) {
        /** @var TestCase&object{admin:User, warehouse:User} $this */
        actingAs($this->{$role})
            ->get(route('reports.purchase.index'))
            ->assertSuccessful();
    })->with(['admin', 'warehouse']);
});

// ============================================================
// Index
// ============================================================

describe('Index', function () {
    it('renders the Reports/Purchase/Index component', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('reports.purchase.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Reports/Purchase/Index'));
    });

    it('returns empty filters when no filter params provided', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.index'))
            ->assertInertia(fn ($page) => $page->where('filters', []));
    });

    it('returns purchase data when filters are provided', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('filters.filters', 1)
                    ->has('filters.sumTotalPurchase')
                    ->has('filters.totalItemsPurchased')
                    ->has('filters.totalTransaction')
                    ->has('filters.purchaseTrends')
                    ->has('filters.topSupplier')
            );
    });

    it('calculates sumTotalPurchase correctly', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        purchaseReport($this->warehouse, $this->supplier, $this->product, ['total' => 10000]);
        purchaseReport($this->warehouse, $this->supplier, $this->product, ['total' => 20000]);

        actingAs($this->admin)
            ->get(route('reports.purchase.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.sumTotalPurchase', 30000)
                    ->where('filters.totalTransaction', 2)
            );
    });

    it('returns empty array when no purchases match filters', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        purchaseReport($this->warehouse, $this->supplier, $this->product, [
            'created_at' => Carbon::now()->subMonth(),
        ]);

        actingAs($this->admin)
            ->get(route('reports.purchase.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertInertia(fn ($page) => $page->where('filters', []));
    });
});

// ============================================================
// Index — date filter
// ============================================================

describe('Date filter', function () {
    it('filters purchases within date range', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        purchaseReport($this->warehouse, $this->supplier, $this->product, ['created_at' => Carbon::today()]);
        purchaseReport($this->warehouse, $this->supplier, $this->product, ['created_at' => Carbon::yesterday()]);
        purchaseReport($this->warehouse, $this->supplier, $this->product, ['created_at' => Carbon::now()->subDays(5)]); // outside

        actingAs($this->admin)
            ->get(route('reports.purchase.index', [
                'start_date' => Carbon::yesterday()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertInertia(fn ($page) => $page->has('filters.filters', 2));
    });

    it('excludes purchases before start_date', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        purchaseReport($this->warehouse, $this->supplier, $this->product, ['created_at' => Carbon::now()->subDays(2)]);
        purchaseReport($this->warehouse, $this->supplier, $this->product, ['created_at' => Carbon::today()]);

        actingAs($this->admin)
            ->get(route('reports.purchase.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertInertia(fn ($page) => $page->has('filters.filters', 1));
    });

    it('excludes purchases after end_date', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        purchaseReport($this->warehouse, $this->supplier, $this->product, ['created_at' => Carbon::today()]);
        purchaseReport($this->warehouse, $this->supplier, $this->product, ['created_at' => Carbon::tomorrow()]);

        actingAs($this->admin)
            ->get(route('reports.purchase.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertInertia(fn ($page) => $page->has('filters.filters', 1));
    });
});

// ============================================================
// Index — supplier & user filter
// ============================================================

describe('Supplier and user filter', function () {
    it('filters by specific supplier', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        $otherSupplier = Supplier::factory()->create();

        purchaseReport($this->warehouse, $this->supplier, $this->product);
        purchaseReport($this->warehouse, $otherSupplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => $this->supplier->id,
                'user' => 'semua-user',
            ]))
            ->assertInertia(fn ($page) => $page->has('filters.filters', 1));
    });

    it('shows all suppliers when supplier is semua-supplier', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        $otherSupplier = Supplier::factory()->create();

        purchaseReport($this->warehouse, $this->supplier, $this->product);
        purchaseReport($this->warehouse, $otherSupplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertInertia(fn ($page) => $page->has('filters.filters', 2));
    });

    it('filters by specific user', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        $otherWarehouse = User::factory()->create(['role' => 'warehouse']);

        purchaseReport($this->warehouse, $this->supplier, $this->product);
        purchaseReport($otherWarehouse, $this->supplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => $this->warehouse->id,
            ]))
            ->assertInertia(fn ($page) => $page->has('filters.filters', 1));
    });

    it('shows all users when user is semua-user', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        $otherWarehouse = User::factory()->create(['role' => 'warehouse']);

        purchaseReport($this->warehouse, $this->supplier, $this->product);
        purchaseReport($otherWarehouse, $this->supplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.index', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertInertia(fn ($page) => $page->has('filters.filters', 2));
    });
});

// ============================================================
// Search Supplier
// ============================================================

describe('Search supplier', function () {
    it('returns supplier matching search query', function () {
        /** @var TestCase&object{admin:User, supplier:Supplier} $this */
        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-supplier', ['search' => $this->supplier->name]))
            ->assertSuccessful()
            ->assertJsonPath('data.0.value', $this->supplier->id);
    });

    it('returns label and value structure', function () {
        /** @var TestCase&object{admin:User, supplier:Supplier} $this */
        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-supplier', ['search' => $this->supplier->name]))
            ->assertSuccessful()
            ->assertJsonStructure([
                'data' => [['value', 'label']],
            ]);
    });

    it('returns empty data when search is blank', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-supplier', ['search' => '']))
            ->assertSuccessful()
            ->assertJsonPath('data', []);
    });

    it('returns 404 when no supplier matches', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-supplier', ['search' => 'xyznonexistent']))
            ->assertStatus(404);
    });

    it('warehouse can search supplier', function () {
        /** @var TestCase&object{warehouse:User, supplier:Supplier} $this */
        actingAs($this->warehouse)
            ->getJson(route('reports.purchase.search-supplier', ['search' => $this->supplier->name]))
            ->assertSuccessful();
    });

    it('searches supplier by exact id match only', function () {
        /** @var TestCase&object{admin:User, supplier:Supplier} $this */
        $supplier2 = Supplier::factory()->create();

        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-supplier', ['search' => (string) $this->supplier->id]))
            ->assertSuccessful()
            ->assertJsonPath('data.0.value', $this->supplier->id);

        $response = actingAs($this->admin)
            ->getJson(route('reports.purchase.search-supplier', ['search' => (string) $supplier2->id]));

        $response->assertSuccessful();
        $data = $response->json('data');
        $ids = collect($data)->pluck('value')->all();
        expect($ids)->toContain($supplier2->id)
            ->and($ids)->not->toContain($this->supplier->id);
    });
});

// ============================================================
// Search User
// ============================================================

describe('Search user', function () {
    it('returns warehouse user matching search query', function () {
        /** @var TestCase&object{admin:User, warehouse:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-user', ['search' => $this->warehouse->name]))
            ->assertSuccessful()
            ->assertJsonPath('data.0.value', $this->warehouse->id);
    });

    it('returns admin user in search results', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-user', ['search' => $this->admin->name]))
            ->assertSuccessful()
            ->assertJsonPath('data.0.value', $this->admin->id);
    });

    it('does not return cashier role in user search', function () {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-user', ['search' => $this->cashier->name]))
            ->assertStatus(404)
            ->assertJsonPath('data', null);
    });

    it('returns empty data when search is blank', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-user', ['search' => '']))
            ->assertSuccessful()
            ->assertJsonPath('data', []);
    });

    it('returns 404 when no user matches', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-user', ['search' => 'xyznonexistent']))
            ->assertStatus(404);
    });

    it('returns label and value structure', function () {
        /** @var TestCase&object{admin:User, warehouse:User} $this */
        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-user', ['search' => $this->warehouse->name]))
            ->assertSuccessful()
            ->assertJsonStructure([
                'data' => [['value', 'label']],
            ]);
    });

    it('searches user by exact id match only', function () {
        /** @var TestCase&object{admin:User, warehouse:User} $this */
        $otherWarehouse = User::factory()->create(['role' => 'warehouse']);

        actingAs($this->admin)
            ->getJson(route('reports.purchase.search-user', ['search' => (string) $this->warehouse->id]))
            ->assertSuccessful()
            ->assertJsonPath('data.0.value', $this->warehouse->id);

        $response = actingAs($this->admin)
            ->getJson(route('reports.purchase.search-user', ['search' => (string) $otherWarehouse->id]));

        $response->assertSuccessful();
        $data = $response->json('data');
        $ids = collect($data)->pluck('value')->all();
        expect($ids)->toContain($otherWarehouse->id)
            ->and($ids)->not->toContain($this->warehouse->id);
    });
});

// ============================================================
// Export to PDF
// ============================================================

describe('Export to PDF', function () {
    it('downloads a PDF file', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        Storage::fake('local');
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.export-to-pdf', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertSuccessful()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('warehouse can export to PDF', function () {
        /** @var TestCase&object{warehouse:User, supplier:Supplier, product:Product} $this */
        Storage::fake('local');
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        actingAs($this->warehouse)
            ->get(route('reports.purchase.export-to-pdf', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertSuccessful();
    });

    it('stores PDF to local storage', function () {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::fake('local');

        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.export-to-pdf', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]));

        $year = Carbon::now('Asia/Shanghai')->format('Y');
        $month = Carbon::now('Asia/Shanghai')->translatedFormat('F');

        $storage->assertExists("reports/purchase/{$year}/{$month}");
    });

    it('validates required fields for PDF export', function (array $data, array $errors) {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('reports.purchase.export-to-pdf', $data))
            ->assertSessionHasErrors($errors);
    })->with([
        'missing start_date' => [
            ['end_date' => Carbon::today()->toDateString(), 'supplier' => 'semua-supplier', 'user' => 'semua-user'],
            ['start_date'],
        ],
        'missing end_date' => [
            ['start_date' => Carbon::today()->toDateString(), 'supplier' => 'semua-supplier', 'user' => 'semua-user'],
            ['end_date'],
        ],
        'invalid start_date' => [
            ['start_date' => 'bukan-tanggal', 'end_date' => Carbon::today()->toDateString(), 'supplier' => 'semua-supplier', 'user' => 'semua-user'],
            ['start_date'],
        ],
        'missing supplier' => [
            ['start_date' => Carbon::today()->toDateString(), 'end_date' => Carbon::today()->toDateString(), 'user' => 'semua-user'],
            ['supplier'],
        ],
        'missing user' => [
            ['start_date' => Carbon::today()->toDateString(), 'end_date' => Carbon::today()->toDateString(), 'supplier' => 'semua-supplier'],
            ['user'],
        ],
    ]);
});

// ============================================================
// Export to Excel
// ============================================================

describe('Export to Excel', function () {
    it('downloads an Excel file', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        Storage::fake('local');
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.export-to-excel', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertSuccessful()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
    });

    it('warehouse can export to Excel', function () {
        /** @var TestCase&object{warehouse:User, supplier:Supplier, product:Product} $this */
        Storage::fake('local');
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        actingAs($this->warehouse)
            ->get(route('reports.purchase.export-to-excel', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertSuccessful();
    });

    it('stores Excel to local storage', function () {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::fake('local');

        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.export-to-excel', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]));

        $year = Carbon::now('Asia/Shanghai')->format('Y');
        $month = Carbon::now('Asia/Shanghai')->translatedFormat('F');

        $storage->assertExists("reports/purchase/{$year}/{$month}");
    });

    it('resolves specific supplier name in Excel filters', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        Storage::fake('local');
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.export-to-excel', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => (string) $this->supplier->id,
                'user' => 'semua-user',
            ]))
            ->assertSuccessful();
    });

    it('resolves specific user name in Excel filters', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        Storage::fake('local');
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        actingAs($this->admin)
            ->get(route('reports.purchase.export-to-excel', [
                'start_date' => Carbon::today()->toDateString(),
                'end_date' => Carbon::today()->toDateString(),
                'supplier' => 'semua-supplier',
                'user' => (string) $this->warehouse->id,
            ]))
            ->assertSuccessful();
    });

    it('validates required fields for Excel export', function (array $data, array $errors) {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('reports.purchase.export-to-excel', $data))
            ->assertSessionHasErrors($errors);
    })->with([
        'missing start_date' => [
            ['end_date' => Carbon::today()->toDateString(), 'supplier' => 'semua-supplier', 'user' => 'semua-user'],
            ['start_date'],
        ],
        'missing end_date' => [
            ['start_date' => Carbon::today()->toDateString(), 'supplier' => 'semua-supplier', 'user' => 'semua-user'],
            ['end_date'],
        ],
        'missing supplier' => [
            ['start_date' => Carbon::today()->toDateString(), 'end_date' => Carbon::today()->toDateString(), 'user' => 'semua-user'],
            ['supplier'],
        ],
        'missing user' => [
            ['start_date' => Carbon::today()->toDateString(), 'end_date' => Carbon::today()->toDateString(), 'supplier' => 'semua-supplier'],
            ['user'],
        ],
    ]);

    it('stores and downloads Excel exactly once per export', function () {
        /** @var TestCase&object{admin:User, warehouse:User, supplier:Supplier, product:Product} $this */
        Excel::fake();
        purchaseReport($this->warehouse, $this->supplier, $this->product);

        $today = Carbon::today()->toDateString();
        $year = Carbon::now('Asia/Shanghai')->format('Y');
        $month = Carbon::now('Asia/Shanghai')->translatedFormat('F');
        $fileName = 'Laporan Pembelian '
            .Carbon::parse($today)->translatedFormat('d F Y').' - '
            .Carbon::parse($today)->translatedFormat('d F Y').' StockEase.xlsx';

        actingAs($this->admin)
            ->get(route('reports.purchase.export-to-excel', [
                'start_date' => $today,
                'end_date' => $today,
                'supplier' => 'semua-supplier',
                'user' => 'semua-user',
            ]))
            ->assertSuccessful();

        Excel::assertStored("reports/purchase/{$year}/{$month}/{$fileName}", 'local');
        Excel::assertDownloaded($fileName);
    });
});
