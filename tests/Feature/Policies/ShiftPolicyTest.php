<?php

use App\Models\Shift;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);
});

describe('super_admin', function () {
    it('passes all ShiftPolicy methods via before()', function () {
        $superAdmin = User::factory()->create();
        $superAdmin->syncRoles('super_admin');
        $shift = Shift::factory()->create();

        expect($superAdmin->can('viewAny', Shift::class))->toBeTrue();
        expect($superAdmin->can('view', $shift))->toBeTrue();
        expect($superAdmin->can('create', Shift::class))->toBeTrue();
        expect($superAdmin->can('close', $shift))->toBeTrue();
    });
});

describe('admin', function () {
    it('passes all ShiftPolicy methods', function () {
        $admin = User::factory()->create();
        $admin->syncRoles('admin');
        $shift = Shift::factory()->create();

        expect($admin->can('viewAny', Shift::class))->toBeTrue();
        expect($admin->can('view', $shift))->toBeTrue();
        expect($admin->can('create', Shift::class))->toBeTrue();
        expect($admin->can('close', $shift))->toBeTrue();
    });
});

describe('cashier', function () {
    it('can close their own shift but not another cashier\'s shift', function () {
        $cashier = User::factory()->create();
        $cashier->syncRoles('cashier');
        $ownShift = Shift::factory()->create(['user_id' => $cashier->id]);
        $otherShift = Shift::factory()->create();

        expect($cashier->can('viewAny', Shift::class))->toBeTrue();
        expect($cashier->can('view', $ownShift))->toBeTrue();
        expect($cashier->can('create', Shift::class))->toBeTrue();
        expect($cashier->can('close', $ownShift))->toBeTrue();
        expect($cashier->can('close', $otherShift))->toBeFalse();
    });
});

describe('warehouse', function () {
    it('fails all ShiftPolicy methods', function () {
        $warehouse = User::factory()->create();
        $warehouse->syncRoles('warehouse');
        $shift = Shift::factory()->create();

        expect($warehouse->can('viewAny', Shift::class))->toBeFalse();
        expect($warehouse->can('view', $shift))->toBeFalse();
        expect($warehouse->can('create', Shift::class))->toBeFalse();
        expect($warehouse->can('close', $shift))->toBeFalse();
    });
});
