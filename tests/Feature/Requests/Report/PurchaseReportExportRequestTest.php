<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    getJson(route('reports.purchase.export-to-excel', [
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
        'supplier' => 'all',
        'user' => 'all',
    ]))->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $params = ['start_date' => '2024-01-01', 'end_date' => '2024-12-31', 'supplier' => 'all', 'user' => 'all'];
    unset($params[$field]);

    actingAs($user)
        ->getJson(route('reports.purchase.export-to-excel', $params))
        ->assertJsonValidationErrors([$field]);
})->with(['start_date', 'end_date', 'supplier', 'user']);

it('rejects invalid start_date format', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->getJson(route('reports.purchase.export-to-excel', [
            'start_date' => 'not-a-date',
            'end_date' => '2024-12-31',
            'supplier' => 'all',
            'user' => 'all',
        ]))
        ->assertJsonValidationErrors(['start_date']);
});
