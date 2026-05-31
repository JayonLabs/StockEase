<?php

namespace Tests\Unit\Actions\Product;

use App\Actions\Product\ReduceProductStock;
use App\Enums\StockLogType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
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

it('creates stock log with StockLogType Out enum value', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $warehouse = Warehouse::factory()->create();
    $sale = Sale::factory()->create();
    $saleItem = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 3,
    ]);

    (new ReduceProductStock)->execute(collect([$saleItem]));

    assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'type' => StockLogType::Out->value,
        'reference_type' => 'Sale',
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

    (new ReduceProductStock)->execute(collect([$saleItem]));

    $log = DB::table('stock_logs')->where('product_id', $product->id)->first();
    expect($log->type)->toBe('out');
    expect($log->type)->toBe(StockLogType::Out->value);
});

it('reduces global stock when no warehouse provided', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $sale = Sale::factory()->create();
    $saleItem = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 4,
    ]);

    (new ReduceProductStock)->execute(collect([$saleItem]));

    expect($product->fresh()->stock)->toBe(6);
});

it('reduces warehouse stock when warehouse provided', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $warehouse->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();
    $sale = Sale::factory()->create();
    $saleItem = SaleItem::factory()->create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 3,
        'warehouse_id' => $warehouse->id,
    ]);

    (new ReduceProductStock)->execute(collect([$saleItem]), $warehouse->id);

    expect($product->fresh()->stock)->toBe(7);
});

it('no hardcoded string literal in codebase for StockLog type', function () {
    $source = file_get_contents(app_path('Actions/Product/ReduceProductStock.php'));
    expect($source)->not->toContain("'type' => 'out'");
    expect($source)->toContain('StockLogType::Out->value');
});
