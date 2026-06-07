<?php

use App\Models\Category;
use App\Models\PaymentTransaction;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleEmail;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Shift;
use App\Models\StockAdjustment;
use App\Models\StockLog;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Trash\TrashService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService = new TrashService;
});

it('returns empty paginator when no trashed items exist', function () {
    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->total())->toBe(0);
});

it('retrieves trashed items from multiple models', function () {
    $category = Category::factory()->create();
    $category->delete();

    $product = Product::factory()->create();
    $product->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->total())->toBe(2);
});

it('does not include non-trashed items', function () {
    Category::factory()->create(); // not deleted
    $deleted = Category::factory()->create();
    $deleted->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->total())->toBe(1);
    expect($result->first()['id'])->toBe($deleted->id);
});

it('includes trashed items from all tracked models', function () {
    $models = [
        Category::factory()->create(),
        Product::factory()->create(),
        Supplier::factory()->create(),
        Unit::factory()->create(),
    ];

    foreach ($models as $model) {
        $model->delete();
    }

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->total())->toBe(count($models));
});

it('maps trashed items to unified structure', function () {
    $category = Category::factory()->create(['name' => 'Test Category']);
    $category->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */
    $result = $this->trashService->getPaginatedTrashedItems();
    $item = $result->first();

    expect($item)->toHaveKeys(['id', 'class', 'type', 'type_label', 'name', 'deleted_at']);
    expect($item['type'])->toBe('Category');
    expect($item['type_label'])->toBe('Kategori');
    expect($item['name'])->toBe('Test Category');
});

it('searches trashed items by name', function () {
    Category::factory()->create(['name' => 'Electronics'])->delete();
    Category::factory()->create(['name' => 'Fashion'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */
    $result = $this->trashService->searchTrashedItems('Electronics');

    expect($result->total())->toBe(1);
    expect($result->first()['name'])->toBe('Electronics');
});

it('searches users by name and email', function () {
    User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com'])->delete();
    User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */
    $result = $this->trashService->searchTrashedItems('jane@example.com');

    expect($result->total())->toBe(1);
    expect($result->first()['name'])->toContain('Jane Doe');
});

it('restores a trashed model', function () {
    $category = Category::factory()->create(['name' => 'Restore Me']);
    $category->delete();

    expect(Category::onlyTrashed()->count())->toBe(1);

    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService->restore(Category::class, $category->id);

    expect(Category::onlyTrashed()->count())->toBe(0);
    expect(Category::find($category->id))->not->toBeNull();
});

it('force deletes a trashed model permanently', function () {
    $category = Category::factory()->create();
    $category->delete();

    expect(Category::withTrashed()->count())->toBe(1);

    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService->forceDelete(Category::class, $category->id);

    expect(Category::withTrashed()->count())->toBe(0);
});

it('throws exception when restoring non-existent trashed model', function () {
    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService->restore(Category::class, 99999);
})->throws(ModelNotFoundException::class);

it('throws exception when force deleting non-existent trashed model', function () {
    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService->forceDelete(Category::class, 99999);
})->throws(ModelNotFoundException::class);

it('returns correct total trashed count', function () {
    /** @var TestCase&object{trashService: TrashService} $this */
    expect($this->trashService->getTotalTrashedCount())->toBe(0);

    $cat1 = Category::factory()->create();
    $cat1->delete();
    $cat2 = Category::factory()->create();
    $cat2->delete();
    $product = Product::factory()->create();
    $product->delete();

    expect($this->trashService->getTotalTrashedCount())->toBe(3);
});

it('uses a single query for total trashed count with multiple models', function () {
    Category::factory()->create()->delete();
    Product::factory()->create()->delete();
    Supplier::factory()->create()->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    DB::enableQueryLog();

    $count = $this->trashService->getTotalTrashedCount();

    DB::disableQueryLog();
    $queries = DB::getQueryLog();

    expect($count)->toBe(3);
    expect($queries)->not->toBeEmpty();

    $nonConnectionQueries = collect($queries)->filter(
        fn ($q) => ! str_contains($q['query'], 'SET')
    )->toArray();

    expect(count($nonConnectionQueries))->toBe(1);
    expect($nonConnectionQueries[0]['query'])->toContain('UNION ALL');
    expect($nonConnectionQueries[0]['query'])->toContain('COUNT');
});

it('getTotalTrashedCount excludes non-trashed items', function () {
    Category::factory()->create();
    $deleted = Category::factory()->create();
    $deleted->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    expect($this->trashService->getTotalTrashedCount())->toBe(1);
});

it('paginates trashed items correctly', function () {
    Category::factory()->count(5)->create()->each->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems(3);

    expect($result->total())->toBe(5);
    expect($result->perPage())->toBe(3);
    expect($result->lastPage())->toBe(2);
});

it('gets a single trashed item with full attributes', function () {
    $category = Category::factory()->create(['name' => 'Show Me']);
    $category->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(Category::class, $category->id);

    expect($item)->toHaveKeys(['id', 'class', 'type', 'type_label', 'name', 'deleted_at', 'attributes']);
    expect($item['type'])->toBe('Category');
    expect($item['name'])->toBe('Show Me');
    expect($item['attributes'])->toBeArray();
    expect($item['attributes'])->not->toBeEmpty();
    expect($item['attributes'][0])->toHaveKeys(['key', 'value']);
});

it('throws exception when getting non-existent trashed item', function () {
    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService->getTrashedItem(Category::class, 99999);
})->throws(ModelNotFoundException::class);

it('gets a trashed product with all its attributes', function () {
    $product = Product::factory()->create([
        'name' => 'Deleted Product',
        'barcode' => '123456789',
        'stock' => 50,
    ]);
    $product->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(Product::class, $product->id);

    expect($item['type'])->toBe('Product');
    expect($item['type_label'])->toBe('Produk');
    expect($item['name'])->toBe('Deleted Product');
    expect($item['attributes'])->toBeArray();

    $foundNames = collect($item['attributes'])->pluck('key')->toArray();
    expect($foundNames)->toContain('Name');
    expect($foundNames)->toContain('Barcode');
    expect($foundNames)->toContain('Stock');
});

it('gets a trashed user with correct display format', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
    $user->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(User::class, $user->id);

    expect($item['type'])->toBe('User');
    expect($item['type_label'])->toBe('User');
    expect($item['name'])->toContain('John Doe');
    expect($item['name'])->toContain('john@example.com');
});

it('gets a trashed sale with customer name', function () {
    $sale = Sale::factory()->create(['customer_name' => 'Budi']);
    $sale->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(Sale::class, $sale->id);

    expect($item['type'])->toBe('Sale');
    expect($item['type_label'])->toBe('Penjualan');
    expect($item['name'])->toBe('Budi');
});

it('resolves foreign keys to human-readable names', function () {
    $supplier = Supplier::factory()->create(['name' => 'PT Makmur']);
    $purchase = Purchase::factory()->create(['supplier_id' => $supplier->id]);
    $purchase->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(Purchase::class, $purchase->id);

    $supplierIdAttr = collect($item['attributes'])->firstWhere('key', 'Supplier id');
    expect($supplierIdAttr)->not->toBeNull();
    expect($supplierIdAttr['value'])->toBe('PT Makmur');
});

it('resolves product foreign keys in promotions', function () {
    $category = Category::factory()->create(['name' => 'Minuman']);
    $promotion = Promotion::factory()->create(['category_id' => $category->id]);
    $promotion->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(Promotion::class, $promotion->id);

    $categoryIdAttr = collect($item['attributes'])->firstWhere('key', 'Category id');
    expect($categoryIdAttr)->not->toBeNull();
    expect($categoryIdAttr['value'])->toBe('Minuman');
});

// --- Trashed warehouse ---

it('retrieves trashed warehouse with name', function () {
    $warehouse = Warehouse::factory()->create(['name' => 'Gudang Utara']);
    $warehouse->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->total())->toBe(1);
    expect($result->first()['type'])->toBe('Warehouse');
    expect($result->first()['name'])->toBe('Gudang Utara');
    expect($result->first()['type_label'])->toBe('Gudang');
});

// --- Trashed shift ---

it('retrieves trashed shift with status in name', function () {
    $shift = Shift::factory()->create(['status' => 'closed']);
    $shift->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('Shift');
    expect($result->first()['name'])->toContain('Shift #');
    expect($result->first()['name'])->toContain('(closed)');
});

// --- Trashed stock models ---

it('retrieves trashed stockLog', function () {
    $log = StockLog::factory()->create();
    $log->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('StockLog');
    expect($result->first()['name'])->toStartWith('Log Stok #');
});

it('retrieves trashed stockAdjustment', function () {
    $adj = StockAdjustment::factory()->create();
    $adj->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('StockAdjustment');
    expect($result->first()['name'])->toStartWith('Penyesuaian Stok #');
});

it('retrieves trashed stockTransfer', function () {
    $transfer = StockTransfer::factory()->create();
    $transfer->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('StockTransfer');
    expect($result->first()['name'])->toStartWith('Transfer Stok #');
});

// --- Trashed purchase/sale items ---

it('retrieves trashed purchaseItem', function () {
    $item = PurchaseItem::factory()->create();
    $item->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('PurchaseItem');
    expect($result->first()['name'])->toStartWith('Item Pembelian #');
});

it('retrieves trashed saleItem', function () {
    $item = SaleItem::factory()->create();
    $item->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('SaleItem');
    expect($result->first()['name'])->toStartWith('Item Penjualan #');
});

// --- Trashed sale return ---

it('retrieves trashed saleReturn', function () {
    $ret = SaleReturn::factory()->create();
    $ret->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('SaleReturn');
    expect($result->first()['name'])->toStartWith('Retur Penjualan #');
});

it('retrieves trashed saleReturnItem', function () {
    $item = SaleReturnItem::factory()->create();
    $item->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('SaleReturnItem');
    expect($result->first()['name'])->toStartWith('Item Retur #');
});

// --- Trashed payment / price / email ---

it('retrieves trashed paymentTransaction', function () {
    $tx = PaymentTransaction::factory()->create();
    $tx->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('PaymentTransaction');
    expect($result->first()['name'])->toStartWith('Pembayaran #');
});

it('retrieves trashed priceHistory', function () {
    $ph = PriceHistory::factory()->create();
    $ph->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('PriceHistory');
    expect($result->first()['name'])->toStartWith('Riwayat Harga #');
});

it('retrieves trashed saleEmail with email in name', function () {
    $email = SaleEmail::factory()->create(['email' => 'customer@example.com']);
    $email->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('SaleEmail');
    expect($result->first()['name'])->toBe('customer@example.com');
});

it('retrieves trashed saleEmail with correct name format', function () {
    $email = SaleEmail::factory()->create(['email' => 'buyer@test.com']);
    $email->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('SaleEmail');
    expect($result->first()['name'])->toBe('buyer@test.com');
});

// --- Search across new models ---

it('searches trashed saleEmail by email', function () {
    SaleEmail::factory()->create(['email' => 'test@example.com'])->delete();
    SaleEmail::factory()->create(['email' => 'other@example.com'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->searchTrashedItems('test@example.com');

    expect($result->total())->toBe(1);
    expect($result->first()['name'])->toBe('test@example.com');
});

it('searches trashed saleReturn by reason', function () {
    SaleReturn::factory()->create(['reason' => 'Defective item'])->delete();
    SaleReturn::factory()->create(['reason' => 'Wrong size'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->searchTrashedItems('Defective');

    expect($result->total())->toBe(1);
    expect($result->first()['type'])->toBe('SaleReturn');
});

it('searches trashed paymentTransaction by external_id', function () {
    PaymentTransaction::factory()->create(['external_id' => 'uuid-abc-123'])->delete();
    PaymentTransaction::factory()->create(['external_id' => 'uuid-def-456'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->searchTrashedItems('uuid-abc');

    expect($result->total())->toBe(1);
    expect($result->first()['type'])->toBe('PaymentTransaction');
});

it('searches trashed stockAdjustment by reason', function () {
    StockAdjustment::factory()->create(['reason' => 'Stock recount'])->delete();
    StockAdjustment::factory()->create(['reason' => 'Damaged goods'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->searchTrashedItems('recount');

    expect($result->total())->toBe(1);
});

// --- Restore and force delete new models ---

it('restores a trashed warehouse', function () {
    $warehouse = Warehouse::factory()->create(['name' => 'Gudang Timur']);
    $warehouse->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService->restore(Warehouse::class, $warehouse->id);

    expect(Warehouse::find($warehouse->id))->not->toBeNull();
    expect(Warehouse::onlyTrashed()->count())->toBe(0);
});

it('force deletes a trashed warehouse', function () {
    $warehouse = Warehouse::factory()->create();
    $warehouse->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService->forceDelete(Warehouse::class, $warehouse->id);

    expect(Warehouse::withTrashed()->where('id', $warehouse->id)->exists())->toBeFalse();
});

it('restores a trashed shift', function () {
    $shift = Shift::factory()->create();
    $shift->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService->restore(Shift::class, $shift->id);

    expect(Shift::find($shift->id))->not->toBeNull();
});

it('force deletes a trashed saleReturn', function () {
    $ret = SaleReturn::factory()->create();
    $ret->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService->forceDelete(SaleReturn::class, $ret->id);

    expect(SaleReturn::withTrashed()->where('id', $ret->id)->exists())->toBeFalse();
});

// --- Total count with all models ---

it('counts trashed items across all 20 tracked models', function () {
    $models = [
        Category::factory()->create(),
        PaymentTransaction::factory()->create(),
        PriceHistory::factory()->create(),
        Product::factory()->create(),
        Promotion::factory()->create(),
        Purchase::factory()->create(),
        PurchaseItem::factory()->create(),
        Sale::factory()->create(),
        SaleEmail::factory()->create(),
        SaleItem::factory()->create(),
        SaleReturn::factory()->create(),
        SaleReturnItem::factory()->create(),
        Shift::factory()->create(),
        StockAdjustment::factory()->create(),
        StockLog::factory()->create(),
        StockTransfer::factory()->create(),
        Supplier::factory()->create(),
        Unit::factory()->create(),
        User::factory()->create(),
        Warehouse::factory()->create(),
    ];

    foreach ($models as $model) {
        $model->delete();
    }

    /** @var TestCase&object{trashService: TrashService} $this */
    expect($this->trashService->getTotalTrashedCount())->toBe(20);

    DB::enableQueryLog();
    $count = $this->trashService->getTotalTrashedCount();
    DB::disableQueryLog();

    $nonConnectionQueries = collect(DB::getQueryLog())->filter(
        fn ($q) => ! str_contains($q['query'], 'SET')
    )->toArray();

    expect($count)->toBe(20);
    expect(count($nonConnectionQueries))->toBe(1);
});

it('getTotalTrashedCount matches paginator total', function () {
    Category::factory()->count(3)->create()->each->delete();
    Product::factory()->count(2)->create()->each->delete();
    Supplier::factory()->create()->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $paginator = $this->trashService->getPaginatedTrashedItems();
    $count = $this->trashService->getTotalTrashedCount();

    expect($count)->toBe($paginator->total());
    expect($count)->toBe(6);
});

// --- getTrashedItem for new models ---

it('gets trashed warehouse detail with attributes', function () {
    $warehouse = Warehouse::factory()->create([
        'name' => 'Gudang Selatan',
        'address' => 'Jl. Merdeka No. 1',
        'phone' => '021-123456',
    ]);
    $warehouse->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(Warehouse::class, $warehouse->id);

    expect($item['type'])->toBe('Warehouse');
    expect($item['type_label'])->toBe('Gudang');
    expect($item['name'])->toBe('Gudang Selatan');

    $attrs = collect($item['attributes']);
    expect($attrs->firstWhere('key', 'Name')['value'])->toBe('Gudang Selatan');
    expect($attrs->firstWhere('key', 'Address')['value'])->toBe('Jl. Merdeka No. 1');
});

it('gets trashed stockAdjustment with attributes', function () {
    $adj = StockAdjustment::factory()->create([
        'old_stock' => 50,
        'new_stock' => 30,
        'reason' => 'Stock recount',
    ]);
    $adj->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(StockAdjustment::class, $adj->id);

    expect($item['type'])->toBe('StockAdjustment');
    expect($item['attributes'])->toBeArray();

    $attrs = collect($item['attributes']);
    expect($attrs->firstWhere('key', 'Reason')['value'])->toBe('Stock recount');
});

it('gets trashed priceHistory with attributes', function () {
    $ph = PriceHistory::factory()->create([
        'old_purchase_price' => 5000,
        'new_purchase_price' => 7500,
        'old_selling_price' => 10000,
        'new_selling_price' => 15000,
        'reason' => 'Supplier price increase',
    ]);
    $ph->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(PriceHistory::class, $ph->id);

    expect($item['type'])->toBe('PriceHistory');

    $attrs = collect($item['attributes']);
    expect($attrs->firstWhere('key', 'Reason')['value'])->toBe('Supplier price increase');
    expect($attrs->firstWhere('key', 'Old purchase price')['value'])->toBe('5000.0000');
});

it('gets trashed paymentTransaction detail excluding raw_response', function () {
    $tx = PaymentTransaction::factory()->create([
        'gateway' => 'midtrans',
        'external_id' => 'midtrans-uuid-123',
        'status' => 'settlement',
        'amount' => 10000,
        'payment_type' => 'qris',
        'raw_response' => '{"huge":"json"}',
    ]);
    $tx->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(PaymentTransaction::class, $tx->id);

    expect($item['type'])->toBe('PaymentTransaction');

    $keys = collect($item['attributes'])->pluck('key')->toArray();
    expect($keys)->not->toContain('Raw response');

    $gateway = collect($item['attributes'])->firstWhere('key', 'Gateway');
    expect($gateway['value'])->toBe('midtrans');
});

it('gets trashed saleEmail with status attribute', function () {
    $email = SaleEmail::factory()->sent()->create(['email' => 'buyer@test.com']);
    $email->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(SaleEmail::class, $email->id);

    $attrs = collect($item['attributes']);
    $emailAttr = $attrs->firstWhere('key', 'Email');
    expect($emailAttr['value'])->toBe('buyer@test.com');

    $status = $attrs->firstWhere('key', 'Status');
    expect($status['value'])->toBe('sent');
});

// --- FK resolution for new FK resolvers (warehouse_id variants) ---

it('resolves to_warehouse_id foreign key', function () {
    $from = Warehouse::factory()->create(['name' => 'Gudang A']);
    $to = Warehouse::factory()->create(['name' => 'Gudang B']);
    $transfer = StockTransfer::factory()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => $to->id,
    ]);
    $transfer->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(StockTransfer::class, $transfer->id);

    $attrs = collect($item['attributes']);
    $fromAttr = $attrs->firstWhere('key', 'From warehouse id');
    expect($fromAttr['value'])->toBe('Gudang A');
    $toAttr = $attrs->firstWhere('key', 'To warehouse id');
    expect($toAttr['value'])->toBe('Gudang B');
});

it('paginates all tracked models together across 20 types', function () {
    Category::factory()->create()->delete();
    Warehouse::factory()->create()->delete();
    Product::factory()->create()->delete();
    Supplier::factory()->create()->delete();
    Unit::factory()->create()->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems(2);

    expect($result->total())->toBe(5);
    expect($result->perPage())->toBe(2);
    expect($result->lastPage())->toBe(3);
});

// ─── Performance-optimized UNION ALL tests ─────────────────────────────

it('sorts trashed items across models by deleted_at descending', function () {
    $category = Category::factory()->create(['name' => 'Older']);
    $product = Product::factory()->create(['name' => 'Newer']);

    DB::table('categories')->where('id', $category->id)->update(['deleted_at' => now()->subDays(3)]);
    DB::table('products')->where('id', $product->id)->update(['deleted_at' => now()->subDay()]);
    $category->refresh();
    $product->refresh();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->total())->toBe(2);
    expect($result->first()['name'])->toBe('Newer');
    expect($result->last()['name'])->toBe('Older');
});

it('handles large number of trashed items efficiently', function () {
    $count = 50;
    for ($i = 0; $i < $count; $i++) {
        $category = Category::factory()->create(['name' => "Category {$i}"]);
        DB::table('categories')->where('id', $category->id)->update(['deleted_at' => now()->subDays($i)]);
    }

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems(10);

    expect($result->total())->toBe($count);
    expect($result->count())->toBe(10);
    expect($result->first()['name'])->toBe('Category 0');
});

it('paginates correctly when search matches across multiple models', function () {
    Category::factory()->create(['name' => 'Electronics Gadget'])->delete();
    Product::factory()->create(['name' => 'Smart Gadget'])->delete();
    Supplier::factory()->create(['name' => 'Gadget World'])->delete();
    Warehouse::factory()->create(['name' => 'Unrelated'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->searchTrashedItems('Gadget');

    expect($result->total())->toBe(3);
    $types = $result->pluck('type')->toArray();
    expect($types)->toContain('Category');
    expect($types)->toContain('Product');
    expect($types)->toContain('Supplier');
});

it('returns empty paginator when no items match search', function () {
    Category::factory()->create(['name' => 'Electronics'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->searchTrashedItems('nonexistent');

    expect($result->total())->toBe(0);
});

it('searches sale items by id across models', function () {
    $purchaseItem = PurchaseItem::factory()->create();
    $purchaseItem->delete();
    $saleItem = SaleItem::factory()->create();
    $saleItem->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->searchTrashedItems((string) $saleItem->id);

    expect($result->total())->toBe(1);
    expect($result->first()['type'])->toBe('SaleItem');
});

it('correctly formats user name in union query', function () {
    $user = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);
    $user->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['name'])->toBe('Alice (alice@example.com)');
});

it('correctly formats shift name with status in union query', function () {
    $shift = Shift::factory()->create(['status' => 'open']);
    $shift->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    $name = $result->first()['name'];
    expect($name)->toStartWith('Shift #');
    expect($name)->toEndWith('(open)');
});

it('correctly formats sale name with customer_name fallback', function () {
    $saleWithCustomer = Sale::factory()->create(['customer_name' => 'John']);
    $saleWithCustomer->delete();
    $saleWithoutCustomer = Sale::factory()->create(['customer_name' => null]);
    $saleWithoutCustomer->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    $names = $result->pluck('name')->toArray();
    expect($names)->toContain('John');
    expect(collect($names)->first(fn ($n) => str_starts_with($n, 'Penjualan #')))->not->toBeNull();
});

it('correctly formats saleEmail name in union query', function () {
    $emailWithAddress = SaleEmail::factory()->create(['email' => 'buyer@test.com']);
    $emailWithAddress->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['name'])->toBe('buyer@test.com');
});

it('handles perPage parameter correctly on second page', function () {
    Category::factory()->count(5)->create()->each->delete();

    Paginator::currentPageResolver(fn () => 2);

    /** @var TestCase&object{trashService: TrashService} $this */
    /** @var LengthAwarePaginator $result */ $result = $this->trashService->getPaginatedTrashedItems(3);

    expect($result->currentPage())->toBe(2);
    expect($result->count())->toBe(2);
});

// ─── FK resolution cache tests ────────────────────────────────────────

it('caches resolved foreign key values within the same service instance', function () {
    $supplier = Supplier::factory()->create(['name' => 'PT Sumber Jaya']);
    $purchase = Purchase::factory()->create(['supplier_id' => $supplier->id]);
    $purchase->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    DB::enableQueryLog();

    // First call — should hit the database
    $this->trashService->getTrashedItem(Purchase::class, $purchase->id);
    $firstQueryCount = count(DB::getQueryLog());
    DB::flushQueryLog();

    // Second call for the same item — FK values should come from cache
    $this->trashService->getTrashedItem(Purchase::class, $purchase->id);
    $secondQueryCount = count(DB::getQueryLog());

    DB::disableQueryLog();

    // The second call should execute fewer FK resolution queries
    expect($secondQueryCount)->toBeLessThan($firstQueryCount);
});

it('resolves multiple FK attributes on the same record with caching', function () {
    $supplier = Supplier::factory()->create(['name' => 'PT Abadi']);
    $user = User::factory()->create(['name' => 'John Doe', 'email' => 'john@test.com']);
    $purchase = Purchase::factory()->create([
        'supplier_id' => $supplier->id,
        'user_id' => $user->id,
    ]);
    $purchase->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(Purchase::class, $purchase->id);

    $attrs = collect($item['attributes']);
    $supplierAttr = $attrs->firstWhere('key', 'Supplier id');
    $userAttr = $attrs->firstWhere('key', 'User id');

    expect($supplierAttr['value'])->toBe('PT Abadi');
    expect($userAttr['value'])->toBe('John Doe');
});

it('cache is effective for repeated FK lookups across items', function () {
    $category = Category::factory()->create(['name' => 'Elektronik']);
    $productA = Product::factory()->create(['category_id' => $category->id, 'name' => 'TV']);
    $productB = Product::factory()->create(['category_id' => $category->id, 'name' => 'Radio']);
    $productA->delete();
    $productB->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    DB::enableQueryLog();

    $this->trashService->getTrashedItem(Product::class, $productA->id);
    DB::flushQueryLog();

    $this->trashService->getTrashedItem(Product::class, $productB->id);
    $queriesForSecond = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'categories'))
        ->count();

    DB::disableQueryLog();

    // The second call should NOT query categories again (cached)
    expect($queriesForSecond)->toBe(0);
});

it('caches null values for FK attributes correctly', function () {
    $promotion = Promotion::factory()->create(['category_id' => null]);
    $promotion->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $item = $this->trashService->getTrashedItem(Promotion::class, $promotion->id);

    $attrs = collect($item['attributes']);
    $categoryAttr = $attrs->firstWhere('key', 'Category id');

    expect($categoryAttr['value'])->toBeNull();
});

it('caches warehouse FK resolution across multiple records', function () {
    $warehouse = Warehouse::factory()->create(['name' => 'Gudang Pusat']);
    $purchaseA = Purchase::factory()->create(['warehouse_id' => $warehouse->id]);
    $purchaseB = Purchase::factory()->create(['warehouse_id' => $warehouse->id]);
    $purchaseA->delete();
    $purchaseB->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    DB::enableQueryLog();

    $this->trashService->getTrashedItem(Purchase::class, $purchaseA->id);
    DB::flushQueryLog();

    $this->trashService->getTrashedItem(Purchase::class, $purchaseB->id);
    $warehouseQueries = collect(DB::getQueryLog())
        ->filter(fn ($q) => str_contains($q['query'], 'warehouses'))
        ->count();

    DB::disableQueryLog();

    expect($warehouseQueries)->toBe(0);
});
