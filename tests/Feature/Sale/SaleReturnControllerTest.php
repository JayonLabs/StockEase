<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockLog;
use App\Models\Unit;
use App\Models\User;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    /** @var TestCase&object{admin:User, cashier:User, warehouse:User} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
});

function createCompletedSale(array $attributes = []): Sale
{
    return Sale::factory()->create(array_merge([
        'payment_method' => 'cash',
        'status' => 'completed',
    ], $attributes));
}

function createProductWithStockForReturn(int $stock = 50): Product
{
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    return Product::factory()->create([
        'name' => 'Product Return Test',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'stock' => $stock,
        'selling_price' => 10000,
        'purchase_price' => 5000,
    ]);
}

function createReturnPayloadData(Sale $sale, string $returnType = 'refund', array $overrides = []): array
{
    $items = $sale->saleItems->map(fn ($item) => [
        'sale_item_id' => $item->id,
        'qty' => 1,
    ])->toArray();

    return array_merge([
        'return_type' => $returnType,
        'reason' => 'Barang rusak',
        'notes' => null,
        'items' => $items,
    ], $overrides);
}

// ============================================================
// Authorization
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user from sale-return index', function () {
        get(route('sale-return.index'))->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from sale-return show', function () {
        $sale = createCompletedSale();
        get(route('sale-return.show', $sale))->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from sale-return store', function () {
        $sale = createCompletedSale();
        post(route('sale-return.store', $sale), [])->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from sale-return detail', function () {
        $return = SaleReturn::factory()->create();
        get(route('sale-return.detail', $return))->assertRedirect(route('login'));
    });

    it('forbids warehouse from sale-return index', function () {
        /** @var TestCase&object{warehouse:User} $this */
        actingAs($this->warehouse)
            ->get(route('sale-return.index'))
            ->assertForbidden();
    });

    it('forbids warehouse from sale-return show', function () {
        /** @var TestCase&object{warehouse:User} $this */
        $sale = createCompletedSale();

        actingAs($this->warehouse)
            ->get(route('sale-return.show', $sale))
            ->assertForbidden();
    });

    it('forbids warehouse from sale-return store', function () {
        /** @var TestCase&object{warehouse:User} $this */
        $sale = createCompletedSale();

        actingAs($this->warehouse)
            ->post(route('sale-return.store', $sale), [])
            ->assertForbidden();
    });

    it('forbids warehouse from sale-return detail', function () {
        /** @var TestCase&object{warehouse:User} $this */
        $return = SaleReturn::factory()->create();

        actingAs($this->warehouse)
            ->get(route('sale-return.detail', $return))
            ->assertForbidden();
    });

    it('allows admin and cashier to access sale-return index', function (string $role) {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        $user = $this->{$role};

        actingAs($user)
            ->get(route('sale-return.index'))
            ->assertSuccessful();
    })->with(['admin', 'cashier']);

    it('allows admin and cashier to access sale-return show', function (string $role) {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        $sale = createCompletedSale();
        $user = $this->{$role};

        actingAs($user)
            ->get(route('sale-return.show', $sale))
            ->assertSuccessful();
    })->with(['admin', 'cashier']);

    it('allows admin and cashier to access sale-return detail', function (string $role) {
        /** @var TestCase&object{admin:User, cashier:User} $this */
        $return = SaleReturn::factory()->create();
        $user = $this->{$role};

        actingAs($user)
            ->get(route('sale-return.detail', $return))
            ->assertSuccessful();
    })->with(['admin', 'cashier']);
});

// ============================================================
// Index
// ============================================================

describe('Index', function () {
    it('renders the SaleReturn/Index component', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale-return.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('SaleReturn/Index'));
    });

    it('passes returns prop with paginator structure', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale-return.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('returns.data')
                    ->has('returns.current_page')
                    ->has('returns.per_page')
                    ->has('returns.total')
            );
    });

    it('returns empty list when no returns exist', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale-return.index'))
            ->assertInertia(fn ($page) => $page->has('returns.data', 0));
    });

    it('paginates with default 10 per page', function () {
        /** @var TestCase&object{admin:User} $this */
        SaleReturn::factory()->count(12)->create();

        actingAs($this->admin)
            ->get(route('sale-return.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('returns.data', 10)
                    ->where('returns.total', 12)
            );
    });

    it('respects per_page query parameter', function () {
        /** @var TestCase&object{admin:User} $this */
        SaleReturn::factory()->count(10)->create();

        actingAs($this->admin)
            ->get(route('sale-return.index', ['per_page' => 5]))
            ->assertInertia(fn ($page) => $page->has('returns.data', 5));
    });
});

// ============================================================
// Index — Date filtering
// ============================================================

describe('Date range filter', function () {
    it('filters returns within date range', function () {
        /** @var TestCase&object{admin:User} $this */
        SaleReturn::factory()->create(['return_date' => '2024-04-01']);
        SaleReturn::factory()->create(['return_date' => '2024-04-15']);
        SaleReturn::factory()->create(['return_date' => '2024-04-30']);
        SaleReturn::factory()->create(['return_date' => '2024-05-01']);

        actingAs($this->admin)
            ->get(route('sale-return.index', ['start' => '2024-04-01', 'end' => '2024-04-30']))
            ->assertInertia(fn ($page) => $page->has('returns.data', 3));
    });

    it('ignores date filter when only start is provided', function () {
        /** @var TestCase&object{admin:User} $this */
        SaleReturn::factory()->create(['return_date' => '2024-03-01']);
        SaleReturn::factory()->create(['return_date' => '2024-04-01']);

        actingAs($this->admin)
            ->get(route('sale-return.index', ['start' => '2024-04-01']))
            ->assertInertia(fn ($page) => $page->has('returns.data', 2));
    });
});

// ============================================================
// Index — search filter
// ============================================================

describe('Search filter', function () {
    it('searches by reason', function () {
        /** @var TestCase&object{admin:User} $this */
        SaleReturn::factory()->create(['reason' => 'Produk rusak']);
        SaleReturn::factory()->create(['reason' => 'Salah kirim']);
        SaleReturn::factory()->create(['reason' => 'Kadaluarsa']);

        actingAs($this->admin)
            ->get(route('sale-return.index', ['search' => 'rusak']))
            ->assertInertia(fn ($page) => $page->has('returns.data', 1));
    });

    it('returns empty when search has no match', function () {
        /** @var TestCase&object{admin:User} $this */
        SaleReturn::factory()->create(['reason' => 'Produk rusak']);

        actingAs($this->admin)
            ->get(route('sale-return.index', ['search' => 'xyznonexistent']))
            ->assertInertia(fn ($page) => $page->has('returns.data', 0));
    });

    it('passes filters prop back to Vue', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale-return.index', ['search' => 'test']))
            ->assertInertia(fn ($page) => $page->where('filters.search', 'test'));
    });
});

// ============================================================
// Index — combined search and date filter
// ============================================================

describe('Combined search and date filter', function () {
    it('filters by both search and date range simultaneously', function () {
        /** @var TestCase&object{admin:User} $this */
        SaleReturn::factory()->create(['reason' => 'Produk rusak', 'return_date' => '2024-04-01']);
        SaleReturn::factory()->create(['reason' => 'Produk rusak', 'return_date' => '2024-04-15']);
        SaleReturn::factory()->create(['reason' => 'Produk rusak', 'return_date' => '2024-05-01']); // outside
        SaleReturn::factory()->create(['reason' => 'Kadaluarsa', 'return_date' => '2024-04-10']);

        actingAs($this->admin)
            ->get(route('sale-return.index', [
                'search' => 'rusak',
                'start' => '2024-04-01',
                'end' => '2024-04-30',
            ]))
            ->assertInertia(fn ($page) => $page->has('returns.data', 2));
    });

    it('passes all filters props back to Vue component', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale-return.index', [
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
});

// ============================================================
// Show — Return form
// ============================================================

describe('Show', function () {
    it('renders the SaleReturn/Show component', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = createCompletedSale();

        actingAs($this->admin)
            ->get(route('sale-return.show', $sale))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('SaleReturn/Show'));
    });

    it('passes sale prop with expected keys', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = createCompletedSale();

        actingAs($this->admin)
            ->get(route('sale-return.show', $sale))
            ->assertInertia(
                fn ($page) => $page
                    ->has('sale.id')
                    ->has('sale.total')
                    ->has('sale.sale_items')
            );
    });

    it('loads sale items and products', function () {
        /** @var TestCase&object{admin:User} $this */
        $product = createProductWithStockForReturn(50);
        $sale = createCompletedSale();
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 3,
            'price' => 10000,
        ]);

        actingAs($this->admin)
            ->get(route('sale-return.show', $sale))
            ->assertInertia(
                fn ($page) => $page
                    ->has('sale.sale_items', 1)
                    ->has('sale.sale_items.0.product')
            );
    });

    it('returns 404 for non-existent sale', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale-return.show', 999999))
            ->assertNotFound();
    });
});

// ============================================================
// Store — Create return
// ============================================================

describe('Store', function () {
    it('processes a refund return successfully', function () {
        /** @var TestCase&object{admin:User} $this */
        $product = createProductWithStockForReturn(50);
        $purchase = Purchase::factory()->create();
        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'qty' => 50,
            'remaining_qty' => 10,
            'price' => 5000,
        ]);
        $sale = createCompletedSale(['total' => 20000]);
        $saleItem = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
            'cost_price' => 5000,
        ]);

        $payload = createReturnPayloadData($sale, 'refund', [
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 1]],
        ]);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload)
            ->assertRedirect(route('sale-return.index'));

        expect(SaleReturn::where('sale_id', $sale->id)->count())->toBe(1);
        $return = SaleReturn::where('sale_id', $sale->id)->first();
        expect($return->return_type)->toBe('refund');
        expect($return->total_refund)->toBeTruthy();
        expect((float) $return->total_refund)->toBeGreaterThan(0);
    });

    it('restores product stock on return', function () {
        /** @var TestCase&object{admin:User} $this */
        $product = createProductWithStockForReturn(50);
        $purchase = Purchase::factory()->create();
        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'qty' => 50,
            'remaining_qty' => 10,
            'price' => 5000,
        ]);
        $sale = createCompletedSale();
        $saleItem = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
        ]);

        $originalStock = (int) $product->stock;
        $payload = createReturnPayloadData($sale, 'exchange', [
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 1]],
        ]);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload)
            ->assertRedirect(route('sale-return.index'));

        $product->refresh();
        expect((int) $product->stock)->toBe($originalStock + 1);
    });

    it('restores FEFO remaining_qty on return', function () {
        /** @var TestCase&object{admin:User} $this */
        $product = createProductWithStockForReturn(50);
        $purchase = Purchase::factory()->create();
        $purchaseItem = PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'qty' => 50,
            'remaining_qty' => 10,
            'price' => 5000,
        ]);
        $sale = createCompletedSale();
        $saleItem = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
        ]);

        $originalRemaining = (int) $purchaseItem->remaining_qty;
        $payload = createReturnPayloadData($sale, 'exchange', [
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 1]],
        ]);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload)
            ->assertRedirect(route('sale-return.index'));

        $purchaseItem->refresh();
        expect((int) $purchaseItem->remaining_qty)->toBe($originalRemaining + 1);
    });

    it('creates stock log entry with type in for returned items', function () {
        /** @var TestCase&object{admin:User} $this */
        $product = createProductWithStockForReturn(50);
        $purchase = Purchase::factory()->create();
        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'qty' => 50,
            'remaining_qty' => 10,
            'price' => 5000,
        ]);
        $sale = createCompletedSale();
        $saleItem = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
        ]);

        $payload = createReturnPayloadData($sale, 'exchange', [
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 1]],
        ]);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload);

        $stockLog = StockLog::where('reference_type', 'SaleReturn')
            ->where('product_id', $product->id)
            ->first();

        expect($stockLog)->not->toBeNull();
        expect($stockLog->type)->toBe('in');
        expect((int) $stockLog->qty)->toBe(1);
        expect($stockLog->note)->toContain('Retur penjualan');
    });

    it('returns total_refund of 0 for exchange type', function () {
        /** @var TestCase&object{admin:User} $this */
        $product = createProductWithStockForReturn(50);
        $purchase = Purchase::factory()->create();
        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'qty' => 50,
            'remaining_qty' => 10,
            'price' => 5000,
        ]);
        $sale = createCompletedSale();
        $saleItem = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 2,
            'price' => 10000,
        ]);

        $payload = createReturnPayloadData($sale, 'exchange', [
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 1]],
        ]);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload)
            ->assertRedirect(route('sale-return.index'));

        $return = SaleReturn::where('sale_id', $sale->id)->first();
        expect((float) $return->total_refund)->toBe(0.0);
    });

    it('rejects return for sale with draft status', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = Sale::factory()->create([
            'payment_method' => 'cash',
            'status' => 'draft',
        ]);

        $payload = createReturnPayloadData($sale);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload)
            ->assertRedirect();

        expect(SaleReturn::count())->toBe(0);
    });

    it('rejects return for sale with pending status', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = Sale::factory()->create([
            'payment_method' => 'qris',
            'status' => 'pending',
        ]);

        $payload = createReturnPayloadData($sale);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload)
            ->assertRedirect();

        expect(SaleReturn::count())->toBe(0);
    });

    it('can return partial quantity of a sale item', function () {
        /** @var TestCase&object{admin:User} $this */
        $product = createProductWithStockForReturn(50);
        $purchase = Purchase::factory()->create();
        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'qty' => 50,
            'remaining_qty' => 10,
            'price' => 5000,
        ]);
        $sale = createCompletedSale();
        $saleItem = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 5,
            'price' => 10000,
        ]);

        $payload = createReturnPayloadData($sale, 'refund', [
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 3]],
        ]);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload)
            ->assertRedirect(route('sale-return.index'));

        $returnItem = SaleReturnItem::first();
        expect((int) $returnItem->qty)->toBe(3);
    });

    it('rejects return with qty exceeding remaining after previous returns', function () {
        /** @var TestCase&object{admin:User} $this */
        $product = createProductWithStockForReturn(50);
        $purchase = Purchase::factory()->create();
        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'qty' => 50,
            'remaining_qty' => 10,
            'price' => 5000,
        ]);
        $sale = createCompletedSale();
        $saleItem = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 3,
            'price' => 10000,
        ]);

        // First return: 2 items
        $payload1 = createReturnPayloadData($sale, 'refund', [
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 2]],
        ]);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload1)
            ->assertRedirect();

        // Second return: try to return 2 more (only 1 left)
        $payload2 = createReturnPayloadData($sale, 'refund', [
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 2]],
        ]);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload2)
            ->assertRedirect();

        // Should still only have 1 return (first one only)
        expect(SaleReturn::where('sale_id', $sale->id)->count())->toBe(1);
    });

    it('allows multiple returns up to the original qty', function () {
        /** @var TestCase&object{admin:User} $this */
        $product = createProductWithStockForReturn(50);
        $purchase = Purchase::factory()->create();
        PurchaseItem::factory()->create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'qty' => 50,
            'remaining_qty' => 10,
            'price' => 5000,
        ]);
        $sale = createCompletedSale();
        $saleItem = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => 3,
            'price' => 10000,
        ]);

        // First return: 2 items
        $payload1 = createReturnPayloadData($sale, 'refund', [
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 2]],
        ]);
        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload1);

        // Second return: remaining 1 item
        $payload2 = createReturnPayloadData($sale, 'refund', [
            'items' => [['sale_item_id' => $saleItem->id, 'qty' => 1]],
        ]);
        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload2);

        // Should now have 2 returns
        expect(SaleReturn::where('sale_id', $sale->id)->count())->toBe(2);
    });
});

// ============================================================
// Store — Validation
// ============================================================

describe('Validation', function () {
    it('requires return_type', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = createCompletedSale();
        SaleItem::factory()->create(['sale_id' => $sale->id]);

        $payload = createReturnPayloadData($sale);
        unset($payload['return_type']);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload)
            ->assertInvalid('return_type');
    });

    it('requires valid return_type value', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = createCompletedSale();
        SaleItem::factory()->create(['sale_id' => $sale->id]);

        $payload = createReturnPayloadData($sale, 'invalid_type');

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), $payload)
            ->assertInvalid('return_type');
    });

    it('requires items array', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = createCompletedSale();

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), [
                'return_type' => 'refund',
            ])
            ->assertInvalid('items');
    });

    it('requires at least one item', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = createCompletedSale();

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), [
                'return_type' => 'refund',
                'items' => [],
            ])
            ->assertInvalid('items');
    });

    it('requires sale_item_id in items', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = createCompletedSale();

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), [
                'return_type' => 'refund',
                'items' => [['qty' => 1]],
            ])
            ->assertInvalid('items.0.sale_item_id');
    });

    it('requires qty of at least 1 in items', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = createCompletedSale();
        $saleItem = SaleItem::factory()->create(['sale_id' => $sale->id]);

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), [
                'return_type' => 'refund',
                'items' => [['sale_item_id' => $saleItem->id, 'qty' => 0]],
            ])
            ->assertInvalid('items.0.qty');
    });

    it('requires valid sale_item_id', function () {
        /** @var TestCase&object{admin:User} $this */
        $sale = createCompletedSale();

        actingAs($this->admin)
            ->post(route('sale-return.store', $sale), [
                'return_type' => 'refund',
                'items' => [['sale_item_id' => 999999, 'qty' => 1]],
            ])
            ->assertInvalid('items.0.sale_item_id');
    });
});

// ============================================================
// Detail — View return detail
// ============================================================

describe('Detail', function () {
    it('renders the SaleReturn/Detail component', function () {
        /** @var TestCase&object{admin:User} $this */
        $return = SaleReturn::factory()->create();

        actingAs($this->admin)
            ->get(route('sale-return.detail', $return))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('SaleReturn/Detail'));
    });

    it('passes saleReturn prop with expected keys', function () {
        /** @var TestCase&object{admin:User} $this */
        $return = SaleReturn::factory()->create();

        actingAs($this->admin)
            ->get(route('sale-return.detail', $return))
            ->assertInertia(
                fn ($page) => $page
                    ->has('saleReturn.id')
                    ->has('saleReturn.return_type')
                    ->has('saleReturn.total_refund')
                    ->has('saleReturn.sale_return_items')
            );
    });

    it('loads return items with product relation', function () {
        /** @var TestCase&object{admin:User} $this */
        $product = createProductWithStockForReturn(50);
        $sale = createCompletedSale();
        $saleItem = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
        ]);
        $return = SaleReturn::factory()->create(['sale_id' => $sale->id]);
        SaleReturnItem::factory()->create([
            'sale_return_id' => $return->id,
            'sale_item_id' => $saleItem->id,
            'product_id' => $product->id,
            'qty' => 1,
            'price' => 10000,
            'total' => 10000,
        ]);

        actingAs($this->admin)
            ->get(route('sale-return.detail', $return))
            ->assertInertia(
                fn ($page) => $page
                    ->has('saleReturn.sale_return_items', 1)
                    ->has('saleReturn.sale_return_items.0.product')
            );
    });

    it('returns 404 for non-existent return', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale-return.detail', 999999))
            ->assertNotFound();
    });
});

// ============================================================
// 404 for non-existent sale
// ============================================================

describe('Non-existent sale', function () {
    it('returns 404 for show with non-existent sale', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->get(route('sale-return.show', 999999))
            ->assertNotFound();
    });

    it('returns 404 for store with non-existent sale', function () {
        /** @var TestCase&object{admin:User} $this */
        actingAs($this->admin)
            ->post(route('sale-return.store', 999999), [])
            ->assertNotFound();
    });
});
