<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

describe('super_admin', function () {
    it('passes all ProductPolicy methods via before()', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles('super_admin');
        $product = Product::factory()->create();

        expect($superAdmin->can('viewAny', Product::class))->toBeTrue();
        expect($superAdmin->can('view', $product))->toBeTrue();
        expect($superAdmin->can('create', Product::class))->toBeTrue();
        expect($superAdmin->can('update', $product))->toBeTrue();
        expect($superAdmin->can('delete', $product))->toBeTrue();
        expect($superAdmin->can('editPrice', $product))->toBeTrue();
    });
});

describe('admin', function () {
    it('passes all ProductPolicy methods including editPrice', function () {
        $admin = User::factory()->create();
        $admin->syncRoles('admin');
        $product = Product::factory()->create();

        expect($admin->can('viewAny', Product::class))->toBeTrue();
        expect($admin->can('view', $product))->toBeTrue();
        expect($admin->can('create', Product::class))->toBeTrue();
        expect($admin->can('update', $product))->toBeTrue();
        expect($admin->can('delete', $product))->toBeTrue();
        expect($admin->can('editPrice', $product))->toBeTrue();
    });
});

describe('warehouse', function () {
    it('passes view, create, update, delete but fails editPrice', function () {
        $warehouse = User::factory()->create();
        $warehouse->syncRoles('warehouse');
        $product = Product::factory()->create();

        expect($warehouse->can('viewAny', Product::class))->toBeTrue();
        expect($warehouse->can('view', $product))->toBeTrue();
        expect($warehouse->can('create', Product::class))->toBeTrue();
        expect($warehouse->can('update', $product))->toBeTrue();
        expect($warehouse->can('delete', $product))->toBeTrue();
        expect($warehouse->can('editPrice', $product))->toBeFalse();
    });
});

describe('cashier', function () {
    it('fails all ProductPolicy methods', function () {
        $cashier = User::factory()->create();
        $cashier->syncRoles('cashier');
        $product = Product::factory()->create();

        expect($cashier->can('viewAny', Product::class))->toBeFalse();
        expect($cashier->can('view', $product))->toBeFalse();
        expect($cashier->can('create', Product::class))->toBeFalse();
        expect($cashier->can('update', $product))->toBeFalse();
        expect($cashier->can('delete', $product))->toBeFalse();
        expect($cashier->can('editPrice', $product))->toBeFalse();
    });
});
