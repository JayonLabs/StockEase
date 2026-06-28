<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Subscription\PlanLimitService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
    $this->service = new PlanLimitService;
    $this->subService = new SubscriptionService;

    $this->owner = User::factory()->create();
    $this->company = Company::create([
        'name' => 'Limit Test Co',
        'slug' => 'limit-test-co',
        'owner_id' => $this->owner->id,
    ]);
    $this->owner->update(['company_id' => $this->company->id]);

    Plan::create([
        'name' => 'Pemula',
        'slug' => 'pemula',
        'price_monthly' => 0,
        'price_annual' => 0,
        'max_products' => 100,
        'max_users' => 3,
        'max_warehouses' => 1,
    ]);

    Plan::create([
        'name' => 'Enterprise',
        'slug' => 'enterprise',
        'price_monthly' => 999000,
        'price_annual' => 849000,
        'max_products' => null,
        'max_users' => null,
        'max_warehouses' => null,
    ]);

    $this->subService->assignFreeSubscription($this->company);
});

it('allows adding product when below limit', function () {
    /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
    actingAs($this->owner);
    Product::factory()->count(50)->create(['company_id' => $this->company->id]);

    expect($this->service->canAddProduct($this->company))->toBeTrue();
});

it('denies adding product when at limit', function () {
    /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
    actingAs($this->owner);
    Product::factory()->count(100)->create(['company_id' => $this->company->id]);

    expect($this->service->canAddProduct($this->company))->toBeFalse();
});

it('allows adding users when below limit', function () {
    /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
    expect($this->service->canAddUser($this->company))->toBeTrue();
});

it('denies adding users when at limit', function () {
    /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
    User::factory()->count(3)->create(['company_id' => $this->company->id]);

    expect($this->service->canAddUser($this->company))->toBeFalse();
});

it('allows adding warehouse when below limit', function () {
    /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
    actingAs($this->owner);

    expect($this->service->canAddWarehouse($this->company))->toBeTrue();
});

it('denies adding warehouse when at limit', function () {
    /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
    actingAs($this->owner);
    Warehouse::factory()->create(['company_id' => $this->company->id]);

    expect($this->service->canAddWarehouse($this->company))->toBeFalse();
});

it('allows unlimited operations for enterprise plan', function () {
    /** @var object{service: PlanLimitService, subService: SubscriptionService, owner: User, company: Company} $this */
    $existing = $this->company->activeSubscription();
    $this->subService->cancelSubscription($existing);

    $enterprise = Plan::where('slug', 'enterprise')->first();
    $this->subService->createTrial($this->company, $enterprise);

    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    actingAs($this->owner);
    Product::factory()
        ->count(5)
        ->for($category)
        ->for($unit, 'unit')
        ->create(['company_id' => $this->company->id]);

    expect($this->service->canAddProduct($this->company))->toBeTrue();
});
