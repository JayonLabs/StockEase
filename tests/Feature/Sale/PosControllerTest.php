<?php

use App\Actions\Sale\RecalculateSaleTotal;
use App\Enums\SaleStatus;
use App\Enums\ShiftStatus;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sale\PosService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(LazilyRefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function setupPosPrerequisites(array $productsWithStock, User $user): array
{
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $shift = Shift::factory()->create([
        'user_id' => $user->id,
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
| Access Tests
|--------------------------------------------------------------------------
*/

it('allows admin and cashier to access POS', function ($role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);
    $response = actingAs($user)->get(route('pos.index'));
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Pos/Index'));
})->with(['admin', 'cashier']);

it('denies warehouse role from accessing POS', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'warehouse']);
    $response = actingAs($user)->get(route('pos.index'));
    $response->assertForbidden();
});

it('shows hasActiveShift false when no open shift', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $response = actingAs($cashier)->get(route('pos.index'));
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Pos/Index')->where('hasActiveShift', false));
});

it('shows hasActiveShift true when shift is open', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    Shift::factory()->create(['user_id' => $cashier->id, 'status' => ShiftStatus::Open->value]);
    $response = actingAs($cashier)->get(route('pos.index'));
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Pos/Index')->where('hasActiveShift', true));
});

/*
|--------------------------------------------------------------------------
| Cart Operations Tests
|--------------------------------------------------------------------------
*/

it('provides a new cart with loaded relations on first visit', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    Sale::where('user_id', $cashier->id)->where('status', 'draft')->delete();
    $response = actingAs($cashier)->get(route('pos.index'));
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Pos/Index')->has('cart')->has('cart.sale_items')->where('cart.sale_items', []));
});

it('can get cart data as JSON', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft']);
    $response = actingAs($cashier)->getJson(route('pos.get-cart'));
    $response->assertOk();
    $response->assertJsonPath('cart.id', $sale->id);
});

it('can add product to cart with prerequisites met', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    setupPosPrerequisites([$product->id => 10], $cashier);
    $response = actingAs($cashier)->postJson(route('pos.add-to-cart'), ['product_id' => $product->id]);
    $response->assertOk();
    assertDatabaseHas('sale_items', ['product_id' => $product->id, 'qty' => 1]);
});

it('can add product to cart by barcode with prerequisites met', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10, 'barcode' => '88888888']);
    setupPosPrerequisites([$product->id => 10], $cashier);
    $response = actingAs($cashier)->postJson(route('pos.add-to-cart-barcode'), ['barcode' => '88888888']);
    $response->assertOk();
    assertDatabaseHas('sale_items', ['product_id' => $product->id, 'qty' => 1]);
});

it('increases quantity when adding the same product multiple times', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    setupPosPrerequisites([$product->id => 10], $cashier);
    actingAs($cashier)->postJson(route('pos.add-to-cart'), ['product_id' => $product->id]);
    $response = actingAs($cashier)->postJson(route('pos.add-to-cart'), ['product_id' => $product->id]);
    $response->assertOk();
    assertDatabaseHas('sale_items', ['product_id' => $product->id, 'qty' => 2]);
});

it('prevents adding the same product if the resulting qty exceeds stock', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 5]);
    setupPosPrerequisites([$product->id => 5], $cashier);
    actingAs($cashier)->postJson(route('pos.add-to-cart'), ['product_id' => $product->id, 'qty' => 3])->assertOk();
    $response = actingAs($cashier)->postJson(route('pos.add-to-cart'), ['product_id' => $product->id, 'qty' => 3]);
    $response->assertBadRequest();
    $response->assertJsonPath('message', 'Stok produk tidak mencukupi');
    assertDatabaseHas('sale_items', ['product_id' => $product->id, 'qty' => 3]);
});

it('returns error if product not found by barcode', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    setupPosPrerequisites([], $cashier);
    $response = actingAs($cashier)->postJson(route('pos.add-to-cart-barcode'), ['barcode' => '99999999']);
    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['barcode']);
});

it('prevents adding product with zero warehouse stock', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 100]);
    setupPosPrerequisites([$product->id => 0], $cashier);
    $response = actingAs($cashier)->postJson(route('pos.add-to-cart'), ['product_id' => $product->id]);
    $response->assertBadRequest();
    $response->assertJsonPath('message', 'Stok produk habis');
});

it('can change product qty in cart', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    setupPosPrerequisites([$product->id => 10], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft']);
    $saleItem = $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => $product->selling_price]);
    $response = actingAs($cashier)->patchJson(route('pos.change-qty'), ['product_id' => $product->id, 'qty' => 5]);
    $response->assertOk();
    assertDatabaseHas('sale_items', ['id' => $saleItem->id, 'qty' => 5]);
});

it('prevents setting qty higher than available stock', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 5]);
    setupPosPrerequisites([$product->id => 5], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft']);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => $product->selling_price]);
    $response = actingAs($cashier)->patchJson(route('pos.change-qty'), ['product_id' => $product->id, 'qty' => 10]);
    $response->assertBadRequest();
    $response->assertJsonPath('message', 'Stok produk tidak mencukupi');
});

it('can remove product from cart using remove-from-cart route', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft']);
    $saleItem = $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => $product->selling_price]);
    $response = actingAs($cashier)->deleteJson(route('pos.remove-from-cart'), ['product_id' => $product->id]);
    $response->assertOk();
    assertDatabaseMissing('sale_items', ['id' => $saleItem->id]);
});

it('can remove product from cart by setting qty to zero via change-qty', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    setupPosPrerequisites([$product->id => 10], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft']);
    $saleItem = $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => $product->selling_price]);
    $response = actingAs($cashier)->patchJson(route('pos.change-qty'), ['product_id' => $product->id, 'qty' => 0]);
    $response->assertOk();
    assertDatabaseMissing('sale_items', ['id' => $saleItem->id]);
});

it('can empty the entire cart', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product1 = Product::factory()->create(['stock' => 10]);
    $product2 = Product::factory()->create(['stock' => 10]);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft']);
    $sale->saleItems()->createMany([
        ['product_id' => $product1->id, 'qty' => 1, 'price' => 100],
        ['product_id' => $product2->id, 'qty' => 1, 'price' => 100],
    ]);
    $response = actingAs($cashier)->deleteJson(route('pos.empty-cart'));
    $response->assertOk();
    assertDatabaseMissing('sale_items', ['sale_id' => $sale->id]);
});

/*
|--------------------------------------------------------------------------
| Prerequisites Guard Tests
|--------------------------------------------------------------------------
*/

it('prevents add to cart without open shift', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    $response = actingAs($cashier)->postJson(route('pos.add-to-cart'), ['product_id' => $product->id]);
    $response->assertBadRequest();
    $response->assertJsonPath('message', 'Silakan buka shift terlebih dahulu.');
});

it('prevents add to cart without warehouse selected', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    Shift::factory()->create(['user_id' => $cashier->id, 'status' => ShiftStatus::Open->value]);
    $response = actingAs($cashier)->postJson(route('pos.add-to-cart'), ['product_id' => $product->id]);
    $response->assertBadRequest();
    $response->assertJsonPath('message', 'Silakan pilih gudang terlebih dahulu.');
});

it('prevents checkout without open shift', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    session(['pos_active_warehouse_id' => $warehouse->id]);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft', 'total' => 1000]);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => 1000]);
    $response = actingAs($cashier)->putJson(route('pos.checkout'), ['payment_method' => 'cash', 'paid' => 1000]);
    $response->assertBadRequest();
    $response->assertJsonPath('message', 'Silakan buka shift terlebih dahulu.');
});

/*
|--------------------------------------------------------------------------
| Checkout Tests (Warehouse-Aware)
|--------------------------------------------------------------------------
*/

it('can checkout with cash and reduces warehouse stock', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10, 'name' => 'Test Product']);
    ['warehouse' => $warehouse] = setupPosPrerequisites([$product->id => 10], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft', 'total' => 1000]);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 2, 'price' => 500]);
    $response = actingAs($cashier)->putJson(route('pos.checkout'), ['payment_method' => 'cash', 'customer_name' => 'John Doe', 'paid' => 1000, 'change' => 0]);
    $response->assertOk();
    assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'completed', 'payment_method' => 'cash', 'warehouse_id' => $warehouse->id]);
    $product->refresh();
    expect($product->stock)->toBe(8);
    assertDatabaseHas('warehouse_product', ['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'stock' => 8]);
});

it('can checkout with QRIS and creates a payment transaction', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    ['warehouse' => $warehouse] = setupPosPrerequisites([$product->id => 10], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft', 'total' => 5000]);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => 5000]);
    $response = actingAs($cashier)->putJson(route('pos.checkout'), ['payment_method' => 'qris', 'customer_name' => 'QRIS Customer', 'order_id' => 'TRX-12345']);
    $response->assertOk();
    assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'pending', 'payment_method' => 'qris', 'warehouse_id' => $warehouse->id]);
    assertDatabaseHas('payment_transactions', ['sale_id' => $sale->id, 'external_id' => 'TRX-12345', 'status' => 'pending', 'payment_type' => 'qris']);
});

it('returns completed_sale_id in JSON response when cash checkout completes', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    setupPosPrerequisites([$product->id => 10], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => SaleStatus::Draft->value, 'total' => 1000]);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => 1000]);
    $response = actingAs($cashier)->putJson(route('pos.checkout'), ['payment_method' => 'cash', 'paid' => 1000, 'change' => 0]);
    $response->assertOk();
    $response->assertJsonPath('completed_sale_id', $sale->id);
});

it('returns null completed_sale_id in JSON response when QRIS checkout is pending', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    setupPosPrerequisites([$product->id => 10], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => SaleStatus::Draft->value, 'total' => 5000]);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => 5000]);
    $response = actingAs($cashier)->putJson(route('pos.checkout'), ['payment_method' => 'qris', 'order_id' => 'TRX-99999']);
    $response->assertOk();
    $response->assertJsonPath('completed_sale_id', null);
});

it('stores validated order_id from request without overwriting with raw input', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    setupPosPrerequisites([$product->id => 10], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft', 'total' => 5000]);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => 5000]);

    $response = actingAs($cashier)->putJson(route('pos.checkout'), [
        'payment_method' => 'qris',
        'order_id' => 'VALID-ORDER-123',
    ]);

    $response->assertOk();
    assertDatabaseHas('payment_transactions', [
        'sale_id' => $sale->id,
        'external_id' => 'VALID-ORDER-123',
        'status' => 'pending',
    ]);
});

it('handles checkout without order_id when payment method is cash', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    setupPosPrerequisites([$product->id => 10], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft', 'total' => 1000]);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => 1000]);

    $response = actingAs($cashier)->putJson(route('pos.checkout'), [
        'payment_method' => 'cash',
        'paid' => 1000,
    ]);

    $response->assertOk();
    assertDatabaseHas('sales', [
        'id' => $sale->id,
        'status' => 'completed',
        'payment_method' => 'cash',
    ]);
});

it('prevents checkout if warehouse stock is insufficient', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 1, 'name' => 'Low Stock Product']);
    setupPosPrerequisites([$product->id => 1], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft', 'total' => 1000]);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 2, 'price' => 500]);
    $response = actingAs($cashier)->putJson(route('pos.checkout'), ['payment_method' => 'cash', 'customer_name' => 'John Doe', 'paid' => 1000, 'change' => 0]);
    $response->assertBadRequest();
    $response->assertJsonPath('message', 'Stok produk Low Stock Product tidak mencukupi untuk checkout.');
});

it('prevents checkout if paid amount is less than total', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10]);
    setupPosPrerequisites([$product->id => 10], $cashier);
    $sale = Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft', 'total' => 1000]);
    $sale->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => 1000]);
    $response = actingAs($cashier)->putJson(route('pos.checkout'), ['payment_method' => 'cash', 'paid' => 500]);
    $response->assertBadRequest();
    $response->assertJsonPath('message', 'Jumlah uang pembayaran kurang dari total belanja.');
});

it('prevents checkout with an empty cart', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    setupPosPrerequisites([], $cashier);
    Sale::factory()->create(['user_id' => $cashier->id, 'status' => 'draft', 'total' => 0]);
    $response = actingAs($cashier)->putJson(route('pos.checkout'), ['payment_method' => 'cash', 'paid' => 0]);
    $response->assertBadRequest();
    $response->assertJsonPath('message', 'Keranjang kosong, tidak bisa checkout');
});

/*
|--------------------------------------------------------------------------
| Warehouse Selection Tests
|--------------------------------------------------------------------------
*/

it('can set active warehouse via API', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $response = actingAs($cashier)->postJson(route('pos.set-warehouse'), ['warehouse_id' => $warehouse->id]);
    $response->assertOk();
    $response->assertJsonPath('warehouse.id', $warehouse->id);
    expect(session('pos_active_warehouse_id'))->toBe($warehouse->id);
});

it('cannot set inactive warehouse', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $warehouse = Warehouse::factory()->create(['is_active' => false]);
    $response = actingAs($cashier)->postJson(route('pos.set-warehouse'), ['warehouse_id' => $warehouse->id]);
    $response->assertBadRequest();
});

it('shows warehouse stock when warehouse is active', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 100]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    DB::table('warehouse_product')->insert(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'stock' => 25, 'created_at' => now(), 'updated_at' => now()]);
    session(['pos_active_warehouse_id' => $warehouse->id]);
    $response = actingAs($cashier)->get(route('pos.index'));
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Pos/Index')->has('products.data'));
});

it('adds to cart using warehouse stock when prerequisites met', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 100]);
    setupPosPrerequisites([$product->id => 3], $cashier);
    $response = actingAs($cashier)->postJson(route('pos.add-to-cart'), ['product_id' => $product->id, 'qty' => 3]);
    $response->assertOk();
    $response = actingAs($cashier)->postJson(route('pos.add-to-cart'), ['product_id' => $product->id, 'qty' => 1]);
    $response->assertBadRequest();
    $response->assertJsonPath('message', 'Stok produk tidak mencukupi');
});

/*
|--------------------------------------------------------------------------
| End-to-End Workflow Tests
|--------------------------------------------------------------------------
*/

it('can complete a full end-to-end POS cash transaction workflow', function () {
    /** @var User $cashier */
    $cashier = User::factory()->create(['role' => 'cashier']);
    $productA = Product::factory()->create(['stock' => 50, 'selling_price' => 10000, 'barcode' => '111111']);
    $productB = Product::factory()->create(['stock' => 20, 'selling_price' => 50000]);
    ['warehouse' => $warehouse] = setupPosPrerequisites([$productA->id => 50, $productB->id => 20], $cashier);

    actingAs($cashier)->get(route('pos.index'))->assertSuccessful();
    postJson(route('pos.add-to-cart-barcode'), ['barcode' => '111111'])->assertOk();
    postJson(route('pos.add-to-cart'), ['product_id' => $productB->id])->assertOk();
    patchJson(route('pos.change-qty'), ['product_id' => $productA->id, 'qty' => 3])->assertOk();

    $cartResponse = getJson(route('pos.get-cart'))->assertOk();
    $cartId = $cartResponse->json('cart.id');
    expect($cartResponse->json('cart.total'))->toEqual(80000);

    putJson(route('pos.checkout'), ['payment_method' => 'cash', 'customer_name' => 'E2E Customer', 'paid' => 100000, 'change' => 20000])->assertOk();

    assertDatabaseHas('sales', ['id' => $cartId, 'status' => 'completed', 'total' => 80000, 'paid' => 100000, 'change' => 20000, 'payment_method' => 'cash', 'customer_name' => 'E2E Customer', 'warehouse_id' => $warehouse->id]);
    expect(DB::table('warehouse_product')->where('warehouse_id', $warehouse->id)->where('product_id', $productA->id)->value('stock'))->toBe(47);
    expect(DB::table('warehouse_product')->where('warehouse_id', $warehouse->id)->where('product_id', $productB->id)->value('stock'))->toBe(19);
    $productA->refresh();
    $productB->refresh();
    expect($productA->stock)->toBe(47);
    expect($productB->stock)->toBe(19);
});

it('fails when paid amount is out of range', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['stock' => 10, 'selling_price' => 1000]);
    setupPosPrerequisites([$product->id => 10], $user);
    $sale = Sale::factory()->create(['user_id' => $user->id, 'total' => 1000, 'status' => 'draft']);
    $response = actingAs($user)->putJson(route('pos.checkout'), ['payment_method' => 'cash', 'paid' => '9999999999999999999999999999999999', 'customer_name' => 'Test Customer']);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['paid']);
});

it('does not execute duplicate queries when fetching the cart', function () {
    /** @var User $user */
    $user = User::factory()->create();
    actingAs($user);
    $service = app(PosService::class);
    $cart = Sale::create(['user_id' => $user->id, 'total' => 0, 'payment_method' => 'pending', 'paid' => 0, 'change' => 0, 'date' => now(), 'status' => 'draft']);
    $product = Product::factory()->create(['selling_price' => 100]);
    $cart->saleItems()->create(['product_id' => $product->id, 'qty' => 1, 'price' => 100]);
    DB::enableQueryLog();
    $fetchedCart = $service->getOrCreateCart();
    $queries = DB::getQueryLog();
    $saleItemQueries = collect($queries)->filter(fn ($query) => str_contains($query['query'], 'select * from `sale_items` where `sale_items`.`sale_id`'));
    expect($saleItemQueries)->toHaveCount(1);
    expect($fetchedCart->id)->toEqual($cart->id);
});

it('adds a product to the cart without redundant relation queries', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = Product::factory()->create(['selling_price' => 500, 'stock' => 10]);
    Shift::factory()->create(['user_id' => $user->id, 'status' => ShiftStatus::Open->value]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    DB::table('warehouse_product')->insert(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'stock' => 10, 'created_at' => now(), 'updated_at' => now()]);
    session(['pos_active_warehouse_id' => $warehouse->id]);
    actingAs($user);
    $service = app(PosService::class);
    $service->getOrCreateCart();
    DB::enableQueryLog();
    $result = $service->addToCart($product->id, 2);
    $queries = DB::getQueryLog();
    $saleItemQueries = collect($queries)->filter(fn ($query) => str_contains($query['query'], 'select * from `sale_items` where'));
    expect($saleItemQueries)->toHaveCount(1)->and($result['total'])->toEqual(1000);
    assertDatabaseHas('sales', ['id' => $result['cart']->id, 'total' => 1000]);
    assertDatabaseHas('sale_items', ['sale_id' => $result['cart']->id, 'product_id' => $product->id, 'qty' => 2]);
});

it('updates product quantity and recalculates total correctly', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product = Product::factory()->create(['selling_price' => 300, 'stock' => 10]);
    Shift::factory()->create(['user_id' => $user->id, 'status' => ShiftStatus::Open->value]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    DB::table('warehouse_product')->insert(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'stock' => 10, 'created_at' => now(), 'updated_at' => now()]);
    session(['pos_active_warehouse_id' => $warehouse->id]);
    actingAs($user);
    $service = app(PosService::class);
    $service->addToCart($product->id, 1);
    DB::enableQueryLog();
    $result = $service->updateCartItemQty($product->id, 3);
    expect($result['total'])->toEqual(900);
    assertDatabaseHas('sales', ['id' => $result['cart']->id, 'total' => 900]);
    assertDatabaseHas('sale_items', ['sale_id' => $result['cart']->id, 'product_id' => $product->id, 'qty' => 3]);
    $result2 = $service->updateCartItemQty($product->id, 0);
    expect($result2['total'])->toEqual(0);
    assertDatabaseMissing('sale_items', ['sale_id' => $result2['cart']->id, 'product_id' => $product->id]);
});

it('removes a product and empties the cart correctly', function () {
    /** @var User $user */
    $user = User::factory()->create();
    $product1 = Product::factory()->create(['selling_price' => 200, 'stock' => 10]);
    $product2 = Product::factory()->create(['selling_price' => 400, 'stock' => 10]);
    Shift::factory()->create(['user_id' => $user->id, 'status' => ShiftStatus::Open->value]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    DB::table('warehouse_product')->insert([
        ['warehouse_id' => $warehouse->id, 'product_id' => $product1->id, 'stock' => 10, 'created_at' => now(), 'updated_at' => now()],
        ['warehouse_id' => $warehouse->id, 'product_id' => $product2->id, 'stock' => 10, 'created_at' => now(), 'updated_at' => now()],
    ]);
    session(['pos_active_warehouse_id' => $warehouse->id]);
    actingAs($user);
    $service = app(PosService::class);
    $service->addToCart($product1->id, 1);
    $result = $service->addToCart($product2->id, 2);
    expect($result['total'])->toEqual(1000);
    $result = $service->removeFromCart($product1->id);
    expect($result['total'])->toEqual(800);
    assertDatabaseMissing('sale_items', ['sale_id' => $result['cart']->id, 'product_id' => $product1->id]);
    $result = $service->emptyCart();
    expect($result['total'])->toEqual(0);
    expect($result['cart']->saleItems)->toBeEmpty();
    assertDatabaseMissing('sale_items', ['sale_id' => $result['cart']->id]);
});

it('prevents checkout if paid amount is less than total expect to be draft', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    actingAs($user);
    $product = Product::factory()->create(['selling_price' => 50000, 'stock' => 10]);
    setupPosPrerequisites([$product->id => 10], $user);
    $sale = Sale::create(['user_id' => $user->id, 'total' => 50000, 'payment_method' => 'pending', 'paid' => 0, 'change' => 0, 'status' => 'draft', 'date' => now()]);
    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'qty' => 1, 'price' => 50000]);
    $response = putJson(route('pos.checkout'), ['payment_method' => 'cash', 'paid' => 1]);
    $response->assertStatus(400);
    $response->assertJson(['message' => 'Jumlah uang pembayaran kurang dari total belanja.']);
    expect($sale->refresh()->status)->toBe('draft');
});

it('successfully completes cash checkout and reduces warehouse stock', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    actingAs($user);
    $product = Product::factory()->create(['selling_price' => 50000, 'stock' => 10]);
    ['warehouse' => $warehouse] = setupPosPrerequisites([$product->id => 10], $user);
    $sale = Sale::create(['user_id' => $user->id, 'total' => 50000, 'payment_method' => 'pending', 'paid' => 0, 'change' => 0, 'status' => 'draft', 'date' => now()]);
    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'qty' => 2, 'price' => 50000]);
    resolve(RecalculateSaleTotal::class)->execute($sale);
    $response = putJson(route('pos.checkout'), ['payment_method' => 'cash', 'paid' => 120000]);
    $response->assertSuccessful();
    $sale->refresh();
    expect($sale->status)->toBe('completed');
    expect($sale->paid)->toEqual(120000.0);
    expect($sale->change)->toEqual(20000.0);
    expect($sale->warehouse_id)->toBe($warehouse->id);
    expect(DB::table('warehouse_product')->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('stock'))->toBe(8);
    expect($product->refresh()->stock)->toBe(8);
});

it('sets QRIS checkout to pending and does not reduce stock immediately', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    actingAs($user);
    $product = Product::factory()->create(['selling_price' => 50000, 'stock' => 10]);
    ['warehouse' => $warehouse] = setupPosPrerequisites([$product->id => 10], $user);
    $sale = Sale::create(['user_id' => $user->id, 'total' => 50000, 'payment_method' => 'pending', 'paid' => 0, 'change' => 0, 'status' => 'draft', 'date' => now()]);
    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'qty' => 1, 'price' => 50000]);
    $response = putJson(route('pos.checkout'), ['payment_method' => 'qris', 'order_id' => 'TRX-123']);
    $response->assertSuccessful();
    $sale->refresh();
    expect($sale->status)->toBe('pending');
    expect($sale->warehouse_id)->toBe($warehouse->id);
    expect($product->refresh()->stock)->toBe(10);
    expect(DB::table('warehouse_product')->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('stock'))->toBe(10);
    $transaction = PaymentTransaction::where('external_id', 'TRX-123')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe('pending');
});

it('completes sale via midtrans webhook with warehouse', function () {
    /** @var User $user */
    $user = User::factory()->create(['role' => 'cashier']);
    $product = Product::factory()->create(['selling_price' => 50000, 'stock' => 10]);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    DB::table('warehouse_product')->insert(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'stock' => 10, 'created_at' => now(), 'updated_at' => now()]);
    $sale = Sale::create(['user_id' => $user->id, 'warehouse_id' => $warehouse->id, 'total' => 50000, 'payment_method' => 'qris', 'paid' => 0, 'change' => 0, 'status' => 'pending', 'date' => now()]);
    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'qty' => 1, 'price' => 50000]);
    $paymentTransaction = PaymentTransaction::create(['sale_id' => $sale->id, 'gateway' => 'midtrans', 'external_id' => 'TRX-999', 'status' => 'pending', 'amount' => 50000, 'payment_type' => 'qris']);
    $serverKey = config('midtrans.server_key');
    $orderId = 'TRX-999';
    $statusCode = '200';
    $grossAmount = '50000.00';
    $validSignatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);
    $payload = ['order_id' => $orderId, 'status_code' => $statusCode, 'gross_amount' => $grossAmount, 'signature_key' => $validSignatureKey, 'transaction_status' => 'settlement', 'payment_type' => 'qris'];
    $response = postJson(route('midtrans.notification'), $payload);
    $response->assertStatus(200);
    expect($sale->refresh()->status)->toBe('completed');
    expect($product->refresh()->stock)->toBe(9);
    expect(DB::table('warehouse_product')->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('stock'))->toBe(9);
    expect($paymentTransaction->refresh()->status)->toBe('settlement');
});
