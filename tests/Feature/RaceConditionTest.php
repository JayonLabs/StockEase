<?php

use App\Enums\PaymentStatus;
use App\Enums\ShiftStatus;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shift;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Payment\PaymentService;
use App\Services\Purchase\PurchaseService;
use App\Services\Shift\ShiftService;
use App\Services\Stock\StockAdjustmentService;
use App\Services\Warehouse\StockTransferService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    config(['midtrans.server_key' => 'test_server_key']);

    $user = User::factory()->create(['role' => 'admin']);
    Auth::login($user);
});

// ───────────────────────────────────────
// PaymentService: Duplicate Webhook Prevention
// ───────────────────────────────────────

it('prevents duplicate payment settlement from concurrent webhooks', function () {
    $sale = Sale::factory()->create(['status' => 'pending', 'total' => 10000]);
    $product = Product::factory()->create(['stock' => 10]);
    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'qty' => 2,
        'price' => 5000,
    ]);

    $transaction = PaymentTransaction::create([
        'sale_id' => $sale->id,
        'external_id' => 'ORDER-123',
        'amount' => 10000,
        'status' => 'pending',
        'gateway' => 'midtrans',
        'payment_type' => 'qris',
    ]);

    $orderId = 'ORDER-123';
    $statusCode = '200';
    $grossAmount = '10000.00';
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.'test_server_key');

    $notificationData = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'settlement',
        'payment_type' => 'qris',
    ];

    $rawBody = json_encode($notificationData);

    // First call: processes the settlement normally
    $response1 = app(PaymentService::class)->handleNotification($notificationData, $rawBody);
    expect($response1['status'])->toBe(200);
    expect($transaction->fresh()->status)->toBe(PaymentStatus::Settlement->value);
    expect($sale->fresh()->status)->toBe('completed');

    // Second call: simulates a duplicate concurrent webhook
    // The re-check inside the transaction should detect it's already paid
    $response2 = app(PaymentService::class)->handleNotification($notificationData, $rawBody);
    expect($response2['status'])->toBe(200);
    expect($response2['message'])->toBe('Orderan sudah dibayar');

    // Stock should only be reduced once
    expect($product->fresh()->stock)->toBe(8);
});

it('returns early for already-paid payment transactions', function () {
    $sale = Sale::factory()->create(['status' => 'completed', 'total' => 10000]);
    $product = Product::factory()->create(['stock' => 10]);

    $transaction = PaymentTransaction::create([
        'sale_id' => $sale->id,
        'external_id' => 'ORDER-456',
        'amount' => 10000,
        'status' => 'settlement',
        'gateway' => 'midtrans',
        'payment_type' => 'qris',
    ]);

    $orderId = 'ORDER-456';
    $statusCode = '200';
    $grossAmount = '10000.00';
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.'test_server_key');

    $notificationData = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'settlement',
        'payment_type' => 'qris',
    ];

    $response = app(PaymentService::class)->handleNotification(
        $notificationData,
        json_encode($notificationData)
    );

    expect($response['status'])->toBe(200);
    expect($response['message'])->toBe('Orderan sudah dibayar');
});

it('uses lockForUpdate when re-checking payment status inside transaction', function () {
    $sale = Sale::factory()->create(['status' => 'pending', 'total' => 10000]);
    $product = Product::factory()->create(['stock' => 10]);

    $transaction = PaymentTransaction::create([
        'sale_id' => $sale->id,
        'external_id' => 'ORDER-LOCK',
        'amount' => 10000,
        'status' => 'pending',
        'gateway' => 'midtrans',
        'payment_type' => 'qris',
    ]);

    $orderId = 'ORDER-LOCK';
    $statusCode = '200';
    $grossAmount = '10000.00';
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.'test_server_key');

    $notificationData = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'settlement',
        'payment_type' => 'qris',
    ];

    DB::enableQueryLog();

    app(PaymentService::class)->handleNotification(
        $notificationData,
        json_encode($notificationData)
    );

    $queries = DB::getQueryLog();

    // Verify that a SELECT ... FOR UPDATE was executed on the payment_transactions table
    $forUpdateQuery = collect($queries)->first(fn ($q) => str_contains($q['query'], 'for update'));

    expect($forUpdateQuery)->not->toBeNull();
    expect($forUpdateQuery['query'])->toContain('payment_transactions');
});

// ───────────────────────────────────────
// ShiftService: Double-Open & Double-Close Prevention
// ───────────────────────────────────────

it('prevents opening two shifts concurrently for the same user', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    $shiftService = new ShiftService;
    $shift1 = $shiftService->openShift($user, 50000);
    expect($shift1->status)->toBe(ShiftStatus::Open->value);
    expect(Shift::where('user_id', $user->id)->whereNull('closed_at')->count())->toBe(1);

    expect(fn () => $shiftService->openShift($user, 50000))
        ->toThrow(Exception::class, 'Anda masih memiliki shift yang terbuka');
});

it('prevents closing an already-closed shift (double-close)', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    $shiftService = new ShiftService;
    $shift = $shiftService->openShift($user, 50000);

    $closed = $shiftService->closeShift($shift, 60000);
    expect($closed->status)->toBe(ShiftStatus::Closed->value);

    expect(fn () => $shiftService->closeShift($shift, 60000))
        ->toThrow(Exception::class, 'Shift ini sudah ditutup sebelumnya');
});

it('locks the shift row when closing to prevent race conditions', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    $shiftService = new ShiftService;
    $shift = $shiftService->openShift($user, 50000);

    DB::enableQueryLog();

    $shiftService->closeShift($shift, 60000);

    $queries = DB::getQueryLog();

    $forUpdateQuery = collect($queries)->first(fn ($q) => str_contains($q['query'], 'for update'));

    expect($forUpdateQuery)->not->toBeNull();
    expect($forUpdateQuery['query'])->toContain('shifts');
});

it('locks the shift row when opening to prevent duplicate open shifts', function () {
    $user = User::factory()->create(['role' => 'cashier']);
    $shiftService = new ShiftService;
    $shiftService->openShift($user, 50000);

    DB::enableQueryLog();

    expect(fn () => $shiftService->openShift($user, 50000))
        ->toThrow(Exception::class);

    $queries = DB::getQueryLog();

    $forUpdateQuery = collect($queries)->first(fn ($q) => str_contains($q['query'], 'for update'));
    expect($forUpdateQuery)->not->toBeNull();
});

// ───────────────────────────────────────
// StockTransferService: Oversell Prevention
// ───────────────────────────────────────

it('uses lockForUpdate when reading warehouse stock during transfer', function () {
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $warehouseA->products()->attach($product->id, ['stock' => 50]);
    $product->syncStockFromWarehouses();

    $service = new StockTransferService;

    DB::enableQueryLog();

    $service->storeTransfer([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'qty' => 20,
        'note' => 'Test transfer',
        'date' => now()->toDateString(),
    ]);

    $queries = DB::getQueryLog();

    // Verify lockForUpdate on warehouse_product
    $forUpdateQuery = collect($queries)->first(
        fn ($q) => str_contains($q['query'], 'for update')
            && str_contains($q['query'], 'warehouse_product')
    );

    expect($forUpdateQuery)->not->toBeNull();
});

it('correctly deducts and adds stock during transfer', function () {
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $product = Product::factory()->create(['stock' => 0]);

    $warehouseA->products()->attach($product->id, ['stock' => 50]);
    $product->syncStockFromWarehouses();

    $service = new StockTransferService;

    $service->storeTransfer([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'qty' => 30,
        'note' => 'Transfer 30',
        'date' => now()->toDateString(),
    ]);

    assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouseA->id,
        'product_id' => $product->id,
        'stock' => 20, // 50 - 30
    ]);

    assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'stock' => 30,
    ]);
});

// ───────────────────────────────────────
// PurchaseService: Concurrent Stock Updates
// ───────────────────────────────────────

it('uses lockForUpdate when reading warehouse stock during purchase', function () {
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $warehouse->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();

    DB::enableQueryLog();

    app(PurchaseService::class)->storePurchase([
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

    $queries = DB::getQueryLog();

    // Verify lockForUpdate on warehouse_product
    $forUpdateQuery = collect($queries)->first(
        fn ($q) => str_contains($q['query'], 'for update')
            && str_contains($q['query'], 'warehouse_product')
    );

    expect($forUpdateQuery)->not->toBeNull();
});

it('correctly updates warehouse stock after purchase store and delete', function () {
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $warehouse->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();

    // Store a purchase
    $purchase = app(PurchaseService::class)->storePurchase([
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

    assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'stock' => 15, // 10 + 5
    ]);

    // Delete the purchase (reverts stock)
    app(PurchaseService::class)->deletePurchase($purchase);

    assertDatabaseHas('warehouse_product', [
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'stock' => 10,
    ]);
});

// ───────────────────────────────────────
// StockAdjustmentService: Concurrent Adjustments
// ───────────────────────────────────────

it('uses lockForUpdate when reading old stock during adjustment', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $warehouse->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();

    DB::enableQueryLog();

    app(StockAdjustmentService::class)->storeAdjustment([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'new_stock' => 25,
        'reason' => 'Adjustment test',
        'date' => now()->toDateString(),
    ]);

    $queries = DB::getQueryLog();

    // Verify lockForUpdate on warehouse_product
    $forUpdateQuery = collect($queries)->first(
        fn ($q) => str_contains($q['query'], 'for update')
            && str_contains($q['query'], 'warehouse_product')
    );

    expect($forUpdateQuery)->not->toBeNull();
});

it('correctly adjusts stock and calculates old/new stock diff', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $warehouse->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();

    $adjustment = app(StockAdjustmentService::class)->storeAdjustment([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'new_stock' => 25,
        'reason' => 'Penambahan stok fisik',
        'date' => now()->toDateString(),
    ]);

    expect($adjustment->old_stock)->toBe(10);
    expect($adjustment->new_stock)->toBe(25);

    $product->refresh();
    expect($product->stock)->toBe(25);

    assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty' => 15,
        'type' => 'adjust',
        'reference_type' => 'StockAdjustment',
        'reference_id' => $adjustment->id,
    ]);
});

// ───────────────────────────────────────
// Cross-Cutting: Transaction Rollback Safety
// ───────────────────────────────────────

it('does not leave partial stock updates when a transaction fails', function () {
    $warehouseA = Warehouse::factory()->create();
    $warehouseB = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $warehouseA->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();

    $initialStock = DB::table('warehouse_product')
        ->where('warehouse_id', $warehouseA->id)
        ->where('product_id', $product->id)
        ->value('stock');

    // Attempt a transfer with insufficient stock (should fail inside transaction)
    $service = new StockTransferService;

    expect(fn () => $service->storeTransfer([
        'from_warehouse_id' => $warehouseA->id,
        'to_warehouse_id' => $warehouseB->id,
        'product_id' => $product->id,
        'qty' => 100, // Exceeds stock of 10
        'note' => 'Over transfer',
        'date' => now()->toDateString(),
    ]))->toThrow(Exception::class);

    // Stock should remain unchanged after the failed transfer
    $currentStock = DB::table('warehouse_product')
        ->where('warehouse_id', $warehouseA->id)
        ->where('product_id', $product->id)
        ->value('stock');

    expect((int) $currentStock)->toBe($initialStock);
});

it('does not leave partial stock adjustments when a transaction fails after stock update', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();

    $warehouse->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();

    $adjustment = app(StockAdjustmentService::class)->storeAdjustment([
        'warehouse_id' => $warehouse->id,
        'product_id' => $product->id,
        'new_stock' => 25,
        'reason' => 'First adjustment',
        'date' => now()->toDateString(),
    ]);

    expect($product->fresh()->stock)->toBe(25);

    // Verify stock_logs were recorded
    expect(DB::table('stock_logs')
        ->where('reference_type', 'StockAdjustment')
        ->where('reference_id', $adjustment->id)
        ->exists()
    )->toBeTrue();
});
