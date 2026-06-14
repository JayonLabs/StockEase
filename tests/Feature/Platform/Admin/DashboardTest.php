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
// Middleware & Authorization
// ---------------------------------------------------------------------------

it('denies guests from platform admin dashboard', function () {
    get(route('platform.owner.dashboard'))
        ->assertRedirect(route('login'));
});

it('denies tenant users with super_admin role', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles('super_admin');

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertForbidden();
});

it('denies tenant users with any tenant role', function () {
    $roles = ['admin', 'cashier', 'warehouse'];

    foreach ($roles as $role) {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->syncRoles($role);

        actingAs($user)
            ->get(route('platform.owner.dashboard'))
            ->assertForbidden();
    }
});

it('allows platform_owner to view dashboard', function () {
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertOk();
});

it('allows platform_owner even when tenancy is active', function () {
    $user = createPlatformOwner();
    $demoCompany = Company::find($user->company_id);

    tenancy()->initialize($demoCompany);

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Data Integrity
// ---------------------------------------------------------------------------

it('passes dashboard data as inertia prop', function () {
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Owner/Dashboard/Index')
            ->has('data.overview')
            ->has('data.subscription_breakdown')
            ->has('data.recent_companies')
            ->has('data.active_companies')
            ->has('data.growth_trend')
        );
});

it('includes overview counts in dashboard data', function () {
    // Create real tenants (not demo)
    Company::factory()->count(2)->create();
    // Demo company (created by createPlatformOwner) = 1 more
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('data.overview.total_companies', 3)
        );
});

it('shows companies from all tenants', function () {
    Company::factory()->count(3)->create();
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('data.overview.total_companies', 4)
        );
});

it('shows subscriptions from all tenants', function () {
    $companies = Company::factory()->count(2)->create();
    $plan = Plan::where('slug', 'pemula')->first();

    Subscription::factory()->create(['company_id' => $companies[0]->id, 'plan_id' => $plan->id, 'status' => 'active']);
    Subscription::factory()->create(['company_id' => $companies[1]->id, 'plan_id' => $plan->id, 'status' => 'active']);

    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('data.overview.active_subscriptions', 2)
        );
});

it('shows users from all tenants', function () {
    $companies = Company::factory()->count(2)->create();

    User::factory()->count(3)->create(['company_id' => $companies[0]->id]);
    User::factory()->count(2)->create(['company_id' => $companies[1]->id]);

    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('data.overview.total_users', 6)
        );
});

// ---------------------------------------------------------------------------
// No Tenant Context (critical for multi-tenant)
// ---------------------------------------------------------------------------

it('returns data without tenant scope via withoutTenancy', function () {
    Company::factory()->count(3)->create();
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('data.overview.total_companies', 4)
        );
});

// ---------------------------------------------------------------------------
// Edge Cases
// ---------------------------------------------------------------------------

it('handles zero data without errors', function () {
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('data.overview.total_companies')
            ->has('data.overview.active_subscriptions')
        );
});

it('handles many tenants without errors', function () {
    Company::factory()->count(50)->create();
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('data.overview.total_companies', 51)
        );
});

it('returns correct data type for all overview fields', function () {
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('data.overview.total_companies', fn ($v) => is_int($v))
            ->where('data.overview.active_companies', fn ($v) => is_int($v))
            ->where('data.overview.total_users', fn ($v) => is_int($v))
            ->where('data.overview.active_subscriptions', fn ($v) => is_int($v))
            ->where('data.overview.mrr', fn ($v) => is_float($v) || is_int($v))
        );
});
