<?php

namespace App\Services\Warehouse;

use App\Enums\StockLogType;
use App\Models\Product;
use App\Models\StockLog;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    /**
     * Get paginated stock transfers with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedTransfers(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return StockTransfer::with(['fromWarehouse', 'toWarehouse', 'product', 'user.roles'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('product', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    })->orWhere('note', 'like', "%{$search}%");
                });
            })
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where(function ($q) use ($warehouseId) {
                    $q->where('from_warehouse_id', $warehouseId)
                        ->orWhere('to_warehouse_id', $warehouseId);
                });
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Store a new stock transfer.
     *
     * @param  array{from_warehouse_id: int, to_warehouse_id: int, product_id: int, qty: int, note: string|null, date: string}  $data
     */
    public function storeTransfer(array $data): StockTransfer
    {
        return DB::transaction(function () use ($data) {
            /** @var Product $product */
            $product = Product::findOrFail($data['product_id']);

            $fromWarehouse = Warehouse::findOrFail($data['from_warehouse_id']);
            $toWarehouse = Warehouse::findOrFail($data['to_warehouse_id']);

            $transfer = StockTransfer::create([
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'product_id' => $data['product_id'],
                'user_id' => Auth::id(),
                'qty' => $data['qty'],
                'note' => $data['note'] ?? null,
                'status' => 'completed',
                'date' => $data['date'],
            ]);

            $fromPivot = $fromWarehouse->products()->where('product_id', $product->id)->first();
            $currentFromStock = $fromPivot ? $fromPivot->pivot->stock : 0;

            $fromWarehouse->products()->syncWithoutDetaching([
                $product->id => [
                    'stock' => max(0, $currentFromStock - $data['qty']),
                ],
            ]);

            $toPivot = $toWarehouse->products()->where('product_id', $product->id)->first();
            $currentToStock = $toPivot ? $toPivot->pivot->stock : 0;

            $toWarehouse->products()->syncWithoutDetaching([
                $product->id => [
                    'stock' => $currentToStock + $data['qty'],
                ],
            ]);

            $noteMessage = "Pindah stok dari {$fromWarehouse->name} ke {$toWarehouse->name}";
            if (! empty($data['note'])) {
                $noteMessage .= ": {$data['note']}";
            }

            StockLog::create([
                'product_id' => $product->id,
                'qty' => $data['qty'],
                'type' => StockLogType::Transfer->value,
                'reference_type' => 'StockTransfer',
                'reference_id' => $transfer->id,
                'note' => $noteMessage,
            ]);

            return $transfer;
        });
    }
}
