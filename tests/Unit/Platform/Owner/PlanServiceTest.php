<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Platform\Owner\PlanService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('returns all plans ordered by sort_order', function () {
    Plan::factory()->enterprise()->create(['sort_order' => 3]);
    Plan::factory()->pemula()->create(['sort_order' => 1]);
    Plan::factory()->profesional()->create(['sort_order' => 2]);

    $result = app(PlanService::class)->getAll();

    expect($result)->toHaveCount(3);
    expect($result[0]->sort_order)->toBe(1);
    expect($result[2]->sort_order)->toBe(3);
});

it('includes subscriptions_count for each plan', function () {
    $plan = Plan::factory()->pemula()->create();
    $companies = Company::factory()->count(2)->create();

    Subscription::factory()->create(['company_id' => $companies[0]->id, 'plan_id' => $plan->id, 'status' => 'active']);
    Subscription::factory()->create(['company_id' => $companies[1]->id, 'plan_id' => $plan->id, 'status' => 'active']);

    $result = app(PlanService::class)->getAll();

    expect($result->first()->subscriptions_count)->toBe(2);
});

it('returns empty collection when no plans exist', function () {
    $result = app(PlanService::class)->getAll();

    expect($result)->toHaveCount(0);
});
