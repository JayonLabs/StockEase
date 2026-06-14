<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    seed(RoleAndPermissionSeeder::class);

    $this->plan = Plan::factory()->pemula()->create();
    $this->planPro = Plan::factory()->profesional()->create();
});

// ---------------------------------------------------------------------------
// Authentication & Authorization
// ---------------------------------------------------------------------------

it('denies guests from subscriptions index', function () {
    get(route('platform.owner.subscriptions.index'))
        ->assertRedirect(route('login'));
});

it('denies guests from subscriptions show', function () {
    get(route('platform.owner.subscriptions.show', 1))
        ->assertRedirect(route('login'));
});

it('denies tenant users from subscriptions index', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles('admin');

    actingAs($user)
        ->get(route('platform.owner.subscriptions.index'))
        ->assertForbidden();
});

it('allows platform_owner to view subscriptions index', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.index'))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Subscriptions Index
// ---------------------------------------------------------------------------

it('renders correct component for subscriptions index', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Owner/Subscription/Index')
        );
});

it('passes subscriptions as paginated data', function () {
    $companies = Company::factory()->count(5)->create();
    foreach ($companies as $company) {
        Subscription::factory()->create([
            'company_id' => $company->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
    }

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.index'))
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data')
            ->where('subscriptions.total', 5)
        );
});

it('includes company and plan relations', function () {
    $company = Company::factory()->create(['name' => 'Subscribed Co']);
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.index'))
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data.0.company')
            ->has('subscriptions.data.0.plan')
            ->where('subscriptions.data.0.company.name', 'Subscribed Co')
        );
});

it('orders subscriptions by latest first', function () {
    $company = Company::factory()->create();
    $oldSub = Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'created_at' => now()->subDays(5),
    ]);
    $newSub = Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'trialing',
        'created_at' => now(),
    ]);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('subscriptions.data.0.id', $newSub->id)
            ->where('subscriptions.data.1.id', $oldSub->id)
        );
});

// ---------------------------------------------------------------------------
// Filtering
// ---------------------------------------------------------------------------

it('filters subscriptions by status', function () {
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

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.index', ['status' => 'canceled']))
        ->assertInertia(fn ($page) => $page
            ->where('subscriptions.total', 1)
            ->where('subscriptions.data.0.status', 'canceled')
            ->where('filters.status', 'canceled')
        );
});

it('passes filters data to the page', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.index', ['status' => 'active']))
        ->assertInertia(fn ($page) => $page
            ->has('filters')
            ->where('filters.status', 'active')
        );
});

// ---------------------------------------------------------------------------
// Subscriptions Show
// ---------------------------------------------------------------------------

it('renders correct component for subscriptions show', function () {
    $company = Company::factory()->create();
    $subscription = Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.show', $subscription->id))
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Owner/Subscription/Show')
        );
});

it('shows subscription details on show page', function () {
    $company = Company::factory()->create(['name' => 'Detail Co']);
    $subscription = Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'billing_cycle' => 'annual',
    ]);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.show', $subscription->id))
        ->assertInertia(fn ($page) => $page
            ->where('subscription.id', $subscription->id)
            ->where('subscription.status', 'active')
            ->where('subscription.billing_cycle', 'annual')
        );
});

it('includes company, plan, and invoices on subscription show', function () {
    $company = Company::factory()->create();
    $owner = User::factory()->create(['company_id' => $company->id]);
    $company->update(['owner_id' => $owner->id]);

    $subscription = Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.show', $subscription->id))
        ->assertInertia(fn ($page) => $page
            ->has('subscription.company')
            ->has('subscription.plan')
            ->has('subscription.invoices')
        );
});

it('returns 404 for non-existent subscription', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.show', 99999))
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Edge Cases
// ---------------------------------------------------------------------------

it('handles empty subscriptions list', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('subscriptions.total', 0)
        );
});

it('handles filter with no matching subscriptions', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.index', ['status' => 'active']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('subscriptions.total', 0)
        );
});

it('shows subscription without company owner gracefully', function () {
    $company = Company::factory()->create(['owner_id' => null]);
    $subscription = Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
    ]);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.subscriptions.show', $subscription->id))
        ->assertOk();
});
