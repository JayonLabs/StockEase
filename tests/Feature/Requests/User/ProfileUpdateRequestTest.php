<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    patchJson(route('profile.update'), ['name' => 'Test', 'email' => 'test@example.com'])
        ->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create();
    $data = ['name' => 'Updated Name', 'email' => 'updated@example.com'];
    unset($data[$field]);

    actingAs($user)
        ->patchJson(route('profile.update'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['name', 'email']);

it('rejects invalid email format', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->patchJson(route('profile.update'), ['name' => 'Updated', 'email' => 'not-an-email'])
        ->assertJsonValidationErrors(['email']);
});

it('rejects duplicate email from another user', function () {
    /** @var User $user */
    $user = User::factory()->create();
    User::factory()->create(['email' => 'taken@example.com']);

    actingAs($user)
        ->patchJson(route('profile.update'), ['name' => 'Updated', 'email' => 'taken@example.com'])
        ->assertJsonValidationErrors(['email']);
});

it('allows keeping same email', function () {
    /** @var User $user */
    $user = User::factory()->create(['email' => 'mine@example.com']);

    actingAs($user)
        ->patchJson(route('profile.update'), ['name' => 'Updated', 'email' => 'mine@example.com'])
        ->assertRedirect();
});

it('rejects name exceeding 255 characters', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->patchJson(route('profile.update'), ['name' => str_repeat('a', 256), 'email' => 'test@example.com'])
        ->assertJsonValidationErrors(['name']);
});
