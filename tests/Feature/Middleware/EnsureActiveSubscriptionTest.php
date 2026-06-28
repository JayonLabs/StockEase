<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureActiveSubscription;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Route::middleware(['web', 'auth', EnsureActiveSubscription::class])
        ->get('/_test/subscription-guard', fn () => response()->json(['ok' => true]))
        ->name('_test.subscription-guard');

    $this->plan = Plan::factory()->profesional()->create();
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    $this->user->syncRoles('super_admin');
});

// ===========================================================================
// Active subscription — diperbolehkan
// ===========================================================================

it('allows access when subscription is active', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(30),
    ]);

    actingAs($this->user)
        ->get('/_test/subscription-guard')
        ->assertOk();
});

it('allows access when subscription is trialing', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'trialing',
        'starts_at' => now()->subDay(),
        'ends_at' => null,
        'trial_ends_at' => now()->addDays(14),
    ]);

    actingAs($this->user)
        ->get('/_test/subscription-guard')
        ->assertOk();
});

it('allows access when subscription has no ends_at (indefinite)', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'ends_at' => null,
    ]);

    actingAs($this->user)
        ->get('/_test/subscription-guard')
        ->assertOk();
});

// ===========================================================================
// Subscription dibatalkan — harus diblokir langsung
// ===========================================================================

it('blocks access when subscription is canceled', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    actingAs($this->user)
        ->get('/_test/subscription-guard')
        ->assertRedirect(route('subscription.index'));
});

it('sets error in session when blocked due to canceled subscription', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    actingAs($this->user)
        ->get('/_test/subscription-guard')
        ->assertSessionHas('error');
});

it('blocks access when no subscription record exists', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    actingAs($this->user)
        ->get('/_test/subscription-guard')
        ->assertRedirect(route('subscription.index'));
});

it('blocks access when subscription is expired', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'expired',
        'starts_at' => now()->subDays(31),
        'ends_at' => now()->subDay(),
    ]);

    actingAs($this->user)
        ->get('/_test/subscription-guard')
        ->assertRedirect(route('subscription.index'));
});

it('blocks access when active subscription has passed ends_at', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now()->subDays(31),
        'ends_at' => now()->subSecond(),
    ]);

    actingAs($this->user)
        ->get('/_test/subscription-guard')
        ->assertRedirect(route('subscription.index'));
});

// ===========================================================================
// JSON request — mengembalikan 402 subscription_required
// ===========================================================================

it('returns 402 with subscription_required flag for JSON request when canceled', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    actingAs($this->user)
        ->getJson('/_test/subscription-guard')
        ->assertStatus(402)
        ->assertJsonFragment(['subscription_required' => true]);
});

// ===========================================================================
// Bypass — platform owner, user tanpa company
// ===========================================================================

it('allows platform_owner through without any subscription', function () {
    $owner = User::factory()->create();
    $owner->syncRoles('platform_owner');

    /** @var User $owner */
    actingAs($owner)
        ->get('/_test/subscription-guard')
        ->assertOk();
});

it('allows user without company through without any subscription check', function () {
    $userNoCompany = User::factory()->create(['company_id' => null]);

    /** @var User $userNoCompany */
    actingAs($userNoCompany)
        ->get('/_test/subscription-guard')
        ->assertOk();
});

// ===========================================================================
// Halaman subscription tetap bisa diakses meski tidak ada langganan
// ===========================================================================

it('allows access to subscription index page when subscription is canceled', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    actingAs($this->user)
        ->get(route('subscription.index'))
        ->assertOk();
});

it('allows access to subscription upgrade when subscription is canceled', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    actingAs($this->user)
        ->postJson(route('subscription.upgrade'), ['plan_id' => $this->plan->id])
        ->assertOk();
});

// ===========================================================================
// Integrasi — cancel mengunci semua route aplikasi
// ===========================================================================

it('blocks dashboard access after subscription is canceled', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    actingAs($this->user)
        ->get(route('dashboard'))
        ->assertRedirect(route('subscription.index'));
});

it('blocks premium feature routes after subscription is canceled', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    $this->user->syncRoles(['admin']);

    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    actingAs($this->user)
        ->get(route('purchase.index'))
        ->assertRedirect(route('subscription.index'));
});

it('blocks activity log route after subscription is canceled', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    $this->user->syncRoles(['admin']);

    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    actingAs($this->user)
        ->get(route('activity-logs.index'))
        ->assertRedirect(route('subscription.index'));
});

// ===========================================================================
// Tidak ada duplikasi subscription saat middleware berjalan
// ===========================================================================

it('does not create new subscription records when blocking access', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    $countBefore = Subscription::where('company_id', $this->company->id)->count();

    actingAs($this->user)->get('/_test/subscription-guard');

    expect(Subscription::where('company_id', $this->company->id)->count())->toBe($countBefore);
});

it('does not create new subscription records on multiple blocked requests', function () {
    /** @var object{company: Company, user: User, plan: Plan} $this */
    Subscription::factory()->create([
        'company_id' => $this->company->id,
        'plan_id' => $this->plan->id,
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    actingAs($this->user)->get('/_test/subscription-guard');
    actingAs($this->user)->get('/_test/subscription-guard');
    actingAs($this->user)->get('/_test/subscription-guard');

    expect(Subscription::where('company_id', $this->company->id)->count())->toBe(1);
});
