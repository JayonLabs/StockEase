<?php

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $supplier = Supplier::factory()->create();

    putJson(route('supplier.update', $supplier), [])
        ->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $supplier = Supplier::factory()->create();
    $data = ['name' => 'Updated Supplier', 'phone' => '08111222333', 'address' => 'Jl. Update No. 2'];
    unset($data[$field]);

    actingAs($user)
        ->putJson(route('supplier.update', $supplier), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['name', 'phone', 'address']);

it('rejects phone with non-numeric characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $supplier = Supplier::factory()->create();

    actingAs($user)
        ->putJson(route('supplier.update', $supplier), [
            'name' => 'Updated',
            'phone' => '081-234-567',
            'address' => 'Jl. Update No. 2',
        ])
        ->assertJsonValidationErrors(['phone']);
});

it('accepts valid update data', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $supplier = Supplier::factory()->create();

    actingAs($user)
        ->putJson(route('supplier.update', $supplier), [
            'name' => 'Updated Supplier',
            'phone' => '08111222333',
            'address' => 'Jl. Update No. 2',
        ])
        ->assertRedirect();
});
