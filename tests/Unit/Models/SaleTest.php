<?php

use App\Actions\Sale\RecalculateSaleTotal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('calculates total from sale items', function () {
    $sale = Sale::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product1->id,
        'qty' => 2,
        'price' => 1000,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product2->id,
        'qty' => 3,
        'price' => 500,
    ]);

    $total = resolve(RecalculateSaleTotal::class)->execute($sale);

    expect((int) $total)->toBe((2 * 1000) + (3 * 500));
    expect((int) $sale->fresh()->total)->toBe((2 * 1000) + (3 * 500));
});
