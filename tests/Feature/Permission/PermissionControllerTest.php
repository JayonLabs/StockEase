<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\get;

it('allows admin to view permissions list', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Permission::create(['name' => 'edit products', 'guard_name' => 'web']);

    $response = actingAs($admin)->get(route('permissions.index'));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Permission/Index')
            ->has('permissions.data')
    );
});

it('redirects unauthenticated users to login', function () {
    get(route('permissions.index'))->assertRedirect(route('login'));
});

it('denies cashier to access permissions list', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);

    actingAs($cashier)->get(route('permissions.index'))->assertForbidden();
});

it('denies warehouse to access permissions list', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create(['role' => 'warehouse']);

    actingAs($warehouse)->get(route('permissions.index'))->assertForbidden();
});

it('allows admin to create a permission', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    $response = actingAs($admin)->post(route('permissions.store'), [
        'name' => 'delete users',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Permission berhasil ditambahkan');
    assertDatabaseHas('permissions', ['name' => 'delete users', 'guard_name' => 'web']);
});

it('validates permission creation', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    if ($data instanceof Closure) {
        $data = $data();
    }

    actingAs($admin)
        ->post(route('permissions.store'), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'missing name' => [
        [],
        ['name'],
    ],
    'name must be unique' => [
        function () {
            Permission::create(['name' => 'existing-perm', 'guard_name' => 'web']);

            return ['name' => 'existing-perm'];
        },
        ['name'],
    ],
]);

it('denies non-admin to create a permission', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);

    actingAs($user)
        ->post(route('permissions.store'), ['name' => 'new perm'])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('allows admin to update a permission', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $permission = Permission::create(['name' => 'old name', 'guard_name' => 'web']);

    $response = actingAs($admin)
        ->put(route('permissions.update', $permission), [
            'name' => 'new name',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Permission berhasil diubah');
    assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'new name']);
});

it('validates permission update', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $permission = Permission::create(['name' => 'perm-one', 'guard_name' => 'web']);

    if ($data instanceof Closure) {
        $data = $data();
    }

    actingAs($admin)
        ->put(route('permissions.update', $permission), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'missing name' => [
        [],
        ['name'],
    ],
    'name must be unique' => [
        function () {
            Permission::create(['name' => 'perm-two', 'guard_name' => 'web']);

            return ['name' => 'perm-two'];
        },
        ['name'],
    ],
]);

it('denies non-admin to update a permission', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $permission = Permission::create(['name' => 'perm', 'guard_name' => 'web']);

    actingAs($user)
        ->put(route('permissions.update', $permission), ['name' => 'changed'])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('allows admin to delete a permission', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $permission = Permission::create(['name' => 'to-delete', 'guard_name' => 'web']);

    $response = actingAs($admin)->delete(route('permissions.destroy', $permission));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Permission berhasil dihapus');
    assertDatabaseMissing('permissions', ['id' => $permission->id]);
});

it('denies non-admin to delete a permission', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $permission = Permission::create(['name' => 'to-delete', 'guard_name' => 'web']);

    actingAs($user)->delete(route('permissions.destroy', $permission))->assertForbidden();
})->with(['cashier', 'warehouse']);
