<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->service = new SubscriptionService;

    $this->owner = User::factory()->create();
    $this->company = Company::create([
        'name' => 'Test Company',
        'slug' => 'test-company',
        'owner_id' => $this->owner->id,
    ]);
    $this->owner->update(['company_id' => $this->company->id]);

    Plan::create([
        'name' => 'Pemula', 'slug' => 'pemula',
        'price_monthly' => 0, 'price_annual' => 0,
        'max_products' => 100, 'max_users' => 3, 'max_warehouses' => 1,
    ]);

    Plan::create([
        'name' => 'Profesional', 'slug' => 'profesional',
        'price_monthly' => 299000, 'price_annual' => 249000,
        'max_products' => 1000, 'max_users' => 10, 'max_warehouses' => 3,
        'trial_days' => 14,
    ]);
});

it('creates a free subscription for Pemula plan', function () {
    $plan = Plan::where('slug', 'pemula')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    expect($subscription->status)->toBe('active');
    expect($subscription->ends_at)->toBeNull();
    expect($subscription->trial_ends_at)->toBeNull();
});

it('creates a trial subscription for Profesional plan', function () {
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    expect($subscription->status)->toBe('trialing');
    expect($subscription->trial_ends_at)->not->toBeNull();
    expect($subscription->trial_ends_at->isFuture())->toBeTrue();
});

it('activates a subscription after payment', function () {
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);
    $subscription->update(['status' => 'trialing', 'trial_ends_at' => now()->addDays(14)]);

    $this->service->activateSubscription($subscription);

    expect($subscription->fresh()->status)->toBe('active');
    expect($subscription->fresh()->trial_ends_at)->toBeNull();
    expect($subscription->fresh()->ends_at->isFuture())->toBeTrue();
});

it('cancels a subscription', function () {
    $plan = Plan::where('slug', 'pemula')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    $this->service->cancelSubscription($subscription);

    expect($subscription->fresh()->status)->toBe('canceled');
    expect($subscription->fresh()->canceled_at)->not->toBeNull();
});

it('expires a subscription and downgrades to Pemula', function () {
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);
    $subscription->update(['status' => 'active', 'ends_at' => now()->subDay()]);

    $this->service->expireSubscription($subscription);

    expect($subscription->fresh()->status)->toBe('expired');

    $newSub = $this->company->activeSubscription();
    expect($newSub)->not->toBeNull();
    expect($newSub->plan->slug)->toBe('pemula');
});

it('downgrades multiple expired subscriptions', function () {
    $company2 = Company::create([
        'name' => 'Test Co 2', 'slug' => 'test-co-2',
        'owner_id' => User::factory()->create()->id,
    ]);
    $company3 = Company::create([
        'name' => 'Test Co 3', 'slug' => 'test-co-3',
        'owner_id' => User::factory()->create()->id,
    ]);

    $plan = Plan::where('slug', 'profesional')->first();
    foreach ([$this->company, $company2, $company3] as $co) {
        $sub = $this->service->createTrial($co, $plan);
        $sub->update(['status' => 'active', 'ends_at' => now()->subDay()]);
    }

    $count = $this->service->downgradeExpiredSubscriptions();
    expect($count)->toBe(3);
});

it('throws exception when company already has active subscription', function () {
    $plan = Plan::where('slug', 'pemula')->first();
    $this->service->createTrial($this->company, $plan);

    $plan2 = Plan::where('slug', 'profesional')->first();
    $this->service->createTrial($this->company, $plan2);
})->throws(RuntimeException::class, 'Company sudah memiliki subscription aktif.');

it('assigns free subscription correctly', function () {
    $subscription = $this->service->assignFreeSubscription($this->company);

    expect($subscription->plan->slug)->toBe('pemula');
    expect($subscription->status)->toBe('active');
    expect($subscription->ends_at)->toBeNull();
});

it('creates an invoice with correct amount', function () {
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    actingAs($this->owner);
    $invoice = $this->service->createInvoice($subscription);

    expect((float) $invoice->amount)->toBe(299000.0);
    expect($invoice->status)->toBe('pending');
});

it('generates midtrans order id with SUB prefix', function () {
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);
    actingAs($this->owner);
    $invoice = $this->service->createInvoice($subscription);

    $orderId = $this->service->generateMidtransOrderId($invoice);

    expect($orderId)->toStartWith('SUB-');
});
