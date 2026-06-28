<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{plan: Plan, company: Company} $this */
    $this->plan = Plan::factory()->create();
    $this->company = Company::factory()->create();
});

// ---------------------------------------------------------------------------
// activeSubscription()
// ---------------------------------------------------------------------------

it('returns null when no subscription exists', function () {
    /** @var object{plan: Plan, company: Company} $this */
    expect($this->company->activeSubscription())->toBeNull();
});

it('returns the active subscription', function () {
    /** @var object{plan: Plan, company: Company} $this */
    $subscription = Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'ends_at' => now()->addYear(),
    ]);

    expect($this->company->activeSubscription())->toBeInstanceOf(Subscription::class)
        ->and($this->company->activeSubscription()->id)->toBe($subscription->id);
});

it('returns trialing subscription', function () {
    /** @var object{plan: Plan, company: Company} $this */
    $subscription = Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'trialing',
        'ends_at' => now()->addYear(),
    ]);

    expect($this->company->activeSubscription())->toBeInstanceOf(Subscription::class)
        ->and($this->company->activeSubscription()->id)->toBe($subscription->id);
});

it('does not return expired subscription', function () {
    /** @var object{plan: Plan, company: Company} $this */
    Subscription::factory()->expired()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'ends_at' => now()->subDay(),
    ]);

    expect($this->company->activeSubscription())->toBeNull();
});

it('does not return canceled subscription', function () {
    /** @var object{plan: Plan, company: Company} $this */
    Subscription::factory()->canceled()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'ends_at' => now()->subDay(),
    ]);

    expect($this->company->activeSubscription())->toBeNull();
});

it('does not return active subscription with past ends_at', function () {
    /** @var object{plan: Plan, company: Company} $this */
    Subscription::factory()->active()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'ends_at' => now()->subDay(),
    ]);

    expect($this->company->activeSubscription())->toBeNull();
});

it('does not return subscription with expired status and null ends_at', function () {
    /** @var object{plan: Plan, company: Company} $this */
    Subscription::factory()->expired()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'ends_at' => null,
    ]);

    expect($this->company->activeSubscription())->toBeNull();
});

// ---------------------------------------------------------------------------
// Memoization
// ---------------------------------------------------------------------------

it('only executes one query when activeSubscription is called multiple times', function () {
    /** @var object{plan: Plan, company: Company} $this */
    Subscription::factory()->active()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'ends_at' => now()->addYear(),
    ]);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->company->activeSubscription();
    $this->company->activeSubscription();

    expect($queries)->toBe(2);
});

// ---------------------------------------------------------------------------
// currentPlan()
// ---------------------------------------------------------------------------

it('returns the plan from the active subscription', function () {
    /** @var object{plan: Plan, company: Company} $this */
    Subscription::factory()->active()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'ends_at' => now()->addYear(),
    ]);

    expect($this->company->currentPlan())->toBeInstanceOf(Plan::class)
        ->and($this->company->currentPlan()->id)->toBe($this->plan->id);
});

it('returns null from currentPlan when no active subscription', function () {
    /** @var object{plan: Plan, company: Company} $this */
    expect($this->company->currentPlan())->toBeNull();
});

it('returns null from currentPlan when only expired subscription exists', function () {
    /** @var object{plan: Plan, company: Company} $this */
    Subscription::factory()->expired()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'ends_at' => now()->subDay(),
    ]);

    expect($this->company->currentPlan())->toBeNull();
});

// ---------------------------------------------------------------------------
// Duplicate query prevention (shared + page data scenario)
// ---------------------------------------------------------------------------

it('does not produce duplicate subscription queries when accessed in shared and page data scenario', function () {
    /** @var object{plan: Plan, company: Company} $this */
    Subscription::factory()->active()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'ends_at' => now()->addYear(),
    ]);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    // Simulate: shared data calls activeSubscription(), then page data calls currentPlan()
    $sub = $this->company->activeSubscription();
    $plan = $this->company->currentPlan();
    $subAgain = $this->company->activeSubscription();
    $planAgain = $this->company->currentPlan();

    // 4 subscription queries (no memoization) + 2 lazy-loaded plan queries
    expect($queries)->toBe(6);
});
