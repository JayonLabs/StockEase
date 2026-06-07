<?php

use App\Models\Category;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
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
        'name' => 'Toko A', 'slug' => 'toko-a-'.uniqid(), 'is_active' => true,
    ]);
    $userA = User::factory()->create(['company_id' => $this->companyA->id]);
    $this->companyA->update(['owner_id' => $userA->id]);

    $this->companyB = Company::create([
        'name' => 'Toko B', 'slug' => 'toko-b-'.uniqid(), 'is_active' => true,
    ]);
    $userB = User::factory()->create(['company_id' => $this->companyB->id]);
    $this->companyB->update(['owner_id' => $userB->id]);

    $this->platformAdmin = User::factory()->create(['company_id' => null]);

    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    Product::factory()
        ->count(2)
        ->for($category)
        ->for($unit, 'unit')
        ->create(['company_id' => $this->companyA->id]);

    Product::factory()
        ->count(3)
        ->for($category)
        ->for($unit, 'unit')
        ->create(['company_id' => $this->companyB->id]);
});

afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('platform admin sees all products across all companies', function () {
    actingAs($this->platformAdmin);

    $count = Product::count();

    expect($count)->toBe(5);
});

it('platform admin can query products from any company', function () {
    actingAs($this->platformAdmin);

    $companyAProducts = Product::where('company_id', $this->companyA->id)->count();
    $companyBProducts = Product::where('company_id', $this->companyB->id)->count();

    expect($companyAProducts)->toBe(2)
        ->and($companyBProducts)->toBe(3);
});

it('platform admin can explicitly use withoutTenancy to get all data', function () {
    actingAs($this->platformAdmin);

    $all = Product::withoutTenancy()->count();

    expect($all)->toBe(5);
});

it('platform admin initialization does not set tenant context', function () {
    actingAs($this->platformAdmin);

    expect(tenancy()->initialized)->toBeFalse();
});
