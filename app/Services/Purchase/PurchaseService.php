<?php

namespace App\Services\Purchase;

use App\Actions\Product\UpdateProductExpiryDate;
use App\Enums\StockLogType;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockLog;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    /**
     * Get paginated purchases with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedPurchases(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $startDate = $filters['start'] ?? null;
        $endDate = $filters['end'] ?? null;

        return Purchase::with('supplier', 'user', 'purchaseItems', 'purchaseItems.product')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->orWhereHas('supplier', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('address', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    })->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('purchaseItems', function ($q) use ($search) {
                        $q->whereHas('product', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->orWhere('barcode', 'like', "%{$search}%");
                        });
                    });
                });
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            })
            ->orderBy('date', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Search suppliers for selection.
     */
    public function searchSuppliers(string $search)
    {
        return Supplier::where('name', 'like', "%{$search}%")
            ->select('id as value', 'name as label')
            ->get();
    }

    /**
     * Search products for selection.
     */
    public function searchProducts(string $search)
    {
        return Product::with('unit:id,name,short_name')
            ->where('name', 'like', "%{$search}%")
            ->select('id', 'name as label', 'purchase_price', 'selling_price', 'unit_id', 'stock')
            ->get();
    }

    /**
     * Store a new purchase and update stock.
     */
    public function storePurchase(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'user_id' => Auth::id(),
                'total' => 0,
                'date' => $data['date'],
            ]);

            $totalPurchase = 0;
            $products = Product::whereIn('id', collect($data['product_items'])->pluck('product_id'))
                ->get()
                ->keyBy('id');

            foreach ($data['product_items'] as $item) {
                $subtotal = $item['qty'] * $item['price'];
                $totalPurchase += $subtotal;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'remaining_qty' => $item['qty'],
                    'price' => $item['price'],
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);

                /** @var Product $product */
                $product = $products[$item['product_id']];
                $product->increment('stock', $item['qty'], [
                    'purchase_price' => $item['price'],
                    'selling_price' => $item['selling_price'],
                ]);
                resolve(UpdateProductExpiryDate::class)->execute($product);

                StockLog::create([
                    'product_id' => $product->id,
                    'qty' => $item['qty'],
                    'type' => StockLogType::In->value,
                    'reference_type' => 'Purchase',
                    'reference_id' => $purchase->id,
                    'note' => "Pembelian produk {$product->name}",
                ]);
            }

            $purchase->update(['total' => $totalPurchase]);

            return $purchase;
        });
    }

    /**
     * Update an existing purchase and adjust stock.
     */
    public function updatePurchase(Purchase $purchase, array $data): bool
    {
        return DB::transaction(function () use ($purchase, $data) {
            $purchase->update([
                'supplier_id' => $data['supplier_id'],
                'user_id' => Auth::id(),
                'date' => $data['date'],
            ]);

            $totalPurchase = 0;
            $productIds = collect($data['product_items'])->pluck('product_id');
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            // Handle deleted items
            $existingItems = PurchaseItem::where('purchase_id', $purchase->id)->get();
            foreach ($existingItems as $existingItem) {
                if (! $productIds->contains($existingItem->product_id)) {
                    /** @var Product|null $product */
                    $product = Product::find($existingItem->product_id);
                    if ($product) {
                        $product->decrement('stock', $existingItem->qty);
                    }
                    $existingItem->forceDelete();
                    if ($product) {
                        resolve(UpdateProductExpiryDate::class)->execute($product);
                    }
                }
            }

            // Handle added/updated items
            foreach ($data['product_items'] as $item) {
                if ($item['qty'] <= 0 || $item['price'] <= 0) {
                    throw new \Exception('Qty atau harga tidak boleh 0');
                }

                $subtotal = $item['qty'] * $item['price'];
                $totalPurchase += $subtotal;

                /** @var Product $product */
                $product = $products[$item['product_id']];
                $oldItem = PurchaseItem::where('purchase_id', $purchase->id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if ($oldItem) {
                    $diffQty = $item['qty'] - $oldItem->qty;
                    $oldItem->update([
                        'qty' => $item['qty'],
                        'remaining_qty' => max(0, $oldItem->remaining_qty + $diffQty),
                        'price' => $item['price'],
                        'expiry_date' => $item['expiry_date'] ?? null,
                    ]);
                    $product->increment('stock', $diffQty);
                } else {
                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $item['product_id'],
                        'qty' => $item['qty'],
                        'remaining_qty' => $item['qty'],
                        'price' => $item['price'],
                        'expiry_date' => $item['expiry_date'] ?? null,
                    ]);
                    $product->increment('stock', $item['qty']);
                    $diffQty = $item['qty'];
                }

                resolve(UpdateProductExpiryDate::class)->execute($product);

                // Update prices if changed
                if ($product->purchase_price != $item['price'] || $product->selling_price != $item['selling_price']) {
                    $product->update([
                        'purchase_price' => $item['price'],
                        'selling_price' => $item['selling_price'],
                    ]);
                }

                StockLog::create([
                    'product_id' => $product->id,
                    'qty' => abs($diffQty),
                    'type' => StockLogType::Adjust->value,
                    'reference_type' => 'Purchase',
                    'reference_id' => $purchase->id,
                    'note' => 'Perubahan pembelian produk '.$product->name,
                ]);
            }

            return $purchase->update(['total' => $totalPurchase]);
        });
    }

    /**
     * Delete a purchase and revert stock.
     */
    public function deletePurchase(Purchase $purchase): bool
    {
        return DB::transaction(function () use ($purchase) {
            $purchaseItems = PurchaseItem::where('purchase_id', $purchase->id)->get();
            $products = Product::whereIn('id', $purchaseItems->pluck('product_id'))->get();

            foreach ($purchaseItems as $purchaseItem) {
                /** @var Product|null $product */
                $product = $products->firstWhere('id', $purchaseItem->product_id);
                if ($product) {
                    $product->decrement('stock', $purchaseItem->qty);
                    StockLog::create([
                        'product_id' => $product->id,
                        'qty' => $purchaseItem->qty,
                        'type' => StockLogType::Out->value,
                        'reference_type' => 'Purchase',
                        'reference_id' => $purchase->id,
                        'note' => "Penghapusan pembelian and pengurangan stok produk {$product->name}",
                    ]);
                }
                $purchaseItem->delete();
                if ($product) {
                    resolve(UpdateProductExpiryDate::class)->execute($product);
                }
            }

            return $purchase->delete();
        });
    }
}
