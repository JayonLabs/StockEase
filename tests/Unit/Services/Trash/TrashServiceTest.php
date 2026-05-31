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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase&object{trashService: TrashService} $this */
    $this->trashService = new TrashService;
});

it('returns empty paginator when no trashed items exist', function () {
    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->total())->toBe(0);
});

it('retrieves trashed items from multiple models', function () {
    $category = Category::factory()->create();
    $category->delete();

    $product = Product::factory()->create();
    $product->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems();

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
    $result = $this->trashService->getPaginatedTrashedItems();

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

    Category::factory()->create()->delete();
    Category::factory()->create()->delete();
    Product::factory()->create()->delete();

    expect($this->trashService->getTotalTrashedCount())->toBe(3);
});

it('paginates trashed items correctly', function () {
    Category::factory()->count(5)->create()->each->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems(3);

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
    $result = $this->trashService->getPaginatedTrashedItems();

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
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('StockLog');
    expect($result->first()['name'])->toStartWith('Log Stok #');
});

it('retrieves trashed stockAdjustment', function () {
    $adj = StockAdjustment::factory()->create();
    $adj->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('StockAdjustment');
    expect($result->first()['name'])->toStartWith('Penyesuaian Stok #');
});

it('retrieves trashed stockTransfer', function () {
    $transfer = StockTransfer::factory()->create();
    $transfer->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('StockTransfer');
    expect($result->first()['name'])->toStartWith('Transfer Stok #');
});

// --- Trashed purchase/sale items ---

it('retrieves trashed purchaseItem', function () {
    $item = PurchaseItem::factory()->create();
    $item->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('PurchaseItem');
    expect($result->first()['name'])->toStartWith('Item Pembelian #');
});

it('retrieves trashed saleItem', function () {
    $item = SaleItem::factory()->create();
    $item->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('SaleItem');
    expect($result->first()['name'])->toStartWith('Item Penjualan #');
});

// --- Trashed sale return ---

it('retrieves trashed saleReturn', function () {
    $ret = SaleReturn::factory()->create();
    $ret->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('SaleReturn');
    expect($result->first()['name'])->toStartWith('Retur Penjualan #');
});

it('retrieves trashed saleReturnItem', function () {
    $item = SaleReturnItem::factory()->create();
    $item->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('SaleReturnItem');
    expect($result->first()['name'])->toStartWith('Item Retur #');
});

// --- Trashed payment / price / email ---

it('retrieves trashed paymentTransaction', function () {
    $tx = PaymentTransaction::factory()->create();
    $tx->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('PaymentTransaction');
    expect($result->first()['name'])->toStartWith('Pembayaran #');
});

it('retrieves trashed priceHistory', function () {
    $ph = PriceHistory::factory()->create();
    $ph->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->getPaginatedTrashedItems();

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
    $result = $this->trashService->getPaginatedTrashedItems();

    expect($result->first()['type'])->toBe('SaleEmail');
    expect($result->first()['name'])->toBe('buyer@test.com');
});

// --- Search across new models ---

it('searches trashed saleEmail by email', function () {
    SaleEmail::factory()->create(['email' => 'test@example.com'])->delete();
    SaleEmail::factory()->create(['email' => 'other@example.com'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->searchTrashedItems('test@example.com');

    expect($result->total())->toBe(1);
    expect($result->first()['name'])->toBe('test@example.com');
});

it('searches trashed saleReturn by reason', function () {
    SaleReturn::factory()->create(['reason' => 'Defective item'])->delete();
    SaleReturn::factory()->create(['reason' => 'Wrong size'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->searchTrashedItems('Defective');

    expect($result->total())->toBe(1);
    expect($result->first()['type'])->toBe('SaleReturn');
});

it('searches trashed paymentTransaction by external_id', function () {
    PaymentTransaction::factory()->create(['external_id' => 'uuid-abc-123'])->delete();
    PaymentTransaction::factory()->create(['external_id' => 'uuid-def-456'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->searchTrashedItems('uuid-abc');

    expect($result->total())->toBe(1);
    expect($result->first()['type'])->toBe('PaymentTransaction');
});

it('searches trashed stockAdjustment by reason', function () {
    StockAdjustment::factory()->create(['reason' => 'Stock recount'])->delete();
    StockAdjustment::factory()->create(['reason' => 'Damaged goods'])->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    $result = $this->trashService->searchTrashedItems('recount');

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
    Category::factory()->create()->delete();
    Warehouse::factory()->create()->delete();
    Shift::factory()->create()->delete();
    StockLog::factory()->create()->delete();
    SaleEmail::factory()->create()->delete();

    /** @var TestCase&object{trashService: TrashService} $this */
    expect($this->trashService->getTotalTrashedCount())->toBe(5);
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
    $result = $this->trashService->getPaginatedTrashedItems(2);

    expect($result->total())->toBe(5);
    expect($result->perPage())->toBe(2);
    expect($result->lastPage())->toBe(3);
});
