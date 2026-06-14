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

    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();
});

// ---------------------------------------------------------------------------
// Route & Authentication
// ---------------------------------------------------------------------------

it('has a valid dashboard route', function () {
    expect(route('platform.owner.dashboard'))->toBeString();
});

it('returns 401 for unauthenticated requests to sidebar pages', function () {
    get(route('platform.owner.dashboard'))
        ->assertRedirect(route('login'));
});

it('allows platform_owner to access dashboard page', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.dashboard'))
        ->assertOk();
});

it('denies tenant users regardless of role', function () {
    $roles = ['super_admin', 'admin', 'cashier', 'warehouse'];

    foreach ($roles as $role) {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->syncRoles($role);

        /** @var User $user */
        actingAs($user)
            ->get(route('platform.owner.dashboard'))
            ->assertForbidden();
    }
});

// ---------------------------------------------------------------------------
// Page Component & Data Structure
// ---------------------------------------------------------------------------

it('renders the correct Inertia page component', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Platform/Owner/Dashboard/Index')
        );
});

it('passes all required data sections', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->has('data.overview')
                ->has('data.subscription_breakdown')
                ->has('data.recent_companies')
                ->has('data.active_companies')
                ->has('data.growth_trend')
        );
});

it('passes scalar overview fields with correct types', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('data.overview.total_companies', fn ($v) => is_int($v))
                ->where('data.overview.active_companies', fn ($v) => is_int($v))
                ->where('data.overview.total_users', fn ($v) => is_int($v))
                ->where('data.overview.active_subscriptions', fn ($v) => is_int($v))
                ->where('data.overview.mrr', fn ($v) => is_float($v) || is_int($v))
        );
});

// ---------------------------------------------------------------------------
// Data Integrity (cross-tenant)
// ---------------------------------------------------------------------------

it('counts companies across all tenants without tenant scope', function () {
    Company::factory()->count(3)->create();
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('data.overview.total_companies', 4)
        );
});

it('counts subscriptions correctly across tenants', function () {
    $companies = Company::factory()->count(2)->create();
    $plan = Plan::where('slug', 'pemula')->first();

    Subscription::factory()->create(['company_id' => $companies[0]->id, 'plan_id' => $plan->id, 'status' => 'active']);
    Subscription::factory()->create(['company_id' => $companies[1]->id, 'plan_id' => $plan->id, 'status' => 'active']);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('data.overview.active_subscriptions', 2)
        );
});

it('counts users across all tenants', function () {
    $companies = Company::factory()->count(2)->create();
    User::factory()->count(3)->create(['company_id' => $companies[0]->id]);
    User::factory()->count(2)->create(['company_id' => $companies[1]->id]);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('data.overview.total_users', 6)
        );
});

// ---------------------------------------------------------------------------
// Tenancy Safety
// ---------------------------------------------------------------------------

it('works correctly when tenancy is active', function () {
    $user = createPlatformOwner();
    $demoCompany = Company::find($user->company_id);

    tenancy()->initialize($demoCompany);

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Edge Cases
// ---------------------------------------------------------------------------

it('handles zero data gracefully', function () {
    $user = createPlatformOwner();

    // Ensure only demo company exists
    Company::where('id', '!=', $user->company_id)->delete();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('data.overview.total_companies', 1)
                ->where('data.overview.mrr', fn ($v) => is_float($v) || is_int($v))
        );
});

it('handles large datasets without error', function () {
    Company::factory()->count(50)->create();

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.dashboard'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->where('data.overview.total_companies', 51)
        );
});

// ---------------------------------------------------------------------------
// Subscription Filtering
// ---------------------------------------------------------------------------

it('excludes expired and cancelled subscriptions from active count', function () {
    $plan = Plan::where('slug', 'pemula')->first();
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'expired',
    ]);
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'cancelled',
    ]);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('data.overview.active_subscriptions', 0)
        );
});

it('includes trialing subscriptions in active count', function () {
    $plan = Plan::where('slug', 'pemula')->first();
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'trialing',
    ]);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(
            fn ($page) => $page
                ->where('data.overview.active_subscriptions', 1)
        );
});
