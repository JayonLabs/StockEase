<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->warehouseUser = User::factory()->create(['role' => 'warehouse']);
});

// --- Sale Index Page ---

it('renders sale index page for admin', function () {
    actingAs($this->admin)
        ->get(route('sale.index'))
        ->assertSuccessful();
});

it('renders sale index page for cashier', function () {
    actingAs($this->cashier)
        ->get(route('sale.index'))
        ->assertSuccessful();
});

it('denies warehouse user from sale index page', function () {
    actingAs($this->warehouseUser)
        ->get(route('sale.index'))
        ->assertForbidden();
});

it('filters sales by date range', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $warehouse->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $inRange = Sale::factory()->create([
        'date' => '2026-05-10',
        'status' => 'completed',
    ]);
    SaleItem::factory()->create(['sale_id' => $inRange->id, 'product_id' => $product->id, 'qty' => 1, 'price' => 10000]);

    $outOfRange = Sale::factory()->create([
        'date' => '2026-04-01',
        'status' => 'completed',
    ]);
    SaleItem::factory()->create(['sale_id' => $outOfRange->id, 'product_id' => $product->id, 'qty' => 1, 'price' => 10000]);

    actingAs($this->admin)
        ->get(route('sale.index', ['start' => '2026-05-01', 'end' => '2026-05-31']))
        ->assertSuccessful();
});

it('renders sale index with no date filters', function () {
    Sale::factory()->create(['date' => now(), 'status' => 'completed']);

    actingAs($this->admin)
        ->get(route('sale.index', ['start' => '', 'end' => '']))
        ->assertSuccessful();
});

// --- Purchase Index Page ---

it('renders purchase index page for admin', function () {
    actingAs($this->admin)
        ->get(route('purchase.index'))
        ->assertSuccessful();
});

it('renders purchase index page for warehouse user', function () {
    actingAs($this->warehouseUser)
        ->get(route('purchase.index'))
        ->assertSuccessful();
});

it('denies cashier from purchase index page', function () {
    actingAs($this->cashier)
        ->get(route('purchase.index'))
        ->assertForbidden();
});

it('filters purchases by date range', function () {
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $warehouse->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $inRange = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'date' => '2026-03-15',
    ]);
    PurchaseItem::factory()->create([
        'purchase_id' => $inRange->id,
        'product_id' => $product->id,
    ]);

    Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'date' => '2026-01-01',
    ]);

    actingAs($this->admin)
        ->get(route('purchase.index', ['start' => '2026-03-01', 'end' => '2026-03-31']))
        ->assertSuccessful();
});

it('renders purchase index with empty date filters', function () {
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();

    Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'date' => now(),
    ]);

    actingAs($this->admin)
        ->get(route('purchase.index', ['start' => '', 'end' => '']))
        ->assertSuccessful();
});

// --- Log Stock Index Page ---

it('renders log-stock index page for admin', function () {
    actingAs($this->admin)
        ->get(route('log-stock.index'))
        ->assertSuccessful();
});

it('renders log-stock index page for warehouse user', function () {
    actingAs($this->warehouseUser)
        ->get(route('log-stock.index'))
        ->assertSuccessful();
});

it('denies cashier from log-stock index page', function () {
    actingAs($this->cashier)
        ->get(route('log-stock.index'))
        ->assertForbidden();
});

it('filters log-stock by date range', function () {
    $product = Product::factory()->create();

    StockLog::factory()->create([
        'product_id' => $product->id,
        'created_at' => '2026-05-15 10:00:00',
    ]);

    StockLog::factory()->create([
        'product_id' => $product->id,
        'created_at' => '2026-04-01 10:00:00',
    ]);

    actingAs($this->admin)
        ->get(route('log-stock.index', ['start' => '2026-05-01', 'end' => '2026-05-31']))
        ->assertSuccessful();
});

it('renders log-stock index with no date filters', function () {
    $product = Product::factory()->create();
    StockLog::factory()->create(['product_id' => $product->id]);

    actingAs($this->admin)
        ->get(route('log-stock.index', ['start' => '', 'end' => '']))
        ->assertSuccessful();
});

// --- Sale Return Index Page ---

it('renders sale-return index page for admin', function () {
    actingAs($this->admin)
        ->get(route('sale-return.index'))
        ->assertSuccessful();
});

it('renders sale-return index page for cashier', function () {
    actingAs($this->cashier)
        ->get(route('sale-return.index'))
        ->assertSuccessful();
});

it('denies warehouse user from sale-return index page', function () {
    actingAs($this->warehouseUser)
        ->get(route('sale-return.index'))
        ->assertForbidden();
});

it('filters sale-return by date range', function () {
    $sale = Sale::factory()->create(['status' => 'completed']);

    SaleReturn::factory()->create([
        'sale_id' => $sale->id,
        'return_date' => '2026-05-20',
        'status' => 'completed',
    ]);

    SaleReturn::factory()->create([
        'sale_id' => $sale->id,
        'return_date' => '2026-04-10',
        'status' => 'completed',
    ]);

    actingAs($this->admin)
        ->get(route('sale-return.index', ['start' => '2026-05-01', 'end' => '2026-05-31']))
        ->assertSuccessful();
});

// --- Unauthenticated Access ---

it('denies unauthenticated access to sale index', function () {
    $this->get(route('sale.index'))
        ->assertRedirect(route('login'));
});

it('denies unauthenticated access to purchase index', function () {
    $this->get(route('purchase.index'))
        ->assertRedirect(route('login'));
});

it('denies unauthenticated access to log-stock index', function () {
    $this->get(route('log-stock.index'))
        ->assertRedirect(route('login'));
});

it('denies unauthenticated access to sale-return index', function () {
    $this->get(route('sale-return.index'))
        ->assertRedirect(route('login'));
});
