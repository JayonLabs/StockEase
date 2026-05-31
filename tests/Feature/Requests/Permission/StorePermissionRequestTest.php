<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('denies unauthorized roles', function (string $role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);

    actingAs($user)
        ->postJson(route('permissions.store'), ['name' => 'test-permission'])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('allows admin to create permission', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->postJson(route('permissions.store'), ['name' => 'new-permission'])
        ->assertRedirect();
});

it('requires name field', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->postJson(route('permissions.store'), [])
        ->assertJsonValidationErrors(['name']);
});

it('rejects name exceeding 255 characters', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->postJson(route('permissions.store'), ['name' => str_repeat('a', 256)])
        ->assertJsonValidationErrors(['name']);
});

it('rejects duplicate permission name', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    Permission::create(['name' => 'existing-permission', 'guard_name' => 'web']);

    actingAs($admin)
        ->postJson(route('permissions.store'), ['name' => 'existing-permission'])
        ->assertJsonValidationErrors(['name']);
});
