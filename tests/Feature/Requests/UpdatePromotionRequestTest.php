<?php

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $promotion = Promotion::factory()->create();

    putJson(route('promotions.update', $promotion), [])
        ->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $promotion = Promotion::factory()->create();
    $data = [
        'name' => 'Updated Promo',
        'type' => PromotionType::Nominal->value,
        'discount_value' => 5000,
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
        'is_active' => true,
    ];
    unset($data[$field]);

    actingAs($user)
        ->putJson(route('promotions.update', $promotion), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['name', 'type', 'start_date', 'end_date']);

it('rejects end_date before start_date', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $promotion = Promotion::factory()->create();

    actingAs($user)
        ->putJson(route('promotions.update', $promotion), [
            'name' => 'Updated Promo',
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
    $promotion = Promotion::factory()->create();

    actingAs($user)
        ->putJson(route('promotions.update', $promotion), [
            'name' => 'Updated Promo',
            'type' => PromotionType::Percentage->value,
            'discount_value' => 110,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ])
        ->assertJsonValidationErrors(['discount_value']);
});

it('accepts valid update', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $promotion = Promotion::factory()->create();

    actingAs($user)
        ->putJson(route('promotions.update', $promotion), [
            'name' => 'Updated Promo',
            'type' => PromotionType::Nominal->value,
            'discount_value' => 10000,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_active' => false,
        ])
        ->assertRedirect();
});
