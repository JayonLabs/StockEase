<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\artisan;

uses(LazilyRefreshDatabase::class);

describe('BackfillSaleItemCosts Command', function () {
    it('backfills cost_price for sale items with zero cost_price', function () {
        $product = Product::factory()->create(['purchase_price' => 5000]);
        $sale = Sale::factory()->create();
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'cost_price' => 0,
            'price' => 10000,
            'qty' => 2,
        ]);

        artisan('app:backfill-sale-item-costs')
            ->expectsOutputToContain('Backfill completed')
            ->assertSuccessful();

        $item = $sale->saleItems()->first();

        expect((float) $item->cost_price)->toBe(5000.0);
    });

    it('backfills cost_price for sale items with zero cost_price using where clause', function () {
        $product = Product::factory()->create(['purchase_price' => 7500]);
        $sale = Sale::factory()->create();

        // cost_price is NOT NULL in schema, but the command queries
        // where cost_price = 0 OR cost_price IS NULL (for safety)
        $item = SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'cost_price' => 0,
            'price' => 15000,
            'qty' => 1,
        ]);

        artisan('app:backfill-sale-item-costs')
            ->assertSuccessful();

        expect((float) $item->fresh()->cost_price)->toBe(7500.0);
    });

    it('skips items that already have a cost_price', function () {
        $product = Product::factory()->create(['purchase_price' => 5000]);
        $sale = Sale::factory()->create();
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'cost_price' => 3000,
            'price' => 10000,
            'qty' => 1,
        ]);

        artisan('app:backfill-sale-item-costs')
            ->assertSuccessful();

        $item = $sale->saleItems()->first();

        expect((float) $item->cost_price)->toBe(3000.0);
    });

    it('recalculates sale total_cost after backfill', function () {
        $product = Product::factory()->create(['purchase_price' => 5000]);
        $sale = Sale::factory()->create(['total_cost' => 0]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'cost_price' => 0,
            'price' => 10000,
            'qty' => 3,
        ]);

        artisan('app:backfill-sale-item-costs')
            ->assertSuccessful();

        $sale->refresh();

        expect((float) $sale->total_cost)->toBe(15000.0); // 3 * 5000
    });

    it('handles multiple sale items across different sales', function () {
        $product1 = Product::factory()->create(['purchase_price' => 4000]);
        $product2 = Product::factory()->create(['purchase_price' => 6000]);

        $sale1 = Sale::factory()->create();
        SaleItem::factory()->create([
            'sale_id' => $sale1->id,
            'product_id' => $product1->id,
            'cost_price' => 0,
            'price' => 8000,
            'qty' => 2,
        ]);

        $sale2 = Sale::factory()->create();
        SaleItem::factory()->create([
            'sale_id' => $sale2->id,
            'product_id' => $product2->id,
            'cost_price' => 0,
            'price' => 12000,
            'qty' => 1,
        ]);

        artisan('app:backfill-sale-item-costs')
            ->assertSuccessful();

        expect((float) $sale1->saleItems->first()->fresh()->cost_price)->toBe(4000.0);
        expect((float) $sale2->saleItems->first()->fresh()->cost_price)->toBe(6000.0);
    });

    it('does not alter non-zero non-null cost_price items', function () {
        $product = Product::factory()->create(['purchase_price' => 5000]);
        $sale = Sale::factory()->create();
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'cost_price' => 4500,
            'price' => 10000,
            'qty' => 1,
        ]);
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'cost_price' => 0,
            'price' => 10000,
            'qty' => 1,
        ]);

        artisan('app:backfill-sale-item-costs')
            ->assertSuccessful();

        $items = $sale->saleItems()->orderBy('id')->get();

        expect((float) $items[0]->cost_price)->toBe(4500.0);
        expect((float) $items[1]->cost_price)->toBe(5000.0);
    });

    it('runs successfully when there are no items to backfill', function () {
        $product = Product::factory()->create(['purchase_price' => 5000]);
        $sale = Sale::factory()->create();
        SaleItem::factory()->create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'cost_price' => 5000,
            'price' => 10000,
            'qty' => 1,
        ]);

        artisan('app:backfill-sale-item-costs')
            ->expectsOutputToContain('Backfill completed')
            ->assertSuccessful();
    });

    it('outputs starting and completion messages', function () {
        artisan('app:backfill-sale-item-costs')
            ->expectsOutputToContain('Starting backfill')
            ->expectsOutputToContain('Recalculating')
            ->expectsOutputToContain('completed successfully');
    });
});
