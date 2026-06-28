<?php

namespace Tests\Feature\Subscription;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{company: Company, user: User} $this */
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
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->get(route('subscription.index'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Subscription/Index')
                ->has('plans', 3)
                ->has('currentSubscription')
        );
});

it('shows current plan details', function () {
    /** @var object{company: Company, user: User} $this */
    $pemula = Plan::where('slug', 'pemula')->first();

    actingAs($this->user)
        ->get(route('subscription.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('currentSubscription.plan.id', $pemula->id)
                ->where('currentSubscription.plan.slug', 'pemula')
        );
});

it('includes plan features as boolean map in shared props', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->whereType('auth.subscription.plan.features', 'array')
                ->where('auth.subscription.plan.features.products', true)
                ->where('auth.subscription.plan.features.pos', true)
                ->where('auth.subscription.plan.features.cashier_shift', true)
                ->where('auth.subscription.plan.features.user_roles', true)
                ->where('auth.subscription.plan.features.purchasing', false)
                ->where('auth.subscription.plan.features.profit_loss', false)
                ->where('auth.subscription.plan.features.file_manager', false)
        );
});

it('professional plan features have correct values in shared props', function () {
    /** @var object{company: Company, user: User} $this */
    $profesional = Plan::where('slug', 'profesional')->first();
    $this->company->subscription()->update(['plan_id' => $profesional->id]);

    actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('auth.subscription.plan.features.purchasing', true)
                ->where('auth.subscription.plan.features.cashier_shift', true)
                ->where('auth.subscription.plan.features.purchase_report', true)
                ->where('auth.subscription.plan.features.stock_report', true)
                ->where('auth.subscription.plan.features.user_roles', true)
                ->where('auth.subscription.plan.features.profit_loss', false)
                ->where('auth.subscription.plan.features.file_manager', false)
        );
});

it('enterprise plan features are all true in shared props', function () {
    /** @var object{company: Company, user: User} $this */
    $enterprise = Plan::where('slug', 'enterprise')->first();
    $this->company->subscription()->update(['plan_id' => $enterprise->id]);

    actingAs($this->user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('auth.subscription.plan.features.purchasing', true)
                ->where('auth.subscription.plan.features.profit_loss', true)
                ->where('auth.subscription.plan.features.file_manager', true)
                ->where('auth.subscription.plan.features.user_roles', true)
        );
});

it('subscription page includes features array for each plan', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->get(route('subscription.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->whereType('plans.0.features', 'array')
                ->whereType('plans.1.features', 'array')
                ->whereType('plans.2.features', 'array')
        );
});

it('includes annual_per_month in plan data', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->get(route('subscription.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->whereType('plans.0.annual_per_month', 'integer')
                ->whereType('plans.0.annual_savings_percent', 'integer')
        );
});

it('includes is_free boolean in each plan', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->get(route('subscription.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->whereType('plans.0.is_free', 'boolean')
                ->whereType('plans.1.is_free', 'boolean')
                ->whereType('plans.2.is_free', 'boolean')
                ->where('plans.0.is_free', false)
                ->where('plans.1.is_free', false)
                ->where('plans.2.is_free', false)
        );
});

it('includes trial_days and slug for each plan to drive button labels', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->get(route('subscription.index'))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->whereType('plans.0.trial_days', 'integer')
                ->whereType('plans.0.slug', 'string')
                ->where('plans.0.slug', 'pemula')
                ->where('plans.0.trial_days', 14)
                ->where('plans.1.slug', 'profesional')
                ->where('plans.1.trial_days', 0)
                ->where('plans.2.slug', 'enterprise')
                ->where('plans.2.trial_days', 0)
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
        /** @var object{company: Company, user: User} $this */
        actingAs($this->user)
            ->postJson(route('subscription.upgrade'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    });

    it('rejects non-existent plan_id', function () {
        /** @var object{company: Company, user: User} $this */
        actingAs($this->user)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => 99999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    });

    it('rejects invalid billing_cycle', function () {
        /** @var object{company: Company, user: User} $this */
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

describe('Upgrade to Pemula plan (with trial)', function () {
    it('starts trial for Pemula plan', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $pemula = Plan::where('slug', 'pemula')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $pemula->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'subscription']);

        $sub = $freshCompany->fresh()->subscription()->first();
        expect($sub->status)->toBe('trialing');
        expect($sub->trial_ends_at)->not->toBeNull();
    });

    it('starts trial with correct billing cycle for Pemula', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $pemula = Plan::where('slug', 'pemula')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $pemula->id,
                'billing_cycle' => 'annual',
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'subscription']);

        $sub = $freshCompany->fresh()->subscription()->first();
        expect($sub->billing_cycle)->toBe('annual');
        expect($sub->status)->toBe('trialing');
    });
});

describe('Upgrade to paid plan without trial', function () {
    it('upgrade to Profesional creates pending_payment (no trial)', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonStructure(['snap_token', 'order_id']);

        $sub = $freshCompany->fresh()->subscription()->first();
        expect($sub->status)->toBe('pending_payment');
        expect($sub->trial_ends_at)->toBeNull();
        expect($sub->ends_at)->toBeNull(); // Not set until payment confirmed

        // activeSubscription() should not return pending_payment subscriptions
        expect($freshCompany->activeSubscription())->toBeNull();
    });

    it('upgrade to Enterprise creates pending_payment (no trial)', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $enterprise = Plan::where('slug', 'enterprise')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $enterprise->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk()
            ->assertJsonStructure(['snap_token', 'order_id']);

        $sub = $freshCompany->fresh()->subscription()->first();
        expect($sub->status)->toBe('pending_payment');
        expect($sub->trial_ends_at)->toBeNull();
        expect($sub->ends_at)->toBeNull();
    });

    it('creates a pending invoice with the correct amount for paid plans', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $sub = $freshCompany->fresh()->subscription()->first();
        $invoice = $sub->invoices()->first();

        expect($invoice)->not->toBeNull();
        expect($invoice->status)->toBe('pending');
        expect((float) $invoice->amount)->toBe(149000.0);
        expect($invoice->midtrans_order_id)->toStartWith('SUB-');
    });

    it('subscription index page shows pending payment info after upgrade without paying', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        // Visit the subscription page — should show pending info, not active
        actingAs($freshUser)
            ->get(route('subscription.index'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('currentSubscription', null)
                    ->has('pendingSubscription')
                    ->where('pendingSubscription.status', 'pending_payment')
                    ->whereType('pendingSubscription.invoice.amount', 'integer')
            );
    });
});

// ---------------------------------------------------------------------------
// Upgrade — from existing subscription
// ---------------------------------------------------------------------------

describe('Upgrade from existing subscription', function () {
    it('can upgrade from free plan to paid plan while active', function () {
        /** @var object{company: Company, user: User} $this */
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($this->user)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $pending = $this->company->fresh()->subscription()
            ->where('status', 'pending_payment')
            ->first();

        expect($pending)->not->toBeNull()
            ->and($pending->plan_id)->toBe($profesional->id);

        // activeSubscription should be null since payment is pending
        expect($this->company->fresh()->activeSubscription())->toBeNull();
    });

    it('cancels old subscription when upgrading', function () {
        /** @var object{company: Company, user: User} $this */
        $oldSubscription = $this->company->activeSubscription();
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($this->user)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        expect($oldSubscription->fresh()->status)->toBe('canceled');
    });

    it('can upgrade while on free trial', function () {
        /** @var object{company: Company, user: User} $this */
        $this->company->activeSubscription()->update(['status' => 'trialing', 'trial_ends_at' => now()->addDays(7)]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($this->user)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $pending = $this->company->fresh()->subscription()
            ->where('status', 'pending_payment')
            ->first();

        expect($pending)->not->toBeNull()
            ->and($pending->plan_id)->toBe($profesional->id);
    });

    it('can switch between paid plans', function () {
        /** @var object{company: Company, user: User} $this */
        $this->company->activeSubscription()->update([
            'plan_id' => Plan::where('slug', 'profesional')->first()->id,
            'status' => 'active',
        ]);
        $enterprise = Plan::where('slug', 'enterprise')->first();

        actingAs($this->user)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $enterprise->id,
                'billing_cycle' => 'annual',
            ])
            ->assertOk();

        $pending = $this->company->fresh()->subscription()
            ->where('status', 'pending_payment')
            ->first();

        expect($pending)->not->toBeNull()
            ->and($pending->plan_id)->toBe($enterprise->id);
    });

    it('only has one non-canceled subscription after upgrade', function () {
        /** @var object{company: Company, user: User} $this */
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($this->user)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $nonCanceledCount = Subscription::where('company_id', $this->company->id)
            ->whereNotIn('status', ['canceled', 'expired'])
            ->count();

        expect($nonCanceledCount)->toBe(1);
    });
});

// ---------------------------------------------------------------------------
// Cancel
// ---------------------------------------------------------------------------

describe('Cancel subscription', function () {
    it('super_admin can cancel a subscription', function () {
        /** @var object{company: Company, user: User} $this */
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
        /** @var object{company: Company, user: User} $this */
        $subscription = $this->company->subscription()->first();

        post(route('subscription.cancel', $subscription))
            ->assertRedirect(route('login'));
    });

    it('does not create duplicate subscriptions after cancel', function () {
        /** @var object{company: Company, user: User} $this */
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['company_id' => $this->company->id]);
        $superAdmin->syncRoles(['super_admin']);

        $subscription = $this->company->subscription()->first();
        $countBefore = Subscription::where('company_id', $this->company->id)->count();

        actingAs($superAdmin)
            ->post(route('subscription.cancel', $subscription));

        expect(Subscription::where('company_id', $this->company->id)->count())->toBe($countBefore);
    });

    it('blocks dashboard access after subscription is canceled', function () {
        /** @var object{company: Company, user: User} $this */
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['company_id' => $this->company->id]);
        $superAdmin->syncRoles(['super_admin']);

        $subscription = $this->company->subscription()->first();

        actingAs($superAdmin)
            ->post(route('subscription.cancel', $subscription));

        actingAs($superAdmin)
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.index'));
    });

    it('allows access to subscription page after cancel so user can re-subscribe', function () {
        /** @var object{company: Company, user: User} $this */
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['company_id' => $this->company->id]);
        $superAdmin->syncRoles(['super_admin']);

        $subscription = $this->company->subscription()->first();

        actingAs($superAdmin)
            ->post(route('subscription.cancel', $subscription));

        actingAs($superAdmin)
            ->get(route('subscription.index'))
            ->assertOk();
    });

    it('blocks all app routes after cancel, redirecting to subscription page', function () {
        /** @var object{company: Company, user: User} $this */
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['company_id' => $this->company->id]);
        $superAdmin->syncRoles(['super_admin']);

        $subscription = $this->company->subscription()->first();

        actingAs($superAdmin)
            ->post(route('subscription.cancel', $subscription));

        // After cancel every app route is gated — EnsureActiveSubscription redirects
        $appRoutes = [
            route('dashboard'),
            route('purchase.index'),
            route('reports.sale.index'),
        ];

        foreach ($appRoutes as $url) {
            actingAs($superAdmin)
                ->get($url)
                ->assertRedirect(route('subscription.index'));
        }
    });
});

// ---------------------------------------------------------------------------
// Webhook — subscription activation after payment
// ---------------------------------------------------------------------------

describe('Midtrans webhook activates subscription', function () {
    it('activates subscription on settlement notification', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $freshCompany->update(['owner_id' => $freshUser->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $sub = $freshCompany->fresh()->subscription()->first();
        expect($sub->status)->toBe('pending_payment');

        // Simulate Midtrans settlement webhook
        $invoice = $sub->invoices()->first();

        $response = $this->postJson(route('midtrans.notification'), [
            'order_id' => $invoice->midtrans_order_id,
            'transaction_status' => 'settlement',
            'transaction_id' => 'TRX-'.fake()->uuid(),
            'payment_type' => 'bank_transfer',
            'status_code' => '200',
            'gross_amount' => (string) $invoice->amount,
            'signature_key' => hash(
                'sha512',
                $invoice->midtrans_order_id.'200'.(string) $invoice->amount.config('midtrans.server_key')
            ),
            'fraud_status' => 'accept',
        ]);

        $response->assertSuccessful();

        $sub = $sub->fresh();
        expect($sub->status)->toBe('active');
        expect($sub->ends_at)->not->toBeNull();
        expect($sub->trial_ends_at)->toBeNull();

        $invoice = $invoice->fresh();
        expect($invoice->status)->toBe('paid');
        expect($invoice->paid_at)->not->toBeNull();

        // Company should now have an active subscription
        expect($freshCompany->fresh()->activeSubscription())->not->toBeNull();
    });

    it('marks invoice as failed and cancels subscription on deny notification', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $freshCompany->update(['owner_id' => $freshUser->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $sub = $freshCompany->fresh()->subscription()->first();
        expect($sub->status)->toBe('pending_payment');

        $invoice = $sub->invoices()->first();

        $response = $this->postJson(route('midtrans.notification'), [
            'order_id' => $invoice->midtrans_order_id,
            'transaction_status' => 'deny',
            'transaction_id' => 'TRX-'.fake()->uuid(),
            'payment_type' => 'bank_transfer',
            'status_code' => '202',
            'gross_amount' => (string) $invoice->amount,
            'signature_key' => hash(
                'sha512',
                $invoice->midtrans_order_id.'202'.(string) $invoice->amount.config('midtrans.server_key')
            ),
            'fraud_status' => 'deny',
        ]);

        $response->assertSuccessful();

        expect($invoice->fresh()->status)->toBe('failed');
        expect($sub->fresh()->status)->toBe('canceled');

        // Company should have reverted to free plan
        $activeSub = $freshCompany->fresh()->activeSubscription();
        expect($activeSub)->not->toBeNull();
        expect($activeSub->plan->slug)->toBe('pemula');
    });

    it('marks invoice as failed and cancels subscription on expire notification', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $freshCompany->update(['owner_id' => $freshUser->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $sub = $freshCompany->fresh()->subscription()->first();
        expect($sub->status)->toBe('pending_payment');

        $invoice = $sub->invoices()->first();

        $response = $this->postJson(route('midtrans.notification'), [
            'order_id' => $invoice->midtrans_order_id,
            'transaction_status' => 'expire',
            'transaction_id' => 'TRX-'.fake()->uuid(),
            'payment_type' => 'bank_transfer',
            'status_code' => '202',
            'gross_amount' => (string) $invoice->amount,
            'signature_key' => hash(
                'sha512',
                $invoice->midtrans_order_id.'202'.(string) $invoice->amount.config('midtrans.server_key')
            ),
            'fraud_status' => 'accept',
        ]);

        $response->assertSuccessful();

        expect($invoice->fresh()->status)->toBe('failed');
        expect($sub->fresh()->status)->toBe('canceled');

        // Company should have reverted to free plan
        $activeSub = $freshCompany->fresh()->activeSubscription();
        expect($activeSub)->not->toBeNull();
        expect($activeSub->plan->slug)->toBe('pemula');
    });

    it('does not activate subscription on pending notification', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $freshCompany->update(['owner_id' => $freshUser->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $sub = $freshCompany->fresh()->subscription()->first();
        $invoice = $sub->invoices()->first();

        $response = $this->postJson(route('midtrans.notification'), [
            'order_id' => $invoice->midtrans_order_id,
            'transaction_status' => 'pending',
            'transaction_id' => 'TRX-'.fake()->uuid(),
            'payment_type' => 'bank_transfer',
            'status_code' => '201',
            'gross_amount' => (string) $invoice->amount,
            'signature_key' => hash(
                'sha512',
                $invoice->midtrans_order_id.'201'.(string) $invoice->amount.config('midtrans.server_key')
            ),
            'fraud_status' => 'accept',
        ]);

        $response->assertSuccessful();

        expect($invoice->fresh()->status)->toBe('pending');
        expect($sub->fresh()->status)->toBe('pending_payment');
    });
});

// ---------------------------------------------------------------------------
// Retry payment
// ---------------------------------------------------------------------------

describe('Retry payment', function () {
    it('returns snap token for existing pending invoice', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $freshCompany->update(['owner_id' => $freshUser->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        // Retry payment
        actingAs($freshUser)
            ->postJson(route('subscription.retry-payment'))
            ->assertOk()
            ->assertJsonStructure(['snap_token', 'order_id']);
    });

    it('returns 404 when no pending payment exists', function () {
        /** @var object{company: Company, user: User} $this */
        actingAs($this->user)
            ->postJson(route('subscription.retry-payment'))
            ->assertNotFound()
            ->assertJson(['message' => 'Tidak ada pembayaran yang tertunda.']);
    });

    it('returns 403 when user has no company', function () {
        /** @var User $userNoCompany */
        $userNoCompany = User::factory()->create();

        actingAs($userNoCompany)
            ->postJson(route('subscription.retry-payment'))
            ->assertForbidden();
    });

    it('pending subscription can be cancelled via cancel endpoint', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['company_id' => $freshCompany->id]);
        $freshCompany->update(['owner_id' => $freshUser->id]);
        $freshUser->syncRoles(['super_admin']);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $sub = $freshCompany->fresh()->subscription()->first();
        expect($sub->status)->toBe('pending_payment');

        // Cancel the pending subscription
        actingAs($freshUser)
            ->post(route('subscription.cancel', ['subscription' => $sub->id]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Subscription dibatalkan.');

        expect($sub->fresh()->status)->toBe('canceled');
    });
});

// ---------------------------------------------------------------------------
// Pending payment upgrade edge cases
// ---------------------------------------------------------------------------

describe('Upgrade with existing pending payment', function () {
    it('cancels existing pending_payment when upgrading to another plan', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $freshCompany->update(['owner_id' => $freshUser->id]);
        $profesional = Plan::where('slug', 'profesional')->first();
        $enterprise = Plan::where('slug', 'enterprise')->first();

        // Subscribe to Profesional — creates pending_payment
        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $oldSub = $freshCompany->fresh()->subscription()->first();
        expect($oldSub->status)->toBe('pending_payment');

        // Upgrade to Enterprise — should cancel old pending_payment and create new
        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $enterprise->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        expect($oldSub->fresh()->status)->toBe('canceled');

        $newSub = $freshCompany->fresh()->subscription()
            ->where('status', 'pending_payment')
            ->first();
        expect($newSub)->not->toBeNull();
        expect($newSub->plan_id)->toBe($enterprise->id);
    });

    it('creates only one pending_payment subscription per company', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $freshCompany->update(['owner_id' => $freshUser->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $count = Subscription::where('company_id', $freshCompany->id)
            ->where('status', 'pending_payment')
            ->count();

        expect($count)->toBe(1);
    });
});

// ---------------------------------------------------------------------------
// User cannot access app routes with pending_payment subscription
// ---------------------------------------------------------------------------

describe('Route access with pending_payment subscription', function () {
    it('redirects to subscription page when user has pending_payment only', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $freshCompany->update(['owner_id' => $freshUser->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        // Should redirect to subscription page, not dashboard
        actingAs($freshUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('subscription.index'));
    });

    it('allows access to subscription page with pending_payment', function () {
        /** @var Company $freshCompany */
        $freshCompany = Company::factory()->create();
        /** @var User $freshUser */
        $freshUser = User::factory()->create(['role' => 'admin', 'company_id' => $freshCompany->id]);
        $freshCompany->update(['owner_id' => $freshUser->id]);
        $profesional = Plan::where('slug', 'profesional')->first();

        actingAs($freshUser)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        actingAs($freshUser)
            ->get(route('subscription.index'))
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->has('pendingSubscription')
                    ->where('currentSubscription', null)
            );
    });
});

// ---------------------------------------------------------------------------
// hadTrial flag in subscription index
// ---------------------------------------------------------------------------

describe('hadTrial flag in subscription index', function () {
    it('returns had_trial false when company has no trial history', function () {
        /** @var object{company: Company, user: User} $this */
        actingAs($this->user)
            ->get(route('subscription.index'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('hadTrial', false)
            );
    });

    it('returns had_trial true when company has had_trial set', function () {
        /** @var object{company: Company, user: User} $this */
        $this->company->update(['had_trial' => true]);

        actingAs($this->user)
            ->get(route('subscription.index'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('hadTrial', true)
            );
    });

    it('returns hadTrial true and null currentSubscription after cancellation', function () {
        /** @var object{company: Company, user: User} $this */
        $this->company->update(['had_trial' => true]);

        // Cancel the active subscription — user now has no active plan
        $this->company->subscription()->first()->update(['status' => 'canceled']);

        actingAs($this->user)
            ->get(route('subscription.index'))
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('hadTrial', true)
                    ->where('currentSubscription', null)
            );
    });
});

// ---------------------------------------------------------------------------
// Double-trial prevention via upgrade endpoint
// ---------------------------------------------------------------------------

describe('upgrade endpoint prevents double trial', function () {
    it('starts trial on first upgrade to plan with trial_days and sets had_trial flag', function () {
        /** @var object{company: Company, user: User} $this */
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['company_id' => $this->company->id]);
        $superAdmin->syncRoles(['super_admin']);

        // Cancel existing subscription so company has no active one
        $this->company->subscription()->first()->update(['status' => 'canceled']);

        $pemula = Plan::where('slug', 'pemula')->first();

        actingAs($superAdmin)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $pemula->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $latest = Subscription::where('company_id', $this->company->id)
            ->orderByDesc('id')
            ->first();

        expect($latest->status)->toBe('trialing');
        expect($latest->trial_ends_at)->not->toBeNull();
        expect($this->company->fresh()->had_trial)->toBeTrue();
    });

    it('creates pending_payment for Pemula when had_trial is already set', function () {
        /** @var object{company: Company, user: User} $this */
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['company_id' => $this->company->id]);
        $superAdmin->syncRoles(['super_admin']);

        $pemula = Plan::where('slug', 'pemula')->first();

        $this->company->update(['had_trial' => true]);

        actingAs($superAdmin)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $pemula->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $latest = Subscription::where('company_id', $this->company->id)
            ->orderByDesc('id')
            ->first();

        expect($latest->status)->toBe('pending_payment');
        expect($latest->trial_ends_at)->toBeNull();
    });

    it('allows upgrade to paid plan without trial when had_trial is set', function () {
        /** @var object{company: Company, user: User} $this */
        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['company_id' => $this->company->id]);
        $superAdmin->syncRoles(['super_admin']);

        $profesional = Plan::where('slug', 'profesional')->first();

        $this->company->update(['had_trial' => true]);

        actingAs($superAdmin)
            ->postJson(route('subscription.upgrade'), [
                'plan_id' => $profesional->id,
                'billing_cycle' => 'monthly',
            ])
            ->assertOk();

        $latest = Subscription::where('company_id', $this->company->id)
            ->orderByDesc('id')
            ->first();

        expect($latest->plan_id)->toBe($profesional->id);
        expect($latest->status)->toBe('pending_payment');
        expect($latest->trial_ends_at)->toBeNull();
        expect($latest->ends_at)->toBeNull();
    });
});
