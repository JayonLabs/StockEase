<?php

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

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

    $this->assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouseA->id,
        'product_id' => $product->id,
        'stock' => 40,
    ]);

    $this->assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'stock' => 10,
    ]);

    $this->assertDatabaseHas('stock_logs', [
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

    $this->assertDatabaseHas('stock_logs', [
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
