<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

describe('super_admin', function () {
    it('passes all SupplierPolicy methods via before()', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles('super_admin');
        $supplier = Supplier::factory()->create();

        expect($superAdmin->can('viewAny', Supplier::class))->toBeTrue();
        expect($superAdmin->can('view', $supplier))->toBeTrue();
        expect($superAdmin->can('create', Supplier::class))->toBeTrue();
        expect($superAdmin->can('update', $supplier))->toBeTrue();
        expect($superAdmin->can('delete', $supplier))->toBeTrue();
    });
});

describe('admin', function () {
    it('passes all SupplierPolicy methods', function () {
        $admin = User::factory()->create();
        $admin->syncRoles('admin');
        $supplier = Supplier::factory()->create();

        expect($admin->can('viewAny', Supplier::class))->toBeTrue();
        expect($admin->can('view', $supplier))->toBeTrue();
        expect($admin->can('create', Supplier::class))->toBeTrue();
        expect($admin->can('update', $supplier))->toBeTrue();
        expect($admin->can('delete', $supplier))->toBeTrue();
    });
});

describe('warehouse', function () {
    it('passes all SupplierPolicy methods', function () {
        $warehouse = User::factory()->create();
        $warehouse->syncRoles('warehouse');
        $supplier = Supplier::factory()->create();

        expect($warehouse->can('viewAny', Supplier::class))->toBeTrue();
        expect($warehouse->can('view', $supplier))->toBeTrue();
        expect($warehouse->can('create', Supplier::class))->toBeTrue();
        expect($warehouse->can('update', $supplier))->toBeTrue();
        expect($warehouse->can('delete', $supplier))->toBeTrue();
    });
});

describe('cashier', function () {
    it('fails all SupplierPolicy methods', function () {
        $cashier = User::factory()->create();
        $cashier->syncRoles('cashier');
        $supplier = Supplier::factory()->create();

        expect($cashier->can('viewAny', Supplier::class))->toBeFalse();
        expect($cashier->can('view', $supplier))->toBeFalse();
        expect($cashier->can('create', Supplier::class))->toBeFalse();
        expect($cashier->can('update', $supplier))->toBeFalse();
        expect($cashier->can('delete', $supplier))->toBeFalse();
    });
});
