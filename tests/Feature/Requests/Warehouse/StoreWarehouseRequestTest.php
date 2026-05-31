<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    postJson(route('warehouse.store'), ['name' => 'Main Warehouse'])
        ->assertUnauthorized();
});

it('requires name field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('warehouse.store'), [])
        ->assertJsonValidationErrors(['name']);
});

it('rejects name exceeding 255 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('warehouse.store'), ['name' => str_repeat('a', 256)])
        ->assertJsonValidationErrors(['name']);
});

it('rejects address exceeding 500 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('warehouse.store'), [
            'name' => 'Main Warehouse',
            'address' => str_repeat('a', 501),
        ])
        ->assertJsonValidationErrors(['address']);
});

it('rejects phone exceeding 50 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('warehouse.store'), [
            'name' => 'Main Warehouse',
            'phone' => str_repeat('1', 51),
        ])
        ->assertJsonValidationErrors(['phone']);
});

it('accepts valid warehouse data', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('warehouse.store'), [
            'name' => 'Main Warehouse',
            'address' => 'Jl. Warehouse No. 1',
            'phone' => '021-12345678',
            'is_active' => true,
        ])
        ->assertRedirect();
});
