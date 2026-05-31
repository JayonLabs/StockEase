<?php

namespace Tests\Unit\Actions\Product;

use App\Actions\Product\RestoreProductStock;
use App\Enums\StockLogType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Auth::login(User::factory()->create(['role' => 'admin']));
});

it('creates stock log with StockLogType In enum value', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $sale = Sale::factory()->create();
    $saleItem = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 3,
    ]);

    $returnItem = new SaleReturnItem([
        'product_id' => $product->id,
        'sale_item_id' => $saleItem->id,
        'qty' => 2,
    ]);
    $returnItem->setAttribute('sale_return_id', 1);

    (new RestoreProductStock)->execute(collect([$returnItem]));

    assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'type' => StockLogType::In->value,
        'reference_type' => 'SaleReturn',
    ]);
});

it('creates stock log with correct type string matching enum', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $sale = Sale::factory()->create();
    $saleItem = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 2,
    ]);

    $returnItem = new SaleReturnItem([
        'product_id' => $product->id,
        'sale_item_id' => $saleItem->id,
        'qty' => 1,
    ]);
    $returnItem->setAttribute('sale_return_id', 1);

    (new RestoreProductStock)->execute(collect([$returnItem]));

    $log = DB::table('stock_logs')->where('product_id', $product->id)->first();
    expect($log->type)->toBe('in');
    expect($log->type)->toBe(StockLogType::In->value);
});

it('restores global stock when no warehouse provided', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $sale = Sale::factory()->create();
    $saleItem = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 5,
    ]);

    $returnItem = new SaleReturnItem([
        'product_id' => $product->id,
        'sale_item_id' => $saleItem->id,
        'qty' => 2,
    ]);
    $returnItem->setAttribute('sale_return_id', 1);

    (new RestoreProductStock)->execute(collect([$returnItem]));

    expect($product->fresh()->stock)->toBe(12);
});

it('restores warehouse stock when warehouse provided', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $warehouse->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();
    $sale = Sale::factory()->create();
    $saleItem = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 3,
    ]);

    $returnItem = new SaleReturnItem([
        'product_id' => $product->id,
        'sale_item_id' => $saleItem->id,
        'qty' => 2,
    ]);
    $returnItem->setAttribute('sale_return_id', 1);

    (new RestoreProductStock)->execute(collect([$returnItem]), $warehouse->id);

    expect($product->fresh()->stock)->toBe(12);
});

it('no hardcoded string literal in codebase for StockLog type', function () {
    $source = file_get_contents(app_path('Actions/Product/RestoreProductStock.php'));
    expect($source)->not->toContain("'type' => 'in'");
    expect($source)->toContain('StockLogType::In->value');
});
