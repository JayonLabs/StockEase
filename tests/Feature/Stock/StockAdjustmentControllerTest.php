<?php

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
});

it('allows admin and warehouse to view stock adjustment list', function ($role) {
    $user = User::factory()->create(['role' => $role]);
    StockAdjustment::factory()->count(3)->create();

    $response = $this->actingAs($user)->get(route('stock-adjustment.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('StockAdjustment/Index')
        ->has('adjustments.data', 3)
    );
})->with(['admin', 'warehouse']);

it('forbids cashier from viewing stock adjustment list', function () {
    $response = $this->actingAs($this->cashier)->get(route('stock-adjustment.index'));
    $response->assertForbidden();
});

it('can store a new stock adjustment via controller', function () {
    $product = Product::factory()->create(['stock' => 100]);

    $response = $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
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
        'product_id' => $product->id,
        'new_stock' => 120,
    ]);
});

it('can search products for adjustment', function () {
    $product = Product::factory()->create(['name' => 'Beras Organik']);

    $response = $this->actingAs($this->admin)
        ->getJson(route('stock-adjustment.search-product', ['search' => 'Beras']));

    $response->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonPath('0.label', 'Beras Organik');
});

it('triggers stock alert notification when stock adjusted below alert threshold', function () {
    Notification::fake();

    $product = Product::factory()->create(['stock' => 100, 'alert_stock' => 20]);

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
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
    Notification::fake();

    $product = Product::factory()->create(['stock' => 100, 'alert_stock' => 15]);

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
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
    Notification::fake();

    $product = Product::factory()->create(['stock' => 100, 'alert_stock' => 10]);

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
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
    Notification::fake();

    $product = Product::factory()->create(['stock' => 5, 'alert_stock' => 10]);

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
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
    Notification::fake();

    $product = Product::factory()->create(['stock' => 3, 'alert_stock' => 10]);

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
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
    Notification::fake();

    $product = Product::factory()->create(['stock' => 50, 'alert_stock' => 10]);

    $this->actingAs($this->admin)->post(route('stock-adjustment.store'), [
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
