<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\Stock\ExpiryReportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->service = new ExpiryReportService;
});

it('gets paginated expiry items', function () {
    PurchaseItem::factory()->count(15)->create([
        'expiry_date' => now()->addDays(15),
    ]);

    $result = $this->service->getPaginatedExpiryItems([], 10);

    expect($result->total())->toBe(15);
    expect($result->count())->toBe(10);
});

it('filters expiry items by search on product name', function () {
    $product = Product::factory()->create(['name' => 'Susu Segar']);
    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addDays(10),
    ]);
    PurchaseItem::factory()->create([
        'expiry_date' => now()->addDays(10),
    ]);

    $result = $this->service->getPaginatedExpiryItems(['search' => 'Susu']);

    expect($result->total())->toBe(1);
});

it('filters expiry items by search on sku', function () {
    $product = Product::factory()->create(['sku' => 'SKU-001']);
    PurchaseItem::factory()->create([
        'product_id' => $product->id,
        'expiry_date' => now()->addDays(10),
    ]);
    PurchaseItem::factory()->create([
        'expiry_date' => now()->addDays(10),
    ]);

    $result = $this->service->getPaginatedExpiryItems(['search' => 'SKU-001']);

    expect($result->total())->toBe(1);
});

it('filters expired items', function () {
    PurchaseItem::factory()->create([
        'expiry_date' => now()->subDays(5),
    ]);
    PurchaseItem::factory()->create([
        'expiry_date' => now()->addDays(10),
    ]);

    $result = $this->service->getPaginatedExpiryItems(['status' => 'expired']);

    expect($result->total())->toBe(1);
});

it('filters near-expired items within 30 days', function () {
    PurchaseItem::factory()->create([
        'expiry_date' => now()->addDays(15),
    ]);
    PurchaseItem::factory()->create([
        'expiry_date' => now()->addDays(60),
    ]);

    $result = $this->service->getPaginatedExpiryItems(['status' => 'near_expired']);

    expect($result->total())->toBe(1);
});

it('orders expiry items by expiry date ascending', function () {
    PurchaseItem::factory()->create([
        'expiry_date' => now()->addDays(30),
    ]);
    PurchaseItem::factory()->create([
        'expiry_date' => now()->addDays(5),
    ]);

    $result = $this->service->getPaginatedExpiryItems([]);

    expect($result->first()->expiry_date->toDateString())->toBe(now()->addDays(5)->toDateString());
});

it('excludes items without expiry date', function () {
    PurchaseItem::factory()->create([
        'expiry_date' => null,
    ]);
    PurchaseItem::factory()->create([
        'expiry_date' => now()->addDays(10),
    ]);

    $result = $this->service->getPaginatedExpiryItems([]);

    expect($result->total())->toBe(1);
});

it('loads product and purchase supplier relationships', function () {
    $product = Product::factory()->create(['name' => 'Energy Drink']);
    $supplier = Supplier::factory()->create(['name' => 'PT Maju']);
    $purchase = Purchase::factory()->create(['supplier_id' => $supplier->id]);
    PurchaseItem::factory()->create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'expiry_date' => now()->addDays(10),
    ]);

    $result = $this->service->getPaginatedExpiryItems([]);

    $item = $result->first();
    expect($item->relationLoaded('product'))->toBeTrue();
    expect($item->relationLoaded('purchase'))->toBeTrue();
    expect($item->product->name)->toBe('Energy Drink');
    expect($item->purchase->supplier->name)->toBe('PT Maju');
});
