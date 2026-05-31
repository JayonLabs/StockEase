<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $this->putJson(route('pos.checkout'), ['payment_method' => 'cash'])
        ->assertUnauthorized();
});

it('requires payment_method field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->putJson(route('pos.checkout'), [])
        ->assertJsonValidationErrors(['payment_method']);
});

it('rejects invalid payment_method value', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->putJson(route('pos.checkout'), ['payment_method' => 'bitcoin'])
        ->assertJsonValidationErrors(['payment_method']);
});

it('rejects pending as payment_method since it is not valid for checkout', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->putJson(route('pos.checkout'), ['payment_method' => 'pending'])
        ->assertJsonValidationErrors(['payment_method']);
});

it('accepts cash as payment_method', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->putJson(route('pos.checkout'), ['payment_method' => 'cash', 'paid' => 50000])
        ->assertJsonMissingValidationErrors(['payment_method']);
});

it('accepts qris as payment_method', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->putJson(route('pos.checkout'), ['payment_method' => 'qris'])
        ->assertJsonMissingValidationErrors(['payment_method']);
});

it('requires paid when payment_method is cash', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->putJson(route('pos.checkout'), ['payment_method' => 'cash'])
        ->assertJsonValidationErrors(['paid']);
});

it('does not require paid when payment_method is qris', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->putJson(route('pos.checkout'), ['payment_method' => 'qris'])
        ->assertJsonMissingValidationErrors(['paid']);
});

it('rejects customer_name exceeding 255 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->putJson(route('pos.checkout'), [
            'payment_method' => 'cash',
            'paid' => 50000,
            'customer_name' => str_repeat('a', 256),
        ])
        ->assertJsonValidationErrors(['customer_name']);
});
