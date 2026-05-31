<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('denies cashier from updating price', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create();

    actingAs($cashier)
        ->patchJson(route('product.price.update', $product), [
            'purchase_price' => 5000,
            'selling_price' => 8000,
            'reason' => 'Price adjustment',
        ])
        ->assertForbidden();
});

it('validates required fields', function (string $field) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();
    $data = ['purchase_price' => 5000, 'selling_price' => 8000, 'reason' => 'Price adjustment'];
    unset($data[$field]);

    actingAs($admin)
        ->patchJson(route('product.price.update', $product), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['purchase_price', 'selling_price', 'reason']);

it('rejects negative purchase_price', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();

    actingAs($admin)
        ->patchJson(route('product.price.update', $product), [
            'purchase_price' => -1,
            'selling_price' => 8000,
            'reason' => 'Test',
        ])
        ->assertJsonValidationErrors(['purchase_price']);
});

it('rejects reason exceeding 255 characters', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();

    actingAs($admin)
        ->patchJson(route('product.price.update', $product), [
            'purchase_price' => 5000,
            'selling_price' => 8000,
            'reason' => str_repeat('a', 256),
        ])
        ->assertJsonValidationErrors(['reason']);
});

it('accepts valid price update', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();

    actingAs($admin)
        ->patchJson(route('product.price.update', $product), [
            'purchase_price' => 5000,
            'selling_price' => 8000,
            'reason' => 'Supplier price increase',
        ])
        ->assertRedirect();
});
