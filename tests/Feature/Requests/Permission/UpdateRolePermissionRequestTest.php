<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('denies unauthorized roles', function (string $roleName) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $roleName]);
    $role = Role::firstOrCreate(['name' => 'target-role', 'guard_name' => 'web']);

    actingAs($user)
        ->putJson(route('role-permissions.update', $role), ['permissions' => []])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('allows updating with empty permissions array', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $role = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

    actingAs($admin)
        ->putJson(route('role-permissions.update', $role), ['permissions' => []])
        ->assertRedirect();
});

it('allows updating without permissions key', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $role = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

    actingAs($admin)
        ->putJson(route('role-permissions.update', $role), [])
        ->assertRedirect();
});

it('rejects permissions that do not exist in database', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $role = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);

    actingAs($admin)
        ->putJson(route('role-permissions.update', $role), ['permissions' => ['non-existent-permission']])
        ->assertJsonValidationErrors(['permissions.0']);
});

it('accepts valid permission names', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $role = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
    Permission::create(['name' => 'view-products', 'guard_name' => 'web']);

    actingAs($admin)
        ->putJson(route('role-permissions.update', $role), ['permissions' => ['view-products']])
        ->assertRedirect();
});
