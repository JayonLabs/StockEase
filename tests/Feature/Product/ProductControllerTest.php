<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

it('allows admin and warehouse to view products', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    Product::factory()->count(3)->create();

    $response = actingAs($user)->get(route('product.index'));

    $response->assertSuccessful();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Product/Index')
            ->has('products.data', 3)
    );
})->with(['admin', 'warehouse']);

it('redirects unauthenticated users to login', function () {
    get(route('product.index'))->assertRedirect(route('login'));
});

it('denies cashier to view products', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);

    actingAs($cashier)->get(route('product.index'))->assertForbidden();
});

it('allows admin and warehouse to view create page', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $units = Unit::factory()->count(2)->create();
    $categories = Category::factory()->count(2)->create();

    actingAs($user)
        ->get(route('product.create'))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->component('Product/form/ProductCreateForm')
                ->has('units')
                ->has('categories')
                ->where('units', function ($prop) use ($units) {
                    $values = collect($prop)->pluck('value');

                    return $values->contains($units[0]->id) && $values->contains($units[1]->id);
                })
                ->where('categories', function ($prop) use ($categories) {
                    $values = collect($prop)->pluck('value');

                    return $values->contains($categories[0]->id) && $values->contains($categories[1]->id);
                })
        );
})->with(['admin', 'warehouse']);

it('allows admin and warehouse to create a product with image', function ($role) {
    Storage::fake('public');

    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $file = UploadedFile::fake()->image('product.jpg');

    $response = actingAs($user)
        ->post(route('product.store'), [
            'name' => 'New Product',
            'category_id' => $category->id,
            'sku' => 'SKU123',
            'barcode' => '123456789',
            'description' => 'Description',
            'purchase_price' => 1000,
            'selling_price' => 2000,
            'stock' => 10,
            'alert_stock' => 5,
            'unit_id' => $unit->id,
            'image' => $file,
        ]);

    $response->assertRedirect(route('product.index'));
    $response->assertSessionHas('success', 'Product berhasil ditambahkan');
    assertDatabaseHas('products', [
        'name' => 'New Product',
        'sku' => 'SKU123',
    ]);

    /** @var Product $product */
    $product = Product::query()->where('name', 'New Product')->firstOrFail();
    $storedPath = str_replace('storage/', '', (string) $product->image_path);
    expect(Storage::disk('public')->exists($storedPath))->toBeTrue();
})->with(['admin', 'warehouse']);

it('validates product creation', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);

    actingAs($admin)
        ->post(route('product.store'), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'missing required fields' => [
        [],
        ['category_id', 'name', 'sku', 'barcode', 'unit_id', 'stock', 'purchase_price', 'selling_price', 'alert_stock'],
    ],
    'invalid category and unit' => [
        [
            'category_id' => 999999,
            'name' => 'Name',
            'sku' => 'SKU',
            'barcode' => 'BAR',
            'unit_id' => 999999,
            'stock' => 1,
            'purchase_price' => 1,
            'selling_price' => 1,
            'alert_stock' => 1,
        ],
        ['category_id', 'unit_id'],
    ],
]);

it('denies cashier to create a product', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);

    actingAs($cashier)
        ->post(route('product.store'), [])
        ->assertForbidden();
});

it('allows admin and warehouse to view edit page', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $product = Product::factory()->create();
    $units = Unit::factory()->count(2)->create();
    $categories = Category::factory()->count(2)->create();

    actingAs($user)
        ->get(route('product.edit', $product))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->component('Product/form/ProductEditForm')
                ->has('product')
                ->has('units')
                ->has('categories')
                ->where('units', function ($prop) use ($units) {
                    $values = collect($prop)->pluck('value');

                    return $values->contains($units[0]->id) && $values->contains($units[1]->id);
                })
                ->where('categories', function ($prop) use ($categories) {
                    $values = collect($prop)->pluck('value');

                    return $values->contains($categories[0]->id) && $values->contains($categories[1]->id);
                })
        );
})->with(['admin', 'warehouse']);

it('allows admin and warehouse to view a product detail', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $product = Product::factory()->create();

    actingAs($user)
        ->get(route('product.show', $product))
        ->assertSuccessful()
        ->assertInertia(
            fn ($page) => $page
                ->component('Product/Show')
                ->has('product')
        );
})->with(['admin', 'warehouse']);

it('allows admin and warehouse to update a product', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $product = Product::factory()->create(['name' => 'Old Product']);
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    $response = actingAs($user)
        ->patch(route('product.update', $product), [
            'name' => 'Updated Product',
            'category_id' => $category->id,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'alert_stock' => 10,
            'unit_id' => $unit->id,
        ]);

    $response->assertRedirect(route('product.index'));
    $response->assertSessionHas('success', 'Product berhasil diubah');
    assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Product',
    ]);
})->with(['admin', 'warehouse']);

it('validates product update', function ($data, $errors) {
    /** @var User $admin */
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();

    actingAs($admin)
        ->patch(route('product.update', $product), $data)
        ->assertSessionHasErrors($errors);
})->with([
    'missing required fields' => [
        [],
        ['category_id', 'name', 'sku', 'barcode', 'unit_id', 'alert_stock'],
    ],
]);

it('denies cashier to update a product', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create();

    actingAs($cashier)
        ->patch(route('product.update', $product), [])
        ->assertForbidden();
});

it('allows admin and warehouse to delete a product', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $product = Product::factory()->create();

    $response = actingAs($user)
        ->delete(route('product.destroy', $product));

    $response->assertRedirect(route('product.index'));
    $response->assertSessionHas('success', 'Product berhasil dihapus');
    assertSoftDeleted('products', ['id' => $product->id]);
})->with(['admin', 'warehouse']);

it('denies cashier to delete a product', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create();

    actingAs($cashier)
        ->delete(route('product.destroy', $product))
        ->assertForbidden();
});
