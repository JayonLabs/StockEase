<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $this->patchJson(route('pos.change-qty'), ['product_id' => 1, 'qty' => 2])
        ->assertUnauthorized();
});

it('requires product_id field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->patchJson(route('pos.change-qty'), ['qty' => 2])
        ->assertJsonValidationErrors(['product_id']);
});

it('requires qty field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create();

    actingAs($user)
        ->patchJson(route('pos.change-qty'), ['product_id' => $product->id])
        ->assertJsonValidationErrors(['qty']);
});

it('accepts qty of zero (removes item)', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);

    actingAs($user)
        ->patchJson(route('pos.change-qty'), ['product_id' => $product->id, 'qty' => 0])
        ->assertJsonMissingValidationErrors(['qty']);
});

it('rejects negative qty', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create();

    actingAs($user)
        ->patchJson(route('pos.change-qty'), ['product_id' => $product->id, 'qty' => -1])
        ->assertJsonValidationErrors(['qty']);
});

it('rejects non-existent product_id', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->patchJson(route('pos.change-qty'), ['product_id' => 99999, 'qty' => 2])
        ->assertJsonValidationErrors(['product_id']);
});
