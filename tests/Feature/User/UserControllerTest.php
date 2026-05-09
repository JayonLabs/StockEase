<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('allows admin to view users list', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->count(5)->create();

    $response = actingAs($admin)->get(route('users.index'));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('User/Index')
            ->where('users.total', 6) // admin + 5 users
    );
});

it('redirects unauthenticated users to login', function () {
    get(route('users.index'))->assertRedirect(route('login'));
});

it('denies cashier to access users list', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);

    $response = actingAs($cashier)->get(route('users.index'));

    $response->assertForbidden();
});

it('denies warehouse to access users list', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create(['role' => 'warehouse']);

    actingAs($warehouse)->get(route('users.index'))->assertForbidden();
});

it('allows admin to create a user', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    $response = actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role' => 'cashier',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'User berhasil ditambahkan');
    assertDatabaseHas('users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'role' => 'cashier',
    ]);
});

it('validates user creation', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    if ($data instanceof Closure) {
        $data = $data();
    }

    actingAs($admin)
        ->post(route('users.store'), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'missing fields' => [
        [],
        ['name', 'email', 'password', 'role'],
    ],
    'email must be unique' => [
        function () {
            $existing = User::factory()->create(['email' => 'taken@example.com']);

            return [
                'name' => 'User',
                'email' => $existing->email,
                'role' => 'cashier',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ];
        },
        ['email'],
    ],
]);

it('denies non-admin to create a user', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);

    actingAs($user)
        ->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role' => 'cashier',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('allows admin to update user details', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create(['name' => 'Old Name', 'role' => 'warehouse']);

    $response = actingAs($admin)
        ->put(route('users.update', $user), [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'admin',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'User berhasil diubah');
    assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Updated Name',
        'role' => 'admin',
    ]);
});

it('validates user update', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['email' => 'user@example.com']);

    if ($data instanceof Closure) {
        $data = $data();
    }

    actingAs($admin)
        ->put(route('users.update', $user), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'missing fields' => [
        [],
        ['name', 'email', 'role'],
    ],
    'email must be unique (except self)' => [
        function () {
            User::factory()->create(['email' => 'taken2@example.com']);

            return [
                'name' => 'Name',
                'email' => 'taken2@example.com',
                'role' => 'cashier',
            ];
        },
        ['email'],
    ],
]);

it('denies non-admin to update a user', function ($role) {
    /** @var User $actor */
    $actor = User::factory()->create(['role' => $role]);
    $user = User::factory()->create();

    actingAs($actor)
        ->put(route('users.update', $user), [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role' => 'admin',
        ])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('allows admin to reset user password', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($admin)
        ->put(route('users.reset-password', $user), [
            'password' => 'new-password-123',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Password berhasil diubah');
    $user->refresh();
    expect(Hash::check('new-password-123', $user->password))->toBeTrue();
});

it('validates reset password', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();

    actingAs($admin)
        ->put(route('users.reset-password', $user), ['password' => 'short'])
        ->assertSessionHasErrors(['password']);
});

it('denies non-admin to reset password', function ($role) {
    /** @var User $actor */
    $actor = User::factory()->create(['role' => $role]);
    $user = User::factory()->create();

    actingAs($actor)
        ->put(route('users.reset-password', $user), ['password' => 'new-password-123'])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('allows admin to delete a user', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $user */
    $user = User::factory()->create();

    $response = actingAs($admin)
        ->delete(route('users.destroy', $user));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'User berhasil dihapus');
    assertSoftDeleted('users', ['id' => $user->id]);
});

it('denies non-admin to delete a user', function ($role) {
    /** @var User $actor */
    $actor = User::factory()->create(['role' => $role]);
    $user = User::factory()->create();

    actingAs($actor)->delete(route('users.destroy', $user))->assertForbidden();
})->with(['cashier', 'warehouse']);
