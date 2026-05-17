<?php

namespace App\Services\Stock;

use App\Actions\NotifyStockAlert;
use App\Actions\Product\UpdateProductExpiryDate;
use App\Enums\StockLogType;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\StockAdjustment;
use App\Models\StockLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    /**
     * Get paginated stock adjustments with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedAdjustments(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return StockAdjustment::with(['product', 'user'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                })->orWhere('reason', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Store a new stock adjustment.
     *
     * @param  array{product_id: int, new_stock: int, reason: string|null, date: string}  $data
     */
    public function storeAdjustment(array $data): StockAdjustment
    {
        return DB::transaction(function () use ($data) {
            /** @var Product $product */
            $product = Product::findOrFail($data['product_id']);
            $oldStock = $product->stock;
            $diff = $data['new_stock'] - $oldStock;

            $adjustment = StockAdjustment::create([
                'user_id' => Auth::id(),
                'product_id' => $data['product_id'],
                'old_stock' => $oldStock,
                'new_stock' => $data['new_stock'],
                'reason' => $data['reason'],
                'date' => $data['date'],
            ]);

            $product->update(['stock' => $data['new_stock']]);

            if ($diff < 0) {
                // Stock decreased: Apply FEFO to remaining_qty
                $qtyToReduce = abs($diff);
                $purchaseItems = PurchaseItem::where('product_id', $product->id)
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

            resolve(UpdateProductExpiryDate::class)->execute($product);

            if ($product->stock <= $product->alert_stock) {
                resolve(NotifyStockAlert::class)->execute($product);
            }

            StockLog::create([
                'product_id' => $product->id,
                'qty' => abs($diff),
                'type' => StockLogType::Adjust->value,
                'reference_type' => 'StockAdjustment',
                'reference_id' => $adjustment->id,
                'note' => "Penyesuaian stok (Stock Opname): {$data['reason']}",
            ]);

            return $adjustment;
        });
    }
}
