<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Helper — create a completed sale with SaleItem for a product.
 *
 * @param  array<string, mixed>  $saleAttributes
 * @param  array<string, mixed>  $itemAttributes
 */
function movementSale(User $user, Product $product, int $qty = 1, array $saleAttributes = []): Sale
{
    $sale = Sale::factory()->create(array_merge([
        'user_id' => $user->id,
        'status' => 'completed',
        'payment_method' => 'cash',
        'date' => Carbon::today()->toDateString(),
        'total' => $qty * 10000,
        'total_cost' => $qty * 5000,
    ], $saleAttributes));

    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => $qty,
        'price' => 10000,
        'cost_price' => 5000,
    ]);

    return $sale;
}

function movementAdmin(): User
{
    return User::role('admin')->first() ?? User::factory()->create(['role' => 'admin']);
}

function movementCashier(): User
{
    return User::role('cashier')->first() ?? User::factory()->create(['role' => 'cashier']);
}

function movementWarehouse(): User
{
    return User::role('warehouse')->first() ?? User::factory()->create(['role' => 'warehouse']);
}

// ============================================================
// Authorization
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user', function () {
        get(route('reports.product-movement'))->assertRedirect(route('login'));
    });

    it('forbids cashier from product movement report', function () {
        actingAs(movementCashier())
            ->get(route('reports.product-movement'))
            ->assertForbidden();
    });

    it('forbids warehouse from product movement report', function () {
        actingAs(movementWarehouse())
            ->get(route('reports.product-movement'))
            ->assertForbidden();
    });

    it('allows admin to access product movement report', function () {
        actingAs(movementAdmin())
            ->get(route('reports.product-movement'))
            ->assertSuccessful();
    });
});

// ============================================================
// Index — page & props
// ============================================================

describe('Index', function () {
    it('renders the Reports/ProductMovement/Index component', function () {
        actingAs(movementAdmin())
            ->get(route('reports.product-movement'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Reports/ProductMovement/Index'));
    });

    it('passes all required props', function () {
        actingAs(movementAdmin())
            ->get(route('reports.product-movement'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('fastMoving')
                    ->has('slowMoving')
                    ->has('chartData')
                    ->has('summary')
                    ->has('filters'),
            );
    });

    it('passes filters prop with start and end keys', function () {
        $start = Carbon::today()->toDateString();
        $end = Carbon::today()->addDays(7)->toDateString();

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', ['start' => $start, 'end' => $end]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.start', $start)
                    ->where('filters.end', $end),
            );
    });

    it('defaults to current month when no date params provided', function () {
        $expectedStart = Carbon::now()->startOfMonth()->format('Y-m-d');
        $expectedEnd = Carbon::now()->endOfMonth()->format('Y-m-d');

        actingAs(movementAdmin())
            ->get(route('reports.product-movement'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('filters.start', $expectedStart)
                    ->where('filters.end', $expectedEnd),
            );
    });

    it('passes summary with correct keys', function () {
        actingAs(movementAdmin())
            ->get(route('reports.product-movement'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('summary.total_products_analyzed')
                    ->has('summary.total_qty_sold')
                    ->has('summary.fast_moving_count')
                    ->has('summary.unsold_products_count'),
            );
    });

    it('passes chartData with fast and slow keys', function () {
        actingAs(movementAdmin())
            ->get(route('reports.product-movement'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('chartData.fast')
                    ->has('chartData.slow'),
            );
    });
});

// ============================================================
// Fast Moving data
// ============================================================

describe('Fast Moving', function () {
    it('returns fast moving products with correct fields', function () {
        $product = Product::factory()->create(['stock' => 50]);
        movementSale(movementAdmin(), $product, 5);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('fastMoving.0.product_id')
                    ->has('fastMoving.0.product_name')
                    ->has('fastMoving.0.sku')
                    ->has('fastMoving.0.total_qty_sold')
                    ->has('fastMoving.0.total_revenue')
                    ->has('fastMoving.0.current_stock'),
            );
    });

    it('orders fast moving products by qty sold descending', function () {
        $productA = Product::factory()->create(['name' => 'Produk A', 'stock' => 100]);
        $productB = Product::factory()->create(['name' => 'Produk B', 'stock' => 100]);

        movementSale(movementAdmin(), $productA, 3);
        movementSale(movementAdmin(), $productB, 10);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('fastMoving.0.product_name', 'Produk B')
                    ->where('fastMoving.1.product_name', 'Produk A'),
            );
    });

    it('excludes draft sales from fast moving', function () {
        $product = Product::factory()->create(['stock' => 50]);

        $draftSale = Sale::factory()->create([
            'user_id' => movementAdmin()->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'date' => Carbon::today()->toDateString(),
        ]);

        SaleItem::factory()->create([
            'sale_id' => $draftSale->id,
            'product_id' => $product->id,
            'qty' => 100,
            'price' => 10000,
            'cost_price' => 5000,
        ]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(fn ($page) => $page->has('fastMoving', 0));
    });

    it('filters fast moving by date range', function () {
        $product = Product::factory()->create(['stock' => 100]);

        // Sale in range
        movementSale(movementAdmin(), $product, 5, [
            'date' => Carbon::today()->toDateString(),
        ]);

        // Sale out of range
        movementSale(movementAdmin(), $product, 999, [
            'date' => Carbon::now()->subMonth()->toDateString(),
        ]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page->where('fastMoving.0.total_qty_sold', 5),
            );
    });

    it('calculates total_qty_sold correctly for aggregated sales of same product', function () {
        $product = Product::factory()->create(['stock' => 100]);

        movementSale(movementAdmin(), $product, 3, ['date' => Carbon::today()->toDateString()]);
        movementSale(movementAdmin(), $product, 7, ['date' => Carbon::today()->toDateString()]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('fastMoving', 1)
                    ->where('fastMoving.0.total_qty_sold', 10),
            );
    });

    it('limits fast moving results to 10', function () {
        for ($i = 0; $i < 15; $i++) {
            $product = Product::factory()->create(['stock' => 50]);
            movementSale(movementAdmin(), $product, $i + 1);
        }

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(fn ($page) => $page->has('fastMoving', 10));
    });
});

// ============================================================
// Slow Moving data
// ============================================================

describe('Slow Moving', function () {
    it('returns slow moving products with correct fields', function () {
        Product::factory()->create(['stock' => 20]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('slowMoving.0.product_id')
                    ->has('slowMoving.0.product_name')
                    ->has('slowMoving.0.sku')
                    ->has('slowMoving.0.total_qty_sold')
                    ->has('slowMoving.0.current_stock')
                    ->has('slowMoving.0.last_sold_at'),
            );
    });

    it('only includes products with stock > 0', function () {
        // Product with stock = 0 should not appear in slow moving
        Product::factory()->create(['stock' => 0]);
        // Product with stock > 0 should appear
        $withStock = Product::factory()->create(['stock' => 10]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('slowMoving', 1)
                    ->where('slowMoving.0.product_id', $withStock->id),
            );
    });

    it('shows zero total_qty_sold for unsold products', function () {
        Product::factory()->create(['stock' => 15]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page->where('slowMoving.0.total_qty_sold', 0),
            );
    });

    it('orders slow moving by qty sold ascending then stock descending', function () {
        // Product A: not sold, stock 50
        $productA = Product::factory()->create(['name' => 'Produk A', 'stock' => 50]);

        // Product B: not sold, stock 5
        $productB = Product::factory()->create(['name' => 'Produk B', 'stock' => 5]);

        // Product C: sold 1 time
        $productC = Product::factory()->create(['name' => 'Produk C', 'stock' => 20]);
        movementSale(movementAdmin(), $productC, 1);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    // Unsold first, then sorted by stock desc: A(50) then B(5) then C(sold=1)
                    ->where('slowMoving.0.product_name', 'Produk A')
                    ->where('slowMoving.1.product_name', 'Produk B')
                    ->where('slowMoving.2.product_name', 'Produk C'),
            );
    });

    it('limits slow moving results to 10', function () {
        for ($i = 0; $i < 15; $i++) {
            Product::factory()->create(['stock' => $i + 1]);
        }

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(fn ($page) => $page->has('slowMoving', 10));
    });
});

// ============================================================
// Summary stats
// ============================================================

describe('Summary stats', function () {
    it('returns zero total_qty_sold when no completed sales', function () {
        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page->where('summary.total_qty_sold', 0),
            );
    });

    it('calculates total_qty_sold correctly', function () {
        $productA = Product::factory()->create(['stock' => 100]);
        $productB = Product::factory()->create(['stock' => 100]);

        movementSale(movementAdmin(), $productA, 4);
        movementSale(movementAdmin(), $productB, 6);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page->where('summary.total_qty_sold', 10),
            );
    });

    it('calculates fast_moving_count correctly', function () {
        $productA = Product::factory()->create(['stock' => 50]);
        $productB = Product::factory()->create(['stock' => 50]);
        Product::factory()->create(['stock' => 50]); // unsold product

        movementSale(movementAdmin(), $productA, 2);
        movementSale(movementAdmin(), $productB, 3);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page->where('summary.fast_moving_count', 2),
            );
    });

    it('counts unsold_products_count correctly', function () {
        $soldProduct = Product::factory()->create(['stock' => 50]);
        Product::factory()->create(['stock' => 20]); // unsold 1
        Product::factory()->create(['stock' => 10]); // unsold 2
        Product::factory()->create(['stock' => 0]);  // no stock - not counted

        movementSale(movementAdmin(), $soldProduct, 3);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page->where('summary.unsold_products_count', 2),
            );
    });

    it('excludes draft sales from summary stats', function () {
        $product = Product::factory()->create(['stock' => 50]);

        $draftSale = Sale::factory()->create([
            'user_id' => movementAdmin()->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'date' => Carbon::today()->toDateString(),
        ]);

        SaleItem::factory()->create([
            'sale_id' => $draftSale->id,
            'product_id' => $product->id,
            'qty' => 99,
            'price' => 10000,
            'cost_price' => 5000,
        ]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('summary.total_qty_sold', 0)
                    ->where('summary.fast_moving_count', 0),
            );
    });
});

// ============================================================
// Chart data
// ============================================================

describe('Chart data', function () {
    it('returns chart data with correct structure', function () {
        $product = Product::factory()->create(['stock' => 50]);
        movementSale(movementAdmin(), $product, 3);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('chartData.fast.0.name')
                    ->has('chartData.fast.0.qty'),
            );
    });

    it('returns slow chart data with stock field', function () {
        Product::factory()->create(['stock' => 25]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->has('chartData.slow.0.name')
                    ->has('chartData.slow.0.qty')
                    ->has('chartData.slow.0.stock'),
            );
    });

    it('returns empty fast chart when no sales in range', function () {
        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(fn ($page) => $page->has('chartData.fast', 0));
    });

    it('chart fast data reflects correct product names and qty', function () {
        $product = Product::factory()->create(['name' => 'Produk Spesial', 'stock' => 100]);
        movementSale(movementAdmin(), $product, 7);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(
                fn ($page) => $page
                    ->where('chartData.fast.0.name', 'Produk Spesial')
                    ->where('chartData.fast.0.qty', 7),
            );
    });
});

// ============================================================
// Date range filter
// ============================================================

describe('Date range filter', function () {
    it('excludes sales before start date from fast moving', function () {
        $product = Product::factory()->create(['stock' => 100]);

        movementSale(movementAdmin(), $product, 999, [
            'date' => Carbon::now()->subDays(5)->toDateString(),
        ]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(fn ($page) => $page->has('fastMoving', 0));
    });

    it('excludes sales after end date from fast moving', function () {
        $product = Product::factory()->create(['stock' => 100]);

        movementSale(movementAdmin(), $product, 999, [
            'date' => Carbon::tomorrow()->toDateString(),
        ]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->toDateString(),
            ]))
            ->assertInertia(fn ($page) => $page->has('fastMoving', 0));
    });

    it('includes sales on start and end date boundaries', function () {
        $productA = Product::factory()->create(['stock' => 50]);
        $productB = Product::factory()->create(['stock' => 50]);

        movementSale(movementAdmin(), $productA, 2, [
            'date' => Carbon::today()->toDateString(),
        ]);
        movementSale(movementAdmin(), $productB, 3, [
            'date' => Carbon::today()->addDays(6)->toDateString(),
        ]);

        actingAs(movementAdmin())
            ->get(route('reports.product-movement', [
                'start' => Carbon::today()->toDateString(),
                'end' => Carbon::today()->addDays(6)->toDateString(),
            ]))
            ->assertInertia(fn ($page) => $page->has('fastMoving', 2));
    });
});
