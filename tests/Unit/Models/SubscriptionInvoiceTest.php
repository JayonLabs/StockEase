<?php

namespace Tests\Unit\Models;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('belongs to a subscription', function () {
    $subscription = Subscription::factory()->create();
    $invoice = SubscriptionInvoice::factory()->create(['subscription_id' => $subscription->id]);

    expect($invoice->subscription)->toBeInstanceOf(Subscription::class);
    expect($invoice->subscription->id)->toBe($subscription->id);
});

it('belongs to a user', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $invoice = SubscriptionInvoice::factory()->create(['user_id' => $user->id]);

    expect($invoice->user)->toBeInstanceOf(User::class);
    expect($invoice->user->id)->toBe($user->id);
});

it('casts amount to decimal', function () {
    $invoice = SubscriptionInvoice::factory()->create(['amount' => 100000]);

    expect($invoice->amount)->toBeNumeric();
});

it('casts paid_at to datetime', function () {
    $now = now();
    $invoice = SubscriptionInvoice::factory()->create(['paid_at' => $now]);

    expect($invoice->paid_at)->toBeInstanceOf(Carbon::class);
});

it('has fillable fields', function () {
    $invoice = new SubscriptionInvoice;

    expect($invoice->getFillable())->toContain('subscription_id');
    expect($invoice->getFillable())->toContain('user_id');
    expect($invoice->getFillable())->toContain('midtrans_order_id');
    expect($invoice->getFillable())->toContain('amount');
    expect($invoice->getFillable())->toContain('status');
    expect($invoice->getFillable())->toContain('paid_at');
});

it('can have different statuses', function () {
    $pending = SubscriptionInvoice::factory()->create(['status' => 'pending']);
    $paid = SubscriptionInvoice::factory()->create(['status' => 'paid']);
    $failed = SubscriptionInvoice::factory()->create(['status' => 'failed']);

    expect($pending->status)->toBe('pending');
    expect($paid->status)->toBe('paid');
    expect($failed->status)->toBe('failed');
});

it('stores midtrans transaction data', function () {
    $invoice = SubscriptionInvoice::factory()->create([
        'midtrans_order_id' => 'ORDER-001',
        'midtrans_transaction_id' => 'TRX-001',
        'midtrans_payment_type' => 'bank_transfer',
    ]);

    expect($invoice->midtrans_order_id)->toBe('ORDER-001');
    expect($invoice->midtrans_transaction_id)->toBe('TRX-001');
    expect($invoice->midtrans_payment_type)->toBe('bank_transfer');
});
