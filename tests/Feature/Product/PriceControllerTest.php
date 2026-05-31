<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(LazilyRefreshDatabase::class);

it('allows admin and warehouse to view update price page', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $product = Product::factory()->create();

    $response = actingAs($user)->get(route('product.price.edit', $product));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Product/Price/Update')
            ->has('product')
            ->has('history')
    );
})->with(['admin', 'warehouse']);

it('allows admin to update product price and log history', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create([
        'purchase_price' => 1000,
        'selling_price' => 2000,
    ]);

    $response = actingAs($admin)
        ->patch(route('product.price.update', $product), [
            'purchase_price' => 1500,
            'selling_price' => 2500,
            'reason' => 'Kenaikan harga supplier',
        ]);

    $response->assertRedirect(route('product.index'));

    assertDatabaseHas('products', [
        'id' => $product->id,
        'purchase_price' => 1500,
        'selling_price' => 2500,
    ]);

    assertDatabaseHas('price_histories', [
        'product_id' => $product->id,
        'user_id' => $admin->id,
        'old_purchase_price' => 1000,
        'new_purchase_price' => 1500,
        'old_selling_price' => 2000,
        'new_selling_price' => 2500,
        'reason' => 'Kenaikan harga supplier',
    ]);
});

it('validates price update request', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();

    $response = actingAs($admin)
        ->patch(route('product.price.update', $product), [
            'purchase_price' => -100, // Invalid
            'selling_price' => 'invalid', // Invalid
            'reason' => '', // Required
        ]);

    $response->assertSessionHasErrors(['purchase_price', 'selling_price', 'reason']);
});

it('prevents non-admin/warehouse from updating price', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create();

    $response = actingAs($user)
        ->patch(route('product.price.update', $product), [
            'purchase_price' => 1500,
            'selling_price' => 2500,
            'reason' => 'Test',
        ]);

    $response->assertForbidden();
});
