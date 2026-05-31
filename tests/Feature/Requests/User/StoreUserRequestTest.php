<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('denies unauthorized roles', function (string $role) {
    /** @var User $actor */
    $actor = User::factory()->create(['role' => $role]);

    actingAs($actor)
        ->postJson(route('users.store'), [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'role' => Role::Cashier->value,
        ])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('validates required fields', function (string $field) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
        'role' => Role::Cashier->value,
    ];
    unset($data[$field]);

    actingAs($admin)
        ->postJson(route('users.store'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['name', 'email', 'password', 'role']);

it('rejects invalid email format', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->postJson(route('users.store'), [
            'name' => 'New User',
            'email' => 'not-an-email',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'role' => Role::Cashier->value,
        ])
        ->assertJsonValidationErrors(['email']);
});

it('rejects duplicate email', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['email' => 'existing@example.com']);

    actingAs($admin)
        ->postJson(route('users.store'), [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'role' => Role::Cashier->value,
        ])
        ->assertJsonValidationErrors(['email']);
});

it('rejects password that does not meet complexity requirements', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->postJson(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'weakpassword',
            'password_confirmation' => 'weakpassword',
            'role' => Role::Cashier->value,
        ])
        ->assertJsonValidationErrors(['password']);
});

it('rejects password when confirmation does not match', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->postJson(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
            'role' => Role::Cashier->value,
        ])
        ->assertJsonValidationErrors(['password']);
});

it('rejects invalid role value', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->postJson(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'role' => 'super-hacker',
        ])
        ->assertJsonValidationErrors(['role']);
});

it('accepts valid user data', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->postJson(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
            'role' => Role::Cashier->value,
        ])
        ->assertRedirect();
});
