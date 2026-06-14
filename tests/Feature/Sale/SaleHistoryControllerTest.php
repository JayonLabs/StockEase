<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    /** @var TestCase&object{admin:User, cashier:User, warehouse:User} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
});

// Helper untuk buat sale yang lolos filter pending
function completedSale(array $attributes = []): Sale
{
    return Sale::factory()->create(array_merge([
        'payment_method' => 'cash',
        'status' => 'completed',
    ], $attributes));
}

// ============================================================
// Authorization
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user from sale index', function () {
        get(route('sale.index'))->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from sale show', function () {
        $sale = completedSale();
        get(route('sale.show', $sale))->assertRedirect(route('login'));
    });

    it('forbids warehouse from sale index', function () {
        /** @var TestCase&object{warehouse:User} $this */
        actingAs($this->warehouse)
            ->get(route('sale.index'))
            ->assertForbidden();
    });

    it('forbids warehouse from sale show', function () {
        /** @var TestCase&object{warehouse:User} $this */
        $sale = completedSale();

        actingAs($this->warehouse)
            ->get(route('sale.show', $sale))
            ->assertForbidden();
    });

    it('forbids warehouse from export to pdf', function () {
        /** @var TestCase&object{warehouse:User} $this */
        $sale = completedSale();

        actingAs($this->warehouse)
            ->get(route('sale.export-to-pdf', $sale))
            ->assertForbidden();
    });

    it('allows admin and cashier to access sale index', function (string $role) {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        $user = $this->{$role};

        actingAs($user)
            ->get(route('sale.index'))
            ->assertSuccessful();
    })->with(['admin', 'cashier']);

    it('allows admin and cashier to access sale show', function (string $role) {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        $sale = completedSale();
        $user = $this->{$role};

        actingAs($user)
            ->get(route('sale.show', $sale))
            ->assertSuccessful();
    })->with(['admin', 'cashier']);
});

// ============================================================
// Index — listing & pagination
// ============================================================

describe('Index', function () {
    it('renders the Sale/Index component', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Sale/Index'));
    });

    it('passes sales prop with paginator structure', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('sales.data')
                    ->has('sales.current_page')
                    ->has('sales.per_page')
                    ->has('sales.total')
            );
    });

    it('paginates with default 10 per page', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale() && Sale::factory()->count(12)->create(['payment_method' => 'cash']);

        actingAs($this->admin)
            ->get(route('sale.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('sales.data', 10)
                    ->where('sales.total', 13)
            );
    });

    it('respects per_page query parameter', function () {
        /** @var TestCase&object{admin:User} $this */
        Sale::factory()->count(10)->create(['payment_method' => 'cash']);

        actingAs($this->admin)
            ->get(route('sale.index', ['per_page' => 5]))
            ->assertInertia(fn ($page) => $page->has('sales.data', 5));
    });

    it('excludes sales with pending payment_method', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale();
        Sale::factory()->create(['payment_method' => 'pending']);

        actingAs($this->admin)
            ->get(route('sale.index'))
            ->assertInertia(fn ($page) => $page->has('sales.data', 1));
    });

    it('orders sales by created_at descending', function () {
        /** @var TestCase&object{admin:User} $this */
        $older = completedSale(['created_at' => now()->subDays(2)]);
        $newer = completedSale(['created_at' => now()]);

        actingAs($this->admin)
            ->get(route('sale.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('sales.data.0.id', $newer->id)
                    ->where('sales.data.1.id', $older->id)
            );
    });

    it('returns empty list when no non-pending sales exist', function () {
        /** @var TestCase&object{admin:User} $this */
        Sale::factory()->count(3)->create(['payment_method' => 'pending']);

        actingAs($this->admin)
            ->get(route('sale.index'))
            ->assertInertia(fn ($page) => $page->has('sales.data', 0));
    });
});

// ============================================================
// Index — filtering by date range
// ============================================================

describe('Date range filter', function () {
    it('filters sales within date range', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale(['date' => '2024-04-01']);
        completedSale(['date' => '2024-04-15']);
        completedSale(['date' => '2024-04-30']);
        completedSale(['date' => '2024-05-01']); // outside

        actingAs($this->admin)
            ->get(route('sale.index', ['start' => '2024-04-01', 'end' => '2024-04-30']))
            ->assertInertia(fn ($page) => $page->has('sales.data', 3));
    });

    it('excludes sales before start date', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale(['date' => '2024-03-31']); // before
        completedSale(['date' => '2024-04-01']);

        actingAs($this->admin)
            ->get(route('sale.index', ['start' => '2024-04-01', 'end' => '2024-04-30']))
            ->assertInertia(fn ($page) => $page->has('sales.data', 1));
    });

    it('excludes sales after end date', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale(['date' => '2024-04-30']);
        completedSale(['date' => '2024-05-01']); // after

        actingAs($this->admin)
            ->get(route('sale.index', ['start' => '2024-04-01', 'end' => '2024-04-30']))
            ->assertInertia(fn ($page) => $page->has('sales.data', 1));
    });

    it('ignores date filter when only start is provided', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale(['date' => '2024-03-01']);
        completedSale(['date' => '2024-04-01']);

        // Service hanya apply filter jika KEDUANYA ada
        actingAs($this->admin)
            ->get(route('sale.index', ['start' => '2024-04-01']))
            ->assertInertia(fn ($page) => $page->has('sales.data', 2));
    });

    it('ignores date filter when only end is provided', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale(['date' => '2024-03-01']);
        completedSale(['date' => '2024-04-01']);

        actingAs($this->admin)
            ->get(route('sale.index', ['end' => '2024-04-01']))
            ->assertInertia(fn ($page) => $page->has('sales.data', 2));
    });
});

// ============================================================
// Index — search
// ============================================================

describe('Search filter', function () {
    it('searches by customer name', function () {
        /** @var TestCase&object{admin:User} $this */
        $cashier = User::factory()->create(['name' => 'Budi Santoso']);
        completedSale(['customer_name' => 'John Doe', 'user_id' => $cashier->id]);
        completedSale(['customer_name' => 'Jane Smith', 'user_id' => $cashier->id]);

        actingAs($this->admin)
            ->get(route('sale.index', ['search' => 'John']))
            ->assertInertia(
                fn ($page) => $page
                    ->has('sales.data', 1)
                    ->where('sales.data.0.customer_name', 'John Doe')
            );
    });

    it('searches by cashier name', function () {
        /** @var TestCase&object{admin:User} $this */
        $cashierA = User::factory()->create(['name' => 'Budi Kasir', 'role' => 'cashier']);
        $cashierB = User::factory()->create(['name' => 'Rina Kasir', 'role' => 'cashier']);

        completedSale(['user_id' => $cashierA->id]);
        completedSale(['user_id' => $cashierB->id]);

        actingAs($this->admin)
            ->get(route('sale.index', ['search' => 'Budi']))
            ->assertInertia(fn ($page) => $page->has('sales.data', 1));
    });

    it('searches by product name through sale items', function () {
        /** @var TestCase&object{admin:User} $this */
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();

        $productA = Product::factory()->create([
            'name' => 'Susu Sapi',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
        ]);
        $productB = Product::factory()->create([
            'name' => 'Kopi Arabika',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
        ]);

        $saleA = completedSale();
        SaleItem::factory()->create(['sale_id' => $saleA->id, 'product_id' => $productA->id]);

        $saleB = completedSale();
        SaleItem::factory()->create(['sale_id' => $saleB->id, 'product_id' => $productB->id]);

        actingAs($this->admin)
            ->get(route('sale.index', ['search' => 'Susu']))
            ->assertInertia(fn ($page) => $page->has('sales.data', 1));
    });

    it('searches by product SKU through sale items', function () {
        /** @var TestCase&object{admin:User} $this */
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();

        $product = Product::factory()->create([
            'sku' => 'SKU-001',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
        ]);

        $sale = completedSale();
        SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id]);

        completedSale(); // sale tanpa produk ini

        actingAs($this->admin)
            ->get(route('sale.index', ['search' => 'SKU-001']))
            ->assertInertia(fn ($page) => $page->has('sales.data', 1));
    });

    it('returns empty when search has no match', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale(['customer_name' => 'John Doe']);

        actingAs($this->admin)
            ->get(route('sale.index', ['search' => 'xyznonexistent']))
            ->assertInertia(fn ($page) => $page->has('sales.data', 0));
    });

    it('search is case insensitive', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale(['customer_name' => 'John Doe']);

        actingAs($this->admin)
            ->get(route('sale.index', ['search' => 'john doe']))
            ->assertInertia(fn ($page) => $page->has('sales.data', 1));
    });
});

// ============================================================
// Index — combined search and date filter
// ============================================================

describe('Combined search and date filter', function () {
    it('filters by both search and date range simultaneously', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale(['customer_name' => 'Budi Customer', 'date' => '2024-04-01']);
        completedSale(['customer_name' => 'Budi Customer', 'date' => '2024-04-15']);
        completedSale(['customer_name' => 'Budi Customer', 'date' => '2024-05-01']); // outside date
        completedSale(['customer_name' => 'Rina Customer', 'date' => '2024-04-10']);

        actingAs($this->admin)
            ->get(route('sale.index', [
                'search' => 'Budi',
                'start' => '2024-04-01',
                'end' => '2024-04-30',
            ]))
            ->assertInertia(fn ($page) => $page->has('sales.data', 2));
    });

    it('preserves search when date filter returns empty', function () {
        /** @var TestCase&object{admin:User} $this */
        completedSale(['customer_name' => 'Target Customer', 'date' => '2024-06-01']);

        actingAs($this->admin)
            ->get(route('sale.index', [
                'search' => 'Target',
                'start' => '2024-04-01',
                'end' => '2024-04-30',
            ]))
            ->assertInertia(fn ($page) => $page->has('sales.data', 0));
    });

    it('passes filters prop back to Vue component', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale.index', [
                'search' => 'ABC',
                'start' => '2024-04-01',
                'end' => '2024-04-30',
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.search', 'ABC')
                    ->where('filters.start', '2024-04-01')
                    ->where('filters.end', '2024-04-30')
            );
    });

    it('eager loads user roles to prevent implicit n plus one during serialization', function () {
        /** @var TestCase&object{admin:User} $this */
        $cashierA = User::factory()->create(['role' => 'cashier']);
        $cashierB = User::factory()->create(['role' => 'cashier']);

        Sale::factory()->count(3)->create([
            'user_id' => $cashierA->id,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);
        Sale::factory()->count(3)->create([
            'user_id' => $cashierB->id,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        DB::enableQueryLog();

        actingAs($this->admin)
            ->get(route('sale.index'))
            ->assertSuccessful();

        $roleQueries = collect(DB::getQueryLog())
            ->filter(fn ($query) => str_contains($query['query'], 'model_has_roles'));

        // With user.roles eager loaded, we expect exactly 1 roles query:
        // the authenticated user's roles are already loaded by factory, so
        // HandleInertiaRequests loadMissing skips them. Only the eager loaded
        // sale user roles trigger one query.
        // Without the fix, HandleInertiaRequests would re-query roles,
        // producing a duplicate.
        expect($roleQueries)->toHaveCount(1);
    });

    it('exposes user role in paginated sales without triggering lazy loading', function () {
        /** @var TestCase&object{admin:User} $this */
        $cashier = User::factory()->create(['role' => 'cashier']);

        Sale::factory()->count(3)->create([
            'user_id' => $cashier->id,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        actingAs($this->admin)
            ->get(route('sale.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('sales.data.0.user.role')
                    ->where('sales.data.0.user.role', 'cashier')
            );
    });
});

// ============================================================
// Show
// ============================================================

describe('Show', function () {
    it('renders the Sale/Show component', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = completedSale();

        actingAs($this->admin)
            ->get(route('sale.show', $sale))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Sale/Show'));
    });

    it('passes sale prop with expected keys', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = completedSale();

        actingAs($this->admin)
            ->get(route('sale.show', $sale))
            ->assertInertia(
                fn ($page) => $page
                    ->has('sale.id')
                    ->has('sale.total')
                    ->has('sale.payment_method')
                    ->has('sale.sale_items')
            );
    });

    it('loads sale items and products eagerly', function () {
        /** @var TestCase&object{admin:User} $this */
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
        ]);

        $sale = completedSale();
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
        ]);

        actingAs($this->admin)
            ->get(route('sale.show', $sale))
            ->assertInertia(
                fn ($page) => $page
                    ->has('sale.sale_items', 1)
                    ->has('sale.sale_items.0.product')
            );
    });

    it('returns 404 for non-existent sale', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale.show', 999999))
            ->assertNotFound();
    });
});

// ============================================================
// Export to PDF
// ============================================================

describe('Export to PDF', function () {
    it('returns a PDF download response for admin', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = completedSale();

        $response = actingAs($this->admin)
            ->get(route('sale.export-to-pdf', $sale));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/pdf');
    });

    it('returns a PDF download response for cashier', function () {
        /** @var TestCase&object{cashier:User} $this */
        $sale = completedSale();

        $response = actingAs($this->cashier)
            ->get(route('sale.export-to-pdf', $sale));

        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/pdf');
    });

    it('uses correct filename in content-disposition header', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = completedSale();

        $response = actingAs($this->admin)
            ->get(route('sale.export-to-pdf', $sale));

        $response->assertHeader(
            'content-disposition',
            "attachment; filename=invoice-{$sale->id}.pdf"
        );
    });

    it('returns 404 for non-existent sale on export', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale.export-to-pdf', 999999))
            ->assertNotFound();
    });
});
