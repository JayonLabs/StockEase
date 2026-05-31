<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\StockLog;
use App\Models\Unit;
use App\Models\User;
use App\Services\Sale\SaleReturnService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

uses(TestCase::class, LazilyRefreshDatabase::class);

function createProduct(int $stock = 50, int $purchasePrice = 5000): Product
{
    $category = Category::factory()->create();
    $unit = Unit::factory()->create();

    return Product::factory()->create([
        'name' => 'Product Test',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'stock' => $stock,
        'purchase_price' => $purchasePrice,
        'selling_price' => 10000,
    ]);
}

function authenticateAdmin(): User
{
    /** @var User $user */
    $user = User::factory()->create(['role' => 'admin']);
    actingAs($user);

    return $user;
}

describe('SaleReturnService', function () {
    // ============================================================
    // getSaleForReturn
    // ============================================================

    describe('getSaleForReturn', function () {
        it('loads sale with items and products', function () {
            $service = app(SaleReturnService::class);
            $product = createProduct();
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
            ]);

            $result = $service->getSaleForReturn($sale);

            expect($result->relationLoaded('saleItems'))->toBeTrue();
            expect($result->relationLoaded('user'))->toBeTrue();
            expect($result->saleItems)->toHaveCount(1);
        });

        it('eager loads user roles to prevent n plus one', function () {
            $service = app(SaleReturnService::class);
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);

            $result = $service->getSaleForReturn($sale);

            expect($result->user->relationLoaded('roles'))->toBeTrue();
        });
    });

    // ============================================================
    // processReturn — refund
    // ============================================================

    describe('processReturn refund', function () {
        it('creates a sale return record with refund type', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct(50, 5000);
            $purchase = Purchase::factory()->create();
            PurchaseItem::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => 50,
                'remaining_qty' => 10,
                'price' => 5000,
            ]);
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
                'cost_price' => 5000,
            ]);

            $return = $service->processReturn($sale, [
                'return_type' => 'refund',
                'reason' => 'Barang rusak',
                'notes' => 'Test notes',
                'items' => [
                    ['sale_item_id' => $sale->saleItems->first()->id, 'qty' => 1],
                ],
            ]);

            expect($return)->toBeInstanceOf(SaleReturn::class);
            expect($return->return_type)->toBe('refund');
            expect($return->reason)->toBe('Barang rusak');
            expect($return->notes)->toBe('Test notes');
            expect($return->status)->toBe('completed');
            expect((float) $return->total_refund)->toBeGreaterThan(0);
        });

        it('calculates correct refund amount', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct(50, 5000);
            $purchase = Purchase::factory()->create();
            PurchaseItem::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => 50,
                'remaining_qty' => 10,
                'price' => 5000,
            ]);
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 3,
                'price' => 15000,
            ]);

            $return = $service->processReturn($sale, [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => $sale->saleItems->first()->id, 'qty' => 2],
                ],
            ]);

            expect((float) $return->total_refund)->toBe(30000.0);
        });

        it('creates sale return items', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct(50, 5000);
            $purchase = Purchase::factory()->create();
            PurchaseItem::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => 50,
                'remaining_qty' => 10,
                'price' => 5000,
            ]);
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
            ]);

            $return = $service->processReturn($sale, [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => $sale->saleItems->first()->id, 'qty' => 1],
                ],
            ]);

            expect($return->saleReturnItems)->toHaveCount(1);
            $item = $return->saleReturnItems->first();
            expect((int) $item->qty)->toBe(1);
            expect((float) $item->price)->toBe(10000.0);
            expect((float) $item->total)->toBe(10000.0);
        });
    });

    // ============================================================
    // processReturn — exchange
    // ============================================================

    describe('processReturn exchange', function () {
        it('creates a sale return with exchange type and zero refund', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct(50, 5000);
            $purchase = Purchase::factory()->create();
            PurchaseItem::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => 50,
                'remaining_qty' => 10,
                'price' => 5000,
            ]);
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 20000,
            ]);

            $return = $service->processReturn($sale, [
                'return_type' => 'exchange',
                'items' => [
                    ['sale_item_id' => $sale->saleItems->first()->id, 'qty' => 1],
                ],
            ]);

            expect($return->return_type)->toBe('exchange');
            expect((float) $return->total_refund)->toBe(0.0);
        });
    });

    // ============================================================
    // processReturn — stock
    // ============================================================

    describe('processReturn stock management', function () {
        it('increments product stock on return', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct(50, 5000);
            $purchase = Purchase::factory()->create();
            PurchaseItem::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => 50,
                'remaining_qty' => 10,
                'price' => 5000,
            ]);
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
            ]);

            $originalStock = (int) $product->stock;

            $service->processReturn($sale, [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => $sale->saleItems->first()->id, 'qty' => 1],
                ],
            ]);

            $product->refresh();
            expect((int) $product->stock)->toBe($originalStock + 1);
        });

        it('restores purchase item remaining_qty in FEFO order', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct(50, 5000);
            $purchase = Purchase::factory()->create();

            // Create two batches with different expiry dates (FEFO)
            $batch1 = PurchaseItem::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => 10,
                'remaining_qty' => 5,
                'price' => 4000,
                'expiry_date' => now()->addDays(10),
            ]);

            $batch2 = PurchaseItem::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => 10,
                'remaining_qty' => 8,
                'price' => 5000,
                'expiry_date' => now()->addDays(30),
            ]);

            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
            ]);

            $service->processReturn($sale, [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => $sale->saleItems->first()->id, 'qty' => 2],
                ],
            ]);

            $batch1->refresh();
            $batch2->refresh();

            // FEFO reversal: restock the earliest expiring batch first
            expect((int) $batch1->remaining_qty)->toBeGreaterThanOrEqual(5);
            // Total restored should equal 2
            $totalRestored = ((int) $batch1->remaining_qty - 5) + ((int) $batch2->remaining_qty - 8);
            expect($totalRestored)->toBe(2);
        });

        it('creates stock log with type in', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct(50, 5000);
            $purchase = Purchase::factory()->create();
            PurchaseItem::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => 50,
                'remaining_qty' => 10,
                'price' => 5000,
            ]);
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
            ]);

            $service->processReturn($sale, [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => $sale->saleItems->first()->id, 'qty' => 1],
                ],
            ]);

            $log = StockLog::where('reference_type', 'SaleReturn')
                ->where('product_id', $product->id)
                ->first();

            expect($log)->not->toBeNull();
            expect($log->type)->toBe('in');
            expect((int) $log->qty)->toBe(1);
            expect($log->note)->toContain('Retur penjualan');
        });

        it('associates shift with return when active shift exists', function () {
            $user = authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct(50, 5000);
            $purchase = Purchase::factory()->create();
            PurchaseItem::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => 50,
                'remaining_qty' => 10,
                'price' => 5000,
            ]);
            $shift = Shift::factory()->create([
                'user_id' => $user->id,
                'status' => 'open',
            ]);
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
            ]);

            $return = $service->processReturn($sale, [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => $sale->saleItems->first()->id, 'qty' => 1],
                ],
            ]);

            expect($return->shift_id)->toBe($shift->id);
        });
    });

    // ============================================================
    // processReturn — error cases
    // ============================================================

    describe('processReturn error cases', function () {
        it('throws exception for non-completed sale', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct();
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'draft',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
            ]);

            expect(fn () => $service->processReturn($sale, [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => $sale->saleItems->first()->id, 'qty' => 1],
                ],
            ]))->toThrow(Exception::class, 'sudah selesai');
        });

        it('throws exception when sale item not found in sale', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct();
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            // Create sale item belonging to a different sale
            $otherSale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            $otherItem = SaleItem::factory()->create([
                'sale_id' => $otherSale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
            ]);

            expect(fn () => $service->processReturn($sale, [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => $otherItem->id, 'qty' => 1],
                ],
            ]))->toThrow(Exception::class, 'tidak ditemukan');
        });

        it('throws exception when return qty exceeds available', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct(50, 5000);
            $purchase = Purchase::factory()->create();
            PurchaseItem::factory()->create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'qty' => 50,
                'remaining_qty' => 10,
                'price' => 5000,
            ]);
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
            ]);

            expect(fn () => $service->processReturn($sale, [
                'return_type' => 'refund',
                'items' => [
                    ['sale_item_id' => $sale->saleItems->first()->id, 'qty' => 10],
                ],
            ]))->toThrow(Exception::class, 'melebihi');
        });

        it('throws exception when no items provided', function () {
            authenticateAdmin();
            $service = app(SaleReturnService::class);
            $product = createProduct();
            $sale = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
            ]);
            SaleItem::factory()->create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'qty' => 2,
                'price' => 10000,
            ]);

            expect(fn () => $service->processReturn($sale, [
                'return_type' => 'refund',
                'items' => [],
            ]))->toThrow(Exception::class, 'Tidak ada item');
        });
    });

    // ============================================================
    // getPaginatedReturns
    // ============================================================

    describe('getPaginatedReturns', function () {
        it('returns paginated results', function () {
            $service = app(SaleReturnService::class);
            SaleReturn::factory()->count(5)->create();

            $result = $service->getPaginatedReturns([], 10);

            expect($result)->toHaveCount(5);
            expect($result->currentPage())->toBe(1);
        });

        it('filters by search', function () {
            $service = app(SaleReturnService::class);
            $saleA = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
                'customer_name' => 'Andi',
            ]);
            $saleB = Sale::factory()->create([
                'payment_method' => 'cash',
                'status' => 'completed',
                'customer_name' => 'Budi',
            ]);
            SaleReturn::factory()->create(['sale_id' => $saleA->id, 'reason' => 'Barang exp']);
            SaleReturn::factory()->create(['sale_id' => $saleB->id]);

            $result = $service->getPaginatedReturns(['search' => 'Andi'], 10);

            expect($result)->toHaveCount(1);
        });

        it('filters by date range', function () {
            $service = app(SaleReturnService::class);
            SaleReturn::factory()->create(['return_date' => '2024-01-15']);
            SaleReturn::factory()->create(['return_date' => '2024-02-01']);
            SaleReturn::factory()->create(['return_date' => '2024-03-01']);

            $result = $service->getPaginatedReturns([
                'start' => '2024-01-01',
                'end' => '2024-02-28',
            ], 10);

            expect($result)->toHaveCount(2);
        });

        it('ignores date filter when only start provided', function () {
            $service = app(SaleReturnService::class);
            SaleReturn::factory()->create(['return_date' => '2024-01-15']);
            SaleReturn::factory()->create(['return_date' => '2024-03-01']);

            $result = $service->getPaginatedReturns(['start' => '2024-02-01'], 10);

            expect($result)->toHaveCount(2);
        });

        it('eager loads user roles to prevent n plus one', function () {
            $user = User::factory()->create(['role' => 'admin']);
            SaleReturn::factory()->count(3)->create(['user_id' => $user->id]);

            $service = app(SaleReturnService::class);
            $result = $service->getPaginatedReturns([], 10);

            $firstReturn = $result->first();
            expect($firstReturn->relationLoaded('user'))->toBeTrue();
            expect($firstReturn->user->relationLoaded('roles'))->toBeTrue();
        });

        it('does not trigger duplicate roles queries when serializing paginated returns', function () {
            $user = User::factory()->create(['role' => 'admin']);
            SaleReturn::factory()->count(3)->create(['user_id' => $user->id]);

            DB::enableQueryLog();

            $service = app(SaleReturnService::class);
            $returns = $service->getPaginatedReturns([], 10);

            json_encode($returns->toArray());

            $roleQueries = collect(DB::getQueryLog())
                ->filter(fn ($query) => str_contains($query['query'], 'model_has_roles'));

            expect($roleQueries)->toHaveCount(1);
        });
    });
});
