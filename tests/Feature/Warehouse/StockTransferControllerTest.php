<?php

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

uses(LazilyRefreshDatabase::class);

// -- AUTHORIZATION --

it('denies unauthenticated users to access stock transfers', function () {
    get(route('stock-transfer.index'))->assertRedirect(route('login'));
});

it('denies non-warehouse users to access stock transfers', function (string $role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);

    actingAs($user)->get(route('stock-transfer.index'))->assertForbidden();
})->with(['cashier']);

// -- INDEX --

it('allows admin to view stock transfers', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    StockTransfer::factory()->count(3)->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
    ]);

    actingAs($admin)
        ->get(route('stock-transfer.index'))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('StockTransfer/Index')
                ->has('transfers.data', 3)
        );
});

it('allows warehouse role to view stock transfers', function () {
    /** @var User $warehouseUser */
    $warehouseUser = User::factory()->create(['role' => 'warehouse']);
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
    ]);

    actingAs($warehouseUser)
        ->get(route('stock-transfer.index'))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('StockTransfer/Index')
                ->has('transfers.data', 1)
        );
});

it('can filter stock transfers by warehouse', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouseA = Warehouse::factory()->create(['name' => 'Gudang A']);
    $warehouseB = Warehouse::factory()->create(['name' => 'Gudang B']);
    $product = Product::factory()->create();

    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
    ]);

    actingAs($admin)
        ->get(route('stock-transfer.index', ['warehouse_id' => $warehouseA->id]))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('StockTransfer/Index')
                ->has('transfers.data', 1)
        );
});

it('can filter stock transfers by search term', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();
    $productA = Product::factory()->create(['name' => 'Keripik Pedas']);
    $productB = Product::factory()->create(['name' => 'Air Mineral']);

    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'product_id' => $productA->id,
    ]);
    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'product_id' => $productB->id,
    ]);

    actingAs($admin)
        ->get(route('stock-transfer.index', ['search' => 'Keripik']))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('StockTransfer/Index')
                ->has('transfers.data', 1)
        );
});

it('can filter stock transfers by search and warehouse combined', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouseA = Warehouse::factory()->create(['name' => 'Gudang A']);
    $warehouseB = Warehouse::factory()->create(['name' => 'Gudang B']);
    $product = Product::factory()->create(['name' => 'Keripik Pedas']);

    // Transfer di warehouseA — harus muncul
    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseA->id,
        'product_id' => $product->id,
    ]);

    // Transfer di warehouseB dengan produk sama — tidak boleh muncul
    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouseB->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
    ]);

    actingAs($admin)
        ->get(route('stock-transfer.index', [
            'search' => 'Keripik',
            'warehouse_id' => $warehouseA->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('StockTransfer/Index')
                ->has('transfers.data', 1)
                ->where('filters.search', 'Keripik')
                ->where('filters.warehouse_id', (string) $warehouseA->id)
        );
});

it('search by note does not bypass warehouse filter', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $product = Product::factory()->create();

    // Transfer di warehouseA dengan note cocok — harus muncul
    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseA->id,
        'product_id' => $product->id,
        'note' => 'urgent delivery',
    ]);

    // Transfer di warehouseB dengan note cocok — tidak boleh muncul
    StockTransfer::factory()->create([
        'from_warehouse_id' => $warehouseB->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'note' => 'urgent delivery',
    ]);

    actingAs($admin)
        ->get(route('stock-transfer.index', [
            'search' => 'urgent',
            'warehouse_id' => $warehouseA->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('StockTransfer/Index')
                ->has('transfers.data', 1)
        );
});

it('returns filters props to frontend', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();

    actingAs($admin)
        ->get(route('stock-transfer.index', [
            'search' => 'test',
            'warehouse_id' => $warehouse->id,
        ]))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('StockTransfer/Index')
                ->where('filters.search', 'test')
                ->where('filters.warehouse_id', (string) $warehouse->id)
                ->has('warehouses')
        );
});

it('does not duplicate roles query when auth user has transfers', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    // Create transfers by the auth user (so auth user appears as transfer user)
    StockTransfer::factory()->count(3)->create([
        'user_id' => $admin->id,
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
    ]);

    DB::enableQueryLog();

    actingAs($admin)
        ->get(route('stock-transfer.index'))
        ->assertSuccessful();

    $rolesQueries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'model_has_roles'));

    DB::disableQueryLog();

    // Should be at most 1 roles query: auth user roles are loaded by middleware,
    // and loadMissing on transfer users skips already-loaded auth user.
    expect($rolesQueries->count())->toBeLessThanOrEqual(1);
});

// -- SEARCH PRODUCT --

it('returns products matching search term', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Product::factory()->create(['name' => 'Air Mineral', 'stock' => 100]);
    Product::factory()->create(['name' => 'Keripik Pedas', 'stock' => 50]);

    actingAs($admin)
        ->getJson(route('stock-transfer.search-product', ['search' => 'Air']))
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonFragment(['label' => 'Air Mineral']);
});

it('returns products even when they are not in the warehouse pivot', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();
    // Produk ada di tabel products tapi TIDAK ada di warehouse_product pivot
    Product::factory()->create(['name' => 'Air Mineral', 'stock' => 100]);

    actingAs($admin)
        ->getJson(route('stock-transfer.search-product', [
            'search' => 'Air',
            'warehouse_id' => $warehouse->id,
        ]))
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonFragment(['label' => 'Air Mineral']);
});

it('returns warehouse_stock from pivot when warehouse_id is provided', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['name' => 'Air Mineral', 'stock' => 100]);
    $warehouse->products()->attach($product->id, ['stock' => 30]);

    $response = actingAs($admin)
        ->getJson(route('stock-transfer.search-product', [
            'search' => 'Air',
            'warehouse_id' => $warehouse->id,
        ]))
        ->assertSuccessful()
        ->assertJsonCount(1);

    expect($response->json('0.warehouse_stock'))->toBe(30);
});

it('returns null warehouse_stock when product not in warehouse pivot', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();
    Product::factory()->create(['name' => 'Air Mineral', 'stock' => 100]);

    $response = actingAs($admin)
        ->getJson(route('stock-transfer.search-product', [
            'search' => 'Air',
            'warehouse_id' => $warehouse->id,
        ]))
        ->assertSuccessful()
        ->assertJsonCount(1);

    expect($response->json('0.warehouse_stock'))->toBeNull();
});

it('searches products by sku and barcode', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Product::factory()->create(['name' => 'Produk A', 'sku' => 'SKU-001', 'stock' => 10]);
    Product::factory()->create(['name' => 'Produk B', 'barcode' => 'BC-999', 'stock' => 5]);
    Product::factory()->create(['name' => 'Produk C', 'stock' => 1]);

    actingAs($admin)
        ->getJson(route('stock-transfer.search-product', ['search' => 'SKU-001']))
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonFragment(['label' => 'Produk A']);

    actingAs($admin)
        ->getJson(route('stock-transfer.search-product', ['search' => 'BC-999']))
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonFragment(['label' => 'Produk B']);
});

it('returns no products when search does not match', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Product::factory()->create(['name' => 'Air Mineral']);

    actingAs($admin)
        ->getJson(route('stock-transfer.search-product', ['search' => 'xyz-tidak-ada']))
        ->assertSuccessful()
        ->assertJsonCount(0);
});

it('denies unauthenticated users to search products', function () {
    getJson(route('stock-transfer.search-product', ['search' => 'Air']))
        ->assertUnauthorized();
});

// -- STORE --

it('allows admin to create a stock transfer', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 100]);

    $warehouseA->products()->attach($product->id, ['stock' => 50]);

    actingAs($admin)
        ->post(route('stock-transfer.store'), [
            'from_warehouse_id' => $warehouseA->id,
            'to_warehouse_id' => $warehouseB->id,
            'product_id' => $product->id,
            'qty' => 10,
            'note' => 'Pindah stok',
            'date' => now()->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Berhasil melakukan pemindahan stok.');

    assertDatabaseHas('stock_transfers', [
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'qty' => 10,
        'status' => 'completed',
    ]);

    assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouseA->id,
        'product_id' => $product->id,
        'stock' => 40,
    ]);

    assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'stock' => 10,
    ]);

    assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => 10,
        'type' => 'transfer',
        'reference_type' => 'StockTransfer',
    ]);
});

it('allows warehouse role to create a stock transfer', function () {
    /** @var User $warehouseUser */
    $warehouseUser = User::factory()->create(['role' => 'warehouse']);
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $warehouseA->products()->attach($product->id, ['stock' => 50]);

    actingAs($warehouseUser)
        ->post(route('stock-transfer.store'), [
            'from_warehouse_id' => $warehouseA->id,
            'to_warehouse_id' => $warehouseB->id,
            'product_id' => $product->id,
            'qty' => 5,
            'date' => now()->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Berhasil melakukan pemindahan stok.');
});

it('creates a stock log when transferring stock', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouseA = Warehouse::factory()->create(['name' => 'Gudang A']);
    $warehouseB = Warehouse::factory()->create(['name' => 'Gudang B']);
    $product = Product::factory()->create();

    $warehouseA->products()->attach($product->id, ['stock' => 30]);

    actingAs($admin)
        ->post(route('stock-transfer.store'), [
            'from_warehouse_id' => $warehouseA->id,
            'to_warehouse_id' => $warehouseB->id,
            'product_id' => $product->id,
            'qty' => 15,
            'note' => 'Catatan transfer',
            'date' => now()->toDateString(),
        ])
        ->assertRedirect();

    assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => 15,
        'type' => 'transfer',
    ]);
});

it('validates stock transfer creation', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Warehouse::factory()->count(2)->create();
    Product::factory()->create();

    // Override IDs with valid values for the missing-field specific tests
    if (isset($data['from_warehouse_id'])) {
        $data['from_warehouse_id'] = 1;
    }
    if (isset($data['to_warehouse_id'])) {
        $data['to_warehouse_id'] = 2;
    }
    if (isset($data['product_id'])) {
        $data['product_id'] = 1;
    }

    actingAs($admin)
        ->post(route('stock-transfer.store'), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'missing from_warehouse_id' => [['to_warehouse_id' => 2, 'product_id' => 1, 'qty' => 5, 'date' => now()->toDateString()], ['from_warehouse_id']],
    'missing to_warehouse_id' => [['from_warehouse_id' => 1, 'product_id' => 1, 'qty' => 5, 'date' => now()->toDateString()], ['to_warehouse_id']],
    'missing product_id' => [['from_warehouse_id' => 1, 'to_warehouse_id' => 2, 'qty' => 5, 'date' => now()->toDateString()], ['product_id']],
    'missing qty' => [['from_warehouse_id' => 1, 'to_warehouse_id' => 2, 'product_id' => 1, 'date' => now()->toDateString()], ['qty']],
    'missing date' => [['from_warehouse_id' => 1, 'to_warehouse_id' => 2, 'product_id' => 1, 'qty' => 5], ['date']],
    'qty less than 1' => [['from_warehouse_id' => 1, 'to_warehouse_id' => 2, 'product_id' => 1, 'qty' => 0, 'date' => now()->toDateString()], ['qty']],
    'same from and to warehouse' => [['from_warehouse_id' => 1, 'to_warehouse_id' => 1, 'product_id' => 1, 'qty' => 5, 'date' => now()->toDateString()], ['to_warehouse_id']],
]);

// ============================================================
// N+1 Regression — PERF-02 (storeTransfer single pivot query)
// ============================================================

it('issues a single warehouse_product query (not two) when storing a transfer', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $fromWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $toWarehouse = Warehouse::factory()->create(['is_active' => true]);
    $product = Product::factory()->create(['stock' => 10]);

    DB::table('warehouse_product')->insert([
        ['warehouse_id' => $fromWarehouse->id, 'product_id' => $product->id, 'stock' => 10, 'created_at' => now(), 'updated_at' => now()],
        ['warehouse_id' => $toWarehouse->id, 'product_id' => $product->id, 'stock' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $product->syncStockFromWarehouses();

    DB::enableQueryLog();

    actingAs($admin)->post(route('stock-transfer.store'), [
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'product_id' => $product->id,
        'qty' => 5,
        'date' => now()->toDateString(),
        'note' => null,
    ])->assertRedirect();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // The old code did 2 separate pivot queries via Eloquent BelongsToMany.
    // The fix uses one whereIn query on warehouse_product directly.
    $pivotLookupQueries = collect($queries)->filter(
        fn ($q) => str_contains($q['query'], 'pivot_product_id')
    );

    expect($pivotLookupQueries)->toHaveCount(0);

    expect(
        DB::table('warehouse_product')->where('warehouse_id', $fromWarehouse->id)->where('product_id', $product->id)->value('stock')
    )->toBe(5);
    expect(
        DB::table('warehouse_product')->where('warehouse_id', $toWarehouse->id)->where('product_id', $product->id)->value('stock')
    )->toBe(5);
});
