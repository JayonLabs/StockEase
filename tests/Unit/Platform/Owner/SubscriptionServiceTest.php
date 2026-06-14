<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Platform\Owner\SubscriptionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->plan = Plan::factory()->pemula()->create();
    $this->planPro = Plan::factory()->profesional()->create();
});

it('returns paginated subscriptions with company and plan', function () {
    $companies = Company::factory()->count(3)->create();
    foreach ($companies as $company) {
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
    }

    $result = app(SubscriptionService::class)->getAll();

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result)->toHaveCount(3);
    expect($result->first()->relationLoaded('company'))->toBeTrue();
    expect($result->first()->relationLoaded('plan'))->toBeTrue();
});

it('orders subscriptions by latest first', function () {
    $company = Company::factory()->create();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'created_at' => now()->subDays(5),
    ]);
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'created_at' => now(),
    ]);

    $result = app(SubscriptionService::class)->getAll();

    expect($result->first()->created_at->gt($result->last()->created_at))->toBeTrue();
});

it('filters by status', function () {
    $company = Company::factory()->create();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
    ]);

    $result = app(SubscriptionService::class)->getAll(status: 'canceled');

    expect($result)->toHaveCount(1);
    expect($result->first()->status)->toBe('canceled');
});

it('returns single subscription with relations', function () {
    $company = Company::factory()->create();
    $subscription = Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);

    $result = app(SubscriptionService::class)->findById($subscription->id);

    expect($result->id)->toBe($subscription->id);
    expect($result->relationLoaded('company'))->toBeTrue();
    expect($result->relationLoaded('plan'))->toBeTrue();
    expect($result->relationLoaded('invoices'))->toBeTrue();
});

it('returns null for non-existent subscription', function () {
    $result = app(SubscriptionService::class)->findById(99999);

    expect($result)->toBeNull();
});

it('respects per page parameter', function () {
    $company = Company::factory()->create();
    Subscription::factory()->count(30)->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);

    $result = app(SubscriptionService::class)->getAll(perPage: 10);

    expect($result)->toHaveCount(10);
});

it('filters subscriptions by status with total matching', function () {
    $company = Company::factory()->create();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
    ]);

    $all = app(SubscriptionService::class)->getAll();
    $active = app(SubscriptionService::class)->getAll(status: 'active');
    $canceled = app(SubscriptionService::class)->getAll(status: 'canceled');

    expect($all->total())->toBe(2);
    expect($active->total())->toBe(1);
    expect($canceled->total())->toBe(1);
});
