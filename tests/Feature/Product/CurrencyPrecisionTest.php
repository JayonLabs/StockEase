<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('stores and retrieves prices with 4 decimal places precision', function () {
    $category = Category::factory()->create();

    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'High Precision Product',
        'sku' => 'PREC-001',
        'unit_id' => Unit::factory()->create()->id,
        'stock' => 100,
        'purchase_price' => '12345.6789',
        'selling_price' => '23456.7891',
        'alert_stock' => 10,
    ]);

    $freshProduct = Product::find($product->id);

    // Assert that the retrieved values maintain the 4 decimal places
    expect($freshProduct->purchase_price)->toBe('12345.6789');
    expect($freshProduct->selling_price)->toBe('23456.7891');
});

it('can handle large numbers with precision', function () {
    $category = Category::factory()->create();

    $largePrice = '99999999999.9999'; // Use string to avoid float rounding in PHP

    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Large Price Product',
        'sku' => 'PREC-002',
        'unit_id' => Unit::factory()->create()->id,
        'stock' => 100,
        'purchase_price' => $largePrice,
        'selling_price' => $largePrice,
        'alert_stock' => 10,
    ]);

    $freshProduct = Product::find($product->id);

    expect($freshProduct->purchase_price)->toBe('99999999999.9999');
});
