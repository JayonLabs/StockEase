<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

beforeEach(function () {
    /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);

    $this->warehouseModel = Warehouse::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->warehouseModel->products()->attach($this->product->id, ['stock' => 10]);
    $this->product->syncStockFromWarehouses();
});

// Helper — buat purchase dengan item
function purchase(User $user, Supplier $supplier, Product $product, Warehouse $warehouseModel, array $purchaseAttributes = [], array $itemAttributes = []): Purchase
{
    $purchase = Purchase::factory()->create(array_merge([
        'user_id' => $user->id,
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouseModel->id,
        'date' => Carbon::today()->toDateString(),
        'total' => 5000,
    ], $purchaseAttributes));

    $purchase->purchaseItems()->create(array_merge([
        'warehouse_id' => $warehouseModel->id,
        'product_id' => $product->id,
        'qty' => 5,
        'remaining_qty' => 5,
        'price' => 1000,
    ], $itemAttributes));

    return $purchase;
}

// Helper — payload store/update yang valid
function purchasePayload(Supplier $supplier, Product $product, Warehouse $warehouseModel, array $overrides = []): array
{
    return array_merge([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouseModel->id,
        'date' => Carbon::today()->toDateString(),
        'product_items' => [
            [
                'product_id' => $product->id,
                'qty' => 5,
                'price' => 1000,
                'selling_price' => 2000,
                'expiry_date' => null,
            ],
        ],
    ], $overrides);
}

// ============================================================
// Authorization
// ============================================================

describe('Authorization', function () {
    it('redirects unauthenticated user from all purchase routes', function (string $method, string $route) {
        $purchase = Purchase::factory()->create();

        $url = in_array($route, ['purchase.update', 'purchase.destroy'])
            ? route($route, $purchase)
            : route($route);

        $this->$method($url)->assertRedirect(route('login'));
    })->with([
        'index' => ['get',    'purchase.index'],
        'store' => ['post',   'purchase.store'],
        'update' => ['put',    'purchase.update'],
        'destroy' => ['delete', 'purchase.destroy'],
    ]);

    it('forbids cashier from all purchase routes', function (string $method, string $route) {
        $purchase = Purchase::factory()->create();

        $url = in_array($route, ['purchase.update', 'purchase.destroy'])
            ? route($route, $purchase)
            : route($route);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->cashier)->$method($url)->assertForbidden();
    })->with([
        'index' => ['get',    'purchase.index'],
        'store' => ['post',   'purchase.store'],
        'update' => ['put',    'purchase.update'],
        'destroy' => ['delete', 'purchase.destroy'],
    ]);

    it('allows admin and warehouse to access purchase index', function (string $role) {
        actingAs($this->{$role})
            ->get(route('purchase.index'))
            ->assertSuccessful();
    })->with(['admin', 'warehouse']);
});

// ============================================================
// Index — listing & pagination
// ============================================================

describe('Index', function () {
    it('renders Purchase/Index component', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Purchase/Index'));
    });

    it('passes warehouses prop to the Vue component', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index'))
            ->assertInertia(fn ($page) => $page->has('warehouses'));
    });

    it('passes purchases prop with paginator structure', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('purchases.data')
                    ->has('purchases.current_page')
                    ->has('purchases.per_page')
                    ->has('purchases.total')
            );
    });

    it('paginates with default 10 per page', function () {
        Purchase::factory()->count(12)->create();

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('purchases.data', 10)
                    ->where('purchases.total', 12)
            );
    });

    it('respects per_page query parameter', function () {
        Purchase::factory()->count(10)->create();

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', ['per_page' => 5]))
            ->assertInertia(fn ($page) => $page->has('purchases.data', 5));
    });

    it('orders purchases by date descending', function () {
        $older = Purchase::factory()->create(['date' => Carbon::now()->subDays(3)->toDateString()]);
        $newer = Purchase::factory()->create(['date' => Carbon::today()->toDateString()]);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index'))
            ->assertInertia(
                fn ($page) => $page
                    ->where('purchases.data.0.id', $newer->id)
                    ->where('purchases.data.1.id', $older->id)
            );
    });
});

// ============================================================
// Index — date range filter
// ============================================================

describe('Date range filter', function () {
    it('filters purchases within date range', function () {
        Purchase::factory()->create(['date' => '2024-04-01']);
        Purchase::factory()->create(['date' => '2024-04-15']);
        Purchase::factory()->create(['date' => '2024-04-30']);
        Purchase::factory()->create(['date' => '2024-05-01']); // outside

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', ['start' => '2024-04-01', 'end' => '2024-04-30']))
            ->assertInertia(fn ($page) => $page->has('purchases.data', 3));
    });

    it('excludes purchases before start date', function () {
        Purchase::factory()->create(['date' => '2024-03-31']);
        Purchase::factory()->create(['date' => '2024-04-01']);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', ['start' => '2024-04-01', 'end' => '2024-04-30']))
            ->assertInertia(fn ($page) => $page->has('purchases.data', 1));
    });

    it('excludes purchases after end date', function () {
        Purchase::factory()->create(['date' => '2024-04-30']);
        Purchase::factory()->create(['date' => '2024-05-01']);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', ['start' => '2024-04-01', 'end' => '2024-04-30']))
            ->assertInertia(fn ($page) => $page->has('purchases.data', 1));
    });

    it('ignores date filter when only start is provided', function () {
        Purchase::factory()->create(['date' => Carbon::now()->subMonth()->toDateString()]);
        Purchase::factory()->create(['date' => Carbon::today()->toDateString()]);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', ['start' => Carbon::today()->toDateString()]))
            ->assertInertia(fn ($page) => $page->has('purchases.data', 2));
    });
});

// ============================================================
// Index — search filter
// ============================================================

describe('Search filter', function () {
    it('searches by supplier name', function () {
        $supplierA = Supplier::factory()->create(['name' => 'Supplier ABC']);
        $supplierB = Supplier::factory()->create(['name' => 'Vendor XYZ']);

        Purchase::factory()->create(['supplier_id' => $supplierA->id]);
        Purchase::factory()->create(['supplier_id' => $supplierB->id]);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', ['search' => 'ABC']))
            ->assertInertia(
                fn ($page) => $page
                    ->has('purchases.data', 1)
                    ->where('purchases.data.0.supplier.name', 'Supplier ABC')
            );
    });

    it('searches by user name', function () {
        $userA = User::factory()->create(['name' => 'Budi Gudang', 'role' => 'warehouse']);
        $userB = User::factory()->create(['name' => 'Rina Admin', 'role' => 'admin']);

        Purchase::factory()->create(['user_id' => $userA->id]);
        Purchase::factory()->create(['user_id' => $userB->id]);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', ['search' => 'Budi']))
            ->assertInertia(fn ($page) => $page->has('purchases.data', 1));
    });

    it('searches by product name through purchase items', function () {
        $productA = Product::factory()->create(['name' => 'Laptop Pro']);
        $productB = Product::factory()->create(['name' => 'Mouse Wireless']);

        $p1 = Purchase::factory()->create();
        $p1->purchaseItems()->create(['product_id' => $productA->id, 'qty' => 1, 'price' => 1000, 'remaining_qty' => 1]);

        $p2 = Purchase::factory()->create();
        $p2->purchaseItems()->create(['product_id' => $productB->id, 'qty' => 1, 'price' => 1000, 'remaining_qty' => 1]);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', ['search' => 'Laptop']))
            ->assertInertia(
                fn ($page) => $page
                    ->has('purchases.data', 1)
                    ->where('purchases.data.0.purchase_items.0.product.name', 'Laptop Pro')
            );
    });

    it('returns empty when search has no match', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        Purchase::factory()->create(['supplier_id' => $this->supplier->id]);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', ['search' => 'xyznonexistent']))
            ->assertInertia(fn ($page) => $page->has('purchases.data', 0));
    });
});

// ============================================================
// Index — combined search and date filter
// ============================================================

describe('Combined search and date filter', function () {
    it('filters by both search and date range simultaneously', function () {
        $supplierA = Supplier::factory()->create(['name' => 'Supplier ABC']);
        $supplierB = Supplier::factory()->create(['name' => 'Supplier XYZ']);

        Purchase::factory()->create(['supplier_id' => $supplierA->id, 'date' => '2024-04-01']);
        Purchase::factory()->create(['supplier_id' => $supplierA->id, 'date' => '2024-04-15']);
        Purchase::factory()->create(['supplier_id' => $supplierA->id, 'date' => '2024-05-01']); // outside date range
        Purchase::factory()->create(['supplier_id' => $supplierB->id, 'date' => '2024-04-10']);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', [
                'search' => 'ABC',
                'start' => '2024-04-01',
                'end' => '2024-04-30',
            ]))
            ->assertInertia(fn ($page) => $page->has('purchases.data', 2));
    });

    it('preserves search when date filter returns empty', function () {
        Purchase::factory()->create(['supplier_id' => Supplier::factory()->create(['name' => 'Target Co'])->id, 'date' => '2024-06-01']);

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', [
                'search' => 'Target Co',
                'start' => '2024-04-01',
                'end' => '2024-04-30',
            ]))
            ->assertInertia(fn ($page) => $page->has('purchases.data', 0));
    });

    it('passes filters prop back to Vue component', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->get(route('purchase.index', [
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
// Search Supplier
// ============================================================

describe('Search supplier', function () {
    it('returns supplier matching search query', function () {

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->getJson(route('purchase.search-supplier', ['search' => $this->supplier->name]))
            ->assertSuccessful()
            ->assertJsonPath('data.0.value', $this->supplier->id);
    });

    it('returns label and value structure', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->getJson(route('purchase.search-supplier', ['search' => $this->supplier->name]))
            ->assertJsonStructure(['data' => [['value', 'label']]]);
    });

    it('returns empty data when search is blank', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->getJson(route('purchase.search-supplier', ['search' => '']))
            ->assertSuccessful()
            ->assertJsonPath('data', []);
    });

    it('returns 404 when no supplier matches', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->getJson(route('purchase.search-supplier', ['search' => 'xyznonexistent']))
            ->assertStatus(404);
    });
});

// ============================================================
// Search Product
// ============================================================

describe('Search product', function () {
    it('returns product matching search query', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->getJson(route('purchase.search-product', ['search' => $this->product->name]))
            ->assertSuccessful()
            ->assertJsonPath('data.0.id', $this->product->id);
    });

    it('returns empty data when search is blank', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->getJson(route('purchase.search-product', ['search' => '']))
            ->assertSuccessful()
            ->assertJsonPath('data', []);
    });

    it('returns empty data when no product matches', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->getJson(route('purchase.search-product', ['search' => 'xyznonexistent']))
            ->assertSuccessful()
            ->assertJsonPath('data', []);
    });

    it('returns product with unit relation', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->getJson(route('purchase.search-product', ['search' => $this->product->name]))
            ->assertSuccessful()
            ->assertJsonStructure(['data' => [['id', 'label', 'purchase_price', 'selling_price', 'stock']]]);
    });
});

// ============================================================
// Store
// ============================================================

describe('Store', function () {
    it('creates a purchase and redirects to index with success message', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->post(route('purchase.store'), purchasePayload($this->supplier, $this->product, $this->warehouseModel))
            ->assertRedirect(route('purchase.index'))
            ->assertSessionHas('success');

        assertDatabaseHas('purchases', ['supplier_id' => $this->supplier->id]);
    });

    it('increments product stock on store', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->post(route('purchase.store'), purchasePayload($this->supplier, $this->product, $this->warehouseModel, [
                'product_items' => [[
                    'product_id' => $this->product->id,
                    'qty' => 5,
                    'price' => 1000,
                    'selling_price' => 2000,
                    'expiry_date' => null,
                ]],
            ]));

        expect($this->product->fresh()->stock)->toBe(15); // 10 + 5
    });

    it('creates stock log with type in on store', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->post(route('purchase.store'), purchasePayload($this->supplier, $this->product, $this->warehouseModel));

        assertDatabaseHas('stock_logs', [
            'product_id' => $this->product->id,
            'qty' => 5,
            'type' => 'in',
            'reference_type' => 'Purchase',
        ]);
    });

    it('calculates total correctly on store', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->post(route('purchase.store'), purchasePayload($this->supplier, $this->product, $this->warehouseModel, [
                'product_items' => [
                    ['product_id' => $this->product->id, 'qty' => 3, 'price' => 2000, 'selling_price' => 4000, 'expiry_date' => null],
                ],
            ]));

        assertDatabaseHas('purchases', ['total' => 6000]);
    });

    it('stores multiple products in one purchase', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $product2 = Product::factory()->create();
        $this->warehouseModel->products()->attach($product2->id, ['stock' => 5]);
        $product2->syncStockFromWarehouses();

        actingAs($this->admin)
            ->post(route('purchase.store'), purchasePayload($this->supplier, $this->product, $this->warehouseModel, [
                'product_items' => [
                    ['product_id' => $this->product->id, 'qty' => 5, 'price' => 1000, 'selling_price' => 2000, 'expiry_date' => null],
                    ['product_id' => $product2->id, 'qty' => 10, 'price' => 500, 'selling_price' => 1000, 'expiry_date' => null],
                ],
            ]));

        expect($this->product->fresh()->stock)->toBe(15);
        expect($product2->fresh()->stock)->toBe(15);
    });

    it('stores purchase item with expiry_date when provided', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $expiryDate = Carbon::today()->addMonths(6)->toDateString();

        actingAs($this->admin)
            ->post(route('purchase.store'), purchasePayload($this->supplier, $this->product, $this->warehouseModel, [
                'product_items' => [[
                    'product_id' => $this->product->id,
                    'qty' => 5,
                    'price' => 1000,
                    'selling_price' => 2000,
                    'expiry_date' => $expiryDate,
                ]],
            ]));

        assertDatabaseHas('purchase_items', [
            'product_id' => $this->product->id,
            'expiry_date' => $expiryDate,
        ]);
    });

    it('warehouse can also store a purchase', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        actingAs($this->warehouse)
            ->post(route('purchase.store'), purchasePayload($this->supplier, $this->product, $this->warehouseModel))
            ->assertRedirect(route('purchase.index'));
    });

    it('validates required fields on store', function (array $data, array $errors) {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->post(route('purchase.store'), $data)
            ->assertSessionHasErrors($errors);
    })->with([
        'missing supplier_id' => [['date' => Carbon::today()->toDateString(), 'product_items' => []], ['supplier_id']],
        'missing date' => [['supplier_id' => 1, 'product_items' => []], ['date']],
        'empty product_items' => [['supplier_id' => 1, 'date' => Carbon::today()->toDateString(), 'product_items' => []], ['product_items']],
        'missing product_items key' => [['supplier_id' => 1, 'date' => Carbon::today()->toDateString()], ['product_items']],
        'invalid supplier_id' => [['supplier_id' => 999999, 'date' => Carbon::today()->toDateString(), 'product_items' => [['product_id' => 1, 'qty' => 1, 'price' => 100, 'selling_price' => 200]]], ['supplier_id']],
    ]);

    it('does not create purchase when validation fails', function () {
        $countBefore = Purchase::count();

        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->post(route('purchase.store'), ['supplier_id' => '', 'date' => '', 'product_items' => []]);

        expect(Purchase::count())->toBe($countBefore);
    });
});

// ============================================================
// Update
// ============================================================

describe('Update', function () {
    it('updates a purchase and redirects to index with success message', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $purchase = purchase($this->admin, $this->supplier, $this->product, $this->warehouseModel);
        $newSupplier = Supplier::factory()->create();

        actingAs($this->admin)
            ->put(route('purchase.update', $purchase), purchasePayload($newSupplier, $this->product, $this->warehouseModel))
            ->assertRedirect(route('purchase.index'))
            ->assertSessionHas('success');

        assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'supplier_id' => $newSupplier->id,
        ]);
    });

    it('adjusts stock when qty increases on update', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $purchase = purchase($this->admin, $this->supplier, $this->product, $this->warehouseModel, [], ['qty' => 5]);
        // Simulate stock after original purchase: warehouse stock = 15
        $this->warehouseModel->products()->syncWithoutDetaching([$this->product->id => ['stock' => 15]]);
        $this->product->syncStockFromWarehouses();

        actingAs($this->admin)
            ->put(route('purchase.update', $purchase), purchasePayload($this->supplier, $this->product, $this->warehouseModel, [
                'product_items' => [[
                    'product_id' => $this->product->id,
                    'qty' => 8, // +3 dari 5
                    'price' => 1000,
                    'selling_price' => 2000,
                    'expiry_date' => null,
                ]],
            ]));

        expect($this->product->fresh()->stock)->toBe(18); // 15 + 3
    });

    it('adjusts stock when qty decreases on update', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $purchase = purchase($this->admin, $this->supplier, $this->product, $this->warehouseModel, [], ['qty' => 5]);
        $this->warehouseModel->products()->syncWithoutDetaching([$this->product->id => ['stock' => 15]]);
        $this->product->syncStockFromWarehouses();

        actingAs($this->admin)
            ->put(route('purchase.update', $purchase), purchasePayload($this->supplier, $this->product, $this->warehouseModel, [
                'product_items' => [[
                    'product_id' => $this->product->id,
                    'qty' => 3, // -2 dari 5
                    'price' => 1000,
                    'selling_price' => 2000,
                    'expiry_date' => null,
                ]],
            ]));

        expect($this->product->fresh()->stock)->toBe(13); // 15 - 2
    });

    it('creates stock log with type adjust on update', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $purchase = purchase($this->admin, $this->supplier, $this->product, $this->warehouseModel, [], ['qty' => 5]);
        $this->warehouseModel->products()->syncWithoutDetaching([$this->product->id => ['stock' => 15]]);
        $this->product->syncStockFromWarehouses();

        actingAs($this->admin)
            ->put(route('purchase.update', $purchase), purchasePayload($this->supplier, $this->product, $this->warehouseModel, [
                'product_items' => [[
                    'product_id' => $this->product->id,
                    'qty' => 8,
                    'price' => 1000,
                    'selling_price' => 2000,
                    'expiry_date' => null,
                ]],
            ]));

        assertDatabaseHas('stock_logs', [
            'product_id' => $this->product->id,
            'type' => 'adjust',
            'reference_type' => 'Purchase',
            'reference_id' => $purchase->id,
        ]);
    });

    it('returns 404 for non-existent purchase on update', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->put(route('purchase.update', 999999), purchasePayload($this->supplier, $this->product, $this->warehouseModel))
            ->assertNotFound();
    });

    it('validates required fields on update', function (array $overrides, array $errors) {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $purchase = purchase($this->admin, $this->supplier, $this->product, $this->warehouseModel);

        actingAs($this->admin)
            ->put(route('purchase.update', $purchase), array_merge(
                purchasePayload($this->supplier, $this->product, $this->warehouseModel),
                $overrides
            ))
            ->assertSessionHasErrors($errors);
    })->with([
        'missing supplier_id' => [['supplier_id' => ''],   ['supplier_id']],
        'missing date' => [['date' => ''],          ['date']],
        'empty product_items' => [['product_items' => []], ['product_items']],
    ]);
});

// ============================================================
// Destroy
// ============================================================

describe('Destroy', function () {
    it('deletes a purchase and redirects to index with success message', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $purchase = purchase($this->admin, $this->supplier, $this->product, $this->warehouseModel);

        actingAs($this->admin)
            ->delete(route('purchase.destroy', $purchase))
            ->assertRedirect(route('purchase.index'))
            ->assertSessionHas('success');

        assertSoftDeleted('purchases', ['id' => $purchase->id]);
    });

    it('decrements product stock on delete', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $purchase = purchase($this->admin, $this->supplier, $this->product, $this->warehouseModel, [], ['qty' => 5]);
        $this->warehouseModel->products()->syncWithoutDetaching([$this->product->id => ['stock' => 15]]);
        $this->product->syncStockFromWarehouses();

        actingAs($this->admin)
            ->delete(route('purchase.destroy', $purchase));

        expect($this->product->fresh()->stock)->toBe(10); // 15 - 5
    });

    it('creates stock log with type out on delete', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $purchase = purchase($this->admin, $this->supplier, $this->product, $this->warehouseModel, [], ['qty' => 5]);

        actingAs($this->admin)
            ->delete(route('purchase.destroy', $purchase));

        assertDatabaseHas('stock_logs', [
            'product_id' => $this->product->id,
            'qty' => 5,
            'type' => 'out',
            'reference_type' => 'Purchase',
            'reference_id' => $purchase->id,
        ]);
    });

    it('deletes all purchase items on purchase delete', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $purchase = purchase($this->admin, $this->supplier, $this->product, $this->warehouseModel);

        actingAs($this->admin)
            ->delete(route('purchase.destroy', $purchase));

        assertSoftDeleted('purchase_items', ['purchase_id' => $purchase->id]);
    });

    it('returns 404 for non-existent purchase on delete', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        actingAs($this->admin)
            ->delete(route('purchase.destroy', 999999))
            ->assertNotFound();
    });

    it('warehouse can also delete a purchase', function () {
        /** @var TestCase&object{admin:User, cashier:User, warehouse:User, warehouseModel:Warehouse, supplier:Supplier, product:Product} $this */
        $purchase = purchase($this->warehouse, $this->supplier, $this->product, $this->warehouseModel);

        actingAs($this->warehouse)
            ->delete(route('purchase.destroy', $purchase))
            ->assertRedirect(route('purchase.index'));
    });
});
