<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('allows admin to view role permissions list', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $response = actingAs($admin)->get(route('role-permissions.index'));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('RolePermission/Index')
            ->has('roles')
            ->has('permissions')
    );
});

it('redirects unauthenticated users to login', function () {
    get(route('role-permissions.index'))->assertRedirect(route('login'));
});

it('denies cashier to access role permissions list', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    actingAs($cashier)->get(route('role-permissions.index'))->assertForbidden();
});

it('allows admin to update role permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $role = Role::findByName('cashier');

    $response = actingAs($admin)
        ->put(route('role-permissions.update', $role), [
            'permissions' => ['view_stock_alerts', 'view_notifications'],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Permission role berhasil diperbarui');
    expect($role->fresh()->permissions->pluck('name')->toArray())
        ->toContain('view_stock_alerts', 'view_notifications');
});

it('validates role permission update', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $role = Role::findByName('cashier');

    actingAs($admin)
        ->put(route('role-permissions.update', $role), [
            'permissions' => ['non-existing-perm'],
        ])
        ->assertSessionHasErrors(['permissions.0']);
});

it('denies non-admin to update role permissions', function ($roleName) {
    /** @var User $user */
    $user = User::factory()->create();
    $user->syncRoles($roleName);
    $role = Role::findByName('cashier');

    actingAs($user)
        ->put(route('role-permissions.update', $role), [
            'permissions' => [],
        ])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

// ============================================
// EDIT PAGE TESTS
// ============================================

it('allows admin to view edit page for a role', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $role = Role::findByName('cashier');

    $response = actingAs($admin)->get(route('role-permissions.edit', $role));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('RolePermission/Edit')
            ->where('role.id', $role->id)
            ->where('role.name', $role->name)
            ->has('permissions')
    );
});

it('edit page shows existing role permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $role = Role::findByName('admin');

    $response = actingAs($admin)->get(route('role-permissions.edit', $role));

    $response->assertInertia(
        fn ($page) => $page
            ->has('role.permissions')
    );

    expect(count($response->inertiaProps()['role']['permissions']))->toBeGreaterThan(0);
});

it('edit page includes all available permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $role = Role::findByName('cashier');

    $response = actingAs($admin)->get(route('role-permissions.edit', $role));

    $totalPermissions = Permission::count();
    expect($response->inertiaProps()['permissions'])->toHaveCount($totalPermissions);
});

it('redirects unauthenticated users from edit page', function () {
    $role = Role::findByName('admin');

    get(route('role-permissions.edit', $role))
        ->assertRedirect(route('login'));
});

it('denies cashier to access edit page', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');
    $role = Role::findByName('cashier');

    actingAs($cashier)
        ->get(route('role-permissions.edit', $role))
        ->assertForbidden();
});

it('denies warehouse to access edit page', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create();
    $warehouse->syncRoles('warehouse');
    $role = Role::findByName('admin');

    actingAs($warehouse)
        ->get(route('role-permissions.edit', $role))
        ->assertForbidden();
});

it('allows super_admin to access edit page', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');
    $role = Role::findByName('cashier');

    actingAs($superAdmin)
        ->get(route('role-permissions.edit', $role))
        ->assertSuccessful();
});

it('update via edit page syncs permissions and redirects back', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $role = Role::findByName('cashier');

    $response = actingAs($admin)
        ->from(route('role-permissions.edit', $role))
        ->put(route('role-permissions.update', $role), [
            'permissions' => ['view_dashboard', 'access_pos'],
        ]);

    $response->assertRedirect(route('role-permissions.edit', $role));
    $response->assertSessionHas('success', 'Permission role berhasil diperbarui');
    expect($role->fresh()->permissions->pluck('name')->toArray())
        ->toContain('view_dashboard', 'access_pos');
});

it('clearing all permissions from edit page updates correctly', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    $role = Role::findByName('admin');
    expect($role->permissions)->not->toBeEmpty();

    actingAs($admin)
        ->from(route('role-permissions.edit', $role))
        ->put(route('role-permissions.update', $role), [
            'permissions' => [],
        ])
        ->assertRedirect();

    expect($role->fresh()->permissions)->toBeEmpty();
});
