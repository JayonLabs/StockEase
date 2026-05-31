<?php

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    postJson(route('pos.send-invoice'), ['sale_id' => 1, 'email' => 'test@example.com'])
        ->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $sale = Sale::factory()->create(['user_id' => $user->id]);
    $data = ['sale_id' => $sale->id, 'email' => 'test@example.com'];
    unset($data[$field]);

    actingAs($user)
        ->postJson(route('pos.send-invoice'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['sale_id', 'email']);

it('rejects non-existent sale_id', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);

    actingAs($user)
        ->postJson(route('pos.send-invoice'), ['sale_id' => 99999, 'email' => 'test@example.com'])
        ->assertJsonValidationErrors(['sale_id']);
});

it('rejects invalid email format', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $sale = Sale::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->postJson(route('pos.send-invoice'), ['sale_id' => $sale->id, 'email' => 'not-an-email'])
        ->assertJsonValidationErrors(['email']);
});

it('rejects email exceeding 255 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $sale = Sale::factory()->create(['user_id' => $user->id]);

    actingAs($user)
        ->postJson(route('pos.send-invoice'), [
            'sale_id' => $sale->id,
            'email' => str_repeat('a', 247).'@test.com',
        ])
        ->assertJsonValidationErrors(['email']);
});
