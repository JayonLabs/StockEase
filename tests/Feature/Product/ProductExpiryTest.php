<?php

use App\Actions\Product\UpdateProductExpiryDate;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(LazilyRefreshDatabase::class);

it('allows admin to create a product with an initial expiry date', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $expiryDate = now()->addYear()->format('Y-m-d');

    $response = actingAs($admin)
        ->post(route('product.store'), [
            'name' => 'Expiring Product',
            'category_id' => $category->id,
            'sku' => 'EXP-001',
            'barcode' => '987654321',
            'purchase_price' => 5000,
            'selling_price' => 7000,
            'stock' => 50,
            'alert_stock' => 10,
            'unit_id' => $unit->id,
            'expiry_date' => $expiryDate,
        ]);

    $response->assertRedirect(route('product.index'));
    assertDatabaseHas('products', [
        'name' => 'Expiring Product',
        'expiry_date' => $expiryDate,
    ]);
});

it('validates expiry_date format on product create', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $response = actingAs($admin)
        ->post(route('product.store'), [
            'name' => 'Invalid Date Product',
            'category_id' => $category->id,
            'sku' => 'INV-001',
            'barcode' => '123',
            'purchase_price' => 1000,
            'selling_price' => 2000,
            'stock' => 1,
            'alert_stock' => 1,
            'unit_id' => $unit->id,
            'expiry_date' => 'invalid-date',
        ]);

    $response->assertSessionHasErrors('expiry_date');
});

it('allows null expiry_date on product create (non-expiring product)', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $response = actingAs($admin)
        ->post(route('product.store'), [
            'name' => 'No Expiry Product',
            'category_id' => $category->id,
            'sku' => 'EXP-002',
            'barcode' => '000000',
            'purchase_price' => 5000,
            'selling_price' => 7000,
            'stock' => 50,
            'alert_stock' => 10,
            'unit_id' => $unit->id,
            'expiry_date' => null,
        ]);

    $response->assertRedirect(route('product.index'));
    assertDatabaseHas('products', [
        'name' => 'No Expiry Product',
        'expiry_date' => null,
    ]);
});

it('ignores expiry_date sent to product update — it is managed by purchases', function () {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create(['expiry_date' => null]);
    $futureDate = now()->addYears(2)->format('Y-m-d');

    $response = actingAs($admin)
        ->patch(route('product.update', $product), [
            'name' => $product->name,
            'category_id' => $product->category_id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'unit_id' => $product->unit_id,
            'alert_stock' => $product->alert_stock,
            'expiry_date' => $futureDate, // silently ignored
        ]);

    $response->assertRedirect(route('product.index'));
    assertDatabaseHas('products', [
        'id' => $product->id,
        'expiry_date' => null,
    ]);
});

it('auto-updates product expiry_date from the earliest purchase batch', function () {
    $product = Product::factory()->create(['expiry_date' => null]);

    /** @var PurchaseItem $earlierBatch */
    $earlierBatch = PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addMonths(3)->toDateString(),
        'remaining_qty' => 10,
    ]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'remaining_qty' => 10,
    ]);

    (new UpdateProductExpiryDate)->execute($product);

    assertDatabaseHas('products', [
        'id' => $product->id,
        'expiry_date' => $earlierBatch->expiry_date->toDateString(),
    ]);
});

it('sets product expiry_date to null when all purchase batches are exhausted', function () {
    $product = Product::factory()->create([
        'expiry_date' => now()->addMonths(3)->toDateString(),
    ]);

    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addMonths(3)->toDateString(),
        'remaining_qty' => 0, // exhausted
    ]);

    (new UpdateProductExpiryDate)->execute($product);

    assertDatabaseHas('products', [
        'id' => $product->id,
        'expiry_date' => null,
    ]);
});
