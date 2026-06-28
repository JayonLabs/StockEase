<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutExceptionHandling;

uses(LazilyRefreshDatabase::class);

it('loads notifications without N+1 queries when accessing inertia pages', function () {
    /** @var User $user */
    $user = User::factory()->create();

    // Create multiple notifications for different products without product_slug
    // This forces the HandleInertiaRequests middleware to load the products.
    $products = Product::factory()->count(5)->create();

    foreach ($products as $product) {
        $user->notifications()->create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\StockAlertNotification',
            'data' => [
                'product_id' => $product->id,
                // Purposely omitting product_slug to trigger the loading logic
            ],
            'read_at' => null,
        ]);
    }

    // Also create some notifications for the same product to test distinct loading
    for ($i = 0; $i < 3; $i++) {
        $user->notifications()->create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\StockAlertNotification',
            'data' => [
                'product_id' => $products->first()->id,
            ],
            'read_at' => null,
        ]);
    }

    actingAs($user);

    // Track queries
    DB::enableQueryLog();

    $response = get('/'); // assuming dashboard route uses inertia

    $response->assertSuccessful();

    $queries = DB::getQueryLog();

    // The query log should contain ONE query for notifications
    // and ONE query to load all products via "where in"
    // So there shouldn't be 8 queries for products.
    $productQueries = collect($queries)->filter(function ($query) {
        return str_contains($query['query'], 'select * from `products` where `id` in');
    });

    $singleProductQueries = collect($queries)->filter(function ($query) {
        return str_contains($query['query'], 'select * from `products` where `products`.`id` =');
    });

    // We expect exactly 1 batch load query and 0 single load queries from the middleware.
    expect($productQueries)->toHaveCount(1)
        ->and($singleProductQueries)->toBeEmpty();

    // Verify inertia props
    $response->assertInertia(function (Assert $page) {
        $page->has('notifications', 8);
        $notifications = $page->toArray()['props']['notifications'];
        foreach ($notifications as $notification) {
            expect($notification['slug'])->not->toBeEmpty();
        }
    });
});

it('only exposes safe user fields in auth prop', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'photo_profile' => 'avatars/test.jpg',
    ]);

    /** @var User $user */
    actingAs($user);

    $response = get('/');

    $response->assertInertia(function (Assert $page) {
        $authUser = $page->toArray()['props']['auth']['user'];

        expect($authUser)->toHaveKeys(['id', 'name', 'email', 'photo_profile', 'role', 'roles', 'permissions']);
        expect($authUser)->not->toHaveKey('password');
        expect($authUser)->not->toHaveKey('remember_token');
        expect($authUser)->not->toHaveKey('email_verified_at');
        expect($authUser)->not->toHaveKey('created_at');
        expect($authUser)->not->toHaveKey('updated_at');
    });
});

it('shares role and permissions as arrays in auth prop', function () {
    $user = User::factory()->create(['role' => 'admin']);

    /** @var User $user */
    actingAs($user);

    $response = get('/');

    $response->assertInertia(function (Assert $page) {
        $authUser = $page->toArray()['props']['auth']['user'];

        expect($authUser['role'])->toBeString();
        expect($authUser['roles'])->toBeArray();
        expect($authUser['roles'])->toContain('admin');
        expect($authUser['permissions'])->toBeArray();
        expect($authUser['permissions'])->not->toBeEmpty();
    });
});

it('returns null auth user for unauthenticated requests', function () {
    $response = withoutExceptionHandling()->get('/login');

    if ($response->status() === 200) {
        $response->assertInertia(function (Assert $page) {
            expect($page->toArray()['props']['auth']['user'] ?? null)->toBeNull();
        });
    }
})->skip(fn () => false, 'auth user is null on guest pages');

it('does not execute duplicate roles queries in the same request', function () {
    $user = User::factory()->create(['role' => 'admin']);

    // Simulate a fresh request where roles relation is not yet loaded
    $user->unsetRelation('roles');
    $user->unsetRelation('permissions');

    /** @var User $user */
    actingAs($user);

    DB::enableQueryLog();

    get('/');

    $rolesQueries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'model_has_roles'));

    DB::disableQueryLog();

    // Roles should be loaded only once (either by middleware or by share(), not both)
    expect($rolesQueries)->toHaveCount(1);
});

// ===========================================================================
// Subscription shared data — kontrak yang digunakan frontend untuk mengunci sidebar
// ===========================================================================

describe('subscription shared data in auth prop', function () {
    beforeEach(function () {
        /** @var object{plan: Plan, company: Company, user: User} $this */
        $this->plan = Plan::factory()->pemula()->create();
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->user->syncRoles('super_admin');
    });

    it('shares subscription data when subscription is active', function () {
        /** @var object{plan: Plan, company: Company, user: User} $this */
        $this->company->subscription()->create([
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        actingAs($this->user)
            ->get('/subscription')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.subscription.status', 'active')
                ->where('auth.subscription.plan.slug', $this->plan->slug)
                ->has('auth.subscription.plan.features')
            );
    });

    it('shares subscription data when subscription is trialing', function () {
        /** @var object{plan: Plan, company: Company, user: User} $this */
        $this->company->subscription()->create([
            'plan_id' => $this->plan->id,
            'status' => 'trialing',
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'trial_ends_at' => now()->addDays(14),
        ]);

        actingAs($this->user)
            ->get('/subscription')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.subscription.status', 'trialing')
                ->has('auth.subscription.plan')
            );
    });

    it('returns null subscription when subscription is canceled', function () {
        /** @var object{plan: Plan, company: Company, user: User} $this */
        $this->company->subscription()->create([
            'plan_id' => $this->plan->id,
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        actingAs($this->user)
            ->get('/subscription')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.subscription', null)
            );
    });

    it('returns null subscription when subscription is expired', function () {
        /** @var object{plan: Plan, company: Company, user: User} $this */
        $this->company->subscription()->create([
            'plan_id' => $this->plan->id,
            'status' => 'expired',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
        ]);

        actingAs($this->user)
            ->get('/subscription')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.subscription', null)
            );
    });

    it('returns null subscription when active subscription has expired ends_at', function () {
        /** @var object{plan: Plan, company: Company, user: User} $this */
        $this->company->subscription()->create([
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
        ]);

        actingAs($this->user)
            ->get('/subscription')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.subscription', null)
            );
    });

    it('returns null subscription when user has no subscription', function () {
        /** @var object{plan: Plan, company: Company, user: User} $this */
        actingAs($this->user)
            ->get('/subscription')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.subscription', null)
            );
    });

    it('shares plan features as key-value boolean map', function () {
        /** @var object{plan: Plan, company: Company, user: User} $this */
        $this->company->subscription()->create([
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);

        actingAs($this->user)
            ->get('/subscription')
            ->assertInertia(function (Assert $page) {
                $features = $page->toArray()['props']['auth']['subscription']['plan']['features'];

                expect($features)->toBeArray();

                // Each value must be a boolean (not an array object)
                foreach ($features as $key => $value) {
                    expect($key)->toBeString();
                    expect($value)->toBeBool();
                }
            });
    });
});
