<?php

use App\Enums\PromotionType;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    postJson(route('promotions.store'), [])
        ->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $data = [
        'name' => 'Test Promo',
        'type' => PromotionType::Nominal->value,
        'discount_value' => 5000,
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
        'is_active' => true,
    ];
    unset($data[$field]);

    actingAs($user)
        ->postJson(route('promotions.store'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['name', 'type', 'start_date', 'end_date']);

it('rejects invalid promotion type', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('promotions.store'), [
            'name' => 'Test Promo',
            'type' => 'invalid-type',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ])
        ->assertJsonValidationErrors(['type']);
});

it('rejects end_date before start_date', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('promotions.store'), [
            'name' => 'Test Promo',
            'type' => PromotionType::Nominal->value,
            'discount_value' => 5000,
            'start_date' => '2024-12-31',
            'end_date' => '2024-01-01',
        ])
        ->assertJsonValidationErrors(['end_date']);
});

it('rejects percentage discount_value above 100', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('promotions.store'), [
            'name' => 'Test Promo',
            'type' => PromotionType::Percentage->value,
            'discount_value' => 101,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ])
        ->assertJsonValidationErrors(['discount_value']);
});

it('requires buy_qty and get_qty for bogo type', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $data = [
        'name' => 'Buy 2 Get 1',
        'type' => PromotionType::Bogo->value,
        'buy_qty' => 2,
        'get_qty' => 1,
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
    ];
    unset($data[$field]);

    actingAs($user)
        ->postJson(route('promotions.store'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['buy_qty', 'get_qty']);

it('accepts valid nominal promotion', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);

    actingAs($user)
        ->postJson(route('promotions.store'), [
            'name' => 'Discount 5k',
            'type' => PromotionType::Nominal->value,
            'discount_value' => 5000,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_active' => true,
        ])
        ->assertRedirect();
});
