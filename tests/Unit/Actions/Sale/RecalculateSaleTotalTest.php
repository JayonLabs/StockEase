<?php

namespace Tests\Unit\Actions\Sale;

use App\Actions\Sale\RecalculateSaleTotal;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

// ─── Basic calculation ────────────────────────────────────────────────────────

it('returns zero total when sale has no items', function () {
    $sale = Sale::factory()->create();

    $total = (new RecalculateSaleTotal)->execute($sale);

    expect($total)->toBe(0.0);
});

it('calculates total as price × qty with no promotions', function () {
    $sale = Sale::factory()->create();
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => Product::factory()->create()->id,
        'price' => 10000,
        'qty' => 3,
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    expect($total)->toBe(30000.0);
});

it('sums totals across multiple items', function () {
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => Product::factory()->create()->id, 'price' => 10000, 'qty' => 2]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => Product::factory()->create()->id, 'price' => 5000, 'qty' => 1]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    expect($total)->toBe(25000.0);
});

it('returns a float', function () {
    $sale = Sale::factory()->create();

    expect((new RecalculateSaleTotal)->execute($sale))->toBeFloat();
});

it('persists total and total_cost to the sale', function () {
    $sale = Sale::factory()->create(['total' => 0, 'total_cost' => 0]);
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => Product::factory()->create()->id,
        'price' => 10000,
        'qty' => 2,
        'cost_price' => 4000,
    ]);

    (new RecalculateSaleTotal)->execute($sale);

    expect((float) $sale->fresh()->total)->toBe(20000.0)
        ->and((float) $sale->fresh()->total_cost)->toBe(8000.0);
});

it('treats zero cost_price as zero in total_cost', function () {
    $sale = Sale::factory()->create();
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => Product::factory()->create()->id,
        'price' => 10000,
        'qty' => 2,
        'cost_price' => 0,
    ]);

    (new RecalculateSaleTotal)->execute($sale);

    expect((float) $sale->fresh()->total_cost)->toBe(0.0);
});

it('loads saleItems relation when not already loaded', function () {
    $sale = Sale::factory()->create();
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => Product::factory()->create()->id,
        'price' => 5000,
        'qty' => 1,
    ]);
    $sale->unsetRelation('saleItems');

    $total = (new RecalculateSaleTotal)->execute($sale);

    expect($total)->toBe(5000.0);
});

// ─── Percentage promotion ─────────────────────────────────────────────────────

it('deducts percentage discount from item total', function () {
    $product = Product::factory()->create();
    Promotion::factory()->percentage(20)->forProduct($product)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 1]);

    // 10000 - 20% = 8000
    expect((new RecalculateSaleTotal)->execute($sale))->toBe(8000.0);
});

it('applies percentage discount to all units', function () {
    $product = Product::factory()->create();
    Promotion::factory()->percentage(10)->forProduct($product)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 3]);

    // 30000 - (10000 * 10% * 3) = 27000
    expect((new RecalculateSaleTotal)->execute($sale))->toBe(27000.0);
});

// ─── Nominal promotion ────────────────────────────────────────────────────────

it('deducts nominal discount per unit', function () {
    $product = Product::factory()->create();
    Promotion::factory()->nominal(2000)->forProduct($product)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 2]);

    // (10000 * 2) - (2000 * 2) = 16000
    expect((new RecalculateSaleTotal)->execute($sale))->toBe(16000.0);
});

it('caps nominal discount so total never goes below zero', function () {
    $product = Product::factory()->create();
    Promotion::factory()->nominal(99999)->forProduct($product)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 1000, 'qty' => 1]);

    expect((new RecalculateSaleTotal)->execute($sale))->toBe(0.0);
});

// ─── BOGO promotion ───────────────────────────────────────────────────────────

it('applies buy-1-get-1 — every other unit is free', function () {
    $product = Product::factory()->create();
    Promotion::factory()->bogo(1, 1)->forProduct($product)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 2]);

    // 2 units: 1 paid, 1 free → 10000
    expect((new RecalculateSaleTotal)->execute($sale))->toBe(10000.0);
});

it('applies buy-2-get-1 promotion correctly', function () {
    $product = Product::factory()->create();
    Promotion::factory()->bogo(2, 1)->forProduct($product)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 3]);

    // 3 units: 2 paid, 1 free → 20000
    expect((new RecalculateSaleTotal)->execute($sale))->toBe(20000.0);
});

it('only triggers bogo for complete groups — remainder pays full price', function () {
    $product = Product::factory()->create();
    Promotion::factory()->bogo(2, 1)->forProduct($product)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 5]);

    // 5 units / group 3 = 1 full group → 1 free; 4 paid × 10000 = 40000
    expect((new RecalculateSaleTotal)->execute($sale))->toBe(40000.0);
});

// ─── Promotion priority ───────────────────────────────────────────────────────

it('product promotion takes precedence over category promotion', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    Promotion::factory()->percentage(50)->forCategory($category)->create();
    Promotion::factory()->percentage(10)->forProduct($product)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 1]);

    // Product promo (10%) wins: 10000 - 1000 = 9000
    expect((new RecalculateSaleTotal)->execute($sale))->toBe(9000.0);
});

it('category promotion takes precedence over general promotion', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    Promotion::factory()->percentage(10)->create();
    Promotion::factory()->percentage(20)->forCategory($category)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 1]);

    // Category promo (20%) wins: 10000 - 2000 = 8000
    expect((new RecalculateSaleTotal)->execute($sale))->toBe(8000.0);
});

it('falls back to general promotion when no product or category match', function () {
    $product = Product::factory()->create();
    Promotion::factory()->percentage(15)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 1]);

    // General promo (15%): 10000 - 1500 = 8500
    expect((new RecalculateSaleTotal)->execute($sale))->toBe(8500.0);
});

it('applies no discount when no promotion matches', function () {
    $product = Product::factory()->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 1]);

    expect((new RecalculateSaleTotal)->execute($sale))->toBe(10000.0);
});

// ─── Item promotion fields ────────────────────────────────────────────────────

it('sets item promotion_id and discount_amount when a promotion applies', function () {
    $product = Product::factory()->create();
    $promo = Promotion::factory()->percentage(20)->forProduct($product)->create();

    $sale = Sale::factory()->create();
    $item = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'price' => 10000,
        'qty' => 1,
    ]);

    (new RecalculateSaleTotal)->execute($sale);

    expect($item->fresh()->promotion_id)->toBe($promo->id)
        ->and((float) $item->fresh()->discount_amount)->toBe(2000.0);
});

it('clears item promotion_id and discount_amount when no promotion applies', function () {
    $product = Product::factory()->create();

    $sale = Sale::factory()->create();
    $item = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'price' => 10000,
        'qty' => 1,
        'discount_amount' => 0,
    ]);

    (new RecalculateSaleTotal)->execute($sale);

    expect($item->fresh()->promotion_id)->toBeNull()
        ->and((float) $item->fresh()->discount_amount)->toBe(0.0);
});

// ─── Inactive promotions ──────────────────────────────────────────────────────

it('ignores inactive promotions', function () {
    $product = Product::factory()->create();
    Promotion::factory()->percentage(50)->inactive()->forProduct($product)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'price' => 10000, 'qty' => 1]);

    expect((new RecalculateSaleTotal)->execute($sale))->toBe(10000.0);
});

// ─── Dirty check — avoid unnecessary item updates ────────────────────────────

it('skips the item UPDATE query when promotion has not changed', function () {
    $product = Product::factory()->create();
    $promo = Promotion::factory()->percentage(20)->forProduct($product)->create();

    $sale = Sale::factory()->create();
    SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'price' => 10000,
        'qty' => 1,
        'promotion_id' => $promo->id,
        'discount_amount' => 2000,
    ]);

    DB::enableQueryLog();
    (new RecalculateSaleTotal)->execute($sale);
    $queries = collect(DB::getQueryLog());

    $itemUpdates = $queries->filter(
        fn ($q) => str_contains(strtolower($q['query']), 'update') &&
                   str_contains($q['query'], 'sale_items')
    );

    expect($itemUpdates)->toBeEmpty();
});

// ─── Promotion query caching with once() ─────────────────────────────────────

it('queries active promotions only once when execute is called multiple times on the same instance', function () {
    $sale1 = Sale::factory()->create();
    $sale2 = Sale::factory()->create();

    $action = new RecalculateSaleTotal;

    DB::enableQueryLog();
    $action->execute($sale1);
    $action->execute($sale2);

    $promotionQueries = collect(DB::getQueryLog())->filter(
        fn ($q) => str_contains($q['query'], 'promotions')
    );

    expect($promotionQueries)->toHaveCount(1);
});
