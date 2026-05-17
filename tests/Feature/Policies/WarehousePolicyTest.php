<?php

use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('super_admin', function () {
    it('passes all WarehousePolicy methods via before()', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles('super_admin');
        $warehouse = Warehouse::factory()->create();

        expect($superAdmin->can('viewAny', Warehouse::class))->toBeTrue();
        expect($superAdmin->can('view', $warehouse))->toBeTrue();
        expect($superAdmin->can('create', Warehouse::class))->toBeTrue();
        expect($superAdmin->can('update', $warehouse))->toBeTrue();
        expect($superAdmin->can('delete', $warehouse))->toBeTrue();
    });
});

describe('admin', function () {
    it('passes all WarehousePolicy methods', function () {
        $admin = User::factory()->create();
        $admin->syncRoles('admin');
        $warehouse = Warehouse::factory()->create();

        expect($admin->can('viewAny', Warehouse::class))->toBeTrue();
        expect($admin->can('view', $warehouse))->toBeTrue();
        expect($admin->can('create', Warehouse::class))->toBeTrue();
        expect($admin->can('update', $warehouse))->toBeTrue();
        expect($admin->can('delete', $warehouse))->toBeTrue();
    });
});

describe('warehouse', function () {
    it('passes all WarehousePolicy methods', function () {
        $warehouseUser = User::factory()->create();
        $warehouseUser->syncRoles('warehouse');
        $warehouse = Warehouse::factory()->create();

        expect($warehouseUser->can('viewAny', Warehouse::class))->toBeTrue();
        expect($warehouseUser->can('view', $warehouse))->toBeTrue();
        expect($warehouseUser->can('create', Warehouse::class))->toBeTrue();
        expect($warehouseUser->can('update', $warehouse))->toBeTrue();
        expect($warehouseUser->can('delete', $warehouse))->toBeTrue();
    });
});

describe('cashier', function () {
    it('fails all WarehousePolicy methods', function () {
        $cashier = User::factory()->create();
        $cashier->syncRoles('cashier');
        $warehouse = Warehouse::factory()->create();

        expect($cashier->can('viewAny', Warehouse::class))->toBeFalse();
        expect($cashier->can('view', $warehouse))->toBeFalse();
        expect($cashier->can('create', Warehouse::class))->toBeFalse();
        expect($cashier->can('update', $warehouse))->toBeFalse();
        expect($cashier->can('delete', $warehouse))->toBeFalse();
    });
});
