<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Services\Product\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('can get paginated products', function () {
    Product::factory()->count(15)->create();

    $service = new ProductService;
    $result = $service->getPaginatedProducts([], 10);

    expect($result->items())->toHaveCount(10);
    expect($result->total())->toBe(15);
});

it('can filter products by search query', function () {
    $category = Category::factory()->create(['name' => 'Tech']);
    Product::factory()->create(['name' => 'Laptop', 'category_id' => $category->id]);
    Product::factory()->create(['name' => 'Bread', 'sku' => 'FOOD001']);

    $service = new ProductService;

    // Search by name
    $result = $service->getPaginatedProducts(['search' => 'Laptop']);
    expect($result->items())->toHaveCount(1);

    // Search by SKU
    $result = $service->getPaginatedProducts(['search' => 'FOOD001']);
    expect($result->items())->toHaveCount(1);

    // Search by Category
    $result = $service->getPaginatedProducts(['search' => 'Tech']);
    expect($result->items())->toHaveCount(1);
});

it('can store a product without image', function () {
    $category = Category::factory()->create();
    $data = [
        'category_id' => $category->id,
        'name' => 'New Product',
        'sku' => 'PROD001',
        'barcode' => '123456',
        'purchase_price' => 1000,
        'selling_price' => 2000,
        'stock' => 10,
        'alert_stock' => 2,
        'unit_id' => Unit::factory()->create()->id,
    ];

    $service = new ProductService;
    $product = $service->storeProduct($data);

    expect($product)->toBeInstanceOf(Product::class);
    expect($product->name)->toBe('New Product');
    expect($product->slug)->toBe('new-product');
    assertDatabaseHas('products', ['name' => 'New Product']);
});

it('can store a product with image', function () {
    $category = Category::factory()->create();
    $image = UploadedFile::fake()->image('product.jpg');
    $data = [
        'category_id' => $category->id,
        'name' => 'Image Product',
        'sku' => 'PROD002',
        'purchase_price' => 1000,
        'selling_price' => 2000,
        'stock' => 10,
        'alert_stock' => 2,
        'unit_id' => Unit::factory()->create()->id,
    ];

    $service = new ProductService;
    $product = $service->storeProduct($data, $image);

    expect($product->image_path)->not->toBeNull();
    expect($product->image_path)->toContain('product/');
    expect(Storage::disk('public')->exists(str_replace('storage/', '', $product->image_path)))->toBeTrue();
});

it('can update a product and replace image', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'name' => 'Old Product',
        'image_path' => 'storage/product/old.jpg',
    ]);
    Storage::disk('public')->put('product/old.jpg', 'content');

    $newImage = UploadedFile::fake()->image('new.jpg');
    $data = ['name' => 'Updated Product', 'category_id' => $category->id];

    $service = new ProductService;
    $service->updateProduct($product, $data, $newImage);

    $product->refresh();
    expect($product->name)->toBe('Updated Product');
    expect($product->slug)->toBe('updated-product');

    expect(Storage::disk('public')->exists('product/old.jpg'))->toBeFalse();
    expect(Storage::disk('public')->exists(str_replace('storage/', '', $product->image_path)))->toBeTrue();
});

it('can delete a product and clean up image', function () {
    $product = Product::factory()->create(['image_path' => 'storage/product/delete.jpg']);
    Storage::disk('public')->put('product/delete.jpg', 'content');

    $service = new ProductService;
    $service->deleteProduct($product);

    assertSoftDeleted('products', ['id' => $product->id]);
    expect(Storage::disk('public')->exists('product/delete.jpg'))->toBeFalse();
});
