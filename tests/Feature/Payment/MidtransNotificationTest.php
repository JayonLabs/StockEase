<?php

namespace Tests\Feature\Payment;

use App\Models\PaymentTransaction;
use App\Models\Sale;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\postJson;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Config::set('midtrans.server_key', 'test-server-key');
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('handles sale midtrans notification with valid signature', function () {
    $sale = Sale::factory()->create(['user_id' => $this->admin->id]);
    $paymentTransaction = PaymentTransaction::factory()->create([
        'sale_id' => $sale->id,
        'external_id' => 'ORDER-123',
        'amount' => 50000,
        'status' => 'pending',
    ]);

    $orderId = 'ORDER-123';
    $statusCode = '200';
    $grossAmount = '50000';
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.config('midtrans.server_key'));

    $response = postJson(route('midtrans.notification'), [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'settlement',
        'payment_type' => 'bank_transfer',
        'transaction_id' => 'TRX-123',
        'fraud_status' => 'accept',
    ]);

    $response->assertOk();
    expect($paymentTransaction->fresh()->status)->toBe('settlement');
});

it('handles subscription midtrans notification - settlement', function () {
    $subscription = Subscription::factory()->create();
    $invoice = SubscriptionInvoice::factory()->create([
        'subscription_id' => $subscription->id,
        'midtrans_order_id' => 'SUB-456',
        'status' => 'pending',
    ]);
    PaymentTransaction::factory()->create([
        'external_id' => 'SUB-456',
        'amount' => (int) $invoice->amount,
        'status' => 'pending',
    ]);

    $orderId = 'SUB-456';
    $statusCode = '200';
    $grossAmount = (string) (int) $invoice->amount;
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.config('midtrans.server_key'));

    postJson(route('midtrans.notification'), [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'settlement',
        'payment_type' => 'credit_card',
        'transaction_id' => 'TRX-SUB-456',
        'fraud_status' => 'accept',
    ])->assertOk();

    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->fresh()->midtrans_transaction_id)->toBe('TRX-SUB-456');
    expect($subscription->fresh()->status)->toBe('active');
});

it('handles subscription notification - failed transaction', function () {
    $subscription = Subscription::factory()->create();
    $invoice = SubscriptionInvoice::factory()->create([
        'subscription_id' => $subscription->id,
        'midtrans_order_id' => 'SUB-789',
        'status' => 'pending',
    ]);
    PaymentTransaction::factory()->create([
        'external_id' => 'SUB-789',
        'amount' => (int) $invoice->amount,
        'status' => 'pending',
    ]);

    $orderId = 'SUB-789';
    $statusCode = '200';
    $grossAmount = (string) (int) $invoice->amount;
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.config('midtrans.server_key'));

    postJson(route('midtrans.notification'), [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'deny',
        'payment_type' => 'bank_transfer',
        'transaction_id' => 'TRX-789',
        'fraud_status' => 'accept',
    ])->assertOk();

    expect($invoice->fresh()->status)->toBe('failed');
});

it('rejects notification with invalid signature', function () {
    postJson(route('midtrans.notification'), [
        'order_id' => 'ORDER-INVALID',
        'status_code' => '200',
        'gross_amount' => '50000',
        'signature_key' => 'invalid-signature',
        'transaction_status' => 'settlement',
        'payment_type' => 'bank_transfer',
        'transaction_id' => 'TRX-INVALID',
    ])->assertStatus(403);
});

it('rejects notification when transaction not found', function () {
    $signatureKey = hash('sha512', 'NONEXISTENT'.'200'.'50000'.config('midtrans.server_key'));

    postJson(route('midtrans.notification'), [
        'order_id' => 'NONEXISTENT',
        'status_code' => '200',
        'gross_amount' => '50000',
        'signature_key' => $signatureKey,
        'transaction_status' => 'settlement',
        'payment_type' => 'bank_transfer',
        'transaction_id' => 'TRX-NONE',
    ])->assertStatus(404);
});

it('rejects notification when gross amount does not match', function () {
    $sale = Sale::factory()->create(['user_id' => $this->admin->id]);
    PaymentTransaction::factory()->create([
        'sale_id' => $sale->id,
        'external_id' => 'ORDER-MISMATCH',
        'amount' => 50000,
        'status' => 'pending',
    ]);

    $orderId = 'ORDER-MISMATCH';
    $statusCode = '200';
    $grossAmount = '99999';
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.config('midtrans.server_key'));

    postJson(route('midtrans.notification'), [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'settlement',
        'payment_type' => 'bank_transfer',
        'transaction_id' => 'TRX-MM',
    ])->assertStatus(400);
});

it('ignores duplicate notification for already paid transaction', function () {
    $sale = Sale::factory()->create(['user_id' => $this->admin->id]);
    PaymentTransaction::factory()->create([
        'sale_id' => $sale->id,
        'external_id' => 'ORDER-DUP',
        'amount' => 50000,
        'status' => 'paid',
    ]);

    $orderId = 'ORDER-DUP';
    $statusCode = '200';
    $grossAmount = '50000';
    $signatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.config('midtrans.server_key'));

    postJson(route('midtrans.notification'), [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'signature_key' => $signatureKey,
        'transaction_status' => 'settlement',
        'payment_type' => 'bank_transfer',
        'transaction_id' => 'TRX-DUP',
    ])->assertOk();
});
