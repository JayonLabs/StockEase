<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    postJson(route('supplier.store'), [])
        ->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $data = ['name' => 'Supplier A', 'phone' => '08123456789', 'address' => 'Jl. Test No. 1'];
    unset($data[$field]);

    actingAs($user)
        ->postJson(route('supplier.store'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['name', 'phone', 'address']);

it('rejects phone with non-numeric characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('supplier.store'), [
            'name' => 'Supplier A',
            'phone' => '0812-3456-789',
            'address' => 'Jl. Test No. 1',
        ])
        ->assertJsonValidationErrors(['phone']);
});

it('rejects phone exceeding 20 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('supplier.store'), [
            'name' => 'Supplier A',
            'phone' => '081234567890123456789',
            'address' => 'Jl. Test No. 1',
        ])
        ->assertJsonValidationErrors(['phone']);
});

it('accepts valid supplier data', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('supplier.store'), [
            'name' => 'Supplier A',
            'phone' => '08123456789',
            'address' => 'Jl. Test No. 1',
        ])
        ->assertRedirect();
});
