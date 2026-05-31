<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

function adjustmentPayload(): array
{
    return [
        'warehouse_id' => Warehouse::factory()->create()->id,
        'product_id' => Product::factory()->create()->id,
        'new_stock' => 100,
        'reason' => 'Stock opname',
        'date' => now()->toDateString(),
    ];
}

it('requires authentication', function () {
    $this->postJson(route('stock-adjustment.store'), adjustmentPayload())
        ->assertUnauthorized();
});

it('denies cashier from creating adjustment', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);

    actingAs($cashier)
        ->postJson(route('stock-adjustment.store'), adjustmentPayload())
        ->assertForbidden();
});

it('allows admin to create adjustment', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->postJson(route('stock-adjustment.store'), adjustmentPayload())
        ->assertRedirect();
});

it('validates required fields', function (string $field) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = adjustmentPayload();
    unset($data[$field]);

    actingAs($admin)
        ->postJson(route('stock-adjustment.store'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['warehouse_id', 'product_id', 'new_stock', 'date']);

it('rejects non-existent warehouse_id', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = adjustmentPayload();
    $data['warehouse_id'] = 99999;

    actingAs($admin)
        ->postJson(route('stock-adjustment.store'), $data)
        ->assertJsonValidationErrors(['warehouse_id']);
});

it('rejects non-existent product_id', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = adjustmentPayload();
    $data['product_id'] = 99999;

    actingAs($admin)
        ->postJson(route('stock-adjustment.store'), $data)
        ->assertJsonValidationErrors(['product_id']);
});

it('rejects negative new_stock', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = adjustmentPayload();
    $data['new_stock'] = -1;

    actingAs($admin)
        ->postJson(route('stock-adjustment.store'), $data)
        ->assertJsonValidationErrors(['new_stock']);
});

it('rejects non-integer new_stock', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = adjustmentPayload();
    $data['new_stock'] = 'abc';

    actingAs($admin)
        ->postJson(route('stock-adjustment.store'), $data)
        ->assertJsonValidationErrors(['new_stock']);
});

it('rejects invalid date format', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = adjustmentPayload();
    $data['date'] = 'not-a-date';

    actingAs($admin)
        ->postJson(route('stock-adjustment.store'), $data)
        ->assertJsonValidationErrors(['date']);
});

it('allows nullable reason', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = adjustmentPayload();
    unset($data['reason']);

    actingAs($admin)
        ->postJson(route('stock-adjustment.store'), $data)
        ->assertRedirect();
});
