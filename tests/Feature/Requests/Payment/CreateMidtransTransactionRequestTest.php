<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    postJson(route('pos.qris-token'), [])
        ->assertUnauthorized();
});

it('requires amount field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('pos.qris-token'), ['customer_name' => 'Test'])
        ->assertJsonValidationErrors(['amount']);
});

it('rejects amount below minimum', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('pos.qris-token'), ['amount' => 0])
        ->assertJsonValidationErrors(['amount']);
});

it('rejects non-numeric amount', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('pos.qris-token'), ['amount' => 'not-a-number'])
        ->assertJsonValidationErrors(['amount']);
});

it('accepts valid amount without customer_name', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('pos.qris-token'), ['amount' => 50000])
        ->assertJsonMissingValidationErrors(['amount']);
});

it('rejects customer_name exceeding max length', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('pos.qris-token'), ['amount' => 50000, 'customer_name' => str_repeat('a', 256)])
        ->assertJsonValidationErrors(['customer_name']);
});
