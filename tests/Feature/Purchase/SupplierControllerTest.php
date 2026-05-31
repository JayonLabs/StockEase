<?php

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

// --- INDEX ---

it('allows admin and warehouse roles to view suppliers', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    Supplier::factory()->count(3)->create();

    $response = actingAs($user)->get(route('supplier.index'));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Supplier/Index')
            ->has('suppliers.data', 3)
    );
})->with(['admin', 'warehouse']);

it('denies cashier to view suppliers', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $response = actingAs($cashier)->get(route('supplier.index'));
    $response->assertForbidden();
});

it('redirects unauthenticated users to login', function () {
    get(route('supplier.index'))->assertRedirect(route('login'));
});

it('filters suppliers by search query', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Supplier::factory()->create(['name' => 'Toko Maju']);
    Supplier::factory()->create(['name' => 'Warung Sejahtera']);

    actingAs($admin)
        ->get(route('supplier.index', ['search' => 'Maju']))
        ->assertInertia(
            fn ($page) => $page
                ->component('Supplier/Index')
                ->has('suppliers.data', 1)
                ->where('suppliers.data.0.name', 'Toko Maju')
        );
});

it('paginates suppliers with custom per_page', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Supplier::factory()->count(5)->create();

    actingAs($admin)
        ->get(route('supplier.index', ['per_page' => 3]))
        ->assertInertia(
            fn ($page) => $page
                ->component('Supplier/Index')
                ->where('suppliers.per_page', 3)
                ->where('suppliers.total', 5)
        );
});

// --- STORE ---

it('allows admin and warehouse to create a supplier', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);

    actingAs($user)
        ->post(route('supplier.store'), [
            'name' => 'New Supplier',
            'phone' => '08123456789',
            'address' => 'Supplier Address',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Supplier berhasil ditambahkan');

    assertDatabaseHas('suppliers', [
        'name' => 'New Supplier',
        'phone' => '08123456789',
        'slug' => 'new-supplier',
    ]);
})->with(['admin', 'warehouse']);

it('validates supplier creation', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    $response = actingAs($admin)
        ->post(route('supplier.store'), $data);

    $response->assertSessionHasErrors($errors);
})->with([
    'empty name' => [['name' => '', 'phone' => '123', 'address' => 'addr'], ['name']],
    'invalid phone' => [['name' => 'Name', 'phone' => 'abc', 'address' => 'addr'], ['phone']],
    'empty address' => [['name' => 'Name', 'phone' => '123', 'address' => ''], ['address']],
]);

it('denies cashier to create a supplier', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);

    actingAs($cashier)
        ->post(route('supplier.store'), ['name' => 'Test', 'phone' => '123', 'address' => 'Test'])
        ->assertForbidden();
});

// --- UPDATE ---

it('allows admin and warehouse to update a supplier', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $supplier = Supplier::factory()->create(['name' => 'Old Name']);

    actingAs($user)
        ->put(route('supplier.update', $supplier), [
            'name' => 'Updated Name',
            'phone' => '08987654321',
            'address' => 'Updated Address',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Supplier berhasil diubah');

    assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'name' => 'Updated Name',
        'slug' => 'updated-name',
    ]);
})->with(['admin', 'warehouse']);

it('does not regenerate slug when supplier name is unchanged', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = Supplier::factory()->create(['name' => 'Same Name']);
    $originalSlug = $supplier->slug;

    actingAs($admin)->put(route('supplier.update', $supplier), [
        'name' => 'Same Name',
        'phone' => '08111111111',
        'address' => 'New Address',
    ]);

    assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'slug' => $originalSlug,
    ]);
});

it('validates supplier update', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = Supplier::factory()->create();

    actingAs($admin)
        ->put(route('supplier.update', $supplier), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'empty name' => [['name' => '', 'phone' => '123', 'address' => 'addr'], ['name']],
    'invalid phone' => [['name' => 'Name', 'phone' => 'abc', 'address' => 'addr'], ['phone']],
    'empty address' => [['name' => 'Name', 'phone' => '123', 'address' => ''], ['address']],
]);

it('denies cashier to update a supplier', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $supplier = Supplier::factory()->create();

    actingAs($cashier)
        ->put(route('supplier.update', $supplier), ['name' => 'Test', 'phone' => '123', 'address' => 'Test'])
        ->assertForbidden();
});

// --- DESTROY ---

it('allows admin and warehouse to delete a supplier', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $supplier = Supplier::factory()->create();

    actingAs($user)
        ->delete(route('supplier.destroy', $supplier))
        ->assertRedirect()
        ->assertSessionHas('success', 'Supplier berhasil dihapus');

    assertSoftDeleted('suppliers', ['id' => $supplier->id]);
})->with(['admin', 'warehouse']);

it('returns error flash when deleting a supplier that has purchases', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = Supplier::factory()->create();
    Purchase::factory()->create(['supplier_id' => $supplier->id]);

    actingAs($admin)
        ->delete(route('supplier.destroy', $supplier))
        ->assertRedirect()
        ->assertSessionHas('error', 'Supplier gagal dihapus');

    assertDatabaseHas('suppliers', ['id' => $supplier->id]);
});

it('denies cashier to delete a supplier', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $supplier = Supplier::factory()->create();

    actingAs($cashier)
        ->delete(route('supplier.destroy', $supplier))
        ->assertForbidden();
});
