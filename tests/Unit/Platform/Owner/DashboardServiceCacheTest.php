<?php

use App\Models\Company;
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

it('caches overview data using Cache::flexible', function () {
    /** @var object{service: DashboardService} $this */
    $companies = Company::factory()->count(3)->create();
    User::factory()->count(5)->create(['company_id' => $companies[0]->id]);

    // First call should compute and cache
    $result = $this->service->getOverview();

    expect($result['total_companies'])->toBe(3)
        ->and($result['total_users'])->toBe(5);

    // Add more data
    Company::factory()->count(2)->create();

    // Second call should return cached result (still 3 companies)
    $cachedResult = $this->service->getOverview();

    expect($cachedResult['total_companies'])->toBe(3);
});

it('uses Cache::flexible with expected TTL values', function () {
    /** @var object{service: DashboardService} $this */
    Cache::shouldReceive('flexible')
        ->once()
        ->with('platform_dashboard_overview', [300, 900], Mockery::on(fn ($c) => is_callable($c)))
        ->andReturn(['total_companies' => 10, 'active_companies' => 5, 'total_users' => 20, 'active_subscriptions' => 3, 'mrr' => 500000.0]);

    $result = $this->service->getOverview();

    expect($result['total_companies'])->toBe(10)
        ->and($result['mrr'])->toBe(500000.0);
});

it('caches subscription breakdown', function () {
    /** @var object{service: DashboardService} $this */
    Cache::shouldReceive('remember')
        ->once()
        ->with('platform_dashboard_subscriptions', 900, Mockery::on(fn ($c) => is_callable($c)))
        ->andReturn([
            ['plan' => 'Pemula', 'slug' => 'pemula', 'count' => 2],
        ]);

    $result = $this->service->getSubscriptionBreakdown();

    expect($result)->toHaveCount(1)
        ->and($result[0]['plan'])->toBe('Pemula');
});

it('does not cache getRecentRegistrations', function () {
    /** @var object{service: DashboardService} $this */
    Company::factory()->create(['name' => 'RecentCo', 'created_at' => now()]);

    $result = $this->service->getRecentRegistrations();

    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('RecentCo');
});

it('limits recent registrations to specified count', function () {
    /** @var object{service: DashboardService} $this */
    Company::factory()->count(25)->create();

    $result = $this->service->getRecentRegistrations(10);

    expect($result)->toHaveCount(10);
});

it('limits active companies to specified count', function () {
    /** @var object{service: DashboardService} $this */
    Company::factory()->count(25)->create(['is_active' => true]);

    $result = $this->service->getActiveCompanies(10);

    expect($result)->toHaveCount(10);
});

it('returns empty array for growth trend when no snapshots', function () {
    /** @var object{service: DashboardService} $this */
    $result = $this->service->getGrowthTrend();

    expect($result)->toBe([]);
});

it('returns empty array for recent registrations when none exist', function () {
    /** @var object{service: DashboardService} $this */
    Company::query()->delete();

    $result = $this->service->getRecentRegistrations();

    expect($result)->toBe([]);
});

it('returns zero MRR when no active subscriptions exist', function () {
    /** @var object{service: DashboardService} $this */
    $overview = $this->service->getOverview();

    expect($overview['mrr'])->toBe(0.0);
});
