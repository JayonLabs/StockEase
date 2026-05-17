<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    /** @var TestCase&object{admin: User} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
});

/**
 * Helper — get or create users to avoid Intelephense warnings with $this
 */
function admin(): User
{
    return User::role('admin')->first() ?? User::factory()->create(['role' => 'admin']);
}

function warehouse(): User
{
    return User::role('warehouse')->first() ?? User::factory()->create(['role' => 'warehouse']);
}

function cashier(): User
{
    return User::role('cashier')->first() ?? User::factory()->create(['role' => 'cashier']);
}

/**
 * Helper — buat completed sale dengan SaleItem dan cost
 */
function profitSale(User $user, array $saleAttributes = [], array $itemAttributes = []): Sale
{
    $product = Product::factory()->create(array_merge([
        'purchase_price' => 5000,
        'selling_price' => 10000,
    ], $itemAttributes['product'] ?? []));

    $sale = Sale::factory()->create(array_merge([
        'user_id' => $user->id,
        'status' => 'completed',
        'payment_method' => 'cash',
        'date' => Carbon::today()->toDateString(),
        'total' => 10000,
        'total_cost' => 5000,
    ], $saleAttributes));

    SaleItem::factory()->create(array_merge([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 10000,
        'cost_price' => 5000,
    ], array_diff_key($itemAttributes, ['product' => null])));

    return $sale;
}

// ============================================================
// Authorization
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user', function () {
        get(route('reports.profit-loss'))->assertRedirect(route('login'));
    });

    it('forbids cashier from profit loss report', function () {
        actingAs(cashier())
            ->get(route('reports.profit-loss'))
            ->assertForbidden();
    });

    it('forbids warehouse from profit loss report', function () {
        actingAs(warehouse())
            ->get(route('reports.profit-loss'))
            ->assertForbidden();
    });

    it('allows admin to access profit loss report', function () {
        actingAs(admin())
            ->get(route('reports.profit-loss'))
            ->assertSuccessful();
    });
});

// ============================================================
// Index — page & props
// ============================================================

describe('Index', function () {
    it('renders the Reports/ProfitLoss/Index component', function () {
        actingAs(admin())
            ->get(route('reports.profit-loss'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Reports/ProfitLoss/Index'));
    });

    it('passes all required props', function () {
        actingAs(admin())
            ->get(route('reports.profit-loss'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('summary')
                    ->has('productBreakdown')
                    ->has('chartData')
                    ->has('filters')
            );
    });

    it('passes summary with correct keys', function () {
        actingAs(admin())
            ->get(route('reports.profit-loss'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('summary.total_revenue')
                    ->has('summary.total_cost')
                    ->has('summary.gross_profit')
                    ->has('summary.profit_margin')
            );
    });

    it('passes filters prop with start and end keys', function () {
        $start = Carbon::today()->toDateString();
        $end = Carbon::today()->addDays(7)->toDateString();

        actingAs(admin())
            ->get(route('reports.profit-loss', ['start' => $start, 'end' => $end]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.start', $start)
                    ->where('filters.end', $end)
            );
    });

    it('defaults to current month when no date params provided', function () {
        $expectedStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $expectedEnd = Carbon::now()->endOfMonth()->format('Y-m-d');

        actingAs(admin())
            ->get(route('reports.profit-loss'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.start', $expectedStart)
                    ->where('filters.end', $expectedEnd)
            );
    });

    it('passes productBreakdown with paginator structure', function () {
        actingAs(admin())
            ->get(route('reports.profit-loss'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('productBreakdown.data')
                    ->has('productBreakdown.current_page')
                    ->has('productBreakdown.per_page')
                    ->has('productBreakdown.total')
            );
    });

    it('passes chartData as an array', function () {
        actingAs(admin())
            ->get(route('reports.profit-loss'))
            ->assertInertia(fn ($page) => $page->has('chartData'));
    });
});

// ============================================================
// Summary — calculations
// ============================================================

describe('Summary calculations', function () {
    it('calculates total_revenue correctly', function () {
        profitSale(admin(), ['total' => 20000, 'date' => Carbon::today()->toDateString()]);
        profitSale(admin(), ['total' => 30000, 'date' => Carbon::today()->toDateString()]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.total_revenue', 50000)
            );
    });

    it('calculates total_cost correctly', function () {
        profitSale(admin(), ['total_cost' => 8000, 'date' => Carbon::today()->toDateString()]);
        profitSale(admin(), ['total_cost' => 12000, 'date' => Carbon::today()->toDateString()]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.total_cost', 20000)
            );
    });

    it('calculates gross_profit correctly', function () {
        profitSale(admin(), [
            'total' => 20000,
            'total_cost' => 8000,
            'date' => Carbon::today()->toDateString(),
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.gross_profit', 12000)
            );
    });

    it('calculates profit_margin correctly', function () {
        // Revenue: 20000, Cost: 10000, Profit: 10000, Margin: 50%
        profitSale(admin(), [
            'total' => 20000,
            'total_cost' => 10000,
            'date' => Carbon::today()->toDateString(),
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.profit_margin', 50)
            );
    });

    it('returns zero profit_margin when no revenue', function () {
        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.total_revenue', 0)
                    ->where('summary.profit_margin', 0)
            );
    });

    it('excludes draft sales from summary', function () {
        profitSale(admin(), [
            'status' => 'completed',
            'total' => 10000,
            'total_cost' => 5000,
            'date' => Carbon::today()->toDateString(),
        ]);

        Sale::factory()->create([
            'user_id' => admin()->id,
            'status' => 'draft',
            'total' => 99999,
            'total_cost' => 99999,
            'date' => Carbon::today()->toDateString(),
            'payment_method' => 'cash',
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.total_revenue', 10000)
                    ->where('summary.total_cost', 5000)
            );
    });
});

// ============================================================
// Date range filter
// ============================================================

describe('Date range filter', function () {
    it('filters summary by date range', function () {
        // This month
        profitSale(admin(), [
            'total' => 20000,
            'total_cost' => 10000,
            'date' => Carbon::today()->toDateString(),
        ]);

        // Last month — tidak masuk filter
        profitSale(admin(), [
            'total' => 50000,
            'total_cost' => 30000,
            'date' => Carbon::now()->subMonth()->toDateString(),
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::now()->startOfMonth()->toDateString(),
                'end' => Carbon::now()->endOfMonth()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.total_revenue', 20000)
                    ->where('summary.total_cost', 10000)
                    ->where('summary.gross_profit', 10000)
            );
    });

    it('excludes sales before start date', function () {
        profitSale(admin(), [
            'total' => 10000,
            'date' => Carbon::now()->subDays(2)->toDateString(),
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.total_revenue', 0)
            );
    });

    it('excludes sales after end date', function () {
        profitSale(admin(), [
            'total' => 10000,
            'date' => Carbon::tomorrow()->toDateString(),
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.total_revenue', 0)
            );
    });

    it('includes sales on start date boundary', function () {
        profitSale(admin(), [
            'total' => 10000,
            'date' => Carbon::today()->toDateString(),
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.total_revenue', 10000)
            );
    });
});

// ============================================================
// Product breakdown
// ============================================================

describe('Product breakdown', function () {
    it('returns product breakdown with correct fields', function () {
        profitSale(admin(), ['date' => Carbon::today()->toDateString()]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('productBreakdown.data.0.product_name')
                    ->has('productBreakdown.data.0.sku')
                    ->has('productBreakdown.data.0.total_qty')
                    ->has('productBreakdown.data.0.revenue')
                    ->has('productBreakdown.data.0.cost')
                    ->has('productBreakdown.data.0.profit')
            );
    });

    it('calculates product breakdown profit correctly', function () {
        $product = Product::factory()->create();

        $sale = Sale::factory()->create([
            'user_id' => admin()->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'date' => Carbon::today()->toDateString(),
            'total' => 30000,
            'total_cost' => 15000,
        ]);

        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 3,
            'price' => 10000,
            'cost_price' => 5000,
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('productBreakdown.data.0.total_qty', '3')
                    ->where('productBreakdown.data.0.revenue', '30000.0000')
                    ->where('productBreakdown.data.0.cost', '15000.0000')
                    ->where('productBreakdown.data.0.profit', '15000.0000')
            );
    });

    it('orders product breakdown by profit descending', function () {
        $productA = Product::factory()->create(['name' => 'Produk A']);
        $productB = Product::factory()->create(['name' => 'Produk B']);

        $sale = Sale::factory()->create([
            'user_id' => admin()->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'date' => Carbon::today()->toDateString(),
            'total' => 50000,
            'total_cost' => 20000,
        ]);

        // Produk A profit = 5000
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $productA->id,
            'qty' => 1,
            'price' => 10000,
            'cost_price' => 5000,
        ]);

        // Produk B profit = 25000
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $productB->id,
            'qty' => 1,
            'price' => 40000,
            'cost_price' => 15000,
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('productBreakdown.data.0.product_name', 'Produk B')
                    ->where('productBreakdown.data.1.product_name', 'Produk A')
            );
    });

    it('paginates product breakdown with default 10 per page', function () {
        for ($i = 0; $i < 12; $i++) {
            profitSale(admin(), ['date' => Carbon::today()->toDateString()]);
        }

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('productBreakdown.data', 10)
                    ->where('productBreakdown.total', 12)
                    ->where('productBreakdown.per_page', 10)
            );
    });

    it('respects per_page parameter', function () {
        for ($i = 0; $i < 10; $i++) {
            profitSale(admin(), ['date' => Carbon::today()->toDateString()]);
        }

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
                'per_page' => 5,
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('productBreakdown.data', 5)
                    ->where('productBreakdown.per_page', 5)
            );
    });

    it('excludes draft sales from product breakdown', function () {
        $product = Product::factory()->create();

        $draftSale = Sale::factory()->create([
            'user_id' => admin()->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'date' => Carbon::today()->toDateString(),
        ]);

        SaleItem::factory()->create([
            'sale_id' => $draftSale->id,
            'product_id' => $product->id,
            'qty' => 5,
            'price' => 10000,
            'cost_price' => 5000,
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('productBreakdown.data', 0)
            );
    });
});

// ============================================================
// Chart data
// ============================================================

describe('Chart data', function () {
    it('returns chart data with correct structure', function () {
        profitSale(admin(), ['date' => Carbon::today()->toDateString()]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('chartData.0.day')
                    ->has('chartData.0.revenue')
                    ->has('chartData.0.cost')
                    ->has('chartData.0.profit')
            );
    });

    it('aggregates chart data per day', function () {
        profitSale(admin(), [
            'total' => 10000,
            'total_cost' => 4000,
            'date' => Carbon::today()->toDateString(),
        ]);

        profitSale(admin(), [
            'total' => 20000,
            'total_cost' => 8000,
            'date' => Carbon::today()->toDateString(),
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('chartData', 1) // satu hari = satu entry
                    ->where('chartData.0.revenue', 30000)
                    ->where('chartData.0.cost', 12000)
                    ->where('chartData.0.profit', 18000)
            );
    });

    it('orders chart data by day ascending', function () {
        profitSale(admin(), [
            'total' => 5000,
            'date' => Carbon::today()->toDateString(),
        ]);

        profitSale(admin(), [
            'total' => 10000,
            'date' => Carbon::yesterday()->toDateString(),
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::yesterday()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('chartData', 2)
                    ->where('chartData.0.day', Carbon::yesterday()->toDateString())
                    ->where('chartData.1.day', Carbon::today()->toDateString())
            );
    });

    it('returns empty chartData when no completed sales in range', function () {
        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(fn ($page) => $page->has('chartData', 0));
    });

    it('excludes draft sales from chart data', function () {
        Sale::factory()->create([
            'user_id' => admin()->id,
            'status' => 'draft',
            'total' => 99999,
            'total_cost' => 99999,
            'date' => Carbon::today()->toDateString(),
            'payment_method' => 'cash',
        ]);

        actingAs(admin())
            ->get(route('reports.profit-loss', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(fn ($page) => $page->has('chartData', 0));
    });
});

test('profit loss report correctly handles pagination while preserving date filters', function () {
    /** @var TestCase&object{admin: User} $this */

    // Create a sale in March (1000)
    Sale::factory()->create([
        'date' => '2026-03-15',
        'status' => 'completed',
        'total' => 1000,
        'total_cost' => 500,
        'user_id' => $this->admin->id,
    ]);

    // Create sales in April (15 * 2000 = 30000)
    $products = Product::factory()->count(15)->create(['purchase_price' => 1000]);
    foreach ($products as $product) {
        $sale = Sale::factory()->create([
            'status' => 'completed',
            'date' => '2026-04-15',
            'total' => 2000,
            'total_cost' => 1000,
            'user_id' => $this->admin->id,
            'payment_method' => 'cash',
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 2000,
            'cost_price' => 1000,
        ]);
    }

    // Initial request for April
    $response = $this->actingAs($this->admin)
        ->get(route('reports.profit-loss', [
            'start' => '2026-04-01',
            'end' => '2026-04-30',
            'per_page' => 10,
        ]));

    $response->assertStatus(200);

    // Verify initial data (only April sales)
    $response->assertInertia(
        fn (Assert $page) => $page
            ->where('summary.total_revenue', 30000)
            ->has('productBreakdown.data', 10)
            ->where('productBreakdown.total', 15)
    );

    // Request for March
    $responseMarch = $this->actingAs($this->admin)
        ->get(route('reports.profit-loss', [
            'start' => '2026-03-01',
            'end' => '2026-03-31',
        ]));

    $responseMarch->assertInertia(
        fn (Assert $page) => $page
            ->where('summary.total_revenue', 1000)
    );

    // Create a sale in February (5000)
    $oldProduct = Product::factory()->create(['purchase_price' => 1000]);
    $oldSale = Sale::factory()->create([
        'status' => 'completed',
        'date' => '2026-02-15',
        'total' => 5000,
        'total_cost' => 2000,
        'user_id' => $this->admin->id,
    ]);
    SaleItem::factory()->create([
        'sale_id' => $oldSale->id,
        'product_id' => $oldProduct->id,
        'qty' => 1,
        'price' => 5000,
        'cost_price' => 2000,
    ]);

    // Request for February
    $responseFeb = $this->actingAs($this->admin)
        ->get(route('reports.profit-loss', [
            'start' => '2026-02-01',
            'end' => '2026-02-28',
        ]));

    $responseFeb->assertInertia(
        fn (Assert $page) => $page
            ->where('summary.total_revenue', 5000)
    );

    // Simulate FIXED DataTable search or pagination where date filters are PRESERVED
    $responseFixed = $this->actingAs($this->admin)
        ->get(route('reports.profit-loss', [
            'page' => 1,
            'search' => '',
            'start' => '2026-02-01',
            'end' => '2026-02-28',
        ]));

    // It should STILL show the data from February (5000)
    $responseFixed->assertInertia(
        fn (Assert $page) => $page
            ->where('summary.total_revenue', 5000)
    );
});
