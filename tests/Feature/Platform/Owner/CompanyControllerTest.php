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

it('denies guests from companies index', function () {
    get(route('platform.owner.companies.index'))
        ->assertRedirect(route('login'));
});

it('denies guests from companies show', function () {
    get(route('platform.owner.companies.show', 1))
        ->assertRedirect(route('login'));
});

it('denies tenant users from companies index', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles('admin');

    actingAs($user)
        ->get(route('platform.owner.companies.index'))
        ->assertForbidden();
});

it('allows platform_owner to view companies index', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.companies.index'))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Companies Index
// ---------------------------------------------------------------------------

it('renders correct Inertia component for companies index', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.companies.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Owner/Company/Index')
        );
});

it('passes companies as paginated data', function () {
    Company::factory()->count(5)->create();
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.companies.index'))
        ->assertInertia(fn ($page) => $page
            ->has('companies.data')
            ->where('companies.total', 6)
            ->where('companies.per_page', 25)
        );
});

it('includes all companies across all tenants', function () {
    Company::factory()->count(3)->create();
    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.companies.index'))
        ->assertInertia(fn ($page) => $page
            ->where('companies.total', 4)
        );
});

it('orders companies by latest first', function () {
    $user = createPlatformOwner();
    $old = Company::factory()->create(['created_at' => now()->subDays(5)]);
    $new = Company::factory()->create(['created_at' => now()->addSecond()]);

    actingAs($user)
        ->get(route('platform.owner.companies.index'))
        ->assertInertia(fn ($page) => $page
            ->where('companies.data.0.id', $new->id)
        );
});

it('includes users_count and subscription relation in companies', function () {
    $company = Company::factory()->create();
    User::factory()->count(3)->create(['company_id' => $company->id]);

    $plan = Plan::factory()->pemula()->create();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $user = createPlatformOwner();

    actingAs($user)
        ->get(route('platform.owner.companies.index'))
        ->assertInertia(fn ($page) => $page
            ->has('companies.data.0.users_count')
            ->has('companies.data.0.subscription')
        );
});

// ---------------------------------------------------------------------------
// Companies Show
// ---------------------------------------------------------------------------

it('renders correct Inertia component for companies show', function () {
    $company = Company::factory()->create();

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.companies.show', $company->id))
        ->assertInertia(fn ($page) => $page
            ->component('Platform/Owner/Company/Show')
        );
});

it('shows company details on show page', function () {
    $company = Company::factory()->create(['name' => 'Test Company']);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.companies.show', $company->id))
        ->assertInertia(fn ($page) => $page
            ->where('company.name', 'Test Company')
        );
});

it('includes owner and subscription on company show', function () {
    $company = Company::factory()->create();
    $owner = User::factory()->create(['company_id' => $company->id]);
    $company->update(['owner_id' => $owner->id]);

    $plan = Plan::factory()->pemula()->create();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.companies.show', $company->id))
        ->assertInertia(fn ($page) => $page
            ->has('company.owner')
            ->has('company.subscription')
        );
});

it('returns 404 for non-existent company', function () {
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.companies.show', 99999))
        ->assertNotFound();
});

// ---------------------------------------------------------------------------
// Edge Cases
// ---------------------------------------------------------------------------

it('handles empty companies list', function () {
    $user = createPlatformOwner();

    Company::where('id', '!=', $user->company_id)->delete();

    actingAs($user)
        ->get(route('platform.owner.companies.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('companies.total', 1)
        );
});

it('handles many companies without performance issues', function () {
    Company::factory()->count(50)->create();

    $start = microtime(true);
    actingAs(createPlatformOwner())
        ->get(route('platform.owner.companies.index'));
    $duration = (microtime(true) - $start) * 1000;

    expect($duration)->toBeLessThan(2000);
});
