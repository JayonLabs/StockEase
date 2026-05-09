<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Sale\PosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create(['role' => 'cashier']);
    Auth::login($user);
});

it('can get categories for select', function () {
    Category::factory()->count(3)->create();
    $posService = new PosService;

    $categories = $posService->getCategories();

    expect($categories)->toHaveCount(3);
    expect($categories->first())->toHaveKeys(['value', 'label']);
});

it('can get paginated products', function () {
    Product::factory()->count(15)->create();
    $posService = new PosService;

    /** @var LengthAwarePaginator $products */
    $products = $posService->getPaginatedProducts([], 10);

    expect($products->total())->toBe(15);
    expect($products->count())->toBe(10);
});

it('can filter products by category', function () {
    $category = Category::factory()->create(['slug' => 'food']);
    Product::factory()->count(5)->create(['category_id' => $category->id]);
    Product::factory()->count(3)->create(); // other categories
    $posService = new PosService;

    $products = $posService->getPaginatedProducts(['category' => 'food']);

    expect($products->total())->toBe(5);
});

it('can get or create a draft cart', function () {
    $posService = new PosService;
    $cart = $posService->getOrCreateCart();

    expect($cart)->toBeInstanceOf(Sale::class);
    expect($cart->status)->toBe('draft');
    expect($cart->user_id)->toBe(Auth::id());

    // Test that it returns existing draft
    $sameCart = $posService->getOrCreateCart();
    expect($sameCart->id)->toBe($cart->id);
});

it('can add item to cart', function () {
    expect(Auth::check())->toBeTrue();
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    $posService = new PosService;

    $result = $posService->addToCart($product->id);

    expect($result['cart']->saleItems)->toHaveCount(1);
    expect((int) $result['cart']->total)->toBe(1000);
    expect((int) $result['total'])->toBe(1000);
});

it('can update item qty in cart', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    $posService = new PosService;
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
    $posService = new PosService;
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
    $posService = new PosService;
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
    $posService = new PosService;
    $posService->addToCart($product->id);

    $posService->emptyCart();

    $cart = $posService->getOrCreateCart();
    expect($cart->saleItems)->toHaveCount(0);
    expect($cart->total)->toBe('0.0000');
});

it('throws exception if adding product with no stock', function () {
    $product = Product::factory()->create(['stock' => 0]);
    $posService = new PosService;

    $posService->addToCart($product->id);
})->throws(Exception::class, 'Stok produk habis');

it('can add item to cart by barcode', function () {
    $product = Product::factory()->create(['stock' => 10, 'barcode' => '123456', 'selling_price' => 1000]);
    $posService = new PosService;

    $result = $posService->addToCartByBarcode('123456');

    expect($result['cart']->saleItems)->toHaveCount(1);
    expect((int) $result['total'])->toBe(1000);
});

it('throws exception if barcode not found', function () {
    $posService = new PosService;
    $posService->addToCartByBarcode('non-existent');
})->throws(Exception::class, 'Produk dengan barcode tersebut tidak ditemukan');

it('can remove item from cart', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    $posService = new PosService;
    $posService->addToCart($product->id);

    $result = $posService->removeFromCart($product->id);

    expect($result['cart']->fresh()->saleItems)->toHaveCount(0);
    expect((int) $result['total'])->toBe(0);
});

it('can checkout a sale successfully', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    $posService = new PosService;
    $posService->addToCart($product->id);

    $checkoutData = [
        'payment_method' => 'cash',
        'customer_name' => 'John Doe',
        'paid' => 1000,
        'change' => 0,
    ];

    $result = $posService->checkout($checkoutData);

    expect($result['cart']->status)->toBe('draft'); // New draft created after checkout

    $completedSale = Sale::where('customer_name', 'John Doe')->first();
    expect($completedSale->status)->toBe('completed');
    expect($product->fresh()->stock)->toBe(9);
});

it('can checkout with QRIS successfully', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    $posService = new PosService;
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

    assertDatabaseHas('payment_transactions', [
        'sale_id' => $pendingSale->id,
        'external_id' => 'TRX-123',
        'status' => 'pending',
    ]);

    // Stock should NOT be reduced yet for QRIS until payment is confirmed (webhook)
    expect($product->fresh()->stock)->toBe(10);
});

it('throws exception if cash is insufficient', function () {
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 5000]);
    $posService = new PosService;
    $posService->addToCart($product->id);

    $checkoutData = [
        'payment_method' => 'cash',
        'paid' => 1000,
    ];

    $posService->checkout($checkoutData);
})->throws(Exception::class, 'Jumlah uang pembayaran kurang dari total belanja.');

it('throws exception if cart is empty on checkout', function () {
    $posService = new PosService;
    $posService->getOrCreateCart(); // Ensure draft exists but empty

    $checkoutData = [
        'payment_method' => 'cash',
        'paid' => 1000,
    ];

    expect(fn () => $posService->checkout($checkoutData))
        ->toThrow(Exception::class, 'Keranjang kosong, tidak bisa checkout');
});

it('throws exception if stock becomes insufficient at checkout', function () {
    $product = Product::factory()->create(['stock' => 5, 'selling_price' => 1000]);
    $posService = new PosService;
    $posService->addToCart($product->id, 5);

    // Simulate stock reduction by another process
    $product->update(['stock' => 2]);

    $checkoutData = [
        'payment_method' => 'cash',
        'paid' => 5000,
    ];

    expect(fn () => $posService->checkout($checkoutData))
        ->toThrow(Exception::class, "Stok produk {$product->name} tidak mencukupi untuk checkout.");
});
