<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

it('creates all permissions in the database', function () {
    $permissionSeeder = new PermissionSeeder;
    $expectedPermissions = $permissionSeeder->getPermissions();

    foreach ($expectedPermissions as $permissionName) {
        expect(Permission::where('name', $permissionName)->where('guard_name', 'web')->exists())
            ->toBeTrue("Permission '{$permissionName}' should exist");
    }

    expect(Permission::count())->toBe(count($expectedPermissions));
});

it('creates all required roles', function () {
    $expectedRoles = ['super_admin', 'admin', 'cashier', 'warehouse'];

    foreach ($expectedRoles as $roleName) {
        expect(Role::where('name', $roleName)->where('guard_name', 'web')->exists())
            ->toBeTrue("Role '{$roleName}' should exist");
    }
});

it('super_admin role has zero explicit permissions (handled by Gate::before)', function () {
    $superAdmin = Role::findByName('super_admin', 'web');

    expect($superAdmin->permissions->count())->toBe(0);
});

it('super_admin can access all permissions via Gate::before', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');

    $permissionSeeder = new PermissionSeeder;
    $allPermissions = $permissionSeeder->getPermissions();

    // view_activity_logs is intentionally excluded from auto-grant —
    // it must be explicitly assigned to the user
    $excludedPermissions = ['view_activity_logs'];

    foreach ($allPermissions as $permissionName) {
        if (in_array($permissionName, $excludedPermissions)) {
            expect($superAdmin->can($permissionName))
                ->toBeFalse("super_admin should NOT be able to '{$permissionName}' without explicit assignment");

            continue;
        }

        expect($superAdmin->can($permissionName))
            ->toBeTrue("super_admin should be able to '{$permissionName}' via Gate::before");
    }
});

it('assigns correct permissions to admin role', function () {
    $admin = Role::findByName('admin', 'web');
    $permissionSeeder = new PermissionSeeder;
    $expectedPermissions = $permissionSeeder->getRolePermissions()['admin'];

    foreach ($expectedPermissions as $permissionName) {
        expect($admin->hasPermissionTo($permissionName))
            ->toBeTrue("admin should have permission '{$permissionName}'");
    }
});

it('assigns correct permissions to cashier role', function () {
    $cashier = Role::findByName('cashier', 'web');
    $permissionSeeder = new PermissionSeeder;
    $expectedPermissions = $permissionSeeder->getRolePermissions()['cashier'];

    foreach ($expectedPermissions as $permissionName) {
        expect($cashier->hasPermissionTo($permissionName))
            ->toBeTrue("cashier should have permission '{$permissionName}'");
    }
});

it('assigns correct permissions to warehouse role', function () {
    $warehouse = Role::findByName('warehouse', 'web');
    $permissionSeeder = new PermissionSeeder;
    $expectedPermissions = $permissionSeeder->getRolePermissions()['warehouse'];

    foreach ($expectedPermissions as $permissionName) {
        expect($warehouse->hasPermissionTo($permissionName))
            ->toBeTrue("warehouse should have permission '{$permissionName}'");
    }
});

it('allows admin user to access admin permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    expect($admin->can('view_users'))->toBeTrue();
    expect($admin->can('create_users'))->toBeTrue();
    expect($admin->can('view_products'))->toBeTrue();
    expect($admin->can('access_pos'))->toBeTrue();
});

it('denies cashier user from admin-only permissions', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    expect($cashier->can('view_users'))->toBeFalse();
    expect($cashier->can('create_users'))->toBeFalse();
    expect($cashier->can('delete_users'))->toBeFalse();
    expect($cashier->can('view_products'))->toBeFalse();
});

it('allows cashier user to access cashier permissions', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    expect($cashier->can('access_pos'))->toBeTrue();
    expect($cashier->can('manage_pos_cart'))->toBeTrue();
    expect($cashier->can('checkout_pos'))->toBeTrue();
    expect($cashier->can('view_sales'))->toBeTrue();
    expect($cashier->can('open_shift'))->toBeTrue();
    expect($cashier->can('close_shift'))->toBeTrue();
});

it('denies warehouse user from cashier-only permissions', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create();
    $warehouse->syncRoles('warehouse');

    expect($warehouse->can('access_pos'))->toBeFalse();
    expect($warehouse->can('checkout_pos'))->toBeFalse();
    expect($warehouse->can('view_sales'))->toBeFalse();
});

it('allows warehouse user to access warehouse permissions', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create();
    $warehouse->syncRoles('warehouse');

    expect($warehouse->can('view_products'))->toBeTrue();
    expect($warehouse->can('create_products'))->toBeTrue();
    expect($warehouse->can('view_purchases'))->toBeTrue();
    expect($warehouse->can('create_purchases'))->toBeTrue();
    expect($warehouse->can('view_stock_adjustments'))->toBeTrue();
    expect($warehouse->can('create_stock_adjustments'))->toBeTrue();
});

it('super_admin bypasses all policy checks via before()', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');

    // Even without explicit permissions, super_admin passes all gate checks
    expect($superAdmin->can('view_dashboard'))->toBeTrue();
    expect($superAdmin->can('delete_permissions'))->toBeTrue();
    expect($superAdmin->can('force_delete_trash'))->toBeTrue();
    expect($superAdmin->can('view_queue_worker_logs'))->toBeTrue();
    expect($superAdmin->can('nonexistent_permission'))->toBeTrue();
});

it('admin role has all expected permissions count', function () {
    $admin = Role::findByName('admin', 'web');
    $permissionSeeder = new PermissionSeeder;
    $expectedPermissions = $permissionSeeder->getRolePermissions()['admin'];

    expect($admin->permissions->count())->toBe(count($expectedPermissions));
});

it('cashier role has all expected permissions count', function () {
    $cashier = Role::findByName('cashier', 'web');
    $permissionSeeder = new PermissionSeeder;
    $expectedPermissions = $permissionSeeder->getRolePermissions()['cashier'];

    expect($cashier->permissions->count())->toBe(count($expectedPermissions));
});

it('warehouse role has all expected permissions count', function () {
    $warehouse = Role::findByName('warehouse', 'web');
    $permissionSeeder = new PermissionSeeder;
    $expectedPermissions = $permissionSeeder->getRolePermissions()['warehouse'];

    expect($warehouse->permissions->count())->toBe(count($expectedPermissions));
});

it('uses performant seeding pattern', function () {
    // This test verifies the seeder runs without errors
    // using the performance-optimized patterns.
    // Permission::make()->saveOrFail() and $permission->assignRole()
    $seeder = new RoleAndPermissionSeeder;
    $seeder->run();

    expect(Permission::count())->toBeGreaterThan(0);
    expect(Role::count())->toBe(5);
});
