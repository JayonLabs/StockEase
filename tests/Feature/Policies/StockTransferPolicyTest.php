<?php

use App\Models\StockTransfer;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

describe('super_admin', function () {
    it('passes all StockTransferPolicy methods via before()', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles('super_admin');
        $stockTransfer = StockTransfer::factory()->create();

        expect($superAdmin->can('viewAny', StockTransfer::class))->toBeTrue();
        expect($superAdmin->can('view', $stockTransfer))->toBeTrue();
        expect($superAdmin->can('create', StockTransfer::class))->toBeTrue();
    });
});

describe('admin', function () {
    it('passes all StockTransferPolicy methods', function () {
        $admin = User::factory()->create();
        $admin->syncRoles('admin');
        $stockTransfer = StockTransfer::factory()->create();

        expect($admin->can('viewAny', StockTransfer::class))->toBeTrue();
        expect($admin->can('view', $stockTransfer))->toBeTrue();
        expect($admin->can('create', StockTransfer::class))->toBeTrue();
    });
});

describe('warehouse', function () {
    it('passes all StockTransferPolicy methods', function () {
        $warehouseUser = User::factory()->create();
        $warehouseUser->syncRoles('warehouse');
        $stockTransfer = StockTransfer::factory()->create();

        expect($warehouseUser->can('viewAny', StockTransfer::class))->toBeTrue();
        expect($warehouseUser->can('view', $stockTransfer))->toBeTrue();
        expect($warehouseUser->can('create', StockTransfer::class))->toBeTrue();
    });
});

describe('cashier', function () {
    it('fails all StockTransferPolicy methods', function () {
        $cashier = User::factory()->create();
        $cashier->syncRoles('cashier');
        $stockTransfer = StockTransfer::factory()->create();

        expect($cashier->can('viewAny', StockTransfer::class))->toBeFalse();
        expect($cashier->can('view', $stockTransfer))->toBeFalse();
        expect($cashier->can('create', StockTransfer::class))->toBeFalse();
    });
});
