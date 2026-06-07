<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Plan::create([
        'name' => 'Pemula', 'slug' => 'pemula',
        'price_monthly' => 0, 'price_annual' => 0,
        'max_products' => 100, 'max_users' => 3, 'max_warehouses' => 1,
    ]);

    Plan::create([
        'name' => 'Profesional', 'slug' => 'profesional',
        'price_monthly' => 299000, 'price_annual' => 249000,
        'max_products' => 1000, 'max_users' => 10, 'max_warehouses' => 3,
        'trial_days' => 14,
    ]);
});

function createCompanyWithOwner(): array
{
    $owner = User::factory()->create();
    $company = Company::create([
        'name' => 'Test Co', 'slug' => 'test-co-'.uniqid(),
        'owner_id' => $owner->id,
    ]);
    $owner->update(['company_id' => $company->id]);
    $owner = $owner->fresh();

    return ['owner' => $owner, 'company' => $company];
}

it('redirects unauthenticated users', function () {
    get(route('subscription.index'))->assertRedirect(route('login'));
});

it('shows subscription page for authenticated user with company', function () {
    $data = createCompanyWithOwner();

    actingAs($data['owner'])
        ->get(route('subscription.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Subscription/Index'));
});

it('shows list of active plans', function () {
    $data = createCompanyWithOwner();

    actingAs($data['owner'])
        ->get(route('subscription.index'))
        ->assertInertia(fn ($page) => $page->has('plans'));
});

it('can upgrade to free Pemula plan', function () {
    $data = createCompanyWithOwner();

    $plan = Plan::where('slug', 'pemula')->first();

    actingAs($data['owner'])
        ->postJson(route('subscription.upgrade'), ['plan_id' => $plan->id])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Berlangganan plan Pemula.');
});

it('starts trial when upgrading to Profesional', function () {
    $data = createCompanyWithOwner();

    $plan = Plan::where('slug', 'profesional')->first();

    actingAs($data['owner'])
        ->postJson(route('subscription.upgrade'), ['plan_id' => $plan->id])
        ->assertSuccessful()
        ->assertJsonPath('message', 'Trial 14 hari dimulai!');
});

it('validates plan_id is required for upgrade', function () {
    $data = createCompanyWithOwner();

    actingAs($data['owner'])
        ->postJson(route('subscription.upgrade'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['plan_id']);
});

it('validates billing_cycle is valid enum', function () {
    $data = createCompanyWithOwner();
    $plan = Plan::where('slug', 'profesional')->first();

    actingAs($data['owner'])
        ->postJson(route('subscription.upgrade'), [
            'plan_id' => $plan->id,
            'billing_cycle' => 'weekly',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_cycle']);
});

it('returns 403 when user has no company', function () {
    $user = User::factory()->create(['company_id' => null]);
    $plan = Plan::where('slug', 'profesional')->first();

    actingAs($user)
        ->postJson(route('subscription.upgrade'), ['plan_id' => $plan->id])
        ->assertForbidden();
});
