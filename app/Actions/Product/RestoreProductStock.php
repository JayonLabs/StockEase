<?php

namespace App\Actions\Product;

use App\Enums\StockLogType;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\StockLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RestoreProductStock
{
    /**
     * Restore product stock from return items.
     *
     * When a warehouse ID is provided, stock is restored to that warehouse's
     * inventory (warehouse_product pivot) and the global products.stock is
     * then synced from all warehouses.
     *
     * When no warehouse ID is provided, stock is restored to the global
     * products.stock column directly (backward compatible).
     */
    public function execute(Collection $returnItems, ?int $warehouseId = null): void
    {
        $productIds = $returnItems->pluck('product_id')->toArray();
        $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

        foreach ($returnItems as $item) {
            /** @var Product $product */
            $product = $products[$item->product_id];

            // FEFO Reversal: Add back to purchase items
            $qtyToRestore = $item->qty;

            $purchaseItemsQuery = PurchaseItem::where('product_id', $product->id)
                ->where('remaining_qty', '<', DB::raw('qty'));

            if ($warehouseId !== null) {
                $purchaseItemsQuery->where('warehouse_id', $warehouseId);
            }

            $purchaseItems = $purchaseItemsQuery
                ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
                ->lockForUpdate()
                ->get();

            foreach ($purchaseItems as $purchaseItem) {
                if ($qtyToRestore <= 0) {
                    break;
                }

                $availableSpace = $purchaseItem->qty - $purchaseItem->remaining_qty;
                $restore = min($availableSpace, $qtyToRestore);

                $purchaseItem->increment('remaining_qty', $restore);
                $qtyToRestore -= $restore;
            }

            // If we couldn't restore all qty (shouldn't happen in normal flow),
            // increment the last purchase item or create a virtual restock
            if ($qtyToRestore > 0 && $purchaseItems->isNotEmpty()) {
                $lastItem = $purchaseItems->last();
                $lastItem->increment('remaining_qty', $qtyToRestore);
            }

            if ($warehouseId !== null) {
                DB::table('warehouse_product')
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $product->id)
                    ->increment('stock', $item->qty);

                $product->syncStockFromWarehouses();
            } else {
                $product->increment('stock', $item->qty);
            }

            (new UpdateProductExpiryDate)->execute($product, $warehouseId);

            StockLog::create([
                'product_id' => $product->id,
                'qty' => $item->qty,
                'type' => StockLogType::In->value,
                'reference_type' => 'SaleReturn',
                'reference_id' => $item->sale_return_id ?? $item->id,
                'note' => 'Retur penjualan produk '.$product->name,
            ]);
        }
    }
}
