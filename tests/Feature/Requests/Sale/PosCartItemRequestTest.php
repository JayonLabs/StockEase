<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $this->postJson(route('pos.add-to-cart'), ['product_id' => 1])
        ->assertUnauthorized();
});

it('requires product_id field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('pos.add-to-cart'), [])
        ->assertJsonValidationErrors(['product_id']);
});

it('rejects product_id that does not exist', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('pos.add-to-cart'), ['product_id' => 99999])
        ->assertJsonValidationErrors(['product_id']);
});

it('accepts valid product_id', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);

    actingAs($user)
        ->postJson(route('pos.add-to-cart'), ['product_id' => $product->id])
        ->assertJsonMissingValidationErrors(['product_id']);
});

it('rejects qty below 1 when provided', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);

    actingAs($user)
        ->postJson(route('pos.add-to-cart'), ['product_id' => $product->id, 'qty' => 0])
        ->assertJsonValidationErrors(['qty']);
});
