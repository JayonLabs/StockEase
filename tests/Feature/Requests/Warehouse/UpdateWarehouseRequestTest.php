<?php

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $warehouse = Warehouse::factory()->create();

    putJson(route('warehouse.update', $warehouse), ['name' => 'Updated'])
        ->assertUnauthorized();
});

it('requires name field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();

    actingAs($user)
        ->putJson(route('warehouse.update', $warehouse), [])
        ->assertJsonValidationErrors(['name']);
});

it('rejects name exceeding 255 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();

    actingAs($user)
        ->putJson(route('warehouse.update', $warehouse), ['name' => str_repeat('a', 256)])
        ->assertJsonValidationErrors(['name']);
});

it('rejects address exceeding 500 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();

    actingAs($user)
        ->putJson(route('warehouse.update', $warehouse), [
            'name' => 'Updated Warehouse',
            'address' => str_repeat('a', 501),
        ])
        ->assertJsonValidationErrors(['address']);
});

it('accepts valid update data', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();

    actingAs($user)
        ->putJson(route('warehouse.update', $warehouse), [
            'name' => 'Updated Warehouse',
            'is_active' => false,
        ])
        ->assertRedirect();
});
