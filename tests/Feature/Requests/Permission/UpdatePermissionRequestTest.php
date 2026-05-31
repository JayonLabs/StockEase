<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('denies unauthorized roles', function (string $role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $permission = Permission::create(['name' => 'some-permission', 'guard_name' => 'web']);

    actingAs($user)
        ->putJson(route('permissions.update', $permission), ['name' => 'new-name'])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('requires name field', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $permission = Permission::create(['name' => 'some-permission', 'guard_name' => 'web']);

    actingAs($admin)
        ->putJson(route('permissions.update', $permission), [])
        ->assertJsonValidationErrors(['name']);
});

it('rejects name exceeding 255 characters', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $permission = Permission::create(['name' => 'some-permission', 'guard_name' => 'web']);

    actingAs($admin)
        ->putJson(route('permissions.update', $permission), ['name' => str_repeat('a', 256)])
        ->assertJsonValidationErrors(['name']);
});

it('rejects duplicate name from another permission', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Permission::create(['name' => 'existing-permission', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'other-permission', 'guard_name' => 'web']);

    actingAs($admin)
        ->putJson(route('permissions.update', $permission), ['name' => 'existing-permission'])
        ->assertJsonValidationErrors(['name']);
});

it('allows updating with same name on same permission', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $permission = Permission::create(['name' => 'same-permission', 'guard_name' => 'web']);

    actingAs($admin)
        ->putJson(route('permissions.update', $permission), ['name' => 'same-permission'])
        ->assertRedirect();
});
