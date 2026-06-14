<?php

namespace Tests\Feature\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
    ]);
});

it('authenticates with valid credentials', function () {
    post('/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

it('authenticates with remember me', function () {
    post('/login', [
        'email' => 'user@example.com',
        'password' => 'password',
        'remember' => 'on',
    ])->assertRedirect(route('dashboard'));
});

it('fails with invalid password', function () {
    post('/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

it('fails with non-existent email', function () {
    post('/login', [
        'email' => 'nonexistent@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

it('validates required fields', function () {
    post('/login', [
        'email' => '',
        'password' => '',
    ])->assertSessionHasErrors(['email', 'password']);
});

it('validates email format', function () {
    post('/login', [
        'email' => 'not-an-email',
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

it('throttles after too many attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        post('/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);
    }

    post('/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

it('redirects platform owner to platform dashboard', function () {
    $this->user->syncRoles('platform_owner');

    post('/login', [
        'email' => 'user@example.com',
        'password' => 'password',
    ])->assertRedirect(route('platform.owner.dashboard'));
});

it('returns json errors when request expects json', function () {
    postJson('/login', [
        'email' => '',
        'password' => '',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});
