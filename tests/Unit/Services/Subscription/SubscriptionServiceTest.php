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
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $this->service = new SubscriptionService;

    $this->owner = User::factory()->create();
    $this->company = Company::create([
        'name' => 'Test Company',
        'slug' => 'test-company',
        'owner_id' => $this->owner->id,
    ]);
    $this->owner->update(['company_id' => $this->company->id]);

    Plan::create([
        'name' => 'Pemula',
        'slug' => 'pemula',
        'price_monthly' => 50000,
        'price_annual' => 500000,
        'max_products' => 100,
        'max_users' => 3,
        'max_warehouses' => 1,
        'trial_days' => 14,
    ]);

    Plan::create([
        'name' => 'Profesional',
        'slug' => 'profesional',
        'price_monthly' => 149000,
        'price_annual' => 1490000,
        'max_products' => 1000,
        'max_users' => 10,
        'max_warehouses' => 3,
        'trial_days' => 0,
    ]);
});

it('creates a trial subscription for Pemula plan', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'pemula')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    expect($subscription->status)->toBe('trialing');
    expect($subscription->trial_ends_at)->not->toBeNull();
    expect($subscription->trial_ends_at->isFuture())->toBeTrue();
    expect($subscription->ends_at)->not->toBeNull();
    expect($subscription->ends_at->isFuture())->toBeTrue();
});

it('creates a pending_payment subscription for Profesional plan (no trial)', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    expect($subscription->status)->toBe('pending_payment');
    expect($subscription->trial_ends_at)->toBeNull();
    expect($subscription->ends_at)->toBeNull();
});

it('activates a subscription after payment', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);
    $subscription->update(['status' => 'trialing', 'trial_ends_at' => now()->addDays(14)]);

    $this->service->activateSubscription($subscription);

    expect($subscription->fresh()->status)->toBe('active');
    expect($subscription->fresh()->trial_ends_at)->toBeNull();
    expect($subscription->fresh()->ends_at->isFuture())->toBeTrue();
});

it('cancels a subscription', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'pemula')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    $this->service->cancelSubscription($subscription);

    expect($subscription->fresh()->status)->toBe('canceled');
    expect($subscription->fresh()->canceled_at)->not->toBeNull();
});

it('activates a pending_payment subscription after payment', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    expect($subscription->status)->toBe('pending_payment');
    expect($subscription->ends_at)->toBeNull();

    $this->service->activateSubscription($subscription);

    $fresh = $subscription->fresh();
    expect($fresh->status)->toBe('active');
    expect($fresh->trial_ends_at)->toBeNull();
    expect($fresh->ends_at)->not->toBeNull();
    expect($fresh->ends_at->isFuture())->toBeTrue();
});

it('activates subscription with annual billing cycle', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan, 'annual');

    expect($subscription->status)->toBe('pending_payment');

    $this->service->activateSubscription($subscription);

    $fresh = $subscription->fresh();
    expect($fresh->status)->toBe('active');
    $expectedEnd = now()->addDays(365);
    expect($fresh->ends_at->toDateString())->toBe($expectedEnd->toDateString());
});

it('handleFailedPayment marks invoice as failed and cancels pending subscription', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    actingAs($this->owner);
    $invoice = $this->service->createInvoice($subscription);

    $this->service->handleFailedPayment($invoice);

    expect($invoice->fresh()->status)->toBe('failed');
    expect($subscription->fresh()->status)->toBe('canceled');

    // Company should have been reverted to free plan
    $activeSub = $this->company->fresh()->activeSubscription();
    expect($activeSub)->not->toBeNull();
    expect($activeSub->plan->slug)->toBe('pemula');
});

it('handleFailedPayment only marks invoice as failed for non-pending subscriptions', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'pemula')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    expect($subscription->status)->toBe('trialing');

    actingAs($this->owner);
    $invoice = $this->service->createInvoice($subscription);

    $this->service->handleFailedPayment($invoice);

    // Invoice is marked failed
    expect($invoice->fresh()->status)->toBe('failed');
    // But subscription remains trialing (not cancelled)
    expect($subscription->fresh()->status)->toBe('trialing');
});

it('expires a subscription and downgrades to active Pemula without trial', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);
    $subscription->update(['status' => 'active', 'ends_at' => now()->subDay()]);

    $this->service->expireSubscription($subscription);

    expect($subscription->fresh()->status)->toBe('expired');

    $newSub = $this->company->activeSubscription();
    expect($newSub)->not->toBeNull();
    expect($newSub->plan->slug)->toBe('pemula');
    expect($newSub->status)->toBe('active');
    expect($newSub->trial_ends_at)->toBeNull();
});

it('downgrades multiple expired subscriptions', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $company2 = Company::create([
        'name' => 'Test Co 2',
        'slug' => 'test-co-2',
        'owner_id' => User::factory()->create()->id,
    ]);
    $company3 = Company::create([
        'name' => 'Test Co 3',
        'slug' => 'test-co-3',
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

it('downgrades expired trialing subscriptions to active Pemula without trial', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'pemula')->first();
    $subscription = $this->service->createTrial($this->company, $plan);
    $subscription->update(['trial_ends_at' => now()->subDay()]);

    expect($subscription->fresh()->status)->toBe('trialing');

    $count = $this->service->downgradeExpiredSubscriptions();
    expect($count)->toBe(1);

    expect($subscription->fresh()->status)->toBe('expired');

    $newSub = $this->company->activeSubscription();
    expect($newSub)->not->toBeNull();
    expect($newSub->plan->slug)->toBe('pemula');
    expect($newSub->status)->toBe('active');
    expect($newSub->trial_ends_at)->toBeNull();
});

it('throws exception when company already has active subscription', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'pemula')->first();
    $this->service->createTrial($this->company, $plan);

    $plan2 = Plan::where('slug', 'profesional')->first();
    $this->service->createTrial($this->company, $plan2);
})->throws(RuntimeException::class, 'Company sudah memiliki subscription aktif.');

it('assigns free subscription as active without trial', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $subscription = $this->service->assignFreeSubscription($this->company);

    expect($subscription->plan->slug)->toBe('pemula');
    expect($subscription->status)->toBe('active');
    expect($subscription->trial_ends_at)->toBeNull();
    expect($subscription->ends_at)->toBeNull();
});

it('upgradePlan from Pemula trial to Profesional creates pending_payment', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $pemula = Plan::where('slug', 'pemula')->first();
    $profesional = Plan::where('slug', 'profesional')->first();

    $this->service->createTrial($this->company, $pemula);
    expect($this->company->activeSubscription()->status)->toBe('trialing');

    $newSub = $this->service->upgradePlan($this->company, $profesional);
    expect($newSub->plan->slug)->toBe('profesional');
    expect($newSub->status)->toBe('pending_payment');
    expect($newSub->trial_ends_at)->toBeNull();
    expect($newSub->ends_at)->toBeNull();
});

it('createTrial with Enterprise plan creates pending_payment without trial', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    Plan::create([
        'name' => 'Enterprise',
        'slug' => 'enterprise',
        'price_monthly' => 299000,
        'price_annual' => 2990000,
        'max_products' => null,
        'max_users' => null,
        'max_warehouses' => null,
        'trial_days' => 0,
    ]);

    $enterprise = Plan::where('slug', 'enterprise')->first();
    $subscription = $this->service->createTrial($this->company, $enterprise);

    expect($subscription->status)->toBe('pending_payment');
    expect($subscription->trial_ends_at)->toBeNull();
    expect($subscription->ends_at)->toBeNull();
});

it('createTrial with free plan creates active without trial', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $freePlan = Plan::create([
        'name' => 'Free Plan',
        'slug' => 'free-plan',
        'price_monthly' => 0,
        'price_annual' => 0,
        'trial_days' => 0,
    ]);

    $subscription = $this->service->createTrial($this->company, $freePlan);

    expect($subscription->status)->toBe('active');
    expect($subscription->trial_ends_at)->toBeNull();
    expect($subscription->ends_at)->toBeNull();
});

it('assignFreeSubscription always creates active even if called again', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $first = $this->service->assignFreeSubscription($this->company);
    expect($first->status)->toBe('active');
    expect($first->trial_ends_at)->toBeNull();

    $first->update(['status' => 'expired']);

    $second = $this->service->assignFreeSubscription($this->company);
    expect($second->status)->toBe('active');
    expect($second->trial_ends_at)->toBeNull();
});

it('can still upgrade from active Pemula to Enterprise without trial', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $enterprise = Plan::create([
        'name' => 'Enterprise',
        'slug' => 'enterprise',
        'price_monthly' => 299000,
        'price_annual' => 2990000,
        'max_products' => null,
        'max_users' => null,
        'max_warehouses' => null,
        'trial_days' => 0,
    ]);

    $this->service->assignFreeSubscription($this->company);
    expect($this->company->activeSubscription()->plan->slug)->toBe('pemula');

    $newSub = $this->service->upgradePlan($this->company, $enterprise);
    expect($newSub->plan->slug)->toBe('enterprise');
    expect($newSub->status)->toBe('pending_payment');
    expect($newSub->trial_ends_at)->toBeNull();
    expect($newSub->ends_at)->toBeNull();
});

it('creates an invoice with correct amount', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    actingAs($this->owner);
    $invoice = $this->service->createInvoice($subscription);

    expect((float) $invoice->amount)->toBe(149000.0);
    expect($invoice->status)->toBe('pending');
});

it('getPendingInvoice returns the latest pending invoice', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    actingAs($this->owner);
    $invoice = $this->service->createInvoice($subscription);

    $found = $this->service->getPendingInvoice($subscription);
    expect($found->id)->toBe($invoice->id);
    expect($found->status)->toBe('pending');
});

it('getPendingInvoice returns null when no pending invoice exists', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    actingAs($this->owner);
    $invoice = $this->service->createInvoice($subscription);
    $invoice->update(['status' => 'paid']);

    $found = $this->service->getPendingInvoice($subscription);
    expect($found)->toBeNull();
});

it('generates midtrans order id with SUB prefix', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();
    $subscription = $this->service->createTrial($this->company, $plan);
    actingAs($this->owner);
    $invoice = $this->service->createInvoice($subscription);

    $orderId = $this->service->generateMidtransOrderId($invoice);

    expect($orderId)->toStartWith('SUB-');
});

it('Pemula createTrial has exactly 14-day trial', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'pemula')->first();
    $subscription = $this->service->createTrial($this->company, $plan);

    $expectedTrialEnd = now()->addDays(14);
    expect($subscription->trial_ends_at->toDateString())->toBe($expectedTrialEnd->toDateString());
});

it('Profesional createTrial has no trial days', function () {
    /** @var object{service: SubscriptionService, owner: User, company: Company} $this */
    $plan = Plan::where('slug', 'profesional')->first();

    expect($plan->trial_days)->toBe(0);

    $subscription = $this->service->createTrial($this->company, $plan);

    expect($subscription->status)->toBe('pending_payment');
    expect($subscription->trial_ends_at)->toBeNull();
});

// ===========================================================================
// Double-trial prevention
// ===========================================================================

describe('createTrial() double-trial prevention', function () {
    it('creates trialing subscription when company has no trial history', function () {
        /** @var object{service: SubscriptionService, company: Company} $this */
        $plan = Plan::where('slug', 'pemula')->first();

        expect($this->company->hadTrial())->toBeFalse();

        $subscription = $this->service->createTrial($this->company, $plan);

        expect($subscription->status)->toBe('trialing');
        expect($subscription->trial_ends_at)->not->toBeNull();
    });

    it('sets had_trial flag on company after trial is created', function () {
        /** @var object{service: SubscriptionService, company: Company} $this */
        $plan = Plan::where('slug', 'pemula')->first();

        expect($this->company->fresh()->had_trial)->toBeFalse();

        $this->service->createTrial($this->company, $plan);

        expect($this->company->fresh()->had_trial)->toBeTrue();
    });

    it('creates pending_payment instead of trial when company already had trial', function () {
        /** @var object{service: SubscriptionService, company: Company} $this */
        $plan = Plan::where('slug', 'pemula')->first();

        $this->company->update(['had_trial' => true]);

        $subscription = $this->service->createTrial($this->company->fresh(), $plan);

        expect($subscription->status)->toBe('pending_payment');
        expect($subscription->trial_ends_at)->toBeNull();
    });

    it('does not set had_trial when plan has no trial_days', function () {
        /** @var object{service: SubscriptionService, company: Company} $this */
        $plan = Plan::where('slug', 'profesional')->first();

        $this->service->createTrial($this->company, $plan);

        expect($this->company->fresh()->had_trial)->toBeFalse();
    });

    it('hadTrial() returns false by default for new company', function () {
        /** @var object{service: SubscriptionService, company: Company} $this */
        expect($this->company->fresh()->had_trial)->toBeFalse();
        expect($this->company->fresh()->hadTrial())->toBeFalse();
    });

    it('hadTrial() returns true after had_trial is set on company', function () {
        /** @var object{service: SubscriptionService, company: Company} $this */
        $this->company->update(['had_trial' => true]);

        expect($this->company->fresh()->hadTrial())->toBeTrue();
    });

    it('prevents second trial via upgradePlan after trial was used', function () {
        /** @var object{service: SubscriptionService, company: Company} $this */
        $pemula = Plan::where('slug', 'pemula')->first();

        // First trial created during registration
        $firstTrial = $this->service->createTrial($this->company, $pemula);
        expect($firstTrial->status)->toBe('trialing');
        expect($this->company->fresh()->had_trial)->toBeTrue();

        // Trial expires, free Pemula assigned
        $firstTrial->update(['status' => 'expired']);
        $this->service->assignFreeSubscription($this->company);

        // User tries to re-trigger Pemula again
        $secondSub = $this->service->upgradePlan($this->company->fresh(), $pemula);

        expect($secondSub->status)->toBe('pending_payment');
        expect($secondSub->trial_ends_at)->toBeNull();
    });
});
