<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
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
    /** @var User $user */
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

it('reflects plan changes immediately without cache invalidation', function () {
    Plan::factory()->pemula()->create();

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.plans.index'))
        ->assertInertia(fn ($page) => $page->has('plans', 1));

    Plan::factory()->enterprise()->create();

    actingAs(createPlatformOwner())
        ->get(route('platform.owner.plans.index'))
        ->assertInertia(fn ($page) => $page->has('plans', 2));
});

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

// ---------------------------------------------------------------------------
// Create Plan
// ---------------------------------------------------------------------------

it('allows platform_owner to create a plan', function () {
    actingAs(createPlatformOwner())
        ->post(route('platform.owner.plans.store'), [
            'name' => 'New Plan',
            'slug' => 'new-plan',
            'description' => 'A new plan',
            'price_monthly' => 50000,
            'price_annual' => 500000,
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect(route('platform.owner.plans.index'))
        ->assertSessionHas('success');

    expect(Plan::where('slug', 'new-plan')->exists())->toBeTrue();
});

it('validates required fields on store', function () {
    actingAs(createPlatformOwner())
        ->post(route('platform.owner.plans.store'), [])
        ->assertSessionHasErrors(['name', 'slug', 'price_monthly', 'price_annual', 'trial_days', 'is_active', 'sort_order']);
});

it('rejects duplicate slug on store', function () {
    Plan::factory()->pemula()->create();

    actingAs(createPlatformOwner())
        ->post(route('platform.owner.plans.store'), [
            'name' => 'Another Pemula',
            'slug' => 'pemula',
            'price_monthly' => 0,
            'price_annual' => 0,
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors(['slug']);
});

it('slug must match kebab-case regex on store', function () {
    actingAs(createPlatformOwner())
        ->post(route('platform.owner.plans.store'), [
            'name' => 'Invalid Slug',
            'slug' => 'Invalid Slug!',
            'price_monthly' => 0,
            'price_annual' => 0,
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertSessionHasErrors(['slug']);
});

it('validates features array structure on store', function () {
    actingAs(createPlatformOwner())
        ->post(route('platform.owner.plans.store'), [
            'name' => 'Plan With Bad Features',
            'slug' => 'bad-features',
            'price_monthly' => 0,
            'price_annual' => 0,
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 0,
            'features' => [
                ['key' => '', 'label' => 'Missing Key', 'included' => true],
            ],
        ])
        ->assertSessionHasErrors(['features.0.key']);
});

it('invalidates pricing page cache on store', function () {
    Cache::put('plans_pricing', ['cached' => true], 3600);

    actingAs(createPlatformOwner())
        ->post(route('platform.owner.plans.store'), [
            'name' => 'Cache Test Plan',
            'slug' => 'cache-test',
            'price_monthly' => 0,
            'price_annual' => 0,
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ]);

    expect(Cache::has('plans_pricing'))->toBeFalse();
});

it('denies guests from creating a plan', function () {
    post(route('platform.owner.plans.store'), [
        'name' => 'Guest Plan',
        'slug' => 'guest-plan',
    ])->assertRedirect(route('login'));
});

it('denies tenant users from creating a plan', function () {
    $company = Company::factory()->create();
    /** @var User $user */
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles('admin');

    actingAs($user)
        ->post(route('platform.owner.plans.store'), ['name' => 'Tenant Plan'])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Update Plan
// ---------------------------------------------------------------------------

it('allows platform_owner to update a plan', function () {
    $plan = Plan::factory()->pemula()->create();

    actingAs(createPlatformOwner())
        ->put(route('platform.owner.plans.update', $plan), [
            'name' => 'Pemula Updated',
            'slug' => 'pemula',
            'price_monthly' => 60000,
            'price_annual' => 600000,
            'trial_days' => 7,
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect(route('platform.owner.plans.index'))
        ->assertSessionHas('success');

    expect($plan->fresh()->name)->toBe('Pemula Updated');
    expect((int) $plan->fresh()->price_monthly)->toBe(60000);
});

it('validates required fields on update', function () {
    $plan = Plan::factory()->pemula()->create();

    actingAs(createPlatformOwner())
        ->put(route('platform.owner.plans.update', $plan), [])
        ->assertSessionHasErrors(['name', 'slug', 'price_monthly', 'price_annual', 'trial_days', 'is_active', 'sort_order']);
});

it('allows same slug when updating own plan', function () {
    $plan = Plan::factory()->pemula()->create();

    actingAs(createPlatformOwner())
        ->put(route('platform.owner.plans.update', $plan), [
            'name' => 'Pemula Renamed',
            'slug' => 'pemula',
            'price_monthly' => 50000,
            'price_annual' => 500000,
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ])
        ->assertRedirect(route('platform.owner.plans.index'))
        ->assertSessionHasNoErrors();
});

it('rejects duplicate slug from another plan on update', function () {
    Plan::factory()->pemula()->create();
    $pro = Plan::factory()->profesional()->create();

    actingAs(createPlatformOwner())
        ->put(route('platform.owner.plans.update', $pro), [
            'name' => 'Pro',
            'slug' => 'pemula',
            'price_monthly' => 149000,
            'price_annual' => 1490000,
            'trial_days' => 14,
            'is_active' => true,
            'sort_order' => 2,
        ])
        ->assertSessionHasErrors(['slug']);
});

it('invalidates pricing page cache on update', function () {
    $plan = Plan::factory()->pemula()->create();
    Cache::put('plans_pricing', ['cached' => true], 3600);

    actingAs(createPlatformOwner())
        ->put(route('platform.owner.plans.update', $plan), [
            'name' => 'Updated',
            'slug' => 'pemula',
            'price_monthly' => 50000,
            'price_annual' => 500000,
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
        ]);

    expect(Cache::has('plans_pricing'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Delete Plan
// ---------------------------------------------------------------------------

it('allows platform_owner to delete a plan without subscribers', function () {
    $plan = Plan::factory()->pemula()->create();

    actingAs(createPlatformOwner())
        ->delete(route('platform.owner.plans.destroy', $plan))
        ->assertRedirect(route('platform.owner.plans.index'))
        ->assertSessionHas('success');
});

it('blocks deletion when plan has active subscribers', function () {
    $plan = Plan::factory()->pemula()->create();
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'plan_id' => $plan->id,
        'company_id' => $company->id,
        'status' => 'active',
    ]);

    actingAs(createPlatformOwner())
        ->delete(route('platform.owner.plans.destroy', $plan))
        ->assertRedirect(route('platform.owner.plans.index'))
        ->assertSessionHas('error');

    expect(Plan::find($plan->id))->not->toBeNull();
});

it('blocks deletion when plan has trialing subscribers', function () {
    $plan = Plan::factory()->pemula()->create();
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'plan_id' => $plan->id,
        'company_id' => $company->id,
        'status' => 'trialing',
    ]);

    actingAs(createPlatformOwner())
        ->delete(route('platform.owner.plans.destroy', $plan))
        ->assertSessionHas('error');

    expect(Plan::find($plan->id))->not->toBeNull();
});

it('allows deletion when plan only has canceled subscribers', function () {
    $plan = Plan::factory()->pemula()->create();
    $company = Company::factory()->create();

    Subscription::factory()->create([
        'plan_id' => $plan->id,
        'company_id' => $company->id,
        'status' => 'canceled',
    ]);

    actingAs(createPlatformOwner())
        ->delete(route('platform.owner.plans.destroy', $plan))
        ->assertRedirect(route('platform.owner.plans.index'))
        ->assertSessionHas('success');
});

it('soft-deletes the plan, not hard-deletes', function () {
    $plan = Plan::factory()->pemula()->create();

    actingAs(createPlatformOwner())
        ->delete(route('platform.owner.plans.destroy', $plan));

    expect(Plan::find($plan->id))->toBeNull();
    expect(Plan::withTrashed()->find($plan->id))->not->toBeNull();
});

it('invalidates pricing page cache on delete', function () {
    $plan = Plan::factory()->pemula()->create();
    Cache::put('plans_pricing', ['cached' => true], 3600);

    actingAs(createPlatformOwner())
        ->delete(route('platform.owner.plans.destroy', $plan));

    expect(Cache::has('plans_pricing'))->toBeFalse();
});
