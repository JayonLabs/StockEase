<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Platform\Analytics\SubscriptionAnalytics;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->analytics = new SubscriptionAnalytics;
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();
});

it('calculates total active subscriptions', function () {
    $companies = Company::factory()->count(3)->create();
    $plan = Plan::where('slug', 'pemula')->first();

    foreach ($companies as $company) {
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    expect($this->analytics->totalActiveSubscriptions())->toBe(3);
});

it('calculates subscription breakdown by plan', function () {
    $planPemula = Plan::where('slug', 'pemula')->first();
    $planPro = Plan::where('slug', 'profesional')->first();
    $companies = Company::factory()->count(3)->create();

    Subscription::factory()->create([
        'company_id' => $companies[0]->id,
        'plan_id' => $planPemula->id,
        'status' => 'active',
    ]);
    Subscription::factory()->create([
        'company_id' => $companies[1]->id,
        'plan_id' => $planPemula->id,
        'status' => 'trialing',
    ]);
    Subscription::factory()->create([
        'company_id' => $companies[2]->id,
        'plan_id' => $planPro->id,
        'status' => 'active',
    ]);

    $breakdown = $this->analytics->breakdownByPlan();

    expect($breakdown)->toHaveCount(2);

    $pemula = collect($breakdown)->firstWhere('slug', 'pemula');
    $pro = collect($breakdown)->firstWhere('slug', 'profesional');

    expect($pemula['count'])->toBe(2);
    expect($pro['count'])->toBe(1);
});

it('excludes expired subscriptions from active count', function () {
    $plan = Plan::where('slug', 'pemula')->first();
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'expired',
    ]);

    expect($this->analytics->totalActiveSubscriptions())->toBe(1);
});

it('calculates MRR correctly for monthly plans', function () {
    $plan = Plan::factory()->create(['price_monthly' => 150000, 'price_annual' => 0]);
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
    ]);

    expect($this->analytics->calculateMrr())->toBe(150000.0);
});

it('calculates MRR correctly for annual plans', function () {
    $plan = Plan::factory()->create(['price_monthly' => 0, 'price_annual' => 2400000]);
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'annual',
    ]);

    // annual / 12 = 2400000 / 12 = 200000
    expect($this->analytics->calculateMrr())->toBe(200000.0);
});

it('returns zero MRR when no active subscriptions', function () {
    expect($this->analytics->calculateMrr())->toBe(0.0);
});

it('handles mixed billing cycles in MRR calculation', function () {
    $planA = Plan::factory()->create(['price_monthly' => 100000, 'price_annual' => 0]);
    $planB = Plan::factory()->create(['price_monthly' => 0, 'price_annual' => 1200000]);
    $companies = Company::factory()->count(2)->create();

    Subscription::factory()->create([
        'company_id' => $companies[0]->id,
        'plan_id' => $planA->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
    ]);
    Subscription::factory()->create([
        'company_id' => $companies[1]->id,
        'plan_id' => $planB->id,
        'status' => 'active',
        'billing_cycle' => 'annual',
    ]);

    // 100000 + (1200000/12) = 100000 + 100000 = 200000
    expect($this->analytics->calculateMrr())->toBe(200000.0);
});

it('includes trialing subscriptions in MRR as potential revenue', function () {
    $plan = Plan::factory()->create(['price_monthly' => 100000, 'price_annual' => 0]);
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'trialing',
        'billing_cycle' => 'monthly',
    ]);

    expect($this->analytics->calculateMrr())->toBe(100000.0);
});

it('treats canceled subscriptions as zero MRR contributors', function () {
    $plan = Plan::factory()->create(['price_monthly' => 100000, 'price_annual' => 0]);
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'canceled',
        'billing_cycle' => 'monthly',
    ]);

    expect($this->analytics->calculateMrr())->toBe(0.0);
});
