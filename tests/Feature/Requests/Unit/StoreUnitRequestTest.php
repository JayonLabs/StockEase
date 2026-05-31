<?php

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    postJson(route('unit.store'), ['name' => 'Kilogram', 'short_name' => 'kg'])
        ->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $data = ['name' => 'Kilogram', 'short_name' => 'kg'];
    unset($data[$field]);

    actingAs($user)
        ->postJson(route('unit.store'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['name', 'short_name']);

it('rejects duplicate name', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    Unit::factory()->create(['name' => 'Kilogram', 'short_name' => 'kg']);

    actingAs($user)
        ->postJson(route('unit.store'), ['name' => 'Kilogram', 'short_name' => 'kg2'])
        ->assertJsonValidationErrors(['name']);
});

it('rejects duplicate short_name', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    Unit::factory()->create(['name' => 'Kilogram', 'short_name' => 'kg']);

    actingAs($user)
        ->postJson(route('unit.store'), ['name' => 'Kilogram2', 'short_name' => 'kg'])
        ->assertJsonValidationErrors(['short_name']);
});

it('accepts valid unit data', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('unit.store'), ['name' => 'Unit-'.Str::random(8), 'short_name' => Str::random(5)])
        ->assertRedirect();
});
