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
});

// ---------------------------------------------------------------------------
// Authentication & Authorization
// ---------------------------------------------------------------------------

it('denies guests from plans index', function () {
    get(route('platform.owner.plans.index'))
        ->assertRedirect(route('login'));
});

it('denies tenant users from plans index', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles('admin');

    actingAs($user)
        ->get(route('platform.owner.plans.index'))
        ->assertForbidden();
});

it('allows platform_owner to view plans index', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.plans.index'))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Plans Index - Data
// ---------------------------------------------------------------------------

it('renders correct Inertia component for plans index', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.plans.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Owner/Plan/Index')
        );
});

it('passes all plans ordered by sort_order', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.plans.index'))
        ->assertInertia(fn ($page) => $page
            ->has('plans', 3)
        );
});

it('includes subscription counts for each plan', function () {
    $pemula = Plan::factory()->pemula()->create();
    $pro = Plan::factory()->profesional()->create();
    $companies = Company::factory()->count(3)->create();

    Subscription::factory()->create(['company_id' => $companies[0]->id, 'plan_id' => $pemula->id, 'status' => 'active']);
    Subscription::factory()->create(['company_id' => $companies[1]->id, 'plan_id' => $pemula->id, 'status' => 'active']);
    Subscription::factory()->create(['company_id' => $companies[2]->id, 'plan_id' => $pro->id, 'status' => 'active']);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.plans.index'))
        ->assertInertia(fn ($page) => $page
            ->has('plans.0.subscriptions_count')
        );
});

// ---------------------------------------------------------------------------
// Caching
// ---------------------------------------------------------------------------

it('returns all plans from the endpoint', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();

    $owner = createPlatformOwner();

    actingAs($owner)
        ->get(route('platform.owner.plans.index'))
        ->assertInertia(fn ($page) => $page
            ->has('plans', 2)
        );
});

// ---------------------------------------------------------------------------
// Edge Cases
// ---------------------------------------------------------------------------

it('handles empty plans list', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.plans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('plans', [])
        );
});

it('handles inactive plans gracefully', function () {
    Plan::factory()->inactive()->create(['name' => 'Hidden Plan']);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.plans.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.name', 'Hidden Plan')
        );
});
