<?php

namespace App\Services\Stock;

use App\Actions\NotifyStockAlert;
use App\Actions\Product\UpdateProductExpiryDate;
use App\Enums\StockLogType;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\StockAdjustment;
use App\Models\StockLog;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function __construct(
        private readonly UpdateProductExpiryDate $updateProductExpiryDate,
        private readonly NotifyStockAlert $notifyStockAlert,
    ) {}

    /**
     * Get paginated stock adjustments with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedAdjustments(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return StockAdjustment::with(['product', 'user.roles', 'warehouse'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('product', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    })->orWhere('reason', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Store a new stock adjustment.
     *
     * @param  array{warehouse_id: int, product_id: int, new_stock: int, reason: string|null, date: string}  $data
     */
    public function storeAdjustment(array $data): StockAdjustment
    {
        return DB::transaction(function () use ($data) {
            /** @var Warehouse $warehouse */
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);

            /** @var Product $product */
            $product = Product::findOrFail($data['product_id']);

            $oldStock = $product->stockInWarehouse($warehouse->id);
            $newStock = (int) $data['new_stock'];
            $diff = $newStock - $oldStock;

            $adjustment = StockAdjustment::create([
                'user_id' => Auth::id(),
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'reason' => $data['reason'],
                'date' => $data['date'],
            ]);

            $warehouse->products()->syncWithoutDetaching([
                $product->id => ['stock' => max(0, $newStock)],
            ]);

            $product->syncStockFromWarehouses();

            if ($diff < 0) {
                $qtyToReduce = abs($diff);
                $purchaseItems = PurchaseItem::where('product_id', $product->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('remaining_qty', '>', 0)
                    ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
                    ->lockForUpdate()
                    ->get();

                foreach ($purchaseItems as $purchaseItem) {
                    if ($qtyToReduce <= 0) {
                        break;
                    }

                    $reduce = min($purchaseItem->remaining_qty, $qtyToReduce);
                    $purchaseItem->decrement('remaining_qty', $reduce);
                    $qtyToReduce -= $reduce;
                }
            }

            $product->refresh();

            $this->updateProductExpiryDate->execute($product);

            if ($product->stock <= $product->alert_stock) {
                $this->notifyStockAlert->execute($product);
            }

            StockLog::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'qty' => $diff,
                'type' => StockLogType::Adjust->value,
                'reference_type' => 'StockAdjustment',
                'reference_id' => $adjustment->id,
                'note' => "Penyesuaian stok (Stock Opname) di {$warehouse->name}: {$data['reason']}",
            ]);

            return $adjustment;
        });
    }
}
