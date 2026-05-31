<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('denies cashier from updating product', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create();

    actingAs($cashier)
        ->putJson(route('product.update', $product), [])
        ->assertForbidden();
});

it('validates required fields', function (string $field) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $data = [
        'category_id' => $category->id,
        'name' => 'Updated Product',
        'sku' => 'SKU-002',
        'barcode' => 'BAR-002',
        'unit_id' => $unit->id,
        'alert_stock' => 3,
    ];
    unset($data[$field]);

    actingAs($admin)
        ->putJson(route('product.update', $product), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['category_id', 'name', 'sku', 'barcode', 'unit_id', 'alert_stock']);

it('rejects non-existent unit_id', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();
    $category = Category::factory()->create();

    actingAs($admin)
        ->putJson(route('product.update', $product), [
            'category_id' => $category->id,
            'name' => 'Updated',
            'sku' => 'SKU-002',
            'barcode' => 'BAR-002',
            'unit_id' => 99999,
            'alert_stock' => 3,
        ])
        ->assertJsonValidationErrors(['unit_id']);
});

it('rejects duplicate sku from another product', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $existing = Product::factory()->create(['sku' => 'EXISTING-SKU']);
    $product = Product::factory()->create();
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    actingAs($admin)
        ->putJson(route('product.update', $product), [
            'category_id' => $category->id,
            'name' => 'Updated',
            'sku' => $existing->sku,
            'barcode' => 'BAR-UNIQUE',
            'unit_id' => $unit->id,
            'alert_stock' => 3,
        ])
        ->assertJsonValidationErrors(['sku']);
});

it('rejects duplicate barcode from another product', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $existing = Product::factory()->create(['barcode' => 'EXISTING-BAR']);
    $product = Product::factory()->create();
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    actingAs($admin)
        ->putJson(route('product.update', $product), [
            'category_id' => $category->id,
            'name' => 'Updated',
            'sku' => 'SKU-UNIQUE',
            'barcode' => $existing->barcode,
            'unit_id' => $unit->id,
            'alert_stock' => 3,
        ])
        ->assertJsonValidationErrors(['barcode']);
});

it('allows same sku when updating self', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    actingAs($admin)
        ->putJson(route('product.update', $product), [
            'category_id' => $category->id,
            'name' => 'Updated Name',
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'unit_id' => $unit->id,
            'alert_stock' => 3,
        ])
        ->assertRedirect();
});

it('allows same barcode when updating self', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    actingAs($admin)
        ->putJson(route('product.update', $product), [
            'category_id' => $category->id,
            'name' => 'Updated Name',
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'unit_id' => $unit->id,
            'alert_stock' => 3,
        ])
        ->assertRedirect();
});
