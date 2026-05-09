<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
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
