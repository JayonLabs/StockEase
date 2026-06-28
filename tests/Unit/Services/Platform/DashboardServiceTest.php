<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Platform\Owner\DashboardService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{service: DashboardService} $this */
    $this->service = app(DashboardService::class);
});

it('returns overview with correct counts from all tenants', function () {
    /** @var object{service: DashboardService} $this */
    Company::factory()->count(3)->create(['is_active' => true]);
    Company::factory()->create(['is_active' => false]);

    $overview = $this->service->getOverview();

    expect($overview['total_companies'])->toBe(4);
    expect($overview['active_companies'])->toBe(3);
});

it('counts all users across all tenants', function () {
    /** @var object{service: DashboardService} $this */
    $companies = Company::factory()->count(2)->create();
    User::factory()->count(2)->create(['company_id' => $companies[0]->id]);
    User::factory()->count(3)->create(['company_id' => $companies[1]->id]);
    User::factory()->count(1)->create(['company_id' => $companies[0]->id]);

    $overview = $this->service->getOverview();

    expect($overview['total_users'])->toBe(6);
});

it('returns subscription breakdown by plan', function () {
    /** @var object{service: DashboardService} $this */
    $planPemula = Plan::factory()->pemula()->create();
    $planPro = Plan::factory()->profesional()->create();
    $companies = Company::factory()->count(3)->create();

    Subscription::factory()->create([
        'company_id' => $companies[0]->id,
        'plan_id' => $planPemula->id,
        'status' => 'active',
    ]);
    Subscription::factory()->count(2)->create([
        'company_id' => $companies[1]->id,
        'plan_id' => $planPro->id,
        'status' => 'active',
    ]);

    $breakdown = $this->service->getSubscriptionBreakdown();

    expect($breakdown)->toHaveCount(2);
    expect($breakdown[0]['plan'])->toBe('Pemula');
    expect($breakdown[0]['count'])->toBe(1);
});

it('includes trialing subscriptions in active count', function () {
    /** @var object{service: DashboardService} $this */
    $plan = Plan::factory()->pemula()->create();
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'trialing',
    ]);

    $overview = $this->service->getOverview();

    expect($overview['active_subscriptions'])->toBe(1);
});

it('excludes expired subscriptions from active count', function () {
    /** @var object{service: DashboardService} $this */
    $plan = Plan::factory()->pemula()->create();
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'expired',
    ]);

    $overview = $this->service->getOverview();

    expect($overview['active_subscriptions'])->toBe(0);
});

it('calculates MRR correctly for monthly plans', function () {
    /** @var object{service: DashboardService} $this */
    $plan = Plan::factory()->create(['price_monthly' => 100000, 'price_annual' => 0]);
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
    ]);

    expect($this->service->getOverview()['mrr'])->toBe(100000.0);
});

it('calculates MRR correctly for annual plans', function () {
    /** @var object{service: DashboardService} $this */
    $plan = Plan::factory()->create(['price_monthly' => 0, 'price_annual' => 1200000]);
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'annual',
    ]);

    expect($this->service->getOverview()['mrr'])->toBe(100000.0);
});

it('returns zero MRR when no active subscriptions', function () {
    /** @var object{service: DashboardService} $this */
    expect($this->service->getOverview()['mrr'])->toBe(0.0);
});

it('returns recent companies ordered by creation date', function () {
    /** @var object{service: DashboardService} $this */
    Company::factory()->create(['name' => 'OldCo', 'created_at' => now()->subDays(5)]);
    Company::factory()->create(['name' => 'NewCo', 'created_at' => now()]);

    $recent = $this->service->getRecentRegistrations(5);

    expect($recent)->toHaveCount(2);
    expect($recent[0]['name'])->toBe('NewCo');
    expect($recent[1]['name'])->toBe('OldCo');
});

it('caches overview data', function () {
    /** @var object{service: DashboardService} $this */
    Cache::shouldReceive('flexible')
        ->once()
        ->with('platform_dashboard_overview', [300, 900], Mockery::on(fn ($c) => is_callable($c)))
        ->andReturn(['total_companies' => 5, 'mrr' => 0]);

    $result = $this->service->getOverview();

    expect($result['total_companies'])->toBe(5);
});

it('handles empty database gracefully', function () {
    /** @var object{service: DashboardService} $this */
    $overview = $this->service->getOverview();

    expect($overview['total_companies'])->toBe(0);
    expect($overview['active_companies'])->toBe(0);
    expect($overview['total_users'])->toBe(0);
    expect($overview['mrr'])->toBe(0.0);
});

it('returns empty growth trend when no snapshots exist', function () {
    /** @var object{service: DashboardService} $this */
    $trend = $this->service->getGrowthTrend();

    expect($trend)->toBe([]);
});

it('returns empty recent companies when none exist', function () {
    /** @var object{service: DashboardService} $this */
    $recent = $this->service->getRecentRegistrations();

    expect($recent)->toBe([]);
});

it('returns subscription breakdown empty when no plans', function () {
    /** @var object{service: DashboardService} $this */
    $breakdown = $this->service->getSubscriptionBreakdown();

    expect($breakdown)->toBeEmpty();
});
