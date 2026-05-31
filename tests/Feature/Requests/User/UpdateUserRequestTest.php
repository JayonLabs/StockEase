<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('denies unauthorized roles', function (string $role) {
    /** @var User $actor */
    $actor = User::factory()->create(['role' => $role]);
    /** @var User $target */
    $target = User::factory()->create();

    actingAs($actor)
        ->putJson(route('users.update', $target), [
            'name' => 'Updated',
            'email' => 'updated@example.com',
            'role' => Role::Cashier->value,
        ])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('validates required fields', function (string $field) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $target */
    $target = User::factory()->create();
    $data = ['name' => 'Updated', 'email' => 'updated@example.com', 'role' => Role::Cashier->value];
    unset($data[$field]);

    actingAs($admin)
        ->putJson(route('users.update', $target), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['name', 'email', 'role']);

it('rejects invalid email format', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $target */
    $target = User::factory()->create();

    actingAs($admin)
        ->putJson(route('users.update', $target), [
            'name' => 'Updated',
            'email' => 'not-an-email',
            'role' => Role::Cashier->value,
        ])
        ->assertJsonValidationErrors(['email']);
});

it('rejects duplicate email from another user', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $target */
    $target = User::factory()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    actingAs($admin)
        ->putJson(route('users.update', $target), [
            'name' => 'Updated',
            'email' => 'taken@example.com',
            'role' => Role::Cashier->value,
        ])
        ->assertJsonValidationErrors(['email']);
});

it('allows keeping same email on same user', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $target */
    $target = User::factory()->create(['email' => 'same@example.com']);

    actingAs($admin)
        ->putJson(route('users.update', $target), [
            'name' => 'Updated',
            'email' => 'same@example.com',
            'role' => Role::Cashier->value,
        ])
        ->assertRedirect();
});

it('rejects invalid role value', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $target */
    $target = User::factory()->create();

    actingAs($admin)
        ->putJson(route('users.update', $target), [
            'name' => 'Updated',
            'email' => 'updated@example.com',
            'role' => 'invalid-role',
        ])
        ->assertJsonValidationErrors(['role']);
});
