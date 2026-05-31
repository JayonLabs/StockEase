<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $this->postJson(route('shift.store'), ['starting_cash' => 100000])
        ->assertUnauthorized();
});

it('requires starting_cash field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('shift.store'), [])
        ->assertJsonValidationErrors(['starting_cash']);
});

it('rejects negative starting_cash', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('shift.store'), ['starting_cash' => -1])
        ->assertJsonValidationErrors(['starting_cash']);
});

it('rejects non-numeric starting_cash', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('shift.store'), ['starting_cash' => 'abc'])
        ->assertJsonValidationErrors(['starting_cash']);
});

it('accepts zero starting_cash', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('shift.store'), ['starting_cash' => 0])
        ->assertRedirect();
});

it('accepts valid starting_cash', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('shift.store'), ['starting_cash' => 500000])
        ->assertRedirect();
});
