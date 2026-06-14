<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\artisan;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $plan = Plan::factory()->pemula()->create();
    $this->company = Company::factory()->create();
    User::factory()->create(['company_id' => $this->company->id]);
});

it('downgrades expired active subscriptions to pemula', function () {
    $plan = Plan::factory()->profesional()->create();
    Subscription::create([
        'company_id' => $this->company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(30),
        'ends_at' => now()->subDay(),
    ]);

    $pemula = Plan::where('slug', 'pemula')->first();

    artisan('subscription:downgrade-expired')
        ->expectsOutputToContain('subscription expired telah didowngrade')
        ->assertSuccessful();

    $this->assertDatabaseHas('subscriptions', [
        'company_id' => $this->company->id,
        'plan_id' => $plan->id,
        'status' => 'expired',
    ]);

    expect($this->company->fresh()->currentPlan()->id)->toBe($pemula->id);
});

it('downgrades expired trialing subscriptions to pemula', function () {
    $plan = Plan::factory()->profesional()->create();
    Subscription::create([
        'company_id' => $this->company->id,
        'plan_id' => $plan->id,
        'status' => 'trialing',
        'starts_at' => now()->subDays(20),
        'trial_ends_at' => now()->subDay(),
    ]);

    $pemula = Plan::where('slug', 'pemula')->first();

    artisan('subscription:downgrade-expired')
        ->assertSuccessful();

    expect($this->company->fresh()->currentPlan()->id)->toBe($pemula->id);
});

it('skips active subscriptions that have not ended', function () {
    $plan = Plan::factory()->profesional()->create();
    Subscription::create([
        'company_id' => $this->company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(10),
        'ends_at' => now()->addDays(20),
    ]);

    artisan('subscription:downgrade-expired')
        ->assertSuccessful();

    expect($this->company->fresh()->currentPlan()->id)->toBe($plan->id);
});

it('skips trialing subscriptions that have not ended', function () {
    $plan = Plan::factory()->profesional()->create();
    Subscription::create([
        'company_id' => $this->company->id,
        'plan_id' => $plan->id,
        'status' => 'trialing',
        'starts_at' => now()->subDays(5),
        'trial_ends_at' => now()->addDays(9),
    ]);

    artisan('subscription:downgrade-expired')
        ->assertSuccessful();

    expect($this->company->fresh()->currentPlan()->id)->toBe($plan->id);
});

it('handles no expired subscriptions gracefully', function () {
    artisan('subscription:downgrade-expired')
        ->expectsOutputToContain('0 subscription expired telah didowngrade')
        ->assertSuccessful();
});

it('downgrades multiple expired subscriptions at once', function () {
    $planProf = Plan::factory()->profesional()->create();
    $planEnterprise = Plan::factory()->enterprise()->create();

    $companyB = Company::factory()->create();
    User::factory()->create(['company_id' => $companyB->id]);

    Subscription::create([
        'company_id' => $this->company->id,
        'plan_id' => $planProf->id,
        'status' => 'active',
        'starts_at' => now()->subDays(30),
        'ends_at' => now()->subDay(),
    ]);

    Subscription::create([
        'company_id' => $companyB->id,
        'plan_id' => $planEnterprise->id,
        'status' => 'active',
        'starts_at' => now()->subDays(60),
        'ends_at' => now()->subDay(),
    ]);

    artisan('subscription:downgrade-expired')
        ->expectsOutputToContain('2 subscription expired telah didowngrade')
        ->assertSuccessful();
});
