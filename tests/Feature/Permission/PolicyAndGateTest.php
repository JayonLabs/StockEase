<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

// ============================================
// SUPER ADMIN GATE::BEFORE TESTS
// ============================================

it('grants super_admin access to any permission via Gate::before', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');

    expect($superAdmin->can('view_users'))->toBeTrue();
    expect($superAdmin->can('create_products'))->toBeTrue();
    expect($superAdmin->can('delete_permissions'))->toBeTrue();
    expect($superAdmin->can('nonexistent_permission'))->toBeTrue();
});

it('grants super_admin access via Gate facade forUser', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');

    $gate = Gate::forUser($superAdmin);

    expect($gate->allows('view_dashboard'))->toBeTrue();
    expect($gate->allows('force_delete_trash'))->toBeTrue();
});

it('denies non-super_admin access to permissions they do not have', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    expect($cashier->can('view_users'))->toBeFalse();
    expect($cashier->can('delete_products'))->toBeFalse();
});

it('allows non-super_admin access to their assigned permissions', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    expect($cashier->can('access_pos'))->toBeTrue();
    expect($cashier->can('checkout_pos'))->toBeTrue();
});

// ============================================
// POLICY TESTS
// ============================================

it('allows super_admin to pass UserPolicy checks without explicit permissions', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');

    expect($superAdmin->can('viewAny', User::class))->toBeTrue();
    expect($superAdmin->can('create', User::class))->toBeTrue();
    expect($superAdmin->can('update', User::factory()->create()))->toBeTrue();
    expect($superAdmin->can('delete', User::factory()->create()))->toBeTrue();
    expect($superAdmin->can('resetPassword', User::factory()->create()))->toBeTrue();
});

it('allows admin to pass UserPolicy via permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    expect($admin->can('viewAny', User::class))->toBeTrue();
    expect($admin->can('create', User::class))->toBeTrue();
    expect($admin->can('update', User::factory()->create()))->toBeTrue();
    expect($admin->can('delete', User::factory()->create()))->toBeTrue();
    expect($admin->can('resetPassword', User::factory()->create()))->toBeTrue();
});

it('denies cashier from UserPolicy checks', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    expect($cashier->can('viewAny', User::class))->toBeFalse();
    expect($cashier->can('create', User::class))->toBeFalse();
    expect($cashier->can('update', User::factory()->create()))->toBeFalse();
    expect($cashier->can('delete', User::factory()->create()))->toBeFalse();
});

it('allows super_admin to pass ProductPolicy checks', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');

    $product = Product::factory()->create();

    expect($superAdmin->can('viewAny', Product::class))->toBeTrue();
    expect($superAdmin->can('create', Product::class))->toBeTrue();
    expect($superAdmin->can('update', $product))->toBeTrue();
    expect($superAdmin->can('delete', $product))->toBeTrue();
    expect($superAdmin->can('editPrice', $product))->toBeTrue();
});

it('allows warehouse to pass ProductPolicy for view/create/edit/delete', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create();
    $warehouse->syncRoles('warehouse');

    $product = Product::factory()->create();

    expect($warehouse->can('viewAny', Product::class))->toBeTrue();
    expect($warehouse->can('create', Product::class))->toBeTrue();
    expect($warehouse->can('update', $product))->toBeTrue();
    expect($warehouse->can('delete', $product))->toBeTrue();
});

it('denies warehouse from editPrice in ProductPolicy', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create();
    $warehouse->syncRoles('warehouse');

    $product = Product::factory()->create();

    expect($warehouse->can('editPrice', $product))->toBeFalse();
});

it('denies cashier from ProductPolicy checks', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    $product = Product::factory()->create();

    expect($cashier->can('viewAny', Product::class))->toBeFalse();
    expect($cashier->can('create', Product::class))->toBeFalse();
    expect($cashier->can('update', $product))->toBeFalse();
});

it('allows super_admin to pass CategoryPolicy checks', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');

    $category = Category::factory()->create();

    expect($superAdmin->can('viewAny', Category::class))->toBeTrue();
    expect($superAdmin->can('create', Category::class))->toBeTrue();
    expect($superAdmin->can('update', $category))->toBeTrue();
    expect($superAdmin->can('delete', $category))->toBeTrue();
});

it('allows admin to pass CategoryPolicy via permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    $category = Category::factory()->create();

    expect($admin->can('viewAny', Category::class))->toBeTrue();
    expect($admin->can('create', Category::class))->toBeTrue();
    expect($admin->can('update', $category))->toBeTrue();
    expect($admin->can('delete', $category))->toBeTrue();
});

it('denies warehouse from CategoryPolicy create/update/delete', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create();
    $warehouse->syncRoles('warehouse');

    $category = Category::factory()->create();

    // warehouse only has 'view_categories', not create/edit/delete
    expect($warehouse->can('viewAny', Category::class))->toBeTrue();
    expect($warehouse->can('create', Category::class))->toBeFalse();
    expect($warehouse->can('update', $category))->toBeFalse();
    expect($warehouse->can('delete', $category))->toBeFalse();
});

it('allows super_admin to pass PermissionPolicy checks', function () {
    /** @var User $superAdmin */
    $superAdmin = User::factory()->create();
    $superAdmin->syncRoles('super_admin');

    expect($superAdmin->can('viewAny', Permission::class))->toBeTrue();
    expect($superAdmin->can('create', Permission::class))->toBeTrue();
    expect($superAdmin->can('update', Permission::first()))->toBeTrue();
    expect($superAdmin->can('delete', Permission::first()))->toBeTrue();
});

it('allows admin to pass PermissionPolicy via permissions', function () {
    /** @var User $admin */
    $admin = User::factory()->create();
    $admin->syncRoles('admin');

    expect($admin->can('viewAny', Permission::class))->toBeTrue();
    expect($admin->can('create', Permission::class))->toBeTrue();
    expect($admin->can('update', Permission::first()))->toBeTrue();
    expect($admin->can('delete', Permission::first()))->toBeTrue();
});

it('denies cashier from PermissionPolicy checks', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create();
    $cashier->syncRoles('cashier');

    expect($cashier->can('viewAny', Permission::class))->toBeFalse();
    expect($cashier->can('create', Permission::class))->toBeFalse();
});
