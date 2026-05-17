<?php

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

describe('SeedProduction Command', function () {
    it('creates all permissions when run on empty database', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $permissions = Permission::all();

        expect($permissions)->not->toBeEmpty();
        expect($permissions->count())->toBeGreaterThan(80);
    });

    it('creates all four roles', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $roles = Role::all();

        expect($roles)->toHaveCount(4);
        expect($roles->pluck('name')->toArray())
            ->toEqualCanonicalizing(['super_admin', 'admin', 'cashier', 'warehouse']);
    });

    it('assigns permissions to admin role', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $adminRole = Role::findByName('admin', 'web');

        expect($adminRole->permissions)->not->toBeEmpty();
        expect($adminRole->hasPermissionTo('view_users'))->toBeTrue();
        expect($adminRole->hasPermissionTo('view_products'))->toBeTrue();
        expect($adminRole->hasPermissionTo('access_pos'))->toBeTrue();
    });

    it('assigns permissions to cashier role', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $cashierRole = Role::findByName('cashier', 'web');

        expect($cashierRole->permissions)->not->toBeEmpty();
        expect($cashierRole->hasPermissionTo('access_pos'))->toBeTrue();
        expect($cashierRole->hasPermissionTo('checkout_pos'))->toBeTrue();
    });

    it('assigns permissions to warehouse role', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $warehouseRole = Role::findByName('warehouse', 'web');

        expect($warehouseRole->permissions)->not->toBeEmpty();
        expect($warehouseRole->hasPermissionTo('view_products'))->toBeTrue();
        expect($warehouseRole->hasPermissionTo('create_products'))->toBeTrue();
    });

    it('super_admin role has no explicit permissions', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $superAdminRole = Role::findByName('super_admin', 'web');

        expect($superAdminRole->permissions)->toBeEmpty();
    });

    it('creates the admin user if not exists', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $user = User::where('email', 'dewajayon3@gmail.com')->first();

        expect($user)->not->toBeNull();
        expect($user->name)->toBe('Dewa Jayon');
    });

    it('assigns super_admin role to the admin user', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $user = User::where('email', 'dewajayon3@gmail.com')->first();

        expect($user->hasRole(RoleEnum::SuperAdmin->value))->toBeTrue();
    });

    it('assigns all permissions directly to the admin user', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $user = User::where('email', 'dewajayon3@gmail.com')->first();
        $totalPermissions = Permission::count();

        expect($user->permissions)->toHaveCount($totalPermissions);
        expect($user->can('view_activity_logs'))->toBeTrue();
        expect($user->can('view_queue_worker_logs'))->toBeTrue();
        expect($user->can('view_dashboard'))->toBeTrue();
    });

    it('does not duplicate permissions when run multiple times', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $firstCount = Permission::count();

        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        expect(Permission::count())->toBe($firstCount);
    });

    it('does not duplicate roles when run multiple times', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $firstCount = Role::count();

        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        expect(Role::count())->toBe($firstCount);
    });

    it('updates existing admin user without creating duplicate', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $firstCount = User::count();

        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        expect(User::count())->toBe($firstCount);
        expect(User::where('email', 'dewajayon3@gmail.com')->count())->toBe(1);
    });

    it('re-syncs permissions for existing admin user', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $user = User::where('email', 'dewajayon3@gmail.com')->first();

        // Manually remove a permission to test re-sync
        $perm = $user->permissions->first();
        $user->revokePermissionTo($perm);

        expect($user->fresh()->permissions->count())->toBe(Permission::count() - 1);

        // Run again — should re-sync all permissions
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        expect($user->fresh()->permissions)->toHaveCount(Permission::count());
    });

    it('view_activity_logs permission exists after seed', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $perm = Permission::where('name', 'view_activity_logs')->where('guard_name', 'web')->first();

        expect($perm)->not->toBeNull();
    });

    it('view_queue_worker_logs permission exists after seed', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $perm = Permission::where('name', 'view_queue_worker_logs')->where('guard_name', 'web')->first();

        expect($perm)->not->toBeNull();
    });

    it('view_activity_logs is NOT assigned to any role', function () {
        artisan('stockease:seed-production --force')
            ->assertSuccessful();

        $roles = Role::all();

        foreach ($roles as $role) {
            expect($role->hasPermissionTo('view_activity_logs'))
                ->toBeFalse("Role '{$role->name}' should NOT have view_activity_logs");
        }
    });
});
