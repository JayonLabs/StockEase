<?php

use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{admin: User, supplier: Supplier, product: Product, warehouseModel: Warehouse} $this */
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->supplier = Supplier::factory()->create();
    $this->product = Product::factory()->create();
    $this->warehouseModel = Warehouse::factory()->create();
    $this->warehouseModel->products()->attach($this->product->id, ['stock' => 0]);
});

it('can store purchase with expiry date', function () {
    $expiryDate = now()->addYear()->toDateString();
    /** @var TestCase&object{admin: User, supplier: Supplier, product: Product, warehouseModel: Warehouse} $this */
    $response = $this->actingAs($this->admin)->post(route('purchase.store'), [
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
        'date' => now()->toDateString(),
        'product_items' => [
            [
                'product_id' => $this->product->id,
                'qty' => 10,
                'price' => 1000,
                'selling_price' => 1500,
                'expiry_date' => $expiryDate,
            ],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('purchase_items', [
        'product_id' => $this->product->id,
        'expiry_date' => $expiryDate,
    ]);
});

it('can view expiry report', function () {
    /** @var TestCase&object{admin: User, supplier: Supplier, product: Product} $this */
    PurchaseItem::factory()->create([
        'product_id' => $this->product->id,
        'expiry_date' => now()->addDays(10)->toDateString(),
    ]);

    $response = $this->actingAs($this->admin)->get(route('reports.expiry.index'));

    $response->assertSuccessful();
    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('Reports/Expiry/Index')
            ->has('expiryData.data', 1)
    );
});

it('can filter expiry report by status', function () {
    /** @var TestCase&object{admin: User, supplier: Supplier, product: Product} $this */
    // Expired
    PurchaseItem::factory()->create([
        'product_id' => $this->product->id,
        'expiry_date' => now()->subDays(5)->toDateString(),
    ]);

    // Near Expired
    PurchaseItem::factory()->create([
        'product_id' => $this->product->id,
        'expiry_date' => now()->addDays(5)->toDateString(),
    ]);

    // Test Expired Filter
    $response = $this->actingAs($this->admin)->get(route('reports.expiry.index', ['status' => 'expired']));
    $response->assertInertia(fn (Assert $page) => $page->has('expiryData.data', 1));

    // Test Near Expired Filter
    $response = $this->actingAs($this->admin)->get(route('reports.expiry.index', ['status' => 'near_expired']));
    $response->assertInertia(fn (Assert $page) => $page->has('expiryData.data', 1));
});
