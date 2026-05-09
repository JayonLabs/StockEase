<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('password can be updated', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
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
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
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
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
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
