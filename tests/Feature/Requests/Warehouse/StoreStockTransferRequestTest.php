<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

it('requires authentication', function () {
    postJson(route('stock-transfer.store'), [])
        ->assertUnauthorized();
});

it('validates required fields', function (string $field) {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);
    $data = [
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
        'product_id' => $product->id,
        'qty' => 2,
        'date' => now()->toDateString(),
    ];
    unset($data[$field]);

    actingAs($user)
        ->postJson(route('stock-transfer.store'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['from_warehouse_id', 'to_warehouse_id', 'product_id', 'qty', 'date']);

it('rejects when from_warehouse_id equals to_warehouse_id', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);

    actingAs($user)
        ->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty' => 2,
            'date' => now()->toDateString(),
        ])
        ->assertJsonValidationErrors(['to_warehouse_id']);
});

it('rejects qty below 1', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);

    actingAs($user)
        ->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'product_id' => $product->id,
            'qty' => 0,
            'date' => now()->toDateString(),
        ])
        ->assertJsonValidationErrors(['qty']);
});

it('rejects non-existent warehouse id', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $to = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);

    actingAs($user)
        ->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => 99999,
            'to_warehouse_id' => $to->id,
            'product_id' => $product->id,
            'qty' => 2,
            'date' => now()->toDateString(),
        ])
        ->assertJsonValidationErrors(['from_warehouse_id']);
});

it('rejects invalid date format', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);

    actingAs($user)
        ->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'product_id' => $product->id,
            'qty' => 2,
            'date' => 'not-a-date',
        ])
        ->assertJsonValidationErrors(['date']);
});

it('rejects note exceeding 500 characters', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    $from = Warehouse::factory()->create();
    $to = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);

    actingAs($user)
        ->postJson(route('stock-transfer.store'), [
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'product_id' => $product->id,
            'qty' => 2,
            'date' => now()->toDateString(),
            'note' => str_repeat('a', 501),
        ])
        ->assertJsonValidationErrors(['note']);
});
