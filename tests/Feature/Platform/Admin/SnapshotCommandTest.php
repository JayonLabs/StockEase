<?php

use App\Console\Commands\TakePlatformSnapshot;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformDailySnapshot;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\artisan;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();
});

it('creates a daily snapshot with correct counts', function () {
    Company::factory()->count(5)->create(['is_active' => true]);
    Company::factory()->create(['is_active' => false]);

    $plan = Plan::where('slug', 'pemula')->first();
    $companies = Company::all();
    foreach ($companies->take(3) as $company) {
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    artisan('platform:snapshot')
        ->assertSuccessful();

    $snapshot = PlatformDailySnapshot::first();

    expect($snapshot)->not->toBeNull();
    expect($snapshot->total_companies)->toBe(6);
    expect($snapshot->active_companies)->toBe(5);
    expect($snapshot->active_subscriptions)->toBe(3);
});

it('creates snapshot with MRR value', function () {
    $plan = Plan::factory()->create(['price_monthly' => 100000, 'price_annual' => 0]);
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'monthly',
    ]);
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'billing_cycle' => 'annual',
    ]);

    artisan('platform:snapshot')->assertSuccessful();

    $snapshot = PlatformDailySnapshot::first();

    expect($snapshot->mrr)->toBe(100000.0);
});

it('only creates one snapshot per day', function () {
    Company::factory()->count(3)->create();

    artisan('platform:snapshot')->assertSuccessful();
    artisan('platform:snapshot')->assertSuccessful();

    $snapshots = PlatformDailySnapshot::all();

    expect($snapshots)->toHaveCount(1);
});

it('creates snapshot with correct user count', function () {
    $companies = Company::factory()->count(2)->create();
    User::factory()->count(5)->create(['company_id' => $companies[0]->id]);
    User::factory()->count(3)->create(['company_id' => $companies[1]->id]);

    artisan('platform:snapshot')->assertSuccessful();

    $snapshot = PlatformDailySnapshot::first();

    expect($snapshot->total_users)->toBe(8);
});

it('creates snapshot with subscription breakdown', function () {
    $planPemula = Plan::where('slug', 'pemula')->first();
    $planPro = Plan::where('slug', 'profesional')->first();
    $companies = Company::factory()->count(2)->create();

    Subscription::factory()->create([
        'company_id' => $companies[0]->id,
        'plan_id' => $planPemula->id,
        'status' => 'active',
    ]);
    Subscription::factory()->create([
        'company_id' => $companies[1]->id,
        'plan_id' => $planPro->id,
        'status' => 'active',
    ]);

    artisan('platform:snapshot')->assertSuccessful();

    $snapshot = PlatformDailySnapshot::first();

    expect($snapshot->subscription_breakdown)->toBeArray();
    expect(count($snapshot->subscription_breakdown))->toBe(2);
});

it('handles empty platform state gracefully', function () {
    artisan('platform:snapshot')->assertSuccessful();

    $snapshot = PlatformDailySnapshot::first();

    expect($snapshot->total_companies)->toBe(0);
    expect($snapshot->total_users)->toBe(0);
    expect($snapshot->mrr)->toBe(0.0);
});

it('has command signature and description', function () {
    $command = new TakePlatformSnapshot;

    expect($command->getName())->toBe('platform:snapshot');
});

it('logs snapshot creation', function () {
    Company::factory()->create();

    artisan('platform:snapshot')
        ->assertSuccessful()
        ->expectsOutputToContain('Platform daily snapshot created');
});
