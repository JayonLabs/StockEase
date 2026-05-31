<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

it('denies cashier from creating purchase', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);

    actingAs($cashier)
        ->postJson(route('purchase.store'), [])
        ->assertForbidden();
});

it('validates required top-level fields', function (string $field) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $data = [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'date' => now()->toDateString(),
        'product_items' => [
            ['product_id' => $product->id, 'qty' => 1, 'price' => 5000, 'selling_price' => 8000],
        ],
    ];
    unset($data[$field]);

    actingAs($admin)
        ->postJson(route('purchase.store'), $data)
        ->assertJsonValidationErrors([$field]);
})->with(['supplier_id', 'warehouse_id', 'date', 'product_items']);

it('rejects empty product_items array', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->postJson(route('purchase.store'), [
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'date' => now()->toDateString(),
            'product_items' => [],
        ])
        ->assertJsonValidationErrors(['product_items']);
});

it('validates product_items nested required fields', function (string $field) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();
    $item = ['product_id' => $product->id, 'qty' => 1, 'price' => 5000, 'selling_price' => 8000];
    unset($item[$field]);

    actingAs($admin)
        ->postJson(route('purchase.store'), [
            'supplier_id' => Supplier::factory()->create()->id,
            'warehouse_id' => Warehouse::factory()->create()->id,
            'date' => now()->toDateString(),
            'product_items' => [$item],
        ])
        ->assertJsonValidationErrors(["product_items.0.{$field}"]);
})->with(['product_id', 'qty', 'price', 'selling_price']);
