<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Platform\Owner\CompanyService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

it('returns paginated companies with users_count', function () {
    $company = Company::factory()->create();
    User::factory()->count(3)->create(['company_id' => $company->id]);

    $result = app(CompanyService::class)->getAll();

    expect($result)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($result->first()->users_count)->toBe(3);
});

it('returns companies ordered by latest first', function () {
    Company::factory()->create(['name' => 'OldCo', 'created_at' => now()->subDays(10)]);
    Company::factory()->create(['name' => 'NewCo', 'created_at' => now()]);

    $result = app(CompanyService::class)->getAll();

    expect($result->first()->name)->toBe('NewCo');
});

it('eager loads subscription relation', function () {
    $company = Company::factory()->create();
    $plan = Plan::factory()->pemula()->create();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $result = app(CompanyService::class)->getAll();

    expect($result->first()->relationLoaded('subscription'))->toBeTrue();
});

it('returns single company with owner and subscription', function () {
    $company = Company::factory()->create(['name' => 'Detail Co']);
    $user = User::factory()->create(['company_id' => $company->id]);
    $company->update(['owner_id' => $user->id]);

    $result = app(CompanyService::class)->findById($company->id);

    expect($result->name)->toBe('Detail Co');
    expect($result->relationLoaded('owner'))->toBeTrue();
    expect($result->relationLoaded('subscription'))->toBeTrue();
});

it('returns null for non-existent company', function () {
    $result = app(CompanyService::class)->findById(99999);

    expect($result)->toBeNull();
});

it('respects per page parameter', function () {
    Company::factory()->count(30)->create();

    $result = app(CompanyService::class)->getAll(perPage: 10);

    expect($result->count())->toBe(10);
});

it('returns correct total count of companies', function () {
    $count = app(CompanyService::class)->getTotalCount();

    expect($count)->toBe(0);
});
