<?php

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Stock\StockAdjustmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $user = User::factory()->create(['role' => 'admin']);
    Auth::login($user);
    $this->warehouseModel = Warehouse::factory()->create();
});

it('can get paginated adjustments', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    StockAdjustment::factory()->count(15)->create([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'user_id' => Auth::id(),
    ]);
    $service = app(StockAdjustmentService::class);

    $results = $service->getPaginatedAdjustments([], 10);

    expect($results->total())->toBe(15);
    expect($results->count())->toBe(10);
});

it('can store a new stock adjustment', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();
    $data = [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 15,
        'reason' => 'Penambahan stok fisik',
        'date' => now()->toDateString(),
    ];
    $service = app(StockAdjustmentService::class);

    $adjustment = $service->storeAdjustment($data);

    expect($adjustment->old_stock)->toBe(10);
    expect($adjustment->new_stock)->toBe(15);
    expect($adjustment->reason)->toBe('Penambahan stok fisik');

    $product->refresh();
    expect($product->stock)->toBe(15);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouseModel->id,
        'qty' => 5, // diff
        'type' => 'adjust',
        'reference_type' => 'StockAdjustment',
        'reference_id' => $adjustment->id,
    ]);
});

it('eager loads user roles in a single query to prevent N+1', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $sameUser = User::factory()->create(['role' => 'admin']);
    StockAdjustment::factory()->count(3)->create([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'user_id' => $sameUser->id,
    ]);
    StockAdjustment::factory()->count(2)->create([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'user_id' => User::factory()->create(['role' => 'warehouse'])->id,
    ]);
    $service = app(StockAdjustmentService::class);

    DB::enableQueryLog();
    $results = $service->getPaginatedAdjustments([]);
    $queries = DB::getQueryLog();

    expect($results->total())->toBe(5);

    $rolesQueries = collect($queries)->filter(fn ($query) => str_contains($query['query'], 'model_has_roles')
    );

    expect($rolesQueries)->toHaveCount(1);
});

it('does not run duplicate role queries when same user has multiple adjustments', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $sameUser = User::factory()->create(['role' => 'warehouse']);
    StockAdjustment::factory()->count(5)->create([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'user_id' => $sameUser->id,
    ]);
    $service = app(StockAdjustmentService::class);

    DB::enableQueryLog();
    $results = $service->getPaginatedAdjustments([]);
    $queries = DB::getQueryLog();

    expect($results->total())->toBe(5);

    $duplicateQueries = collect($queries)
        ->filter(fn ($query) => str_contains($query['query'], 'model_has_roles'))
        ->groupBy(fn ($query) => $query['query'].json_encode($query['bindings']))
        ->filter(fn ($group) => $group->count() > 1);

    expect($duplicateQueries)->toHaveCount(0);
});

it('can handle negative stock adjustment', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();
    $data = [
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 7,
        'reason' => 'Barang rusak',
        'date' => now()->toDateString(),
    ];
    $service = app(StockAdjustmentService::class);

    $adjustment = $service->storeAdjustment($data);

    expect($product->refresh()->stock)->toBe(7);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'warehouse_id' => $this->warehouseModel->id,
        'qty' => -3,
        'type' => 'adjust',
    ]);
});

it('preserves signed qty for increase adjustments', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();
    $service = app(StockAdjustmentService::class);

    $service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 20,
        'reason' => 'Restock',
        'date' => now()->toDateString(),
    ]);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => 10,
        'type' => 'adjust',
    ]);
});

it('preserves signed qty for decrease adjustments', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 15]);
    $product->syncStockFromWarehouses();
    $service = app(StockAdjustmentService::class);

    $service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 5,
        'reason' => 'Damaged',
        'date' => now()->toDateString(),
    ]);

    $this->assertDatabaseHas('stock_logs', [
        'product_id' => $product->id,
        'qty' => -10,
        'type' => 'adjust',
    ]);
});

it('increase and decrease adjustments produce distinct log entries', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();
    $service = app(StockAdjustmentService::class);

    $service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 15,
        'reason' => 'Increase',
        'date' => now()->toDateString(),
    ]);

    $service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 5,
        'reason' => 'Decrease',
        'date' => now()->toDateString(),
    ]);

    $logs = DB::table('stock_logs')
        ->where('product_id', $product->id)
        ->where('type', 'adjust')
        ->orderBy('id')
        ->get();

    expect($logs)->toHaveCount(2);
    expect((int) $logs[0]->qty)->toBe(5);
    expect((int) $logs[1]->qty)->toBe(-10);
});

it('signed qty allows distinguishing direction in stock log', function () {
    /** @var object{warehouseModel: Warehouse} $this */
    $product = Product::factory()->create();
    $this->warehouseModel->products()->attach($product->id, ['stock' => 10]);
    $product->syncStockFromWarehouses();
    $service = app(StockAdjustmentService::class);

    $service->storeAdjustment([
        'warehouse_id' => $this->warehouseModel->id,
        'product_id' => $product->id,
        'new_stock' => 5,
        'reason' => 'Decrease',
        'date' => now()->toDateString(),
    ]);

    $log = DB::table('stock_logs')->where('product_id', $product->id)->first();

    $isDecrease = (int) $log->qty < 0;
    expect($isDecrease)->toBeTrue();
    expect(abs((int) $log->qty))->toBe(5);
});
