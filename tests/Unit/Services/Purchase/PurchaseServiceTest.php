<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Purchase\PurchaseService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $user = User::factory()->create(['role' => 'admin']);
    Auth::login($user);
    $this->warehouseModel = Warehouse::factory()->create();
});

it('can get paginated purchases', function () {
    Purchase::factory()->count(15)->create();
    $purchaseService = app(PurchaseService::class);

    /** @var LengthAwarePaginator */
    $purchases = $purchaseService->getPaginatedPurchases([], 10);

    expect($purchases->total())->toBe(15);
    expect($purchases->count())->toBe(10);
});

it('can filter purchases by search', function () {
    $supplier = Supplier::factory()->create(['name' => 'Abadi Jaya']);
    Purchase::factory()->create(['supplier_id' => $supplier->id]);
    Purchase::factory()->count(2)->create();
    $purchaseService = app(PurchaseService::class);

    /** @var LengthAwarePaginator */
    $purchases = $purchaseService->getPaginatedPurchases(['search' => 'Abadi']);

    expect($purchases->total())->toBe(1);
    expect($purchases->first()->supplier->name)->toBe('Abadi Jaya');
});

it('can filter purchases by date range', function () {
    Purchase::factory()->create(['date' => now()->subDays(10)->toDateString()]);
    $targetPurchase = Purchase::factory()->create(['date' => now()->toDateString()]);
    $purchaseService = app(PurchaseService::class);

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
    $purchaseService = app(PurchaseService::class);

    $results = $purchaseService->searchSuppliers('Supplier A');

    expect($results)->toHaveCount(1);
    expect($results->first()->label)->toBe('Supplier A');
});

it('can search products', function () {
    Product::factory()->create(['name' => 'Buku Tulis']);
    Product::factory()->create(['name' => 'Pensil']);
    $purchaseService = app(PurchaseService::class);

    $results = $purchaseService->searchProducts('Buku');

    expect($results)->toHaveCount(1);
    expect($results->first()->label)->toBe('Buku Tulis');
});

it('can store a new purchase and increments stock', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $supplier = Supplier::factory()->create();
    $product1 = Product::factory()->create(['purchase_price' => 1000]);
    $product2 = Product::factory()->create(['purchase_price' => 500]);
    $this->warehouseModel->products()->attach($product1->id, ['stock' => 10]);
    $this->warehouseModel->products()->attach($product2->id, ['stock' => 5]);
    $product1->syncStockFromWarehouses();
    $product2->syncStockFromWarehouses();
    $purchaseService = app(PurchaseService::class);

    $data = [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
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
        'warehouse_id' => $this->warehouseModel->id,
        'qty' => 5,
        'type' => 'in',
    ]);
});

it('can update an existing purchase and adjust stock', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 20]);
    $product->syncStockFromWarehouses();
    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
    ]);
    $purchaseService = app(PurchaseService::class);
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'qty' => 10,
        'remaining_qty' => 10,
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
        'warehouse_id' => $this->warehouseModel->id,
        'qty' => 5, // diff qty
        'type' => 'adjust',
    ]);
});

it('preserves signed qty in stock_log for purchase decrease adjustment', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 20]);
    $product->syncStockFromWarehouses();
    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
    ]);
    $purchaseService = app(PurchaseService::class);
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'qty' => 15,
        'remaining_qty' => 15,
        'price' => 1000,
    ]);

    // Update: change qty from 15 to 5 (diff -10)
    $data = [
        'supplier_id' => $supplier->id,
        'date' => $purchase->date,
        'product_items' => [
            [
                'product_id' => $product->id,
                'qty' => 5,
                'price' => 1000,
                'selling_price' => 2000,
            ],
        ],
    ];

    $purchaseService->updatePurchase($purchase, $data);

    assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouseModel->id,
        'qty' => -10,
        'type' => 'adjust',
    ]);
});

it('can delete a purchase and revert stock', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 20]);
    $product->syncStockFromWarehouses();
    $purchase = Purchase::factory()->create(['warehouse_id' => $this->warehouseModel->id]);
    $purchaseService = app(PurchaseService::class);
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'qty' => 5,
        'remaining_qty' => 5,
        'price' => 1000,
    ]);

    $purchaseService->deletePurchase($purchase);

    assertSoftDeleted('purchases', ['id' => $purchase->id]);
    $product->refresh();
    expect($product->stock)->toBe(15); // 20 - 5

    assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouseModel->id,
        'qty' => 5,
        'type' => 'out',
    ]);
});

// ─── N+1 query fix tests ───────────────────────────────────────────────

it('does not execute N+1 queries when updating purchase with multiple items', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $supplier = Supplier::factory()->create();
    $products = Product::factory()->count(5)->create();
    $this->warehouseModel->products()->attach($products->pluck('id')->mapWithKeys(fn ($id) => [$id => ['stock' => 50]]));
    $products->each->syncStockFromWarehouses();

    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
    ]);

    foreach ($products as $product) {
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'warehouse_id' => $this->warehouseModel->id,
            'product_id' => $product->id,
            'qty' => 10,
            'remaining_qty' => 10,
            'price' => 1000,
        ]);
    }

    $purchaseService = app(PurchaseService::class);

    $data = [
        'supplier_id' => $supplier->id,
        'date' => $purchase->date,
        'product_items' => $products->map(fn ($p) => [
            'product_id' => $p->id,
            'qty' => 15,
            'price' => 1200,
            'selling_price' => 2000,
        ])->toArray(),
    ];

    DB::enableQueryLog();
    $purchaseService->updatePurchase($purchase, $data);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $singleItemLookups = collect($queries)
        ->filter(fn ($q) => str_contains($q['query'], 'select * from `purchase_items` where (`purchase_items`.`purchase_id` = ? and `product_id` = ?'))
        ->count();

    expect($singleItemLookups)->toBe(0);
});

it('can add a new item to an existing purchase during update', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $supplier = Supplier::factory()->create();
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();
    $this->warehouseModel->products()->attach([$productA->id => ['stock' => 30], $productB->id => ['stock' => 30]]);
    $productA->syncStockFromWarehouses();
    $productB->syncStockFromWarehouses();

    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
    ]);

    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $productA->id,
        'qty' => 5,
        'remaining_qty' => 5,
        'price' => 500,
    ]);

    $purchaseService = app(PurchaseService::class);

    $data = [
        'supplier_id' => $supplier->id,
        'date' => $purchase->date,
        'product_items' => [
            ['product_id' => $productA->id, 'qty' => 5, 'price' => 600, 'selling_price' => 1000],
            ['product_id' => $productB->id, 'qty' => 10, 'price' => 800, 'selling_price' => 1500],
        ],
    ];

    $purchaseService->updatePurchase($purchase, $data);

    $productB->refresh();
    expect($productB->stock)->toBe(40); // 30 + 10 new
    expect(PurchaseItem::where('purchase_id', $purchase->id)->count())->toBe(2);
});

it('removes items not present in updated product_items', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $supplier = Supplier::factory()->create();
    $productA = Product::factory()->create();
    $productB = Product::factory()->create();
    $this->warehouseModel->products()->attach([$productA->id => ['stock' => 30], $productB->id => ['stock' => 30]]);
    $productA->syncStockFromWarehouses();
    $productB->syncStockFromWarehouses();

    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
    ]);

    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $productA->id,
        'qty' => 5,
        'remaining_qty' => 5,
        'price' => 500,
    ]);
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $productB->id,
        'qty' => 10,
        'remaining_qty' => 10,
        'price' => 800,
    ]);

    $purchaseService = app(PurchaseService::class);

    // Update: remove productB entirely
    $data = [
        'supplier_id' => $supplier->id,
        'date' => $purchase->date,
        'product_items' => [
            ['product_id' => $productA->id, 'qty' => 3, 'price' => 600, 'selling_price' => 1000],
        ],
    ];

    $purchaseService->updatePurchase($purchase, $data);

    $productB->refresh();
    expect($productB->stock)->toBe(20); // 30 - 10 removed
    expect(PurchaseItem::where('purchase_id', $purchase->id)->count())->toBe(1);
    expect(PurchaseItem::where('purchase_id', $purchase->id)->first()->product_id)->toBe($productA->id);
});

it('correctly updates total after modifying items', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 50]);
    $product->syncStockFromWarehouses();

    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
    ]);

    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'qty' => 10,
        'remaining_qty' => 10,
        'price' => 1000,
    ]);
    $purchase->update(['total' => 10000]);

    $purchaseService = app(PurchaseService::class);

    $data = [
        'supplier_id' => $supplier->id,
        'date' => $purchase->date,
        'product_items' => [
            ['product_id' => $product->id, 'qty' => 5, 'price' => 2000, 'selling_price' => 3000],
        ],
    ];

    $purchaseService->updatePurchase($purchase, $data);

    $purchase->refresh();
    expect((int) $purchase->total)->toBe(5 * 2000);
});

// ─── Negative Stock Prevention ────────────────────────────────────────────────

it('throws when removing purchase item would make warehouse stock negative', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 5]);
    $product->syncStockFromWarehouses();
    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
    ]);
    $purchaseService = app(PurchaseService::class);
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'qty' => 10,
        'remaining_qty' => 10,
        'price' => 1000,
    ]);

    // Simulate sold stock: reduce warehouse stock below the purchase qty
    $this->warehouseModel->products()->syncWithoutDetaching([
        $product->id => ['stock' => 3],
    ]);
    $product->syncStockFromWarehouses();

    // Update without this product — would try to subtract 10 but only 3 in stock
    $data = [
        'supplier_id' => $supplier->id,
        'date' => $purchase->date,
        'product_items' => [],
    ];

    expect(fn () => $purchaseService->updatePurchase($purchase, $data))
        ->toThrow(Exception::class);
});

it('throws when reducing purchase qty below available warehouse stock', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 20]);
    $product->syncStockFromWarehouses();
    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
    ]);
    $purchaseService = app(PurchaseService::class);
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'qty' => 20,
        'remaining_qty' => 20,
        'price' => 1000,
    ]);

    // Simulate sold stock: reduce warehouse stock to 5
    $this->warehouseModel->products()->syncWithoutDetaching([
        $product->id => ['stock' => 5],
    ]);
    $product->syncStockFromWarehouses();

    // Try to reduce purchase qty from 20 to 15 — diff -5, but only 5 in stock (would go to 0)
    $data = [
        'supplier_id' => $supplier->id,
        'date' => $purchase->date,
        'product_items' => [
            [
                'product_id' => $product->id,
                'qty' => 1,
                'price' => 1000,
                'selling_price' => 2000,
            ],
        ],
    ];

    expect(fn () => $purchaseService->updatePurchase($purchase, $data))
        ->toThrow(Exception::class);
});

it('allows reducing purchase qty when enough warehouse stock exists', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 20]);
    $product->syncStockFromWarehouses();
    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->warehouseModel->id,
    ]);
    $purchaseService = app(PurchaseService::class);
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'qty' => 15,
        'remaining_qty' => 15,
        'price' => 1000,
    ]);

    // Reduce purchase qty from 15 to 10 — diff -5, stock 20 → 15 (OK)
    $data = [
        'supplier_id' => $supplier->id,
        'date' => $purchase->date,
        'product_items' => [
            [
                'product_id' => $product->id,
                'qty' => 10,
                'price' => 1000,
                'selling_price' => 2000,
            ],
        ],
    ];

    $purchaseService->updatePurchase($purchase, $data);

    $product->refresh();
    expect($product->stock)->toBe(15);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'type' => 'adjust',
        'qty' => -5,
    ]);
});
