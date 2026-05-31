<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    deleteJson(route('profile.destroy'), ['password' => 'password'])
        ->assertUnauthorized();
});

it('requires password field', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->deleteJson(route('profile.destroy'), [])
        ->assertJsonValidationErrors(['password']);
});

it('rejects wrong current password', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->deleteJson(route('profile.destroy'), ['password' => 'wrong-password'])
        ->assertJsonValidationErrors(['password']);
});

it('accepts correct current password', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->deleteJson(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect();
});
