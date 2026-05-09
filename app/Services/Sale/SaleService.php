<?php

namespace App\Services\Sale;

use App\Enums\PaymentMethod;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SaleService
{
    /**
     * Get paginated sales with search and date filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedSales(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $startDate = $filters['start'] ?? null;
        $endDate = $filters['end'] ?? null;

        return Sale::with('user', 'saleItems', 'saleItems.product', 'paymentTransaction')
            ->where('payment_method', '!=', PaymentMethod::Pending->value)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('customer_name', 'like', "%{$search}%")
                        ->orWhereHas('saleItems', function ($q) use ($search) {
                            $q->whereHas('product', function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%")
                                    ->orWhere('barcode', 'like', "%{$search}%");
                            });
                        })
                        ->orWhereHas('paymentTransaction', function ($q) use ($search) {
                            $q->where('payment_method', 'like', "%{$search}%")
                                ->orWhere('status', 'like', "%{$search}%")
                                ->orWhere('external_id', 'like', "%{$search}%");
                        })
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Get sale details with loaded relationships.
     */
    public function getSaleDetails(Sale $sale): Sale
    {
        return $sale->load('user', 'saleItems', 'saleItems.product', 'paymentTransaction');
    }
}
