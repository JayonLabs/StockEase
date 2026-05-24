<?php

namespace Tests\Feature\Stock;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{admin: User, warehouse: User, cashier: User, superAdmin: User} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->warehouse = User::factory()->create(['role' => 'warehouse']);
    $this->cashier = User::factory()->create(['role' => 'cashier']);
    $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
});

it('returns 401 for unauthenticated requests', function () {
    $response = getJson(route('low-stock.index'));

    $response->assertUnauthorized();
});

it('returns 403 for users with unauthorized role', function (string $role) {
    /** @var User $user */
    $user = User::factory()->create(['role' => $role]);

    $response = actingAs($user)->getJson(route('low-stock.index'));

    $response->assertForbidden();
})->with([
    'cashier' => 'cashier',
]);

it('allows admin to access', function () {
    /** @var TestCase&object{admin: User} $this */
    $response = $this->actingAs($this->admin)->getJson(route('low-stock.index'));

    $response->assertSuccessful();
});

it('allows warehouse to access', function () {
    /** @var TestCase&object{warehouse: User} $this */
    $response = $this->actingAs($this->warehouse)->getJson(route('low-stock.index'));

    $response->assertSuccessful();
});

it('allows super admin to access', function () {
    /** @var TestCase&object{superAdmin: User} $this */
    $response = $this->actingAs($this->superAdmin)->getJson(route('low-stock.index'));

    $response->assertSuccessful();
});

it('only returns products where stock is below or equal to alert_stock', function () {
    /** @var TestCase&object{admin: User} $this */
    Product::factory()->create([
        'stock' => 2,
        'alert_stock' => 5,
        'name' => 'Low Stock Item',
    ]);
    Product::factory()->create([
        'stock' => 10,
        'alert_stock' => 5,
        'name' => 'High Stock Item',
    ]);
    Product::factory()->create([
        'stock' => 5,
        'alert_stock' => 5,
        'name' => 'Exact Alert Item',
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('low-stock.index'));

    $response->assertSuccessful()
        ->assertJsonCount(2)
        ->assertJsonFragment(['name' => 'Low Stock Item'])
        ->assertJsonFragment(['name' => 'Exact Alert Item'])
        ->assertJsonMissingExact(['name' => 'High Stock Item']);
});

it('returns empty array when no products are low stock', function () {
    /** @var TestCase&object{admin: User} $this */
    Product::factory()->create([
        'stock' => 50,
        'alert_stock' => 5,
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('low-stock.index'));

    $response->assertSuccessful()
        ->assertJsonCount(0);
});

it('does not expose sensitive product fields', function () {
    /** @var TestCase&object{admin: User} $this */
    Product::factory()->create([
        'stock' => 2,
        'alert_stock' => 5,
        'purchase_price' => 50000,
        'selling_price' => 75000,
        'barcode' => '1234567890123',
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('low-stock.index'));

    $response->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonMissing(['purchase_price'])
        ->assertJsonMissing(['selling_price'])
        ->assertJsonMissing(['barcode'])
        ->assertJsonMissing(['image_path'])
        ->assertJsonMissing(['created_at'])
        ->assertJsonMissing(['updated_at'])
        ->assertJsonMissing(['deleted_at']);
});

it('returns only allowed columns', function () {
    /** @var TestCase&object{admin: User} $this */
    Product::factory()->create([
        'stock' => 2,
        'alert_stock' => 5,
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('low-stock.index'));

    $response->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonStructure([
            '*' => ['id', 'slug', 'name', 'sku', 'stock', 'alert_stock', 'unit_id'],
        ]);
});

it('uses single query to fetch low stock products', function () {
    /** @var TestCase&object{admin: User} $this */
    Product::factory()->count(5)->create([
        'stock' => 2,
        'alert_stock' => 5,
    ]);

    DB::enableQueryLog();

    $this->actingAs($this->admin)->getJson(route('low-stock.index'));

    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    $selectQueries = array_filter($queries, fn ($q) => $q['query'] !== 'select 1');

    expect($selectQueries)->toHaveCount(1);
});
