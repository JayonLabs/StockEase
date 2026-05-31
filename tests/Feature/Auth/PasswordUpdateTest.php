<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

test('password can be updated', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    expect(Hash::check('NewPassword123!', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/profile');
});

test('validates current password is required', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => '',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect('/profile');
});

test('validates new password must be confirmed', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');
});

test('validates new password minimum length', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');
});

test('validates password update with missing fields', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', []);

    $response
        ->assertSessionHasErrors(['current_password', 'password'])
        ->assertRedirect('/profile');
});

test('rejects weak password without mixed case, numbers, or symbols', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');
});
