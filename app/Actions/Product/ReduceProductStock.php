<?php

namespace App\Actions\Product;

use App\Actions\NotifyStockAlert;
use App\Actions\Sale\RecalculateSaleTotal;
use App\Enums\StockLogType;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\StockLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReduceProductStock
{
    /**
     * Reduce product stock from sale items.
     *
     * When a warehouse ID is provided, stock is reduced from that warehouse's
     * inventory (warehouse_product pivot) using FEFO filtered by warehouse.
     * The global products.stock is then synced from all warehouses.
     *
     * When no warehouse ID is provided, stock is reduced from the global
     * products.stock column directly (backward compatible).
     */
    public function execute(Collection $saleItems, ?int $warehouseId = null): void
    {
        $productIds = $saleItems->pluck('product_id')->toArray();
        $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

        $warehouseStocks = $warehouseId !== null
            ? DB::table('warehouse_product')
                ->where('warehouse_id', $warehouseId)
                ->whereIn('product_id', $productIds)
                ->pluck('stock', 'product_id')
            : collect();

        foreach ($saleItems as $item) {
            /** @var Product $product */
            $product = $products[$item->product_id];

            if ($warehouseId !== null) {
                $warehouseStock = (int) ($warehouseStocks[$product->id] ?? 0);

                if ($warehouseStock < $item->qty) {
                    throw new \Exception("Stok produk {$product->name} tidak cukup di gudang.");
                }
            } elseif ($product->stock < $item->qty) {
                throw new \Exception("Stok produk {$product->name} tidak cukup.");
            }

            // FEFO Logic: Deduct stock from the earliest expiring purchase items
            $qtyToReduce = $item->qty;
            $totalItemCost = 0;

            $purchaseItemsQuery = PurchaseItem::where('product_id', $product->id)
                ->where('remaining_qty', '>', 0);

            if ($warehouseId !== null) {
                $purchaseItemsQuery->where('warehouse_id', $warehouseId);
            }

            $purchaseItems = $purchaseItemsQuery
                ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
                ->lockForUpdate()
                ->get();

            foreach ($purchaseItems as $purchaseItem) {
                if ($qtyToReduce <= 0) {
                    break;
                }

                $reduce = min($purchaseItem->remaining_qty, $qtyToReduce);

                // Track cost
                $totalItemCost += $reduce * $purchaseItem->price;

                $purchaseItem->decrement('remaining_qty', $reduce);
                $qtyToReduce -= $reduce;
            }

            // Update SaleItem with calculated cost_price (weighted average)
            $item->update([
                'cost_price' => $item->qty > 0 ? $totalItemCost / $item->qty : 0,
            ]);

            if ($warehouseId !== null) {
                DB::table('warehouse_product')
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $product->id)
                    ->decrement('stock', $item->qty);

                $product->syncStockFromWarehouses();
            } else {
                $product->decrement('stock', $item->qty);
            }

            (new UpdateProductExpiryDate)->execute($product, $warehouseId);
            $product->refresh();

            if ($product->stock <= $product->alert_stock) {
                (new NotifyStockAlert)->execute($product);
            }

            StockLog::create([
                'product_id' => $product->id,
                'qty' => $item->qty,
                'type' => StockLogType::Out->value,
                'reference_type' => 'Sale',
                'reference_id' => $item->sale_id,
                'note' => 'Penjualan produk '.$product->name,
            ]);
        }

        // Update total_cost in Sale models
        $saleIds = $saleItems->pluck('sale_id')->unique();
        Sale::whereIn('id', $saleIds)->get()->each(function ($sale) {
            resolve(RecalculateSaleTotal::class)->execute($sale);
        });
    }
}
