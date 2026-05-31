<?php

use App\Models\Category;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

function productPayload(): array
{
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    return [
        'category_id' => $category->id,
        'name' => 'Test Product',
        'sku' => 'SKU-001',
        'barcode' => 'BAR-001',
        'unit_id' => $unit->id,
        'stock' => 10,
        'purchase_price' => 5000,
        'selling_price' => 8000,
        'alert_stock' => 2,
    ];
}

it('denies cashier from creating product', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);

    actingAs($cashier)
        ->postJson(route('product.store'), productPayload())
        ->assertForbidden();
});

it('allows warehouse role to create product', function () {
    /** @var User $warehouse */
    $warehouse = User::factory()->create(['role' => 'warehouse']);

    actingAs($warehouse)
        ->postJson(route('product.store'), productPayload())
        ->assertRedirect();
});

it('validates required fields', function (string $field) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = productPayload();
    unset($data[$field]);

    actingAs($admin)
        ->postJson(route('product.store'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['category_id', 'name', 'sku', 'barcode', 'unit_id', 'stock', 'purchase_price', 'selling_price', 'alert_stock']);

it('rejects non-existent category_id', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = productPayload();
    $data['category_id'] = 99999;

    actingAs($admin)
        ->postJson(route('product.store'), $data)
        ->assertJsonValidationErrors(['category_id']);
});

it('rejects negative stock', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = productPayload();
    $data['stock'] = -1;

    actingAs($admin)
        ->postJson(route('product.store'), $data)
        ->assertJsonValidationErrors(['stock']);
});

it('rejects invalid expiry_date format', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = productPayload();
    $data['expiry_date'] = 'not-a-date';

    actingAs($admin)
        ->postJson(route('product.store'), $data)
        ->assertJsonValidationErrors(['expiry_date']);
});

it('rejects duplicate sku', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = productPayload();

    actingAs($admin)
        ->postJson(route('product.store'), $data)
        ->assertRedirect();

    actingAs($admin)
        ->postJson(route('product.store'), $data)
        ->assertJsonValidationErrors(['sku']);
});

it('rejects duplicate barcode', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $data = productPayload();

    actingAs($admin)
        ->postJson(route('product.store'), $data)
        ->assertRedirect();

    actingAs($admin)
        ->postJson(route('product.store'), $data)
        ->assertJsonValidationErrors(['barcode']);
});
