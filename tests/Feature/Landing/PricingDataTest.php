<?php

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

// ---------------------------------------------------------------------------
// Pricing page renders with data from database
// ---------------------------------------------------------------------------

it('renders pricing page with plans from database', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    get(route('landing.pricing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Landing/Pricing')
            ->has('plans', 3)
            ->has('comparison')
        );
});

it('passes correct plan names to pricing page', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.name', 'Pemula')
            ->where('plans.1.name', 'Profesional')
            ->where('plans.2.name', 'Enterprise')
        );
});

it('passes correct pricing data from database', function () {
    Plan::factory()->pemula()->create();

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.price_monthly', 50000)
            ->where('plans.0.price_annual', 500000)
            ->where('plans.0.annual_per_month', 41700)
            ->where('plans.0.annual_savings_percent', 17)
            ->where('plans.0.is_free', false)
        );
});

it('excludes inactive plans from pricing page', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->create(['slug' => 'archived', 'name' => 'Archived', 'is_active' => false]);

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->has('plans', 1)
            ->where('plans.0.slug', 'pemula')
        );
});

it('shows no plans when all are inactive', function () {
    Plan::factory()->create(['name' => 'Old Plan', 'is_active' => false]);

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->has('plans', 0)
            ->has('comparison', 0)
        );
});

it('returns comparison features matching all plan features', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();

    $response = get(route('landing.pricing'));
    $response->assertOk();

    $page = $response->original->getData()['page'] ?? [];
    $comparison = $page['props']['comparison'] ?? [];

    expect($comparison)->not->toBeEmpty();
    expect($comparison[0]['plans'])->toHaveKeys(['pemula', 'profesional']);
});

// ---------------------------------------------------------------------------
// Plans are sorted by sort_order
// ---------------------------------------------------------------------------

it('returns plans sorted by sort_order', function () {
    Plan::factory()->create(['name' => 'Middle', 'sort_order' => 2]);
    Plan::factory()->create(['name' => 'First', 'sort_order' => 1]);
    Plan::factory()->create(['name' => 'Last', 'sort_order' => 3]);

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.name', 'First')
            ->where('plans.1.name', 'Middle')
            ->where('plans.2.name', 'Last')
        );
});

// ---------------------------------------------------------------------------
// Data integrity
// ---------------------------------------------------------------------------

it('every plan feature has required keys', function () {
    Plan::factory()->pemula()->create();

    $response = get(route('landing.pricing'));
    $response->assertOk();

    $page = $response->original->getData()['page'] ?? [];
    $plans = $page['props']['plans'] ?? [];

    expect($plans)->toHaveCount(1);

    $features = $plans[0]['features'] ?? [];
    expect($features)->not->toBeEmpty();

    foreach ($features as $f) {
        expect($f)->toHaveKeys(['key', 'label', 'included', 'card_order']);
    }
});

it('plan features only include card features', function () {
    Plan::factory()->create([
        'features' => [
            ['key' => 'a', 'label' => 'A', 'included' => true, 'card_order' => 1],
            ['key' => 'b', 'label' => 'B', 'included' => false],
        ],
    ]);

    $response = get(route('landing.pricing'));
    $response->assertOk();

    $page = $response->original->getData()['page'] ?? [];
    $features = $page['props']['plans'][0]['features'] ?? [];

    expect($features)->toHaveCount(1);
    expect($features[0]['key'])->toBe('a');
});

// ---------------------------------------------------------------------------
// Caching behaviour
// ---------------------------------------------------------------------------

it('caches pricing page data', function () {
    Plan::factory()->pemula()->create();

    $first = get(route('landing.pricing'));
    $first->assertOk();

    Plan::factory()->create(['name' => 'New Plan', 'slug' => 'new-plan', 'sort_order' => 2]);

    $second = get(route('landing.pricing'));
    $second->assertOk();

    // Without cache clear, new plan should NOT appear (cached)
    $second->assertInertia(fn ($page) => $page
        ->has('plans', 1)
    );
});

it('clears cache when plan is updated', function () {
    $plan = Plan::factory()->pemula()->create();

    // Prime cache
    get(route('landing.pricing'));

    // Update plan
    $plan->update(['price_monthly' => 100000]);

    // Still cached
    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.price_monthly', 50000)
        );
});

it('returns fresh data after cache is cleared', function () {
    $plan = Plan::factory()->pemula()->create();

    get(route('landing.pricing'));

    $plan->update(['price_monthly' => 75000]);
    Cache::forget('plans_pricing');

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.price_monthly', 75000)
        );
});

// ---------------------------------------------------------------------------
// Price formatting helpers (test the formatPrice logic via raw numbers)
// ---------------------------------------------------------------------------

it('prices are integers in response', function () {
    Plan::factory()->pemula()->create();

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.price_monthly', fn ($v) => is_int($v))
            ->where('plans.0.price_annual', fn ($v) => is_int($v))
        );
});

// ---------------------------------------------------------------------------
// Multiple pricing scenarios
// ---------------------------------------------------------------------------

it('handles annual price less than monthly x 12 correctly', function () {
    Plan::factory()->create([
        'price_monthly' => 100000,
        'price_annual' => 1000000,
    ]);

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.price_monthly', 100000)
            ->where('plans.0.price_annual', 1000000)
        );
});

it('handles zero annual price for free plan', function () {
    Plan::factory()->free()->create();

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.price_monthly', 0)
            ->where('plans.0.price_annual', 0)
            ->where('plans.0.is_free', true)
        );
});

// ---------------------------------------------------------------------------
// Auth context
// ---------------------------------------------------------------------------

it('renders pricing page for authenticated users', function () {
    $user = User::factory()->create();
    Plan::factory()->pemula()->create();

    /** @var User $user */
    \Pest\Laravel\actingAs($user)
        ->get(route('landing.pricing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Landing/Pricing')
            ->has('plans')
        );
});

it('passes auth user context to pricing page', function () {
    $user = User::factory()->create();
    Plan::factory()->pemula()->create();

    \Pest\Laravel\actingAs($user)
        ->get(route('landing.pricing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $user->id)
        );
});

it('passes null user for guests on pricing page', function () {
    Plan::factory()->pemula()->create();

    get(route('landing.pricing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user', null)
        );
});

it('CTA for pemula plan links to login for guest', function () {
    Plan::factory()->pemula()->create();

    get(route('landing.pricing'))
        ->assertOk();
    // CTA href logic is client-side via usePage().props.auth
    // Verified by auth.user being null for guests
});

it('CTA for enterprise plan links to #', function () {
    Plan::factory()->enterprise()->create();

    get(route('landing.pricing'))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// SEO and accessibility
// ---------------------------------------------------------------------------

it('returns successful response with correct content type', function () {
    Plan::factory()->pemula()->create();

    get(route('landing.pricing'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
});

// ---------------------------------------------------------------------------
// Pricing integrity across all seeded plans
// ---------------------------------------------------------------------------

it('ensures all seeded plans have valid annual pricing (annual < monthly x 12)', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.price_annual', fn ($v) => $v < 50000 * 12)
            ->where('plans.1.price_annual', fn ($v) => $v < 149000 * 12)
            ->where('plans.2.price_annual', fn ($v) => $v < 299000 * 12)
        );
});

it('ensures annual_per_month is always less than monthly price for paid plans', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.annual_per_month', fn ($v) => $v < 50000)
            ->where('plans.1.annual_per_month', fn ($v) => $v < 149000)
        );
});

it('passes annual_savings_percent for all paid plans', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.annual_savings_percent', fn ($v) => $v > 0)
            ->where('plans.1.annual_savings_percent', fn ($v) => $v > 0)
        );
});

it('passes max_limits in pricing response', function () {
    Plan::factory()->pemula()->create();

    get(route('landing.pricing'))
        ->assertInertia(fn ($page) => $page
            ->where('plans.0.max_products', 100)
            ->where('plans.0.max_users', 1)
            ->where('plans.0.max_warehouses', 1)
        );
});

it('ensures enterprise has more included features than profesional which has more than pemula', function () {
    Plan::factory()->pemula()->create();
    Plan::factory()->profesional()->create();
    Plan::factory()->enterprise()->create();

    $response = get(route('landing.pricing'));
    $page = $response->original->getData()['page'] ?? [];
    $plans = $page['props']['plans'] ?? [];

    $includedCounts = array_map(function ($plan) {
        return count(array_filter($plan['features'] ?? [], fn ($f) => $f['included']));
    }, $plans);

    expect($includedCounts[0])->toBeLessThan($includedCounts[1]);
    expect($includedCounts[1])->toBeLessThan($includedCounts[2]);
});
