<?php

use App\Enums\PaymentMethod;
use App\Enums\ShiftStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sale\PosService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create(['role' => 'cashier']);
    Auth::login($user);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function setupPosShiftAndWarehouse(array $productsWithStock): array
{
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $shift = Shift::factory()->create([
        'user_id' => Auth::id(),
        'status' => ShiftStatus::Open->value,
    ]);

    foreach ($productsWithStock as $productId => $stock) {
        DB::table('warehouse_product')->insert([
            'warehouse_id' => $warehouse->id,
            'product_id' => $productId,
            'stock' => $stock,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    session(['pos_active_warehouse_id' => $warehouse->id]);

    return ['warehouse' => $warehouse, 'shift' => $shift];
}

/*
|--------------------------------------------------------------------------
| Category & Product Tests
|--------------------------------------------------------------------------
*/

it('can get categories for select', function () {
    Category::factory()->count(3)->create();
    $posService = app(PosService::class);

    $categories = $posService->getCategories();

    expect($categories)->toHaveCount(3);
    expect($categories->first())->toHaveKeys(['value', 'label']);
});

it('can get paginated products', function () {
    Product::factory()->count(15)->create();
    $posService = app(PosService::class);

    /** @var LengthAwarePaginator $products */
    $products = $posService->getPaginatedProducts([], 10);

    expect($products->total())->toBe(15);
    expect($products->count())->toBe(10);
});

it('can filter products by category', function () {
    $category = Category::factory()->create(['slug' => 'food']);
    Product::factory()->count(5)->create(['category_id' => $category->id]);
    Product::factory()->count(3)->create();
    $posService = app(PosService::class);

    $products = $posService->getPaginatedProducts(['category' => 'food']);

    expect($products->total())->toBe(5);
});

/*
|--------------------------------------------------------------------------
| Warehouse Tests
|--------------------------------------------------------------------------
*/

it('can set active warehouse', function () {
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $posService = app(PosService::class);

    $posService->setActiveWarehouse($warehouse->id);

    expect(session('pos_active_warehouse_id'))->toBe($warehouse->id);
    expect($posService->getActiveWarehouseId())->toBe($warehouse->id);
});

it('throws when setting inactive warehouse', function () {
    $warehouse = Warehouse::factory()->create(['is_active' => false]);
    $posService = app(PosService::class);

    expect(fn () => $posService->setActiveWarehouse($warehouse->id))
        ->toThrow(ModelNotFoundException::class);
});

it('throws requireWarehouseId when no warehouse set', function () {
    $posService = app(PosService::class);

    expect(fn () => $posService->requireWarehouseId())
        ->toThrow(Exception::class, 'Silakan pilih gudang terlebih dahulu.');
});

it('loads warehouse stock in paginated products', function () {
    $product = Product::factory()->create(['stock' => 100]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    DB::table('warehouse_product')->insert([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'stock' => 25,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    session(['pos_active_warehouse_id' => $warehouse->id]);

    $posService = app(PosService::class);
    $products = $posService->getPaginatedProducts([]);

    expect($products->total())->toBe(1);
    $productData = $products->items()[0];
    expect($productData->warehouse_stock)->toBe(25);
    expect($productData->stock)->toBe(100);
});

/*
|--------------------------------------------------------------------------
| Prerequisites Guard Tests
|--------------------------------------------------------------------------
*/

it('throws when adding to cart without open shift', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $posService = app(PosService::class);

    expect(fn () => $posService->addToCart($product->id))
        ->toThrow(Exception::class, 'Silakan buka shift terlebih dahulu.');
});

it('throws when adding to cart without warehouse', function () {
    $product = Product::factory()->create(['stock' => 10]);
    Shift::factory()->create([
        'user_id' => Auth::id(),
        'status' => ShiftStatus::Open->value,
    ]);
    $posService = app(PosService::class);

    expect(fn () => $posService->addToCart($product->id))
        ->toThrow(Exception::class, 'Silakan pilih gudang terlebih dahulu.');
});

it('throws when updating qty without prerequisites', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $posService = app(PosService::class);

    expect(fn () => $posService->updateCartItemQty($product->id, 1))
        ->toThrow(Exception::class, 'Silakan buka shift terlebih dahulu.');
});

it('throws when adding by barcode without prerequisites', function () {
    $product = Product::factory()->create(['stock' => 10, 'barcode' => '123456']);
    $posService = app(PosService::class);

    expect(fn () => $posService->addToCartByBarcode('123456'))
        ->toThrow(Exception::class, 'Silakan buka shift terlebih dahulu.');
});

/*
|--------------------------------------------------------------------------
| Cart Tests (with prerequisites)
|--------------------------------------------------------------------------
*/

it('can get or create a draft cart', function () {
    $posService = app(PosService::class);
    $cart = $posService->getOrCreateCart();

    expect($cart)->toBeInstanceOf(Sale::class);
    expect($cart->status)->toBe('draft');
    expect($cart->user_id)->toBe(Auth::id());

    $sameCart = $posService->getOrCreateCart();
    expect($sameCart->id)->toBe($cart->id);
});

it('can add item to cart', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    setupPosShiftAndWarehouse([$product->id => 10]);
    $posService = app(PosService::class);

    $result = $posService->addToCart($product->id);

    expect($result['cart']->saleItems)->toHaveCount(1);
    expect((int) $result['cart']->total)->toBe(1000);
    expect((int) $result['total'])->toBe(1000);
});

it('can update item qty in cart', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    setupPosShiftAndWarehouse([$product->id => 10]);
    $posService = app(PosService::class);
    $cart = $posService->getOrCreateCart();
    SaleItem::create([
        'sale_id' => $cart->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 1000,
    ]);

    $result = $posService->updateCartItemQty($product->id, 5);

    expect((int) $result['total'])->toBe(5000);
    expect($cart->fresh()->saleItems->first()->qty)->toBe(5);
});

it('throws exception if updating qty exceeds product stock', function () {
    $product = Product::factory()->create(['stock' => 5, 'selling_price' => 1000]);
    setupPosShiftAndWarehouse([$product->id => 5]);
    $posService = app(PosService::class);
    $cart = $posService->getOrCreateCart();
    SaleItem::create([
        'sale_id' => $cart->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 1000,
    ]);

    $posService->updateCartItemQty($product->id, 10);
})->throws(Exception::class, 'Stok produk tidak mencukupi');

it('removes item from cart if qty is 0', function () {
    $product = Product::factory()->create(['stock' => 10]);
    setupPosShiftAndWarehouse([$product->id => 10]);
    $posService = app(PosService::class);
    $cart = $posService->getOrCreateCart();
    SaleItem::create([
        'sale_id' => $cart->id,
        'product_id' => $product->id,
        'qty' => 1,
        'price' => 1000,
    ]);

    $posService->updateCartItemQty($product->id, 0);

    expect($cart->fresh()->saleItems)->toHaveCount(0);
});

it('can empty the cart', function () {
    $product = Product::factory()->create(['stock' => 10]);
    setupPosShiftAndWarehouse([$product->id => 10]);
    $posService = app(PosService::class);
    $posService->addToCart($product->id);

    $posService->emptyCart();

    $cart = $posService->getOrCreateCart();
    expect($cart->saleItems)->toHaveCount(0);
    expect($cart->total)->toBe('0.0000');
});

it('throws exception if adding product with no warehouse stock', function () {
    $product = Product::factory()->create(['stock' => 100]);
    setupPosShiftAndWarehouse([$product->id => 0]);
    $posService = app(PosService::class);

    $posService->addToCart($product->id);
})->throws(Exception::class, 'Stok produk habis');

it('can add item to cart by barcode', function () {
    $product = Product::factory()->create(['stock' => 10, 'barcode' => '123456', 'selling_price' => 1000]);
    setupPosShiftAndWarehouse([$product->id => 10]);
    $posService = app(PosService::class);

    $result = $posService->addToCartByBarcode('123456');

    expect($result['cart']->saleItems)->toHaveCount(1);
    expect((int) $result['total'])->toBe(1000);
});

it('throws exception if barcode not found', function () {
    $product = Product::factory()->create(['stock' => 10]);
    setupPosShiftAndWarehouse([$product->id => 10]);
    $posService = app(PosService::class);

    $posService->addToCartByBarcode('non-existent');
})->throws(Exception::class, 'Produk dengan barcode tersebut tidak ditemukan');

it('can remove item from cart', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    setupPosShiftAndWarehouse([$product->id => 10]);
    $posService = app(PosService::class);
    $posService->addToCart($product->id);

    $result = $posService->removeFromCart($product->id);

    expect($result['cart']->fresh()->saleItems)->toHaveCount(0);
    expect((int) $result['total'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Warehouse-Aware Cart Tests
|--------------------------------------------------------------------------
*/

it('uses warehouse stock when adding to cart with active warehouse', function () {
    $product = Product::factory()->create(['stock' => 100, 'selling_price' => 1000]);
    setupPosShiftAndWarehouse([$product->id => 5]);
    $posService = app(PosService::class);

    $result = $posService->addToCart($product->id, 5);

    expect($result['cart']->saleItems)->toHaveCount(1);

    expect(fn () => $posService->addToCart($product->id, 1))
        ->toThrow(Exception::class, 'Stok produk tidak mencukupi');
});

it('prevents adding product with zero warehouse stock', function () {
    $product = Product::factory()->create(['stock' => 100]);
    setupPosShiftAndWarehouse([$product->id => 0]);
    $posService = app(PosService::class);

    expect(fn () => $posService->addToCart($product->id))
        ->toThrow(Exception::class, 'Stok produk habis');
});

/*
|--------------------------------------------------------------------------
| Checkout Tests (Warehouse-Aware)
|--------------------------------------------------------------------------
*/

it('can checkout a sale successfully', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    setupPosShiftAndWarehouse([$product->id => 10]);
    $posService = app(PosService::class);
    $posService->addToCart($product->id);

    $checkoutData = [
        'payment_method' => 'cash',
        'customer_name' => 'John Doe',
        'paid' => 1000,
        'change' => 0,
    ];

    $result = $posService->checkout($checkoutData);

    expect($result['cart']->status)->toBe('draft');

    $completedSale = Sale::where('customer_name', 'John Doe')->first();
    expect($completedSale->status)->toBe('completed');
    expect($completedSale->warehouse_id)->not->toBeNull();
    expect($product->fresh()->stock)->toBe(9);
});

it('can checkout with QRIS successfully', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    setupPosShiftAndWarehouse([$product->id => 10]);
    $posService = app(PosService::class);
    $posService->addToCart($product->id);

    $checkoutData = [
        'payment_method' => 'qris',
        'customer_name' => 'John Doe',
        'order_id' => 'TRX-123',
    ];

    $result = $posService->checkout($checkoutData);

    $pendingSale = Sale::where('customer_name', 'John Doe')->first();
    expect($pendingSale->status)->toBe('pending');
    expect($pendingSale->payment_method)->toBe('qris');
    expect($pendingSale->warehouse_id)->not->toBeNull();

    assertDatabaseHas('payment_transactions', [
        'sale_id' => $pendingSale->id,
        'external_id' => 'TRX-123',
        'status' => 'pending',
    ]);

    expect($product->fresh()->stock)->toBe(10);
});

it('throws exception if cash is insufficient', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 5000]);
    setupPosShiftAndWarehouse([$product->id => 10]);
    $posService = app(PosService::class);
    $posService->addToCart($product->id);

    $checkoutData = [
        'payment_method' => 'cash',
        'paid' => 1000,
    ];

    $posService->checkout($checkoutData);
})->throws(Exception::class, 'Jumlah uang pembayaran kurang dari total belanja.');

it('throws exception if cart is empty on checkout', function () {
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    Shift::factory()->create(['user_id' => Auth::id(), 'status' => ShiftStatus::Open->value]);
    session(['pos_active_warehouse_id' => $warehouse->id]);
    $posService = app(PosService::class);
    $posService->getOrCreateCart();

    $checkoutData = [
        'payment_method' => 'cash',
        'paid' => 1000,
    ];

    expect(fn () => $posService->checkout($checkoutData))
        ->toThrow(Exception::class, 'Keranjang kosong, tidak bisa checkout');
});

it('throws exception if shift is not open before checkout', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    session(['pos_active_warehouse_id' => $warehouse->id]);
    $posService = app(PosService::class);
    $posService->addToCart($product->id);
})->throws(Exception::class, 'Silakan buka shift terlebih dahulu.');

it('throws exception if warehouse is not set before checkout', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    Shift::factory()->create(['user_id' => Auth::id(), 'status' => ShiftStatus::Open->value]);
    $posService = app(PosService::class);
    $posService->addToCart($product->id);
})->throws(Exception::class, 'Silakan pilih gudang terlebih dahulu.');

it('throws exception if stock becomes insufficient at checkout', function () {
    $product = Product::factory()->create(['stock' => 5, 'selling_price' => 1000]);
    ['warehouse' => $warehouse] = setupPosShiftAndWarehouse([$product->id => 5]);
    $posService = app(PosService::class);
    $posService->addToCart($product->id, 5);

    DB::table('warehouse_product')
        ->where('warehouse_id', $warehouse->id)
        ->where('product_id', $product->id)
        ->update(['stock' => 2]);

    $checkoutData = [
        'payment_method' => 'cash',
        'paid' => 5000,
    ];

    expect(fn () => $posService->checkout($checkoutData))
        ->toThrow(Exception::class, "Stok produk {$product->name} tidak mencukupi untuk checkout.");
});

/*
|--------------------------------------------------------------------------
| getActiveShiftId Memoization Tests (once)
|--------------------------------------------------------------------------
*/

it('memoizes getActiveShiftId — queries shift once during addToCart despite multiple calls', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    setupPosShiftAndWarehouse([$product->id => 10]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $posService = app(PosService::class);
    $posService->addToCart($product->id);

    DB::disableQueryLog();

    $shiftQueries = collect(DB::getQueryLog())
        ->filter(fn ($log) => str_contains($log['query'], 'shifts'))
        ->count();

    expect($shiftQueries)->toBe(1);
});

it('memoizes getActiveShiftId — queries shift once during checkout despite multiple calls', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    session(['pos_active_warehouse_id' => $warehouse->id]);

    Shift::factory()->create([
        'user_id' => Auth::id(),
        'status' => ShiftStatus::Open->value,
    ]);

    $cart = Sale::factory()->create([
        'user_id' => Auth::id(),
        'warehouse_id' => $warehouse->id,
        'shift_id' => null,
        'status' => 'draft',
        'total' => 2000,
        'date' => now(),
    ]);

    SaleItem::factory()->create([
        'sale_id' => $cart->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty' => 2,
        'price' => 1000,
    ]);

    DB::table('warehouse_product')->insert([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'stock' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $posService = app(PosService::class);
    $posService->checkout([
        'payment_method' => 'cash',
        'paid' => 2000,
    ]);

    DB::disableQueryLog();

    $shiftQueries = collect(DB::getQueryLog())
        ->filter(fn ($log) => str_contains($log['query'], 'shifts'))
        ->count();

    expect($shiftQueries)->toBe(1);
});

it('different PosService instances do not share once cache', function () {
    Shift::factory()->create([
        'user_id' => Auth::id(),
        'status' => ShiftStatus::Open->value,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $service1 = app(PosService::class);
    $cart1 = $service1->getOrCreateCart();

    $cart1->forceDelete();

    $service2 = app(PosService::class);
    $service2->getOrCreateCart();

    DB::disableQueryLog();

    $shiftQueries = collect(DB::getQueryLog())
        ->filter(fn ($log) => str_contains($log['query'], 'shifts'))
        ->count();

    expect($shiftQueries)->toBe(2);
});

it('enforces only one draft sale per user via database unique constraint', function () {
    $posService = app(PosService::class);
    $cart = $posService->getOrCreateCart();

    expect($cart->status)->toBe('draft');

    // Attempting to insert another draft directly should violate the unique constraint
    $threw = false;
    try {
        Sale::create([
            'user_id' => Auth::id(),
            'shift_id' => null,
            'warehouse_id' => null,
            'total' => 0,
            'payment_method' => PaymentMethod::Pending->value,
            'paid' => 0,
            'change' => 0,
            'date' => now(),
            'status' => 'draft',
        ]);
    } catch (UniqueConstraintViolationException $e) {
        $threw = true;
    }

    expect($threw)->toBeTrue();
});

it('returns existing draft sale instead of creating a duplicate', function () {
    $posService = app(PosService::class);
    $cart1 = $posService->getOrCreateCart();

    $cart2 = $posService->getOrCreateCart();

    expect($cart2->id)->toBe($cart1->id);
    expect(Sale::where('user_id', Auth::id())->where('status', 'draft')->count())->toBe(1);
});
