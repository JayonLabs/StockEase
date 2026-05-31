<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

test('the application returns a successful response', function () {
    $user = User::factory()->create();

    /** @var User $user */
    $response = actingAs($user)->get('/');

    $response->assertStatus(200);
});
