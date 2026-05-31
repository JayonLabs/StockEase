<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $category = Category::factory()->create();

    putJson(route('category.update', $category), ['name' => 'Updated'])
        ->assertUnauthorized();
});

it('requires name field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();

    actingAs($user)
        ->putJson(route('category.update', $category), [])
        ->assertJsonValidationErrors(['name']);
});

it('rejects name exceeding 255 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();

    actingAs($user)
        ->putJson(route('category.update', $category), ['name' => str_repeat('a', 256)])
        ->assertJsonValidationErrors(['name']);
});

it('accepts valid name', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();

    actingAs($user)
        ->putJson(route('category.update', $category), ['name' => 'Updated Category'])
        ->assertRedirect();
});
