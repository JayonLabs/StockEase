<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $this->postJson(route('pos.add-to-cart-barcode'), ['barcode' => '12345'])
        ->assertUnauthorized();
});

it('requires barcode field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('pos.add-to-cart-barcode'), [])
        ->assertJsonValidationErrors(['barcode']);
});

it('rejects barcode that does not exist in products', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('pos.add-to-cart-barcode'), ['barcode' => 'NON-EXISTENT-9999'])
        ->assertJsonValidationErrors(['barcode']);
});

it('accepts valid barcode that exists', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    Product::factory()->create(['barcode' => 'VALID-BAR-001', 'stock' => 10]);

    actingAs($user)
        ->postJson(route('pos.add-to-cart-barcode'), ['barcode' => 'VALID-BAR-001'])
        ->assertJsonMissingValidationErrors(['barcode']);
});

it('rejects qty below minimum when provided', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    Product::factory()->create(['barcode' => 'VALID-BAR-002', 'stock' => 10]);

    actingAs($user)
        ->postJson(route('pos.add-to-cart-barcode'), ['barcode' => 'VALID-BAR-002', 'qty' => 0])
        ->assertJsonValidationErrors(['qty']);
});
