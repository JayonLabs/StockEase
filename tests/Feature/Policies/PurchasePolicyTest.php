<?php

use App\Models\Purchase;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('super_admin', function () {
    it('passes all PurchasePolicy methods via before()', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles('super_admin');
        $purchase = Purchase::factory()->create();

        expect($superAdmin->can('viewAny', Purchase::class))->toBeTrue();
        expect($superAdmin->can('view', $purchase))->toBeTrue();
        expect($superAdmin->can('create', Purchase::class))->toBeTrue();
        expect($superAdmin->can('update', $purchase))->toBeTrue();
        expect($superAdmin->can('delete', $purchase))->toBeTrue();
    });
});

describe('admin', function () {
    it('passes all PurchasePolicy methods', function () {
        $admin = User::factory()->create();
        $admin->syncRoles('admin');
        $purchase = Purchase::factory()->create();

        expect($admin->can('viewAny', Purchase::class))->toBeTrue();
        expect($admin->can('view', $purchase))->toBeTrue();
        expect($admin->can('create', Purchase::class))->toBeTrue();
        expect($admin->can('update', $purchase))->toBeTrue();
        expect($admin->can('delete', $purchase))->toBeTrue();
    });
});

describe('warehouse', function () {
    it('passes all PurchasePolicy methods', function () {
        $warehouse = User::factory()->create();
        $warehouse->syncRoles('warehouse');
        $purchase = Purchase::factory()->create();

        expect($warehouse->can('viewAny', Purchase::class))->toBeTrue();
        expect($warehouse->can('view', $purchase))->toBeTrue();
        expect($warehouse->can('create', Purchase::class))->toBeTrue();
        expect($warehouse->can('update', $purchase))->toBeTrue();
        expect($warehouse->can('delete', $purchase))->toBeTrue();
    });
});

describe('cashier', function () {
    it('fails all PurchasePolicy methods', function () {
        $cashier = User::factory()->create();
        $cashier->syncRoles('cashier');
        $purchase = Purchase::factory()->create();

        expect($cashier->can('viewAny', Purchase::class))->toBeFalse();
        expect($cashier->can('view', $purchase))->toBeFalse();
        expect($cashier->can('create', Purchase::class))->toBeFalse();
        expect($cashier->can('update', $purchase))->toBeFalse();
        expect($cashier->can('delete', $purchase))->toBeFalse();
    });
});
