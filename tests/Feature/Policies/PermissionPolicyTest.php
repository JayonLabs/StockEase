<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

describe('super_admin', function () {
    it('passes all PermissionPolicy methods via before()', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles('super_admin');
        $permission = Permission::first();

        expect($superAdmin->can('viewAny', Permission::class))->toBeTrue();
        expect($superAdmin->can('view', $permission))->toBeTrue();
        expect($superAdmin->can('create', Permission::class))->toBeTrue();
        expect($superAdmin->can('update', $permission))->toBeTrue();
        expect($superAdmin->can('delete', $permission))->toBeTrue();
    });
});

describe('admin', function () {
    it('passes all PermissionPolicy methods', function () {
        $admin = User::factory()->create();
        $admin->syncRoles('admin');
        $permission = Permission::first();

        expect($admin->can('viewAny', Permission::class))->toBeTrue();
        expect($admin->can('view', $permission))->toBeTrue();
        expect($admin->can('create', Permission::class))->toBeTrue();
        expect($admin->can('update', $permission))->toBeTrue();
        expect($admin->can('delete', $permission))->toBeTrue();
    });
});

describe('cashier', function () {
    it('fails all PermissionPolicy methods', function () {
        $cashier = User::factory()->create();
        $cashier->syncRoles('cashier');
        $permission = Permission::first();

        expect($cashier->can('viewAny', Permission::class))->toBeFalse();
        expect($cashier->can('view', $permission))->toBeFalse();
        expect($cashier->can('create', Permission::class))->toBeFalse();
        expect($cashier->can('update', $permission))->toBeFalse();
        expect($cashier->can('delete', $permission))->toBeFalse();
    });
});

describe('warehouse', function () {
    it('fails all PermissionPolicy methods', function () {
        $warehouse = User::factory()->create();
        $warehouse->syncRoles('warehouse');
        $permission = Permission::first();

        expect($warehouse->can('viewAny', Permission::class))->toBeFalse();
        expect($warehouse->can('view', $permission))->toBeFalse();
        expect($warehouse->can('create', Permission::class))->toBeFalse();
        expect($warehouse->can('update', $permission))->toBeFalse();
        expect($warehouse->can('delete', $permission))->toBeFalse();
    });
});
