<?php

use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['midtrans.server_key' => 'test_server_key']);

    $this->paymentService = new PaymentService;
});

it('can handle notification and update sale status to completed', function () {
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

    $response = $this->paymentService->handleNotification($notificationData, json_encode($notificationData));

    expect($response['status'])->toBe(200);
    expect($transaction->fresh()->status)->toBe('settlement');
    expect($sale->fresh()->status)->toBe('completed');
    expect($product->fresh()->stock)->toBe(8); // Stock reduced on settlement
});


it('uses payment status enum values for non-paid notification statuses', function (string $transactionStatus, string $expectedStatus) {
    $transaction = PaymentTransaction::factory()->create([
        'external_id' => 'ORDER-'.$transactionStatus,
        'amount' => 10000,
        'status' => 'pending',
    ]);

    $statusCode = '200';
    $grossAmount = '10000.00';
    $signatureKey = hash('sha512', $transaction->external_id.$statusCode.$grossAmount.'test_server_key');

    $notificationData = [
        'order_id' => $transaction->external_id,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => $transactionStatus,
        'payment_type' => 'qris',
    ];

    $response = $this->paymentService->handleNotification($notificationData, json_encode($notificationData));

    expect($response['status'])->toBe(200);
    expect($transaction->fresh()->status)->toBe($expectedStatus);
})->with([
    'pending' => ['pending', 'pending'],
    'deny' => ['deny', 'deny'],
    'expire' => ['expire', 'expired'],
    'cancel' => ['cancel', 'cancel'],
    'unknown' => ['unexpected-status', 'unknown'],
]);

it('uses challenge enum value for challenged credit card captures', function () {
    $transaction = PaymentTransaction::factory()->create([
        'external_id' => 'ORDER-CHALLENGE',
        'amount' => 10000,
        'status' => 'pending',
    ]);

    $statusCode = '200';
    $grossAmount = '10000.00';
    $signatureKey = hash('sha512', $transaction->external_id.$statusCode.$grossAmount.'test_server_key');

    $notificationData = [
        'order_id' => $transaction->external_id,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'capture',
        'payment_type' => 'credit_card',
        'fraud_status' => 'challenge',
    ];

    $response = $this->paymentService->handleNotification($notificationData, json_encode($notificationData));

    expect($response['status'])->toBe(200);
    expect($transaction->fresh()->status)->toBe('challenge');
});

it('throws exception for invalid signature', function () {
    $notificationData = [
        'order_id' => 'ORDER-123',
        'status_code' => '200',
        'gross_amount' => '10000.00',
        'signature_key' => 'invalid_signature',
    ];

    $this->paymentService->handleNotification($notificationData, '');
})->throws(Exception::class, 'Invalid signature');

it('throws exception if transaction not found', function () {
    $orderId = 'NON-EXISTENT';
    $statusCode = '200';
    $grossAmount = '10000.00';
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.'test_server_key');

    $notificationData = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
    ];

    $this->paymentService->handleNotification($notificationData, '');
})->throws(Exception::class, 'Transaksi tidak ditemukan');

it('throws exception if gross amount mismatch', function () {
    $transaction = PaymentTransaction::factory()->create([
        'external_id' => 'ORDER-123',
        'amount' => 10000,
    ]);

    $orderId = 'ORDER-123';
    $statusCode = '200';
    $grossAmount = '5000.00'; // Mismatch
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.'test_server_key');

    $notificationData = [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
    ];

    $this->paymentService->handleNotification($notificationData, '');
})->throws(Exception::class, 'Nominal pembayaran tidak sesuai');
