<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    putJson('/password', [
        'current_password' => 'password',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ])->assertUnauthorized();
});

it('requires current_password field', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->putJson('/password', [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertJsonValidationErrors(['current_password']);
});

it('rejects wrong current_password', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->putJson('/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertJsonValidationErrors(['current_password']);
});

it('requires new password field', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->putJson('/password', ['current_password' => 'password'])
        ->assertJsonValidationErrors(['password']);
});

it('rejects weak new password', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->putJson('/password', [
            'current_password' => 'password',
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ])
        ->assertJsonValidationErrors(['password']);
});

it('rejects mismatched password confirmation', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->putJson('/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ])
        ->assertJsonValidationErrors(['password']);
});

it('accepts valid password update', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->putJson('/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
        ->assertRedirect();
});
