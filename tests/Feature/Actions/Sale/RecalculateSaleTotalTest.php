<?php

use App\Actions\Sale\RecalculateSaleTotal;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

// ============================================================
// Helpers
// ============================================================

/**
 * Create a Sale with SaleItems eagerly loaded.
 *
 * @param  array<array{product?: Product, qty?: int, price?: float, cost_price?: float}>  $items
 */
function makeSaleWithItems(array $items): Sale
{
    $sale = Sale::factory()->create(['total' => 0, 'total_cost' => 0]);

    foreach ($items as $itemData) {
        $product = $itemData['product'] ?? Product::factory()->create();
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'qty' => $itemData['qty'] ?? 1,
            'price' => $itemData['price'] ?? 10000,
            'cost_price' => $itemData['cost_price'] ?? 0,
        ]);
    }

    return $sale->load('saleItems.product');
}

// ============================================================
// No promotion tests
// ============================================================

it('calculates sale total with no active promotions', function () {
    $sale = makeSaleWithItems([
        ['qty' => 2, 'price' => 10000],
        ['qty' => 3, 'price' => 5000],
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    expect($total)->toBe(35000.0);
    expect((float) $sale->fresh()->total)->toBe(35000.0);
});

it('returns float and updates sale total', function () {
    $sale = makeSaleWithItems([['qty' => 1, 'price' => 20000]]);

    $result = (new RecalculateSaleTotal)->execute($sale);

    expect($result)->toBeFloat()->toBe(20000.0);
});

it('accumulates total_cost from cost_price of each item', function () {
    $sale = makeSaleWithItems([
        ['qty' => 2, 'price' => 10000, 'cost_price' => 4000],
        ['qty' => 1, 'price' => 8000, 'cost_price' => 3000],
    ]);

    (new RecalculateSaleTotal)->execute($sale);

    expect((float) $sale->fresh()->total_cost)->toBe(11000.0);
});

// ============================================================
// Percentage promotion tests
// ============================================================

it('applies percentage promotion to product-specific items', function () {
    $product = Product::factory()->create();
    Promotion::factory()->percentage(20)->forProduct($product)->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 2, 'price' => 10000],
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    // 2 × 10000 = 20000 − 20% (4000) = 16000
    expect($total)->toBe(16000.0);
});

it('saves promotion_id and discount_amount on sale item', function () {
    $product = Product::factory()->create();
    $promo = Promotion::factory()->percentage(10)->forProduct($product)->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 1, 'price' => 10000],
    ]);

    (new RecalculateSaleTotal)->execute($sale);

    $saleItem = $sale->saleItems->first()->fresh();

    expect($saleItem->promotion_id)->toBe($promo->id);
    expect((float) $saleItem->discount_amount)->toBe(1000.0);
});

// ============================================================
// Nominal promotion tests
// ============================================================

it('applies nominal promotion capped at item total', function () {
    $product = Product::factory()->create();
    Promotion::factory()->nominal(15000)->forProduct($product)->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 1, 'price' => 10000],
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    // discount capped: min(15000*1, 10000*1) = 10000 → total = 0
    expect($total)->toBe(0.0);
});

it('applies nominal promotion per quantity', function () {
    $product = Product::factory()->create();
    Promotion::factory()->nominal(2000)->forProduct($product)->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 3, 'price' => 10000],
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    // 3×10000 = 30000 − (2000×3 = 6000) = 24000
    expect($total)->toBe(24000.0);
});

// ============================================================
// BOGO promotion tests
// ============================================================

it('applies BOGO promotion — buy 1 get 1 free', function () {
    $product = Product::factory()->create();
    Promotion::factory()->bogo(1, 1)->forProduct($product)->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 4, 'price' => 10000],
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    // groupSize=2, free items = floor(4/2)*1 = 2 × 10000 = 20000 discount
    // total = 40000 − 20000 = 20000
    expect($total)->toBe(20000.0);
});

it('applies BOGO promotion — buy 2 get 1 free', function () {
    $product = Product::factory()->create();
    Promotion::factory()->bogo(2, 1)->forProduct($product)->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 6, 'price' => 10000],
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    // groupSize=3, free items = floor(6/3)*1 = 2 × 10000 = 20000 discount
    // total = 60000 − 20000 = 40000
    expect($total)->toBe(40000.0);
});

it('applies no BOGO discount when qty is less than groupSize', function () {
    $product = Product::factory()->create();
    Promotion::factory()->bogo(3, 1)->forProduct($product)->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 2, 'price' => 10000],
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    // floor(2/4) = 0 free items
    expect($total)->toBe(20000.0);
});

// ============================================================
// Promotion priority tests
// ============================================================

it('prefers product-specific promotion over category promotion', function () {
    $product = Product::factory()->create();
    $categoryPromo = Promotion::factory()->percentage(50)->forCategory($product->category)->create();
    $productPromo = Promotion::factory()->percentage(10)->forProduct($product)->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 1, 'price' => 10000],
    ]);

    (new RecalculateSaleTotal)->execute($sale);

    expect($sale->saleItems->first()->fresh()->promotion_id)->toBe($productPromo->id);
});

it('prefers category promotion over general promotion', function () {
    $product = Product::factory()->create();
    $generalPromo = Promotion::factory()->percentage(50)->create(['product_id' => null, 'category_id' => null]);
    $categoryPromo = Promotion::factory()->percentage(10)->forCategory($product->category)->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 1, 'price' => 10000],
    ]);

    (new RecalculateSaleTotal)->execute($sale);

    expect($sale->saleItems->first()->fresh()->promotion_id)->toBe($categoryPromo->id);
});

it('falls back to general promotion when no product or category promo matches', function () {
    $product = Product::factory()->create();
    $generalPromo = Promotion::factory()->percentage(10)->create(['product_id' => null, 'category_id' => null]);

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 1, 'price' => 10000],
    ]);

    (new RecalculateSaleTotal)->execute($sale);

    expect($sale->saleItems->first()->fresh()->promotion_id)->toBe($generalPromo->id);
});

it('does not apply inactive promotions', function () {
    $product = Product::factory()->create();
    Promotion::factory()->percentage(50)->forProduct($product)->inactive()->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 1, 'price' => 10000],
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    expect($total)->toBe(10000.0);
    expect($sale->saleItems->first()->fresh()->promotion_id)->toBeNull();
});

it('does not apply expired promotions', function () {
    $product = Product::factory()->create();
    Promotion::factory()->percentage(50)->forProduct($product)->create([
        'start_date' => now()->subDays(10),
        'end_date' => now()->subDays(1), // expired
    ]);

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 1, 'price' => 10000],
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    expect($total)->toBe(10000.0);
});

it('does not apply future promotions', function () {
    $product = Product::factory()->create();
    Promotion::factory()->percentage(50)->forProduct($product)->create([
        'start_date' => now()->addDays(1),
        'end_date' => now()->addDays(10),
    ]);

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 1, 'price' => 10000],
    ]);

    $total = (new RecalculateSaleTotal)->execute($sale);

    expect($total)->toBe(10000.0);
});

// ============================================================
// Dirty-check optimisation: sale item only saved when changed
// ============================================================

it('does not update sale item when promotion did not change', function () {
    $product = Product::factory()->create();
    $promo = Promotion::factory()->percentage(10)->forProduct($product)->create();

    $sale = makeSaleWithItems([
        ['product' => $product, 'qty' => 1, 'price' => 10000],
    ]);

    // First call — sets promotion_id and discount_amount
    (new RecalculateSaleTotal)->execute($sale);

    $item = $sale->saleItems->first()->fresh();
    $updatedAt = $item->updated_at;

    // Reload relations to reset isDirty tracking
    $sale->load('saleItems.product');

    // Second call — values unchanged, should not save
    (new RecalculateSaleTotal)->execute($sale);

    expect($item->fresh()->updated_at->eq($updatedAt))->toBeTrue();
});

// ============================================================
// Edge cases
// ============================================================

it('handles a sale with no items — total is zero', function () {
    $sale = Sale::factory()->create(['total' => 500, 'total_cost' => 200]);
    $sale->load('saleItems.product');

    $total = (new RecalculateSaleTotal)->execute($sale);

    expect($total)->toBe(0.0);
    expect((float) $sale->fresh()->total)->toBe(0.0);
});

it('loads saleItems relation automatically when not already loaded', function () {
    $sale = makeSaleWithItems([['qty' => 2, 'price' => 5000]]);
    // Use a fresh unloaded instance
    $freshSale = Sale::find($sale->id);

    expect($freshSale->relationLoaded('saleItems'))->toBeFalse();

    $total = (new RecalculateSaleTotal)->execute($freshSale);

    expect($total)->toBe(10000.0);
});
