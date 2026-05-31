<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

describe('super_admin', function () {
    it('passes all UserPolicy methods via before()', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles('super_admin');

        expect($superAdmin->can('viewAny', User::class))->toBeTrue();
        expect($superAdmin->can('view', User::factory()->create()))->toBeTrue();
        expect($superAdmin->can('create', User::class))->toBeTrue();
        expect($superAdmin->can('update', User::factory()->create()))->toBeTrue();
        expect($superAdmin->can('delete', User::factory()->create()))->toBeTrue();
        expect($superAdmin->can('resetPassword', User::factory()->create()))->toBeTrue();
    });
});

describe('admin', function () {
    it('passes all UserPolicy methods', function () {
        $admin = User::factory()->create();
        $admin->syncRoles('admin');

        expect($admin->can('viewAny', User::class))->toBeTrue();
        expect($admin->can('view', User::factory()->create()))->toBeTrue();
        expect($admin->can('create', User::class))->toBeTrue();
        expect($admin->can('update', User::factory()->create()))->toBeTrue();
        expect($admin->can('delete', User::factory()->create()))->toBeTrue();
        expect($admin->can('resetPassword', User::factory()->create()))->toBeTrue();
    });
});

describe('cashier', function () {
    it('fails all UserPolicy methods', function () {
        $cashier = User::factory()->create();
        $cashier->syncRoles('cashier');

        expect($cashier->can('viewAny', User::class))->toBeFalse();
        expect($cashier->can('view', User::factory()->create()))->toBeFalse();
        expect($cashier->can('create', User::class))->toBeFalse();
        expect($cashier->can('update', User::factory()->create()))->toBeFalse();
        expect($cashier->can('delete', User::factory()->create()))->toBeFalse();
        expect($cashier->can('resetPassword', User::factory()->create()))->toBeFalse();
    });
});

describe('warehouse', function () {
    it('fails all UserPolicy methods', function () {
        $warehouse = User::factory()->create();
        $warehouse->syncRoles('warehouse');

        expect($warehouse->can('viewAny', User::class))->toBeFalse();
        expect($warehouse->can('view', User::factory()->create()))->toBeFalse();
        expect($warehouse->can('create', User::class))->toBeFalse();
        expect($warehouse->can('update', User::factory()->create()))->toBeFalse();
        expect($warehouse->can('delete', User::factory()->create()))->toBeFalse();
        expect($warehouse->can('resetPassword', User::factory()->create()))->toBeFalse();
    });
});
