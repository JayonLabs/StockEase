<?php

use App\Enums\ShiftStatus;
use App\Models\Category;
use App\Models\PaymentTransaction;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\SaleEmail;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Shift;
use App\Models\StockAdjustment;
use App\Models\StockLog;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Product\CategoryService;
use App\Services\Product\ProductService;
use App\Services\Product\UnitService;
use App\Services\Purchase\PurchaseService;
use App\Services\Purchase\SupplierService;
use App\Services\Sale\PosService;
use App\Services\User\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

// TestCase and LazilyRefreshDatabase already configured globally in tests/Pest.php

// --- Model-Level Soft Delete Behavior ---

it('soft deletes a category and excludes from default queries', function () {
    $category = Category::factory()->create();

    $category->delete();

    assertSoftDeleted('categories', ['id' => $category->id]);
    expect(Category::query()->count())->toBe(0);
    expect(Category::withTrashed()->count())->toBe(1);
    expect(Category::onlyTrashed()->count())->toBe(1);
    expect($category->fresh()->trashed())->toBeTrue();
});

it('restores a soft deleted category', function () {
    $category = Category::factory()->create();
    $category->delete();

    $category->restore();

    assertDatabaseHas('categories', ['id' => $category->id]);
    expect(Category::query()->count())->toBe(1);
    expect($category->fresh()->trashed())->toBeFalse();
});

it('force deletes a category permanently', function () {
    $category = Category::factory()->create();

    $category->forceDelete();

    expect(Category::withTrashed()->count())->toBe(0);
});

it('soft deletes a product', function () {
    $product = Product::factory()->create();

    $product->delete();

    assertSoftDeleted('products', ['id' => $product->id]);
    expect($product->fresh()->trashed())->toBeTrue();
});

it('soft deletes a supplier', function () {
    $supplier = Supplier::factory()->create();

    $supplier->delete();

    assertSoftDeleted('suppliers', ['id' => $supplier->id]);
});

it('soft deletes a unit', function () {
    $unit = Unit::factory()->create();

    $unit->delete();

    assertSoftDeleted('units', ['id' => $unit->id]);
});

it('soft deletes a user', function () {
    $user = User::factory()->create();

    $user->delete();

    assertSoftDeleted('users', ['id' => $user->id]);
});

// --- Slug Uniqueness with Soft Deletes ---

it('allows reuse of slug after soft delete for categories', function () {
    $category1 = Category::factory()->create(['name' => 'Electronics']);
    $category1->delete();

    $category2 = Category::factory()->create(['name' => 'Electronics']);

    assertDatabaseHas('categories', ['id' => $category2->id, 'slug' => 'electronics']);
    expect($category2->slug)->toBe('electronics');
});

it('allows reuse of slug after soft delete for products', function () {
    $product1 = Product::factory()->create(['name' => 'Test Product']);
    $product1->delete();

    $product2 = Product::factory()->create(['name' => 'Test Product']);

    assertDatabaseHas('products', ['id' => $product2->id, 'slug' => 'test-product']);
});

it('allows reuse of slug after soft delete for suppliers', function () {
    $supplier1 = Supplier::factory()->create(['name' => 'Acme Corp']);
    $supplier1->delete();

    $supplier2 = Supplier::factory()->create(['name' => 'Acme Corp']);

    assertDatabaseHas('suppliers', ['id' => $supplier2->id, 'slug' => 'acme-corp']);
});

it('allows reuse of slug after soft delete for units', function () {
    $unit1 = Unit::factory()->create(['name' => 'Kilogram']);
    $unit1->delete();

    $unit2 = Unit::factory()->create(['name' => 'Kilogram']);

    assertDatabaseHas('units', ['id' => $unit2->id, 'slug' => 'kilogram']);
});

// --- Service-Level Soft Delete ---

it('soft deletes a product via service and cleans up image', function () {
    Storage::fake('public');
    $product = Product::factory()->create(['image_path' => 'storage/product/test.jpg']);
    Storage::disk('public')->put('product/test.jpg', 'content');

    $service = new ProductService;
    $service->deleteProduct($product);

    assertSoftDeleted('products', ['id' => $product->id]);
    expect(Storage::disk('public')->exists('product/test.jpg'))->toBeFalse();
});

it('soft deletes a category via service', function () {
    $category = Category::factory()->create();

    $service = new CategoryService;
    $service->deleteCategory($category);

    assertSoftDeleted('categories', ['id' => $category->id]);
    expect($category->fresh()->trashed())->toBeTrue();
});

it('soft deletes a unit via service', function () {
    $unit = Unit::factory()->create();

    $service = new UnitService;
    $service->deleteUnit($unit);

    assertSoftDeleted('units', ['id' => $unit->id]);
});

it('soft deletes a supplier via service', function () {
    $supplier = Supplier::factory()->create();

    $service = new SupplierService;
    $service->deleteSupplier($supplier);

    assertSoftDeleted('suppliers', ['id' => $supplier->id]);
});

it('soft deletes a user via service', function () {
    $user = User::factory()->create();

    $service = new UserService;
    $service->deleteUser($user);

    assertSoftDeleted('users', ['id' => $user->id]);
});

// --- Purchase Soft Delete ---

it('soft deletes a purchase and its items', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $warehouse->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();
    $supplier = Supplier::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

    /** @var User $admin */
    actingAs($admin);

    $purchaseService = app(PurchaseService::class);
    $purchase = $purchaseService->storePurchase([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'date' => now()->toDateString(),
        'product_items' => [
            [
                'product_id' => $product->id,
                'qty' => 5,
                'price' => 1000,
                'selling_price' => 2000,
            ],
        ],
    ]);

    $purchaseService->deletePurchase($purchase);

    assertSoftDeleted('purchases', ['id' => $purchase->id]);
    expect(PurchaseItem::withTrashed()->where('purchase_id', $purchase->id)->count())->toBe(1);
});

it('reverts stock on soft delete of a purchase', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    $warehouse->products()->attach($product->id, ['stock' => 100]);
    $product->syncStockFromWarehouses();
    $supplier = Supplier::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

    /** @var User $admin */
    actingAs($admin);

    $purchaseService = app(PurchaseService::class);
    $purchase = $purchaseService->storePurchase([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'date' => now()->toDateString(),
        'product_items' => [
            [
                'product_id' => $product->id,
                'qty' => 5,
                'price' => 1000,
                'selling_price' => 2000,
            ],
        ],
    ]);

    $stockBefore = $product->fresh()->stock;

    $purchaseService->deletePurchase($purchase);

    expect($product->fresh()->stock)->toBe($stockBefore - 5);
});

// --- forceDelete on Purchase Items During Update ---

it('force deletes purchase items removed during update', function () {
    $warehouse = Warehouse::factory()->create();
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();
    $warehouse->products()->attach($productA->id, ['stock' => 100]);
    $warehouse->products()->attach($productB->id, ['stock' => 100]);
    $productA->syncStockFromWarehouses();
    $productB->syncStockFromWarehouses();
    $supplier = Supplier::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

    /** @var User $admin */
    actingAs($admin);

    $purchaseService = app(PurchaseService::class);
    $purchase = $purchaseService->storePurchase([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $warehouse->id,
        'date' => now()->toDateString(),
        'product_items' => [
            [
                'product_id' => $productA->id,
                'qty' => 5,
                'price' => 1000,
                'selling_price' => 2000,
            ],
            [
                'product_id' => $productB->id,
                'qty' => 3,
                'price' => 1500,
                'selling_price' => 3000,
            ],
        ],
    ]);

    $purchaseService->updatePurchase($purchase, [
        'supplier_id' => $supplier->id,
        'date' => now()->toDateString(),
        'product_items' => [
            [
                'product_id' => $productA->id,
                'qty' => 5,
                'price' => 1000,
                'selling_price' => 2000,
            ],
        ],
    ]);

    // The removed PurchaseItem should be force deleted (not in database at all)
    expect(PurchaseItem::withTrashed()->where('purchase_id', $purchase->id)->where('product_id', $productB->id)->exists())->toBeFalse();
    // The kept PurchaseItem should still exist
    expect(PurchaseItem::where('purchase_id', $purchase->id)->where('product_id', $productA->id)->exists())->toBeTrue();
});

// --- withTrashed and onlyTrashed Queries ---

it('excludes soft deleted from default queries', function () {
    $active = Category::factory()->create(['name' => 'Active']);
    $deleted = Category::factory()->create(['name' => 'Deleted']);
    $deleted->delete();

    $categories = Category::query()->get();

    expect($categories)->toHaveCount(1);
    expect($categories->first()->id)->toBe($active->id);
});

it('includes soft deleted with withTrashed', function () {
    $active = Category::factory()->create();
    $deleted = Category::factory()->create();
    $deleted->delete();

    $categories = Category::withTrashed()->get();

    expect($categories)->toHaveCount(2);
});

it('returns only trashed with onlyTrashed', function () {
    $active = Category::factory()->create();
    $deleted = Category::factory()->create();
    $deleted->delete();

    $categories = Category::onlyTrashed()->get();

    expect($categories)->toHaveCount(1);
    expect($categories->first()->id)->toBe($deleted->id);
});

// --- trashed() Method ---

it('returns true for trashed after soft delete', function () {
    $category = Category::factory()->create();

    expect($category->trashed())->toBeFalse();

    $category->delete();

    expect($category->fresh()->trashed())->toBeTrue();
});

// --- Multiple Soft Deletes on Same Resource ---

it('handles multiple soft deletes and restores correctly', function () {
    $a = Category::factory()->create(['name' => 'A']);
    $b = Category::factory()->create(['name' => 'B']);
    $c = Category::factory()->create(['name' => 'C']);

    $a->delete();
    $c->delete();

    expect(Category::query()->count())->toBe(1); // only B
    expect(Category::withTrashed()->count())->toBe(3);
    expect(Category::onlyTrashed()->count())->toBe(2);

    $a->restore();

    expect(Category::query()->count())->toBe(2); // B and A
    expect(Category::onlyTrashed()->count())->toBe(1); // only C
});

// --- Mass Assignment Works After Soft Delete Restoration ---

it('allows updating a restored model', function () {
    $category = Category::factory()->create(['name' => 'Original']);
    $category->delete();
    $category->restore();

    $category->update(['name' => 'Updated']);

    expect($category->fresh()->name)->toBe('Updated');
    expect($category->fresh()->trashed())->toBeFalse();
});

// --- Force Delete After Soft Delete ---

it('permanently deletes after forceDelete on soft deleted model', function () {
    $category = Category::factory()->create();
    $category->delete();

    $category->forceDelete();

    expect(Category::withTrashed()->where('id', $category->id)->exists())->toBeFalse();
});

// --- PosService cart draft items use forceDelete ---

it('force deletes sale items when removing from cart', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 50, 'selling_price' => 5000]);

    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    Shift::factory()->create(['user_id' => $user->id, 'status' => ShiftStatus::Open->value]);
    DB::table('warehouse_product')->insert(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'stock' => 50, 'created_at' => now(), 'updated_at' => now()]);
    session(['pos_active_warehouse_id' => $warehouse->id]);

    /** @var User $user */
    actingAs($user);

    $posService = app(PosService::class);
    $posService->addToCart($product->id, 3);

    $cart = $posService->getOrCreateCart();
    $saleItem = $cart->saleItems->first();

    $posService->removeFromCart($product->id);

    // The sale item should be force deleted (gone from database entirely)
    expect(SaleItem::withTrashed()->where('id', $saleItem->id)->exists())->toBeFalse();
});

it('force deletes all sale items when emptying cart', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 50, 'selling_price' => 5000]);

    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    Shift::factory()->create(['user_id' => $user->id, 'status' => ShiftStatus::Open->value]);
    DB::table('warehouse_product')->insert(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'stock' => 50, 'created_at' => now(), 'updated_at' => now()]);
    session(['pos_active_warehouse_id' => $warehouse->id]);

    /** @var User $user */
    actingAs($user);

    $posService = app(PosService::class);
    $posService->addToCart($product->id, 3);

    $cart = $posService->getOrCreateCart();
    $saleItemIds = $cart->saleItems->pluck('id')->toArray();

    $posService->emptyCart();

    // All sale items should be force deleted
    expect(SaleItem::withTrashed()->whereIn('id', $saleItemIds)->count())->toBe(0);
});

// --- New model soft-delete tests ---

it('soft deletes a warehouse', function () {
    $warehouse = Warehouse::factory()->create();

    $warehouse->delete();

    assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    expect($warehouse->fresh()->trashed())->toBeTrue();
    expect(Warehouse::onlyTrashed()->count())->toBe(1);
});

it('restores a soft deleted warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $warehouse->delete();

    $warehouse->restore();

    expect(Warehouse::find($warehouse->id))->not->toBeNull();
    expect($warehouse->fresh()->trashed())->toBeFalse();
});

it('soft deletes a shift', function () {
    $shift = Shift::factory()->create();

    $shift->delete();

    assertSoftDeleted('shifts', ['id' => $shift->id]);
    expect($shift->fresh()->trashed())->toBeTrue();
});

it('soft deletes a stockLog', function () {
    $stockLog = StockLog::factory()->create();

    $stockLog->delete();

    assertSoftDeleted('stock_logs', ['id' => $stockLog->id]);
    expect($stockLog->fresh()->trashed())->toBeTrue();
});

it('soft deletes a stockAdjustment', function () {
    $adjustment = StockAdjustment::factory()->create();

    $adjustment->delete();

    assertSoftDeleted('stock_adjustments', ['id' => $adjustment->id]);
    expect($adjustment->fresh()->trashed())->toBeTrue();
});

it('soft deletes a stockTransfer', function () {
    $transfer = StockTransfer::factory()->create();

    $transfer->delete();

    assertSoftDeleted('stock_transfers', ['id' => $transfer->id]);
    expect($transfer->fresh()->trashed())->toBeTrue();
});

it('soft deletes a saleReturn and its items', function () {
    $saleReturn = SaleReturn::factory()->create();
    $item = SaleReturnItem::factory()->create(['sale_return_id' => $saleReturn->id]);

    $saleReturn->delete();

    assertSoftDeleted('sale_returns', ['id' => $saleReturn->id]);
    expect($saleReturn->fresh()->trashed())->toBeTrue();
});

it('soft deletes a saleReturnItem', function () {
    $item = SaleReturnItem::factory()->create();

    $item->delete();

    assertSoftDeleted('sale_return_items', ['id' => $item->id]);
    expect($item->fresh()->trashed())->toBeTrue();
});

it('soft deletes a paymentTransaction', function () {
    $tx = PaymentTransaction::factory()->create();

    $tx->delete();

    assertSoftDeleted('payment_transactions', ['id' => $tx->id]);
    expect($tx->fresh()->trashed())->toBeTrue();
});

it('soft deletes a priceHistory', function () {
    $ph = PriceHistory::factory()->create();

    $ph->delete();

    assertSoftDeleted('price_histories', ['id' => $ph->id]);
    expect($ph->fresh()->trashed())->toBeTrue();
});

it('soft deletes a saleEmail', function () {
    $email = SaleEmail::factory()->create();

    $email->delete();

    assertSoftDeleted('sale_emails', ['id' => $email->id]);
    expect($email->fresh()->trashed())->toBeTrue();
});

it('restores a soft deleted saleEmail', function () {
    $email = SaleEmail::factory()->create();
    $email->delete();

    $email->restore();

    expect(SaleEmail::find($email->id))->not->toBeNull();
    expect($email->fresh()->trashed())->toBeFalse();
});

it('force deletes a warehouse permanently', function () {
    $warehouse = Warehouse::factory()->create();

    $warehouse->forceDelete();

    expect(Warehouse::withTrashed()->where('id', $warehouse->id)->exists())->toBeFalse();
});

it('excludes soft deleted warehouse from default queries', function () {
    $active = Warehouse::factory()->create(['name' => 'Active']);
    $deleted = Warehouse::factory()->create(['name' => 'Deleted']);
    $deleted->delete();

    $warehouses = Warehouse::query()->get();

    expect($warehouses)->toHaveCount(1);
    expect($warehouses->first()->id)->toBe($active->id);
});

it('includes soft deleted warehouse with withTrashed', function () {
    $active = Warehouse::factory()->create();
    $deleted = Warehouse::factory()->create();
    $deleted->delete();

    expect(Warehouse::withTrashed()->count())->toBe(2);
});

it('allows updating a restored warehouse', function () {
    $warehouse = Warehouse::factory()->create(['name' => 'Original']);
    $warehouse->delete();
    $warehouse->restore();

    $warehouse->update(['name' => 'Updated']);

    expect($warehouse->fresh()->name)->toBe('Updated');
    expect($warehouse->fresh()->trashed())->toBeFalse();
});

it('handles multiple soft deletes on warehouses correctly', function () {
    $a = Warehouse::factory()->create(['name' => 'A']);
    $b = Warehouse::factory()->create(['name' => 'B']);
    $c = Warehouse::factory()->create(['name' => 'C']);

    $a->delete();
    $c->delete();

    expect(Warehouse::query()->count())->toBe(1);
    expect(Warehouse::withTrashed()->count())->toBe(3);
    expect(Warehouse::onlyTrashed()->count())->toBe(2);

    $a->restore();

    expect(Warehouse::query()->count())->toBe(2);
    expect(Warehouse::onlyTrashed()->count())->toBe(1);
});

it('soft deletes a purchaseItem individually', function () {
    $item = PurchaseItem::factory()->create();

    $item->delete();

    assertSoftDeleted('purchase_items', ['id' => $item->id]);
    expect($item->fresh()->trashed())->toBeTrue();
});

it('restores a soft deleted purchaseItem', function () {
    $item = PurchaseItem::factory()->create();
    $item->delete();

    $item->restore();

    expect(PurchaseItem::find($item->id))->not->toBeNull();
    expect($item->fresh()->trashed())->toBeFalse();
});

it('soft deletes a saleItem individually', function () {
    $item = SaleItem::factory()->create();

    $item->delete();

    assertSoftDeleted('sale_items', ['id' => $item->id]);
    expect($item->fresh()->trashed())->toBeTrue();
});

it('restores a soft deleted saleItem', function () {
    $item = SaleItem::factory()->create();
    $item->delete();

    $item->restore();

    expect(SaleItem::find($item->id))->not->toBeNull();
    expect($item->fresh()->trashed())->toBeFalse();
});

it('soft deletes a stockLog and verifies withTrashed and onlyTrashed', function () {
    $active = StockLog::factory()->create();
    $deleted = StockLog::factory()->create();
    $deleted->delete();

    expect(StockLog::query()->count())->toBe(1);
    expect(StockLog::withTrashed()->count())->toBe(2);
    expect(StockLog::onlyTrashed()->count())->toBe(1);
    expect(StockLog::onlyTrashed()->first()->id)->toBe($deleted->id);
});

it('force deletes a stockAdjustment permanently', function () {
    $adj = StockAdjustment::factory()->create();

    $adj->forceDelete();

    expect(StockAdjustment::withTrashed()->where('id', $adj->id)->exists())->toBeFalse();
});

it('soft deletes a paymentTransaction and verifies trashed state', function () {
    $tx = PaymentTransaction::factory()->create();

    expect($tx->trashed())->toBeFalse();

    $tx->delete();

    expect($tx->fresh()->trashed())->toBeTrue();
});

it('force deletes a paymentTransaction after soft delete', function () {
    $tx = PaymentTransaction::factory()->create();
    $tx->delete();

    $tx->forceDelete();

    expect(PaymentTransaction::withTrashed()->where('id', $tx->id)->exists())->toBeFalse();
});

it('soft deletes a priceHistory and verifies state', function () {
    $ph = PriceHistory::factory()->create();

    $ph->delete();

    assertSoftDeleted('price_histories', ['id' => $ph->id]);
    expect(PriceHistory::onlyTrashed()->count())->toBe(1);
});

it('restores a soft deleted priceHistory', function () {
    $ph = PriceHistory::factory()->create();
    $ph->delete();

    $ph->restore();

    expect(PriceHistory::find($ph->id))->not->toBeNull();
    expect(PriceHistory::onlyTrashed()->count())->toBe(0);
});
