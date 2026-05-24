<?php

namespace App\Actions\Product;

use App\Models\Product;
use App\Models\PurchaseItem;

class UpdateProductExpiryDate
{
    /**
     * Update the expiry date based on the earliest expiring available stock.
     *
     * When a warehouse ID is provided, only purchase items from that warehouse
     * are considered when determining the earliest expiry date.
     */
    public function execute(Product $product, ?int $warehouseId = null): void
    {
        $query = PurchaseItem::where('product_id', $product->id)
            ->where('remaining_qty', '>', 0);

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        $earliestBatch = $query
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->first();

        $product->update([
            'expiry_date' => $earliestBatch ? $earliestBatch->expiry_date : null,
        ]);
    }
}
