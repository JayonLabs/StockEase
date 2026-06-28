<?php

namespace Tests\Feature\Stock;

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
use function Pest\Laravel\getJson;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{company: Company, user: User} $this */
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['role' => 'admin', 'company_id' => $this->company->id]);

    $plan = Plan::factory()->pemula()->create();
    Subscription::factory()->create(['company_id' => $this->company->id, 'plan_id' => $plan->id, 'status' => 'active']);

    $category = Category::factory()->create(['company_id' => $this->company->id]);
    $unit = Unit::factory()->create(['company_id' => $this->company->id]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'name' => 'Produk ABC',
        'purchase_price' => 5000,
        'selling_price' => 7500,
        'stock' => 100,
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'name' => 'Produk XYZ',
        'purchase_price' => 10000,
        'selling_price' => 15000,
        'stock' => 50,
    ]);
});

it('searches products by name', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->getJson(route('stock-adjustment.search-product', ['search' => 'ABC']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['label' => 'Produk ABC']);
});

it('returns empty results for non-matching search', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->getJson(route('stock-adjustment.search-product', ['search' => 'NONEXISTENT']))
        ->assertOk()
        ->assertJsonCount(0);
});

it('returns products when search is partial', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->getJson(route('stock-adjustment.search-product', ['search' => 'roduk']))
        ->assertOk()
        ->assertJsonCount(2);
});

it('returns products for authenticated user only', function () {
    getJson(route('stock-adjustment.search-product', ['search' => 'ABC']))
        ->assertUnauthorized();
});

it('returns products with value/label format', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->getJson(route('stock-adjustment.search-product', ['search' => 'ABC']))
        ->assertOk()
        ->assertJsonStructure([[
            'value',
            'label',
            'stock',
        ]]);
});

it('filters by stock availability', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->getJson(route('stock-adjustment.search-product'))
        ->assertOk()
        ->assertJsonCount(2);
});

it('includes warehouse_stock when warehouse_id is provided', function () {
    /** @var object{company: Company, user: User} $this */
    $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

    actingAs($this->user)
        ->getJson(route('stock-adjustment.search-product', [
            'search' => 'ABC',
            'warehouse_id' => $warehouse->id,
        ]))
        ->assertOk()
        ->assertJsonStructure([[
            'value',
            'label',
            'stock',
            'warehouse_stock',
        ]]);
});

it('does not include warehouse_stock when no warehouse_id given', function () {
    /** @var object{company: Company, user: User} $this */
    actingAs($this->user)
        ->getJson(route('stock-adjustment.search-product', ['search' => 'ABC']))
        ->assertOk()
        ->assertJsonMissingPath('0.warehouse_stock');
});
