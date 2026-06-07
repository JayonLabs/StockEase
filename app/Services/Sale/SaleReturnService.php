<?php

namespace App\Services\Sale;

use App\Actions\Product\RestoreProductStock;
use App\Enums\SaleReturnStatus;
use App\Enums\SaleReturnType;
use App\Enums\SaleStatus;
use App\Enums\ShiftStatus;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleReturnService
{
    /**
     * Create a new service instance.
     */
    public function __construct(
        private readonly RestoreProductStock $restoreProductStock,
    ) {}

    /**
     * Get paginated sale returns with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedReturns(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $startDate = $filters['start'] ?? null;
        $endDate = $filters['end'] ?? null;

        return SaleReturn::with([
            'user.roles',
            'sale',
            'saleReturnItems',
            'saleReturnItems.product',
        ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reason', 'like', "%{$search}%")
                        ->orWhereHas('sale', function ($q) use ($search) {
                            $q->where('customer_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('return_date', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get sale details for the return form.
     */
    public function getSaleForReturn(Sale $sale): Sale
    {
        return $sale->load([
            'user.roles',
            'saleItems',
            'saleItems.product',
            'paymentTransaction',
        ]);
    }

    /**
     * Process a sale return.
     *
     * @param  array<int, array{sale_item_id: int, qty: int}>  $items
     */
    public function processReturn(Sale $sale, array $data): SaleReturn
    {
        if ($sale->status !== SaleStatus::Completed->value) {
            throw new \Exception('Hanya transaksi yang sudah selesai yang dapat diretur.');
        }

        $sale->load('saleItems', 'saleItems.product');

        return DB::transaction(function () use ($sale, $data) {
            $saleItems = $sale->saleItems->keyBy('id');

            $returnItems = collect($data['items'])->map(function ($itemData) use ($saleItems) {
                $saleItem = $saleItems->get($itemData['sale_item_id']);

                if (! $saleItem) {
                    throw new \Exception('Item penjualan tidak ditemukan dalam transaksi ini.');
                }

                $alreadyReturned = SaleReturnItem::where('sale_item_id', $saleItem->id)
                    ->whereHas('saleReturn', function ($q) {
                        $q->where('status', SaleReturnStatus::Completed->value);
                    })
                    ->sum('qty');

                $availableQty = $saleItem->qty - $alreadyReturned;

                if ($itemData['qty'] > $availableQty) {
                    throw new \Exception("Jumlah retur untuk produk \"{$saleItem->product->name}\" melebihi jumlah yang tersedia ({$availableQty}).");
                }

                return [
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'qty' => $itemData['qty'],
                    'price' => $saleItem->price,
                    'total' => bcdiv(bcmul((string) $itemData['qty'], (string) $saleItem->price, 4), '1', 4),
                ];
            });

            if ($returnItems->isEmpty()) {
                throw new \Exception('Tidak ada item yang diretur.');
            }

            $totalRefund = $data['return_type'] === SaleReturnType::Refund->value
                ? $returnItems->sum('total')
                : 0;

            $activeShift = Shift::where('user_id', Auth::id())
                ->where('status', ShiftStatus::Open->value)
                ->latest()
                ->first();

            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'user_id' => Auth::id(),
                'shift_id' => $activeShift?->id,
                'return_type' => $data['return_type'],
                'total_refund' => $totalRefund,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'return_date' => now(),
                'status' => SaleReturnStatus::Completed->value,
            ]);

            $createdItems = collect();

            foreach ($returnItems as $item) {
                $returnItem = SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id' => $item['sale_item_id'],
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'total' => $item['total'],
                ]);
                $createdItems->push($returnItem);
            }

            $this->restoreProductStock->execute($createdItems);

            return $saleReturn->load('saleReturnItems', 'saleReturnItems.product');
        });
    }
}
