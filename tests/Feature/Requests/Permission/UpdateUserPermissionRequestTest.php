<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('denies unauthorized roles', function (string $role) {
    /** @var User $actor */
    $actor = User::factory()->create(['role' => $role]);
    /** @var User $target */
    $target = User::factory()->create();

    actingAs($actor)
        ->putJson(route('user-permissions.update', $target), ['permissions' => []])
        ->assertForbidden();
})->with(['cashier', 'warehouse']);

it('allows updating with empty permissions array', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $target */
    $target = User::factory()->create();

    actingAs($admin)
        ->putJson(route('user-permissions.update', $target), ['permissions' => []])
        ->assertRedirect();
});

it('allows updating without permissions key', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $target */
    $target = User::factory()->create();

    actingAs($admin)
        ->putJson(route('user-permissions.update', $target), [])
        ->assertRedirect();
});

it('rejects permissions that do not exist in database', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $target */
    $target = User::factory()->create();

    actingAs($admin)
        ->putJson(route('user-permissions.update', $target), ['permissions' => ['ghost-permission']])
        ->assertJsonValidationErrors(['permissions.0']);
});

it('accepts valid permission names', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    /** @var User $target */
    $target = User::factory()->create();
    Permission::create(['name' => 'view-sales', 'guard_name' => 'web']);

    actingAs($admin)
        ->putJson(route('user-permissions.update', $target), ['permissions' => ['view-sales']])
        ->assertRedirect();
});
