<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\StockAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{admin: User, warehouse: User, cashier: User, warehouseModel: Warehouse} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->warehouseModel = Warehouse::factory()->create();
});

it('allows admin and warehouse to view stock adjustment list', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    StockAdjustment::factory()->count(3)->create();

    $response = actingAs($user)->get(route('stock-adjustment.index'));

    $response->assertSuccessful();
    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('StockAdjustment/Index')
            ->has('adjustments.data', 3)
            ->has('warehouses')
    );
})->with(['admin', 'warehouse']);

it('forbids cashier from viewing stock adjustment list', function () {
    /** @var TestCase&object{cashier: User} $this */
    $response = $this->actingAs($this->cashier)->get(route('stock-adjustment.index'));
    $response->assertForbidden();
});

it('can store a new stock adjustment via controller', function () {
    /** @var TestCase&object{admin: User, warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $response = $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 120,
        'reason' => 'Stock opname bulanan',
        'date' => now()->toDateString(),
    ]);

    $response->assertRedirect(route('stock-adjustment.index'));
    $response->assertSessionHas('success');

    $product->refresh();
    expect($product->stock)->toBe(120);

    $this->assertDatabaseHas('stock_adjustments', [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 120,
    ]);
});

it('does not run duplicate role queries when loading stock adjustment index', function () {
    /** @var TestCase&object{admin: User, warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $sameUser = User::factory()->create(['role' => 'warehouse']);
    StockAdjustment::factory()->count(4)->create([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'user_id' => $sameUser->id,
    ]);

    DB::enableQueryLog();
    $response = $this->actingAs($this->admin)->get(route('stock-adjustment.index'));
    $queries = DB::getQueryLog();

    $response->assertSuccessful();

    $duplicateQueries = collect($queries)
        ->filter(fn ($query) => str_contains($query['query'], 'model_has_roles'))
        ->groupBy(fn ($query) => $query['query'].json_encode($query['bindings']))
        ->filter(fn ($group) => $group->count() > 1);

    expect($duplicateQueries)->toHaveCount(0);
});

it('can search products for adjustment', function () {
    /** @var TestCase&object{admin: User} $this */
    $product = Product::factory()->create(['name' => 'Beras Organik']);

    $response = $this->actingAs($this->admin)
        ->getJson(route('stock-adjustment.search-product', ['search' => 'Beras']));

    $response->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonPath('0.label', 'Beras Organik');
});

it('triggers stock alert notification when stock adjusted below alert threshold', function () {
    /** @var TestCase&object{admin: User, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create(['alert_stock' => 20]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 10,
        'reason' => 'Stock opname — found missing items',
        'date' => now()->toDateString(),
    ])->assertRedirect(route('stock-adjustment.index'));

    $product->refresh();
    expect($product->stock)->toBe(10);

    Notification::assertSentTo(
        [$this->admin],
        StockAlertNotification::class,
        function ($notification) use ($product) {
            return $notification->toArray($this->admin)['product_id'] === $product->id;
        }
    );
});

it('triggers stock alert notification when stock adjusted exactly to alert threshold', function () {
    /** @var TestCase&object{admin: User, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create(['alert_stock' => 15]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 15,
        'reason' => 'Adjusted to exactly alert level',
        'date' => now()->toDateString(),
    ]);

    $product->refresh();
    expect($product->stock)->toBe(15);

    Notification::assertSentTo(
        [$this->admin],
        StockAlertNotification::class
    );
});

it('does not trigger stock alert notification when stock adjusted down but still above alert', function () {
    /** @var TestCase&object{admin: User, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create(['alert_stock' => 10]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 50,
        'reason' => 'Reduced but still safe',
        'date' => now()->toDateString(),
    ]);

    $product->refresh();
    expect($product->stock)->toBe(50);

    Notification::assertNothingSent();
});

it('does not trigger stock alert notification when stock adjusted up', function () {
    /** @var TestCase&object{admin: User, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create(['alert_stock' => 10]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 5]);
    $product->syncStockFromWarehouses();

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 200,
        'reason' => 'Found extra stock during opname',
        'date' => now()->toDateString(),
    ]);

    $product->refresh();
    expect($product->stock)->toBe(200);

    Notification::assertNothingSent();
});

it('does not trigger duplicate notification when adjusting stock that is already below alert', function () {
    /** @var TestCase&object{admin: User, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create(['alert_stock' => 10]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 3]);
    $product->syncStockFromWarehouses();

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 2,
        'reason' => 'Further reduced already-low stock',
        'date' => now()->toDateString(),
    ]);

    $product->refresh();
    expect($product->stock)->toBe(2);

    Notification::assertSentTo(
        [$this->admin],
        StockAlertNotification::class
    );
});

it('sends notification to all users when stock adjusted below alert', function () {
    /** @var TestCase&object{admin: User, warehouse: User, cashier: User, warehouseModel: Warehouse} $this */
    Notification::fake();

    $product = Product::factory()->create(['alert_stock' => 10]);
    $this->warehouseModel->products()->attach($product->id, ['stock' => 50]);
    $product->syncStockFromWarehouses();

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 5,
        'reason' => 'Stock low after opname',
        'date' => now()->toDateString(),
    ]);

    Notification::assertSentTo(
        [$this->admin, $this->warehouse, $this->cashier],
        StockAlertNotification::class
    );
});
