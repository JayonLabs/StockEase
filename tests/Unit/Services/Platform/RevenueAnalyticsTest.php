<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Services\Platform\Analytics\RevenueAnalytics;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{analytics: RevenueAnalytics, subscription: Subscription} $this */
    $this->analytics = new RevenueAnalytics;
    $plan = Plan::factory()->pemula()->create();
    $company = Company::factory()->create();
    $this->subscription = Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);
});

it('calculates total revenue from paid invoices', function () {
    /** @var object{analytics: RevenueAnalytics, subscription: Subscription} $this */
    SubscriptionInvoice::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 50000,
        'status' => 'paid',
    ]);
    SubscriptionInvoice::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 75000,
        'status' => 'paid',
    ]);

    expect($this->analytics->totalRevenue())->toBe(125000.0);
});

it('returns zero when no invoices exist', function () {
    /** @var object{analytics: RevenueAnalytics, subscription: Subscription} $this */
    expect($this->analytics->totalRevenue())->toBe(0.0);
});

it('handles multiple paid invoices correctly', function () {
    /** @var object{analytics: RevenueAnalytics, subscription: Subscription} $this */
    SubscriptionInvoice::factory()->count(5)->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 100000,
        'status' => 'paid',
    ]);

    expect($this->analytics->totalRevenue())->toBe(500000.0);
});

it('ignores unpaid invoices in revenue calculation', function () {
    /** @var object{analytics: RevenueAnalytics, subscription: Subscription} $this */
    SubscriptionInvoice::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 100000,
        'status' => 'paid',
    ]);
    SubscriptionInvoice::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 200000,
        'status' => 'pending',
    ]);
    SubscriptionInvoice::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 300000,
        'status' => 'failed',
    ]);

    expect($this->analytics->totalRevenue())->toBe(100000.0);
});

it('ignores refunded invoices in revenue calculation', function () {
    /** @var object{analytics: RevenueAnalytics, subscription: Subscription} $this */
    SubscriptionInvoice::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 50000,
        'status' => 'paid',
    ]);
    SubscriptionInvoice::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 50000,
        'status' => 'refunded',
    ]);

    expect($this->analytics->totalRevenue())->toBe(50000.0);
});

it('calculates revenue across multiple subscriptions', function () {
    /** @var object{analytics: RevenueAnalytics, subscription: Subscription} $this */
    $company2 = Company::factory()->create();
    $plan2 = Plan::factory()->profesional()->create();
    $sub2 = Subscription::factory()->create([
        'company_id' => $company2->id,
        'plan_id' => $plan2->id,
        'status' => 'active',
    ]);

    SubscriptionInvoice::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 50000,
        'status' => 'paid',
    ]);
    SubscriptionInvoice::factory()->create([
        'subscription_id' => $sub2->id,
        'amount' => 150000,
        'status' => 'paid',
    ]);

    expect($this->analytics->totalRevenue())->toBe(200000.0);
});

it('returns revenue by month as array', function () {
    /** @var object{analytics: RevenueAnalytics, subscription: Subscription} $this */
    SubscriptionInvoice::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 50000,
        'status' => 'paid',
        'paid_at' => now()->startOfMonth(),
    ]);
    SubscriptionInvoice::factory()->create([
        'subscription_id' => $this->subscription->id,
        'amount' => 75000,
        'status' => 'paid',
        'paid_at' => now()->startOfMonth()->subMonth(),
    ]);

    $monthly = $this->analytics->revenueByMonth(3);

    expect($monthly)->toBeArray();
    expect(count($monthly))->toBeGreaterThanOrEqual(1);
});

it('returns empty array when no revenue data for requested period', function () {
    /** @var object{analytics: RevenueAnalytics, subscription: Subscription} $this */
    $monthly = $this->analytics->revenueByMonth(3);

    expect($monthly)->toBe([]);
});
