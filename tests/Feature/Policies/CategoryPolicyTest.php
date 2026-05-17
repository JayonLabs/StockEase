<?php

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

describe('super_admin', function () {
    it('passes all CategoryPolicy methods via before()', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles('super_admin');
        $category = Category::factory()->create();

        expect($superAdmin->can('viewAny', Category::class))->toBeTrue();
        expect($superAdmin->can('view', $category))->toBeTrue();
        expect($superAdmin->can('create', Category::class))->toBeTrue();
        expect($superAdmin->can('update', $category))->toBeTrue();
        expect($superAdmin->can('delete', $category))->toBeTrue();
    });
});

describe('admin', function () {
    it('passes all CategoryPolicy methods', function () {
        $admin = User::factory()->create();
        $admin->syncRoles('admin');
        $category = Category::factory()->create();

        expect($admin->can('viewAny', Category::class))->toBeTrue();
        expect($admin->can('view', $category))->toBeTrue();
        expect($admin->can('create', Category::class))->toBeTrue();
        expect($admin->can('update', $category))->toBeTrue();
        expect($admin->can('delete', $category))->toBeTrue();
    });
});

describe('warehouse', function () {
    it('passes viewAny and view but fails create, update, delete', function () {
        $warehouse = User::factory()->create();
        $warehouse->syncRoles('warehouse');
        $category = Category::factory()->create();

        expect($warehouse->can('viewAny', Category::class))->toBeTrue();
        expect($warehouse->can('view', $category))->toBeTrue();
        expect($warehouse->can('create', Category::class))->toBeFalse();
        expect($warehouse->can('update', $category))->toBeFalse();
        expect($warehouse->can('delete', $category))->toBeFalse();
    });
});

describe('cashier', function () {
    it('fails all CategoryPolicy methods', function () {
        $cashier = User::factory()->create();
        $cashier->syncRoles('cashier');
        $category = Category::factory()->create();

        expect($cashier->can('viewAny', Category::class))->toBeFalse();
        expect($cashier->can('view', $category))->toBeFalse();
        expect($cashier->can('create', Category::class))->toBeFalse();
        expect($cashier->can('update', $category))->toBeFalse();
        expect($cashier->can('delete', $category))->toBeFalse();
    });
});
