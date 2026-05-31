<?php

use App\Enums\SaleReturnType;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    $sale = Sale::factory()->create();

    postJson(route('sale-return.store', $sale), [])
        ->assertUnauthorized();
});

it('requires return_type field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
    $item = SaleItem::factory()->create(['sale_id' => $sale->id]);

    actingAs($user)
        ->postJson(route('sale-return.store', $sale), [
            'items' => [['sale_item_id' => $item->id, 'qty' => 1]],
        ])
        ->assertJsonValidationErrors(['return_type']);
});

it('rejects invalid return_type value', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
    $item = SaleItem::factory()->create(['sale_id' => $sale->id]);

    actingAs($user)
        ->postJson(route('sale-return.store', $sale), [
            'return_type' => 'invalid-type',
            'items' => [['sale_item_id' => $item->id, 'qty' => 1]],
        ])
        ->assertJsonValidationErrors(['return_type']);
});

it('requires items array', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completed']);

    actingAs($user)
        ->postJson(route('sale-return.store', $sale), [
            'return_type' => SaleReturnType::Refund->value,
        ])
        ->assertJsonValidationErrors(['items']);
});

it('rejects empty items array', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completed']);

    actingAs($user)
        ->postJson(route('sale-return.store', $sale), [
            'return_type' => SaleReturnType::Refund->value,
            'items' => [],
        ])
        ->assertJsonValidationErrors(['items']);
});

it('validates items nested qty field', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
    $item = SaleItem::factory()->create(['sale_id' => $sale->id]);

    actingAs($user)
        ->postJson(route('sale-return.store', $sale), [
            'return_type' => SaleReturnType::Refund->value,
            'items' => [['sale_item_id' => $item->id, 'qty' => 0]],
        ])
        ->assertJsonValidationErrors(['items.0.qty']);
});

it('accepts valid refund return', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $sale = Sale::factory()->create(['user_id' => $user->id, 'status' => 'completed']);
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'qty' => 2]);

    actingAs($user)
        ->postJson(route('sale-return.store', $sale), [
            'return_type' => SaleReturnType::Refund->value,
            'items' => [['sale_item_id' => $item->id, 'qty' => 1]],
        ])
        ->assertRedirect();
});
