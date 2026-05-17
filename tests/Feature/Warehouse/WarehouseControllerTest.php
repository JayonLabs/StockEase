<?php

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

// -- AUTHORIZATION --

it('denies unauthenticated users to access warehouses', function () {
    get(route('warehouse.index'))->assertRedirect(route('login'));
});

it('denies non-admin users to access warehouses', function (string $role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);

    actingAs($user)->get(route('warehouse.index'))->assertForbidden();
})->with(['cashier']);

// -- INDEX --

it('allows admin to view warehouses', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Warehouse::factory()->count(3)->create();

    actingAs($admin)
        ->get(route('warehouse.index'))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Warehouse/Index')
                ->has('warehouses.data', 3)
        );
});

it('allows warehouse role to view warehouses', function () {
    /** @var User $warehouseUser */
    $warehouseUser = User::factory()->create(['role' => 'warehouse']);
    Warehouse::factory()->count(3)->create();

    actingAs($warehouseUser)
        ->get(route('warehouse.index'))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Warehouse/Index')
                ->has('warehouses.data', 3)
        );
});

it('can filter warehouses by search term', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Warehouse::factory()->create(['name' => 'Gudang Pusat']);
    Warehouse::factory()->create(['name' => 'Toko A']);

    actingAs($admin)
        ->get(route('warehouse.index', ['search' => 'Pusat']))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Warehouse/Index')
                ->has('warehouses.data', 1)
                ->where('warehouses.data.0.name', 'Gudang Pusat')
        );
});

it('can paginate warehouses', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Warehouse::factory()->count(15)->create();

    actingAs($admin)
        ->get(route('warehouse.index', ['per_page' => 5]))
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('Warehouse/Index')
                ->has('warehouses.data', 5)
                ->where('warehouses.per_page', 5)
        );
});

// -- STORE --

it('allows admin to create a warehouse', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->post(route('warehouse.store'), [
            'name' => 'Gudang Baru',
            'address' => 'Jl. Baru No. 1',
            'phone' => '021-9999999',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Gudang berhasil ditambahkan');

    assertDatabaseHas('warehouses', [
        'name' => 'Gudang Baru',
        'slug' => 'gudang-baru',
        'address' => 'Jl. Baru No. 1',
        'phone' => '021-9999999',
        'is_active' => true,
    ]);
});

it('allows creating warehouse without optional fields', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->post(route('warehouse.store'), [
            'name' => 'Gudang Minimal',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Gudang berhasil ditambahkan');

    assertDatabaseHas('warehouses', [
        'name' => 'Gudang Minimal',
        'slug' => 'gudang-minimal',
        'is_active' => true,
    ]);
});

it('validates warehouse creation', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->post(route('warehouse.store'), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'empty name' => [['name' => ''], ['name']],
    'name too long' => [['name' => str_repeat('a', 256)], ['name']],
]);

// -- UPDATE --

it('allows admin to update a warehouse name and slug', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create(['name' => 'Gudang Lama', 'slug' => 'gudang-lama']);

    actingAs($admin)
        ->put(route('warehouse.update', $warehouse), [
            'name' => 'Gudang Baru',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Gudang berhasil diupdate');

    assertDatabaseHas('warehouses', [
        'id' => $warehouse->id,
        'name' => 'Gudang Baru',
        'slug' => 'gudang-baru',
    ]);
});

it('does not change slug if warehouse name is not changed on update', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create(['name' => 'Same Name', 'slug' => 'same-name-123']);

    actingAs($admin)
        ->put(route('warehouse.update', $warehouse), [
            'name' => 'Same Name',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Gudang berhasil diupdate');

    assertDatabaseHas('warehouses', [
        'id' => $warehouse->id,
        'name' => 'Same Name',
        'slug' => 'same-name-123',
    ]);
});

it('validates warehouse update', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();

    actingAs($admin)
        ->put(route('warehouse.update', $warehouse), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'empty name' => [['name' => ''], ['name']],
    'name too long' => [['name' => str_repeat('a', 256)], ['name']],
]);

// -- DESTROY --

it('allows admin to delete a warehouse', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();

    actingAs($admin)
        ->delete(route('warehouse.destroy', $warehouse))
        ->assertRedirect()
        ->assertSessionHas('success', 'Gudang berhasil dihapus');

    assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
});
