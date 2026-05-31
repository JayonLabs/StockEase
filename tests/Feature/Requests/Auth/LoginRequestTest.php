<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires email field', function () {
    postJson('/login', ['password' => 'password'])
        ->assertJsonValidationErrors(['email']);
});

it('rejects invalid email format', function () {
    postJson('/login', ['email' => 'not-an-email', 'password' => 'password'])
        ->assertJsonValidationErrors(['email']);
});

it('requires password field', function () {
    postJson('/login', ['email' => 'user@example.com'])
        ->assertJsonValidationErrors(['password']);
});

it('requires both fields', function () {
    postJson('/login', [])
        ->assertJsonValidationErrors(['email', 'password']);
});
