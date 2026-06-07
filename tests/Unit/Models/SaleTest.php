<?php

use App\Actions\Sale\RecalculateSaleTotal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
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

describe('saleReturns relationship', function () {
    it('returns empty collection when no returns exist', function () {
        $sale = Sale::factory()->create();

        expect($sale->saleReturns)->toBeCollection();
        expect($sale->saleReturns)->toHaveCount(0);
    });

    it('returns related sale returns', function () {
        $sale = Sale::factory()->create();

        $return1 = SaleReturn::factory()->create(['sale_id' => $sale->id, 'reason' => 'Defect']);
        $return2 = SaleReturn::factory()->create(['sale_id' => $sale->id, 'reason' => 'Wrong item']);

        $returns = $sale->saleReturns;

        expect($returns)->toHaveCount(2);
        expect($returns->pluck('id')->toArray())
            ->toEqualCanonicalizing([$return1->id, $return2->id]);
        expect($returns->pluck('reason')->toArray())
            ->toEqualCanonicalizing(['Defect', 'Wrong item']);
    });

    it('does not return sale returns from other sales', function () {
        $sale1 = Sale::factory()->create();
        $sale2 = Sale::factory()->create();

        $return1 = SaleReturn::factory()->create(['sale_id' => $sale1->id]);
        SaleReturn::factory()->create(['sale_id' => $sale2->id]);

        expect($sale1->saleReturns)->toHaveCount(1);
        expect($sale1->saleReturns->first()->id)->toBe($return1->id);
    });

    it('eager loads saleReturns with the sale items relationship', function () {
        $sale = Sale::factory()->create();

        SaleReturn::factory()->count(2)->create(['sale_id' => $sale->id]);

        $result = Sale::with('saleReturns')->find($sale->id);

        expect($result->saleReturns)->toHaveCount(2);
    });

    it('supports querying through the relationship', function () {
        $sale = Sale::factory()->create();

        SaleReturn::factory()->create([
            'sale_id' => $sale->id,
            'status' => 'completed',
        ]);
        SaleReturn::factory()->create([
            'sale_id' => $sale->id,
            'status' => 'canceled',
        ]);

        $completedCount = $sale->saleReturns()->where('status', 'completed')->count();

        expect($completedCount)->toBe(1);
    });

    it('is the inverse of SaleReturn::sale()', function () {
        $sale = Sale::factory()->create();
        $saleReturn = SaleReturn::factory()->create(['sale_id' => $sale->id]);

        expect($sale->saleReturns->first()->id)->toBe($saleReturn->id);
        expect($saleReturn->sale->id)->toBe($sale->id);
    });
});
