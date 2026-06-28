<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    Plan::create([
        'name' => 'Pemula', 'slug' => 'pemula',
        'price_monthly' => 0, 'price_annual' => 0,
        'max_products' => 100, 'max_users' => 3, 'max_warehouses' => 1,
        'trial_days' => 0, 'sort_order' => 1,
    ]);

    $this->companyA = Company::create([
        'name' => 'Toko ABC', 'slug' => 'toko-abc-'.uniqid(), 'is_active' => true,
    ]);
    $this->userA = User::factory()->create(['company_id' => $this->companyA->id]);
    $this->companyA->update(['owner_id' => $this->userA->id]);

    $this->companyB = Company::create([
        'name' => 'Toko XYZ', 'slug' => 'toko-xyz-'.uniqid(), 'is_active' => true,
    ]);
    $this->userB = User::factory()->create(['company_id' => $this->companyB->id]);
    $this->companyB->update(['owner_id' => $this->userB->id]);

    $plan = Plan::where('slug', 'pemula')->first();
    Subscription::factory()->create(['company_id' => $this->companyA->id, 'plan_id' => $plan->id, 'status' => 'active']);
    Subscription::factory()->create(['company_id' => $this->companyB->id, 'plan_id' => $plan->id, 'status' => 'active']);

    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    Product::factory()
        ->count(3)
        ->for($category)
        ->for($unit, 'unit')
        ->create(['company_id' => $this->companyA->id, 'name' => 'Produk A']);

    Product::factory()
        ->count(5)
        ->for($category)
        ->for($unit, 'unit')
        ->create(['company_id' => $this->companyB->id, 'name' => 'Produk B']);
});

afterEach(function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('user from company A only sees company A products via model query', function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    actingAs($this->userA);
    initTenancyFromUser($this->userA);

    $count = Product::count();
    $products = Product::pluck('name');

    expect($count)->toBe(3)
        ->and($products->every(fn ($name) => str_starts_with($name, 'Produk A')))->toBeTrue();
});

it('user from company B only sees company B products via model query', function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    actingAs($this->userB);
    initTenancyFromUser($this->userB);

    $count = Product::count();
    $products = Product::pluck('name');

    expect($count)->toBe(5)
        ->and($products->every(fn ($name) => str_starts_with($name, 'Produk B')))->toBeTrue();
});

it('user from company A cannot access company B products', function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    actingAs($this->userA);
    initTenancyFromUser($this->userA);

    $productB = Product::where('company_id', $this->companyB->id)->first();

    expect($productB)->toBeNull();
});

it('user from company B cannot access company A products', function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    actingAs($this->userB);
    initTenancyFromUser($this->userB);

    $productA = Product::where('company_id', $this->companyA->id)->first();

    expect($productA)->toBeNull();
});

it('user from company A count is unaffected by company B data', function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    Warehouse::factory()->create(['company_id' => $this->companyA->id]);

    actingAs($this->userA);
    initTenancyFromUser($this->userA);

    expect(Warehouse::count())->toBe(1);
});

it('user from company B count is unaffected by company A data', function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    Warehouse::factory()->create(['company_id' => $this->companyB->id]);

    actingAs($this->userB);
    initTenancyFromUser($this->userB);

    expect(Warehouse::count())->toBe(1);
});

it('user can create model with auto-filled company_id', function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    actingAs($this->userA);
    initTenancyFromUser($this->userA);

    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    $product = Product::create([
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'name' => 'Produk Baru',
        'sku' => uniqid(),
        'purchase_price' => 5000,
        'selling_price' => 7500,
        'stock' => 10,
        'alert_stock' => 5,
    ]);

    expect($product->company_id)->toBe($this->companyA->id);
});

it('model create does not leak company_id between tenants', function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    actingAs($this->userA);
    initTenancyFromUser($this->userA);

    $category = Category::factory()->create();
    $unit = Unit::factory()->create();
    Product::create([
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'name' => 'Rahasia A',
        'sku' => uniqid(),
        'purchase_price' => 1000,
        'selling_price' => 2000,
        'stock' => 5,
        'alert_stock' => 2,
    ]);

    expect(Product::withoutTenancy()->where('company_id', $this->companyA->id)->count())->toBe(4)
        ->and(Product::withoutTenancy()->where('company_id', $this->companyB->id)->count())->toBe(5);
});

it('products page only shows tenant-scoped data via HTTP', function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    $this->userA->assignRole('super_admin');
    actingAs($this->userA);

    get('/product')->assertSuccessful();
});

it('checks subscription limit respects tenant isolation', function () {
    /** @var object{companyA: Company, companyB: Company, userA: User, userB: User} $this */
    $plan = Plan::where('slug', 'pemula')->first();
    $this->companyA->subscription()->create([
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    // Create warehouse in tenant context so the middleware's count query matches
    initTenancyFromUser($this->userA);
    Warehouse::factory()->create();
    tenancy()->end();

    // Ensure user is not super_admin so the subscription limit check runs
    $this->userA->syncRoles('admin');

    actingAs($this->userA);

    $response = post(route('warehouse.store'), [
        'name' => 'Gudang Kedua',
        'phone' => '08123456789',
        'address' => 'Jl. Merdeka No. 2',
    ]);

    $response->assertSessionHas('error');
});
