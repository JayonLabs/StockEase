<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->adminUser = User::factory()->create(['role' => 'admin']);

    $this->plan = Plan::factory()->profesional()->create();

    $this->targetCompany = Company::factory()->create();
    $this->targetUser = User::factory()->create([
        'company_id' => $this->targetCompany->id,
        'role' => 'admin',
    ]);
    $this->targetCompany->update(['owner_id' => $this->targetUser->id]);

    $this->subscription = Subscription::factory()->active()->create([
        'company_id' => $this->targetCompany->id,
        'plan_id' => $this->plan->id,
    ]);
});

// ---------------------------------------------------------------------------
// Authorization
// ---------------------------------------------------------------------------

describe('Authorization', function () {
    it('redirects unauthenticated user from index', function () {
        get(route('admin.subscriptions.index'))->assertRedirect(route('login'));
    });

    it('redirects unauthenticated user from show', function () {
        get(route('admin.subscriptions.show', $this->subscription))->assertRedirect(route('login'));
    });

    it('forbids cashier from accessing admin subscription index', function () {
        $cashier = User::factory()->create(['role' => 'cashier']);

        actingAs($cashier)
            ->get(route('admin.subscriptions.index'))
            ->assertForbidden();
    });

    it('allows admin to access subscription index', function () {
        actingAs($this->adminUser)
            ->get(route('admin.subscriptions.index'))
            ->assertOk();
    });
});

// ---------------------------------------------------------------------------
// Index
// ---------------------------------------------------------------------------

describe('Index', function () {
    it('renders the correct Inertia component', function () {
        actingAs($this->adminUser)
            ->get(route('admin.subscriptions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Subscriptions/Index')
                ->has('subscriptions')
                ->has('filters')
            );
    });

    it('lists subscriptions with company and plan relations', function () {
        actingAs($this->adminUser)
            ->get(route('admin.subscriptions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('subscriptions.data', 1)
            );
    });

    it('filters subscriptions by status', function () {
        Subscription::factory()->canceled()->create([
            'company_id' => Company::factory()->create()->id,
            'plan_id' => $this->plan->id,
        ]);

        actingAs($this->adminUser)
            ->get(route('admin.subscriptions.index', ['status' => 'active']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('subscriptions.data', 1)
                ->where('filters.status', 'active')
            );
    });

    it('shows all subscriptions when no status filter applied', function () {
        Subscription::factory()->canceled()->create([
            'company_id' => Company::factory()->create()->id,
            'plan_id' => $this->plan->id,
        ]);

        actingAs($this->adminUser)
            ->get(route('admin.subscriptions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('subscriptions.data', 2)
            );
    });

    it('paginates subscriptions', function () {
        Subscription::factory()->count(30)->create(['plan_id' => $this->plan->id]);

        actingAs($this->adminUser)
            ->get(route('admin.subscriptions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('subscriptions.data', 25)
                ->has('subscriptions.total')
            );
    });
});

// ---------------------------------------------------------------------------
// Show
// ---------------------------------------------------------------------------

describe('Show', function () {
    it('renders the show component with subscription data', function () {
        actingAs($this->adminUser)
            ->get(route('admin.subscriptions.show', $this->subscription))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Subscriptions/Show')
                ->has('subscription')
                ->where('subscription.id', $this->subscription->id)
            );
    });

    it('loads related company, owner, plan, and invoices', function () {
        actingAs($this->adminUser)
            ->get(route('admin.subscriptions.show', $this->subscription))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('subscription.company')
                ->has('subscription.plan')
                ->has('subscription.invoices')
            );
    });
});

// ---------------------------------------------------------------------------
// Update
// ---------------------------------------------------------------------------

describe('Update', function () {
    it('updates subscription status successfully', function () {
        actingAs($this->adminUser)
            ->put(route('admin.subscriptions.update', $this->subscription), [
                'status' => 'canceled',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Subscription diperbarui.');

        expect($this->subscription->fresh()->status)->toBe('canceled');
    });

    it('updates ends_at and notes', function () {
        $endsAt = now()->addYear()->toDateString();

        actingAs($this->adminUser)
            ->put(route('admin.subscriptions.update', $this->subscription), [
                'status' => 'active',
                'ends_at' => $endsAt,
                'notes' => 'Extended by support team',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $this->subscription->fresh();
        expect($fresh->notes)->toBe('Extended by support team');
    });

    it('rejects invalid status value', function () {
        actingAs($this->adminUser)
            ->put(route('admin.subscriptions.update', $this->subscription), [
                'status' => 'invalid-status',
            ])
            ->assertSessionHasErrors(['status']);
    });

    it('accepts all valid status values', function (string $status) {
        actingAs($this->adminUser)
            ->put(route('admin.subscriptions.update', $this->subscription), [
                'status' => $status,
            ])
            ->assertRedirect();
    })->with(['active', 'canceled', 'expired', 'trialing', 'pending']);
});

// ---------------------------------------------------------------------------
// Assign
// ---------------------------------------------------------------------------

describe('Assign', function () {
    it('assigns a plan to a user company', function () {
        $freshCompany = Company::factory()->create();
        $freshUser = User::factory()->create([
            'company_id' => $freshCompany->id,
            'role' => 'admin',
        ]);
        $freshCompany->update(['owner_id' => $freshUser->id]);

        actingAs($this->adminUser)
            ->post(route('admin.subscriptions.assign'), [
                'user_id' => $freshUser->id,
                'plan_id' => $this->plan->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Subscription berhasil di-assign.');

        expect($freshCompany->fresh()->activeSubscription())->not->toBeNull();
    });

    it('returns error when user has no company', function () {
        $userNoCompany = User::factory()->create();

        actingAs($this->adminUser)
            ->post(route('admin.subscriptions.assign'), [
                'user_id' => $userNoCompany->id,
                'plan_id' => $this->plan->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'User tidak memiliki company.');
    });

    it('rejects missing user_id', function () {
        actingAs($this->adminUser)
            ->post(route('admin.subscriptions.assign'), [
                'plan_id' => $this->plan->id,
            ])
            ->assertSessionHasErrors(['user_id']);
    });

    it('rejects non-existent user_id', function () {
        actingAs($this->adminUser)
            ->post(route('admin.subscriptions.assign'), [
                'user_id' => 99999,
                'plan_id' => $this->plan->id,
            ])
            ->assertSessionHasErrors(['user_id']);
    });

    it('rejects missing plan_id', function () {
        actingAs($this->adminUser)
            ->post(route('admin.subscriptions.assign'), [
                'user_id' => $this->targetUser->id,
            ])
            ->assertSessionHasErrors(['plan_id']);
    });
});
