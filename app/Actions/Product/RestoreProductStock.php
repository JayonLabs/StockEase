<?php

namespace App\Actions\Product;

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
     * This function increments the stock of each product in the given return items
     * by the quantity returned. It also reverses FEFO by adding back to the
     * purchase_items.remaining_qty in the same order they were deducted.
     */
    public function execute(Collection $returnItems): void
    {
        $productIds = $returnItems->pluck('product_id')->toArray();
        $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

        foreach ($returnItems as $item) {
            /** @var Product $product */
            $product = $products[$item->product_id];

            // FEFO Reversal: Add back to purchase items
            // We add to purchase items in reverse of expiry order (oldest last so it's
            // available again for future sales), same order as the original deduction
            $qtyToRestore = $item->qty;
            $purchaseItems = PurchaseItem::where('product_id', $product->id)
                ->where('remaining_qty', '<', DB::raw('qty'))
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

            $product->increment('stock', $item->qty);
            (new UpdateProductExpiryDate)->execute($product);

            StockLog::create([
                'product_id' => $product->id,
                'qty' => $item->qty,
                'type' => 'in',
                'reference_type' => 'SaleReturn',
                'reference_id' => $item->sale_return_id ?? $item->id,
                'note' => 'Retur penjualan produk '.$product->name,
            ]);
        }
    }
}
