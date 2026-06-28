<?php

namespace Tests\Feature\Middleware;

use App\Models\Category;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    $this->plan = Plan::factory()->create([
        'slug' => 'test-plan',
        'max_products' => 2,
        'max_users' => 1,
        'max_warehouses' => 1,
    ]);

    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
        'role' => 'admin',
    ]);
    $this->company->update(['owner_id' => $this->user->id]);

    $this->company->subscription()->create([
        'plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);
});

it('allows request when limit not exceeded', function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    actingAs($this->user)
        ->post(route('warehouse.store'), [
            'name' => 'Gudang Baru',
            'phone' => '08123456789',
            'address' => 'Jl. Baru No. 1',
        ])
        ->assertSessionHas('success');
});

it('blocks warehouse creation when limit exceeded', function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    Warehouse::factory()->create(['company_id' => $this->company->id]);

    actingAs($this->user)
        ->post(route('warehouse.store'), [
            'name' => 'Gudang Kedua',
            'phone' => '08123456789',
            'address' => 'Jl. Kedua No. 2',
        ])
        ->assertSessionHas('error');
});

it('blocks product creation when product limit exceeded', function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    Product::factory()->count(2)->create(['company_id' => $this->company->id]);

    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    actingAs($this->user)
        ->post(route('products.store'), [
            'name' => 'Produk Melebihi Batas',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 1000,
            'selling_price' => 2000,
            'stock' => 10,
            'alert_stock' => 2,
        ])
        ->assertSessionHas('error');
});

it('super_admin (store owner) is blocked when warehouse limit is reached', function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    $this->user->syncRoles('super_admin');
    Warehouse::factory()->create(['company_id' => $this->company->id]);

    actingAs($this->user)
        ->post(route('warehouse.store'), [
            'name' => 'Gudang Super Admin',
            'phone' => '08123456789',
            'address' => 'Jl. Super No. 1',
        ])
        ->assertSessionHas('error');
});

it('warehouse limit is scoped per company, not global', function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    $otherCompany = Company::factory()->create();
    Warehouse::factory()->count(5)->create(['company_id' => $otherCompany->id]);

    actingAs($this->user)
        ->post(route('warehouse.store'), [
            'name' => 'Gudang Company Sendiri',
            'phone' => '08123456789',
            'address' => 'Jl. Sendiri No. 1',
        ])
        ->assertSessionHas('success');
});

it('product limit is scoped per company, not global', function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    $otherCompany = Company::factory()->create();
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    Product::factory()->count(10)->create(['company_id' => $otherCompany->id]);

    actingAs($this->user)
        ->post(route('products.store'), [
            'name' => 'Produk Company Sendiri',
            'sku' => 'SKU-TEST-001',
            'barcode' => '1234567890',
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'purchase_price' => 1000,
            'selling_price' => 2000,
            'stock' => 10,
            'alert_stock' => 2,
        ])
        ->assertSessionHas('success');
});

it('returns 403 when user is unauthenticated', function () {
    post(route('warehouse.store'), [
        'name' => 'Gudang Tanpa Login',
        'phone' => '08123456789',
        'address' => 'Jl. No Auth',
    ])->assertRedirect(route('login'));
});

it('returns json error when API expects JSON and limit exceeded', function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    Warehouse::factory()->create(['company_id' => $this->company->id]);

    actingAs($this->user)
        ->postJson(route('warehouse.store'), [
            'name' => 'Gudang JSON',
            'phone' => '08123456789',
            'address' => 'Jl. JSON',
        ])
        ->assertStatus(403)
        ->assertJson(['message' => 'Batas maksimal gudang untuk plan '.$this->plan->name.' telah tercapai. Upgrade plan Anda.']);
});

it('throws 403 when no user authenticated and route requires auth', function () {
    post(route('warehouse.store'), [
        'name' => 'Gudang Rahasia',
        'phone' => '08123456789',
        'address' => 'Jl. Rahasia',
    ])->assertRedirect(route('login'));
});

it('redirects to subscription page when company has no active plan without creating new subscription', function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    $this->company->subscription()->delete();

    actingAs($this->user)
        ->post(route('warehouse.store'), [
            'name' => 'Gudang No Plan',
            'phone' => '08123456789',
            'address' => 'Jl. No Plan',
        ])
        ->assertRedirect(route('subscription.index'));

    expect($this->company->fresh()->activeSubscription())->toBeNull();
});

it('passes through when user has no company_id', function () {
    /** @var User $userNoCompany */
    $userNoCompany = User::factory()->create();
    $userNoCompany->update(['company_id' => null]);

    actingAs($userNoCompany)
        ->post(route('warehouse.store'), [
            'name' => 'Gudang No Company',
            'phone' => '08123456789',
            'address' => 'Jl. No Company',
        ])
        ->assertSessionHasNoErrors();
});

it('blocks user creation when user limit exceeded', function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    // plan already has max_users: 1 and 1 user exists ($this->user)
    actingAs($this->user)
        ->post(route('users.store'), [
            'name' => 'User Melebihi Batas',
            'email' => 'over.limit@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'cashier',
        ])
        ->assertSessionHas('error');
});

it('returns json error for user limit exceeded on JSON request', function () {
    /** @var object{plan: Plan, company: Company, user: User} $this */
    actingAs($this->user)
        ->postJson(route('users.store'), [
            'name' => 'User JSON Limit',
            'email' => 'json.limit@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role' => 'cashier',
        ])
        ->assertStatus(403)
        ->assertJson(['message' => 'Batas maksimal user untuk plan '.$this->plan->name.' telah tercapai. Upgrade plan Anda.']);
});
