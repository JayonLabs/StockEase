<?php

namespace Tests\Feature\Subscription;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['role' => 'admin', 'company_id' => $this->company->id]);
    $this->company->update(['owner_id' => $this->user->id]);

    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    $this->company->subscription()->create([
        'plan_id' => Plan::where('slug', 'pemula')->first()->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);
});

it('displays subscription page with plans', function () {
    actingAs($this->user)
        ->get(route('subscription.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Subscription/Index')
            ->has('plans', 3)
            ->has('currentSubscription')
        );
});

it('shows current plan details', function () {
    $pemula = Plan::where('slug', 'pemula')->first();

    actingAs($this->user)
        ->get(route('subscription.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentSubscription.plan.id', $pemula->id)
            ->where('currentSubscription.plan.slug', 'pemula')
        );
});

it('includes annual_per_month in plan data', function () {
    actingAs($this->user)
        ->get(route('subscription.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->whereType('plans.0.annual_per_month', 'integer')
            ->whereType('plans.0.annual_savings_percent', 'integer')
        );
});

it('redirects to login when not authenticated', function () {
    get(route('subscription.index'))
        ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Upgrade — validation
// ---------------------------------------------------------------------------

describe('Upgrade validation', function () {
    it('rejects missing plan_id', function () {
        /** @var object{user: User} $this */
        actingAs($this->user)
            ->postJson(route('subscription.upgrade'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    });

    it('rejects non-existent plan_id', function () {
        /** @var object{user: User} $this */
        actingAs($this->user)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => 99999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    });

    it('rejects invalid billing_cycle', function () {
        /** @var object{user: User} $this */
        $plan = Plan::where('slug', 'pemula')->first();

        actingAs($this->user)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $plan->id,
                'billing_cycle' => 'weekly',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['billing_cycle']);
    });

    it('accepts monthly as billing_cycle', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $plan = Plan::factory()->free()->create(['slug' => 'pemula-free']);

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Upgrade — free plan
// ---------------------------------------------------------------------------

describe('Upgrade to free plan', function () {
    it('subscribes to free plan immediately', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $freePlan = Plan::factory()->free()->create();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $freePlan->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'subscription']);

        expect($freshCompany->fresh()->activeSubscription())->not->toBeNull();
    });

    it('returns 403 when user has no company', function () {
        /** @var User $userNoCompany */
        $userNoCompany = User::factory()->create();

        actingAs($userNoCompany)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => Plan::where('slug', 'pemula')->first()->id,
            ])
            ->assertForbidden();
    });
});

// ---------------------------------------------------------------------------
// Upgrade — paid plan with trial
// ---------------------------------------------------------------------------

describe('Upgrade to paid plan with trial', function () {
    it('starts trial for paid plan with trial_days', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $paidPlan = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $paidPlan->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk()
            ->assertJson(['message' => 'Trial 14 hari dimulai!']);

        $sub = $freshCompany->fresh()->subscription()->first();
        expect($sub->status)->toBe('trialing');
    });

    it('starts annual trial for paid plan', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $paidPlan = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $paidPlan->id,
                'billing_cycle' => 'annual',
            ])
            ->assertOk()
            ->assertJson(['message' => 'Trial 14 hari dimulai!']);

        $sub = $freshCompany->fresh()->subscription()->first();
        expect($sub->billing_cycle)->toBe('annual');
    });
});

// ---------------------------------------------------------------------------
// Cancel
// ---------------------------------------------------------------------------

describe('Cancel subscription', function () {
    it('super_admin can cancel a subscription', function () {
        /** @var object{company: Company} $this */
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['company_id' => $this->company->id]);
        $superAdmin->syncRoles(['super_admin']);

        $subscription = $this->company->subscription()->first();

        actingAs($superAdmin)
            ->post(route('subscription.cancel', $subscription))
            ->assertRedirect()
            ->assertSessionHas('success', 'Subscription dibatalkan.');

        expect($subscription->fresh()->status)->toBe('canceled');
    });

    it('requires authentication to cancel', function () {
        /** @var object{company: Company} $this */
        $subscription = $this->company->subscription()->first();

        post(route('subscription.cancel', $subscription))
            ->assertRedirect(route('login'));
    });
});
