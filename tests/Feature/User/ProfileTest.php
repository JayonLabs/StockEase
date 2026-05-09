<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;

uses(RefreshDatabase::class);

test('profile page is displayed', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    assertGuest();
    expect($user->fresh()->trashed())->toBeTrue();
});

test('correct password must be provided to delete account', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    expect($user->fresh())->not->toBeNull();
});

test('validates profile update with empty name', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->patch('/profile', [
            'name' => '',
            'email' => 'valid@example.com',
        ]);

    $response
        ->assertSessionHasErrors('name')
        ->assertRedirect('/');
});

test('validates profile update with invalid email', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->patch('/profile', [
            'name' => 'Valid Name',
            'email' => 'not-an-email',
        ]);

    $response
        ->assertSessionHasErrors('email')
        ->assertRedirect('/');
});

test('validates profile update with duplicate email', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['email' => 'other@example.com']);

    $response = actingAs($user)
        ->patch('/profile', [
            'name' => 'Valid Name',
            'email' => 'other@example.com',
        ]);

    $response
        ->assertSessionHasErrors('email')
        ->assertRedirect('/');
});

test('validates profile update with missing fields', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->patch('/profile', []);

    $response
        ->assertSessionHasErrors(['name', 'email'])
        ->assertRedirect('/');
});

test('validates account deletion with missing password', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->delete('/profile', []);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');
});
