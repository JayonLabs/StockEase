<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    getJson(route('reports.sale.export-to-excel', [
        'start' => '2024-01-01',
        'end' => '2024-12-31',
        'cashier' => 'all',
        'payment' => 'all',
    ]))->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $params = ['start' => '2024-01-01', 'end' => '2024-12-31', 'cashier' => 'all', 'payment' => 'all'];
    unset($params[$field]);

    actingAs($user)
        ->getJson(route('reports.sale.export-to-excel', $params))
        ->assertJsonValidationErrors([$field]);
})->with(['start', 'end', 'cashier', 'payment']);

it('rejects invalid start date format', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->getJson(route('reports.sale.export-to-excel', [
            'start' => 'not-a-date',
            'end' => '2024-12-31',
            'cashier' => 'all',
            'payment' => 'all',
        ]))
        ->assertJsonValidationErrors(['start']);
});
