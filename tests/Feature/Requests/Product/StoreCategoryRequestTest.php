<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    postJson(route('category.store'), ['name' => 'Electronics'])
        ->assertUnauthorized();
});

it('requires name field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('category.store'), [])
        ->assertJsonValidationErrors(['name']);
});

it('rejects name exceeding 255 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('category.store'), ['name' => str_repeat('a', 256)])
        ->assertJsonValidationErrors(['name']);
});

it('accepts valid name', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('category.store'), ['name' => 'Electronics'])
        ->assertRedirect();
});
