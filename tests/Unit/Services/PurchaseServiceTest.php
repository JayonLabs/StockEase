<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchase\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create(['role' => 'admin']);
    Auth::login($user);
});

it('can get paginated purchases', function () {
    Purchase::factory()->count(15)->create();
    $purchaseService = new PurchaseService;

    /** @var LengthAwarePaginator */
    $purchases = $purchaseService->getPaginatedPurchases([], 10);

    expect($purchases->total())->toBe(15);
    expect($purchases->count())->toBe(10);
});

it('can filter purchases by search', function () {
    $supplier = Supplier::factory()->create(['name' => 'Abadi Jaya']);
    Purchase::factory()->create(['supplier_id' => $supplier->id]);
    Purchase::factory()->count(2)->create();
    $purchaseService = new PurchaseService;

    /** @var LengthAwarePaginator */
    $purchases = $purchaseService->getPaginatedPurchases(['search' => 'Abadi']);

    expect($purchases->total())->toBe(1);
    expect($purchases->first()->supplier->name)->toBe('Abadi Jaya');
});

it('can filter purchases by date range', function () {
    Purchase::factory()->create(['date' => now()->subDays(10)->toDateString()]);
    $targetPurchase = Purchase::factory()->create(['date' => now()->toDateString()]);
    $purchaseService = new PurchaseService;

    /** @var LengthAwarePaginator */
    $purchases = $purchaseService->getPaginatedPurchases([
        'start' => now()->subDays(1)->toDateString(),
        'end' => now()->toDateString(),
    ]);

    expect($purchases->total())->toBe(1);
    expect($purchases->first()->id)->toBe($targetPurchase->id);
});

it('can search suppliers', function () {
    Supplier::factory()->create(['name' => 'Supplier A']);
    Supplier::factory()->create(['name' => 'Supplier B']);
    $purchaseService = new PurchaseService;

    $results = $purchaseService->searchSuppliers('Supplier A');

    expect($results)->toHaveCount(1);
    expect($results->first()->label)->toBe('Supplier A');
});

it('can search products', function () {
    Product::factory()->create(['name' => 'Buku Tulis']);
    Product::factory()->create(['name' => 'Pensil']);
    $purchaseService = new PurchaseService;

    $results = $purchaseService->searchProducts('Buku');

    expect($results)->toHaveCount(1);
    expect($results->first()->label)->toBe('Buku Tulis');
});

it('can store a new purchase and increments stock', function () {
    $supplier = Supplier::factory()->create();
    $product1 = Product::factory()->create(['stock' => 10, 'purchase_price' => 1000]);
    $product2 = Product::factory()->create(['stock' => 5, 'purchase_price' => 500]);
    $purchaseService = new PurchaseService;

    $data = [
        'supplier_id' => $supplier->id,
        'date' => now()->toDateString(),
        'product_items' => [
            [
                'product_id' => $product1->id,
                'qty' => 5,
                'price' => 1200,
                'selling_price' => 2000,
            ],
            [
                'product_id' => $product2->id,
                'qty' => 10,
                'price' => 600,
                'selling_price' => 1000,
            ],
        ],
    ];

    $purchase = $purchaseService->storePurchase($data);

    expect($purchase->supplier_id)->toBe($supplier->id);
    expect((int) $purchase->total)->toBe((5 * 1200) + (10 * 600));

    $product1->refresh();
    $product2->refresh();

    expect($product1->stock)->toBe(15);
    expect((int) $product1->purchase_price)->toBe(1200);
    expect($product2->stock)->toBe(15);
    expect((int) $product2->purchase_price)->toBe(600);

    assertDatabaseHas('stock_logs', [
        'product_id' => $product1->id,
        'qty' => 5,
        'type' => 'in',
    ]);
});

it('can update an existing purchase and adjust stock', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create(['stock' => 20]);
    $purchase = Purchase::factory()->create(['supplier_id' => $supplier->id]);
    $purchaseService = new PurchaseService;
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'qty' => 10,
        'price' => 1000,
    ]);

    // Update: change qty from 10 to 15 (diff +5)
    $data = [
        'supplier_id' => $supplier->id,
        'date' => $purchase->date,
        'product_items' => [
            [
                'product_id' => $product->id,
                'qty' => 15,
                'price' => 1000,
                'selling_price' => 2000,
            ],
        ],
    ];

    $purchaseService->updatePurchase($purchase, $data);

    $product->refresh();
    expect($product->stock)->toBe(25); // 20 + (15 - 10)

    assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => 5, // diff qty
        'type' => 'adjust',
    ]);
});

it('can delete a purchase and revert stock', function () {
    $purchase = Purchase::factory()->create();
    $product = Product::factory()->create(['stock' => 20]);
    $purchaseService = new PurchaseService;
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'product_id' => $product->id,
        'qty' => 5,
        'price' => 1000,
    ]);

    $purchaseService->deletePurchase($purchase);

    assertSoftDeleted('purchases', ['id' => $purchase->id]);
    $product->refresh();
    expect($product->stock)->toBe(15); // 20 - 5

    assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => 5,
        'type' => 'out',
    ]);
});
