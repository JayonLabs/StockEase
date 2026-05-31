<?php

use App\Actions\Product\ReduceProductStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('can reduce stock from sale items', function () {
    $product = Product::factory()->create(['stock' => 10, 'alert_stock' => 5]);
    $sale = Sale::factory()->create();
    $saleItems = collect([
        new SaleItem(['product_id' => $product->id, 'qty' => 3, 'price' => 1000, 'sale_id' => $sale->id]),
    ]);

    resolve(ReduceProductStock::class)->execute($saleItems);

    $product->refresh();
    expect($product->stock)->toBe(7);

    assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => 3,
        'type' => 'out',
        'reference_type' => 'Sale',
        'reference_id' => $sale->id,
    ]);
});

it('throws exception if stock is insufficient', function () {
    $product = Product::factory()->create(['stock' => 2]);
    $sale = Sale::factory()->create();
    $saleItems = collect([
        new SaleItem(['product_id' => $product->id, 'qty' => 5, 'price' => 1000, 'sale_id' => $sale->id]),
    ]);

    expect(fn () => resolve(ReduceProductStock::class)->execute($saleItems))
        ->toThrow(Exception::class, "Stok produk {$product->name} tidak cukup.");
});

it('triggers stock alert notification when stock hits alert level', function () {
    $admin = User::factory()->create(['role' => 'admin']); // To receive notification

    $product = Product::factory()->create(['stock' => 6, 'alert_stock' => 5]);
    $sale = Sale::factory()->create();
    $saleItems = collect([
        new SaleItem(['product_id' => $product->id, 'qty' => 2, 'price' => 1000, 'sale_id' => $sale->id]),
    ]);

    Notification::fake();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    resolve(ReduceProductStock::class)->execute($saleItems);

    $product->refresh();
    expect($product->stock)->toBe(4); // 6 - 2 = 4 (<= 5)

    Notification::assertSentTo($admin, StockAlertNotification::class);
});
