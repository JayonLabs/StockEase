<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

it('allows admin to view user permissions list', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    User::factory()->create();

    $response = actingAs($admin)->get(route('user-permissions.index'));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('UserPermission/Index')
            ->has('users.data')
            ->has('permissions')
    );
});

it('includes roles in user permissions list response', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    $response = actingAs($admin)->get(route('user-permissions.index'));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('UserPermission/Index')
            ->has('users.data', 2)
            ->where('users.data.0.roles.0.name', 'admin')
            ->where('users.data.1.roles.0.name', 'cashier')
    );
});

it('paginates user permissions list correctly', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    User::factory()->count(15)->create();

    $response = actingAs($admin)->get(route('user-permissions.index', ['per_page' => 10]));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('UserPermission/Index')
            ->where('users.per_page', 10)
            ->where('users.total', 16) // admin + 15
    );
});

it('redirects unauthenticated users to login', function () {
    get(route('user-permissions.index'))->assertRedirect(route('login'));
});

it('denies cashier to access user permissions list', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    actingAs($cashier)->get(route('user-permissions.index'))->assertForbidden();
});

it('allows admin to update user permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($admin)
        ->put(route('user-permissions.update', $user), [
            'permissions' => ['view_stock_alerts', 'view_notifications'],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Permission user berhasil diperbarui');
    expect($user->fresh()->permissions->pluck('name')->toArray())
        ->toContain('view_stock_alerts', 'view_notifications');
});

it('validates user permission update', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('user-permissions.update', $user), [
            'permissions' => ['non-existing-perm'],
        ])
        ->assertSessionHasErrors(['permissions.0']);
});

it('denies non-admin to update user permissions', function ($roleName) {
    /** @var User $actor */
    $actor = User::factory()->create();
    $actor->syncRoles($roleName);
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($actor)
        ->put(route('user-permissions.update', $user), [
            'permissions' => [],
        ])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

// ============================================
// BEST PRACTICE: Direct Permissions Tests
// ============================================

it('user inherits permissions from role without direct permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    // Admin should have permissions from role, not direct
    expect($admin->permissions)->toBeEmpty();
    expect($admin->roles->first()->permissions)->not->toBeEmpty();
    expect($admin->can('view_users'))->toBeTrue();
    expect($admin->can('create_users'))->toBeTrue();
});

it('user with direct permission can access that permission even without role', function () {
    /** @var User $user */
    $user = new User([
        'name' => 'No Role User',
        'email' => 'norole@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->save();

    // No role assigned
    expect($user->roles)->toBeEmpty();

    // Give direct permission
    $user->givePermissionTo('view_stock_alerts');

    expect($user->can('view_stock_alerts'))->toBeTrue();
    expect($user->can('view_users'))->toBeFalse();
});

it('user can have both role and direct permissions', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->syncRoles('cashier');

    // Cashier has role permissions
    expect($user->can('access_pos'))->toBeTrue();

    // Add direct permission (exception case)
    $user->givePermissionTo('view_users');

    // Should have both
    expect($user->can('access_pos'))->toBeTrue();
    expect($user->can('view_users'))->toBeTrue();
});

it('direct permissions override role limitations', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    // Cashier cannot normally view users
    expect($cashier->can('view_users'))->toBeFalse();

    // Give direct permission override
    $cashier->givePermissionTo('view_users');

    // Now can view users
    expect($cashier->can('view_users'))->toBeTrue();
});

it('syncPermissions replaces all direct permissions', function () {
    /** @var User $user */
    $user = new User([
        'name' => 'Sync Test',
        'email' => 'sync@example.com',
        'password' => bcrypt('password'),
    ]);
    $user->save();

    $user->givePermissionTo('view_stock_alerts');
    $user->givePermissionTo('view_notifications');

    expect($user->permissions)->toHaveCount(2);

    // Replace with different permissions
    $user->syncPermissions(['view_dashboard']);

    expect($user->fresh()->permissions)->toHaveCount(1);
    expect($user->can('view_dashboard'))->toBeTrue();
    expect($user->can('view_stock_alerts'))->toBeFalse();
});

it('super_admin bypasses direct permission checks via Gate::before', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');

    // No direct permissions needed
    expect($superAdmin->permissions)->toBeEmpty();
    expect($superAdmin->can('any_permission_name'))->toBeTrue();
});

it('removing direct permission revokes access but role permissions remain', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $user->syncRoles('cashier');
    $user->givePermissionTo('view_users');

    expect($user->can('access_pos'))->toBeTrue(); // from role
    expect($user->can('view_users'))->toBeTrue(); // direct

    // Remove direct permission
    $user->revokePermissionTo('view_users');

    expect($user->can('access_pos'))->toBeTrue(); // role still works
    expect($user->can('view_users'))->toBeFalse(); // direct gone
});

// ============================================
// EDIT PAGE TESTS
// ============================================

it('allows admin to view edit page for a user', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    /** @var User $user */
    $user = User::factory()->create();
    $user->syncRoles('cashier');

    $response = actingAs($admin)->get(route('user-permissions.edit', $user));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('UserPermission/Edit')
            ->where('user.id', $user->id)
            ->where('user.name', $user->name)
            ->has('permissions')
    );
});

it('edit page shows user roles', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    /** @var User $user */
    $user = User::factory()->create();
    $user->syncRoles('cashier');

    $response = actingAs($admin)->get(route('user-permissions.edit', $user));

    $response->assertInertia(
        fn ($page) => $page
            ->has('user.roles')
            ->where('user.roles.0.name', 'cashier')
    );
});

it('edit page shows existing direct permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    /** @var User $user */
    $user = User::factory()->create();
    $user->givePermissionTo('view_stock_alerts');
    $user->givePermissionTo('view_notifications');

    $response = actingAs($admin)->get(route('user-permissions.edit', $user));

    $response->assertInertia(
        fn ($page) => $page
            ->has('user.permissions', 2)
    );
});

it('edit page includes all available permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($admin)->get(route('user-permissions.edit', $user));

    $response->assertInertia(
        fn ($page) => $page
            ->has('permissions')
    );

    $totalPermissions = Permission::count();
    expect($response->inertiaProps()['permissions'])->toHaveCount($totalPermissions);
});

it('redirects unauthenticated users from edit page', function () {
    /** @var User $user */
    $user = User::factory()->create();

    get(route('user-permissions.edit', $user))
        ->assertRedirect(route('login'));
});

it('denies cashier to access edit page', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($cashier)
        ->get(route('user-permissions.edit', $user))
        ->assertForbidden();
});

it('denies warehouse to access edit page', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create();
    $warehouse->syncRoles('warehouse');
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($warehouse)
        ->get(route('user-permissions.edit', $user))
        ->assertForbidden();
});

it('allows super_admin to access edit page', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($superAdmin)
        ->get(route('user-permissions.edit', $user))
        ->assertSuccessful();
});

it('update via edit page syncs permissions and redirects back', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    /** @var User $user */
    $user = User::factory()->create();
    $user->givePermissionTo('view_dashboard');

    $response = actingAs($admin)
        ->from(route('user-permissions.edit', $user))
        ->put(route('user-permissions.update', $user), [
            'permissions' => ['view_stock_alerts', 'view_notifications'],
        ]);

    $response->assertRedirect(route('user-permissions.edit', $user));
    $response->assertSessionHas('success', 'Permission user berhasil diperbarui');

    $updatedPermissions = $user->fresh()->permissions->pluck('name')->toArray();
    expect($updatedPermissions)->toContain('view_stock_alerts');
    expect($updatedPermissions)->toContain('view_notifications');
    expect($updatedPermissions)->not->toContain('view_dashboard');
});

it('clearing all permissions from edit page updates correctly', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');
    /** @var User $user */
    $user = User::factory()->create();
    $user->givePermissionTo('view_dashboard');
    $user->givePermissionTo('view_stock_alerts');

    expect($user->permissions)->toHaveCount(2);

    actingAs($admin)
        ->from(route('user-permissions.edit', $user))
        ->put(route('user-permissions.update', $user), [
            'permissions' => [],
        ])
        ->assertRedirect();

    expect($user->fresh()->permissions)->toBeEmpty();
});
