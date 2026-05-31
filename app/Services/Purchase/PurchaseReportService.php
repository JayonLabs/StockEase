<?php

namespace App\Services\Purchase;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PurchaseReportService
{
    /**
     * Get filtered purchases based on provided criteria.
     */
    public function getFilteredPurchases(array $filters): Collection
    {
        return Purchase::with('supplier', 'user', 'purchaseItems', 'purchaseItems.product')
            ->when($filters['start_date'] ?? null, function ($query, $start) {
                return $query->whereDate('created_at', '>=', $start);
            })
            ->when($filters['end_date'] ?? null, function ($query, $end) {
                return $query->whereDate('created_at', '<=', $end);
            })
            ->when(($filters['supplier'] ?? null) && $filters['supplier'] !== 'semua-supplier', function ($query) use ($filters) {
                return $query->where('supplier_id', $filters['supplier']);
            })
            ->when(($filters['user'] ?? null) && $filters['user'] !== 'semua-user', function ($query) use ($filters) {
                return $query->where('user_id', $filters['user']);
            })
            ->get();
    }

    /**
     * Get data summary for the report index page.
     */
    public function getIndexReportData(Collection $purchases): array
    {
        if ($purchases->isEmpty()) {
            return [];
        }

        $sumTotalPurchase = $purchases->sum('total');
        $totalItemsPurchased = $purchases->flatMap->purchaseItems->sum('qty');
        $totalTransaction = $purchases->count();

        $purchaseTrends = $purchases->groupBy(function ($item) {
            return Carbon::parse($item->created_at)->translatedFormat('M');
        })->map(function ($item) {
            return $item->sum('total');
        });

        $topSupplier = $purchases->groupBy('supplier_id')->map(function ($items) {
            return [
                'supplier_name' => $items->first()->supplier->name ?? 'Unknown',
                'total_purchase' => $items->sum('total'),
                'transaction_count' => $items->count(),
            ];
        })->sortByDesc('total_purchase')->take(5)->values();

        return [
            'filters' => $purchases->toArray(),
            'sumTotalPurchase' => $sumTotalPurchase,
            'totalItemsPurchased' => $totalItemsPurchased,
            'totalTransaction' => $totalTransaction,
            'purchaseTrends' => [
                'labels' => $purchaseTrends->keys()->values(),
                'data' => $purchaseTrends->values(),
            ],
            'topSupplier' => $topSupplier,
        ];
    }

    /**
     * Get data prepared for PDF export.
     */
    public function getPdfReportData(Collection $purchases, array $filters): array
    {
        $sumTotalPurchase = $purchases->sum('total');
        $totalItemsPurchased = $purchases->flatMap->purchaseItems->sum('qty');
        $totalTransaction = $purchases->count();

        $purchaseProducts = $purchases->flatMap->purchaseItems
            ->groupBy('product_id')->map(function ($items) {
                return (object) [
                    'date' => $items->first()->purchase->created_at,
                    'product_name' => $items->first()->product->name ?? 'Unknown',
                    'product_price' => $items->first()->price,
                    'total_purchase' => $items->sum(function ($i) {
                        return $i->qty * $i->price;
                    }),
                    'qty' => $items->sum('qty'),
                ];
            })
            ->values();

        $userName = ($filters['user'] ?? 'semua-user') === 'semua-user'
            ? 'semua-user'
            : User::find($filters['user'])?->name ?? 'semua-user';

        $supplierName = ($filters['supplier'] ?? 'semua-supplier') === 'semua-supplier'
            ? 'semua-supplier'
            : Supplier::find($filters['supplier'])?->name ?? 'semua-supplier';

        return [
            'startDate' => Carbon::parse($filters['start_date'])->translatedFormat('d F Y'),
            'endDate' => Carbon::parse($filters['end_date'])->translatedFormat('d F Y'),
            'purchases' => $purchaseProducts,
            'sumTotalPurchase' => $sumTotalPurchase,
            'totalItemsPurchased' => $totalItemsPurchased,
            'totalTransaction' => $totalTransaction,
            'user' => $userName,
            'supplier' => $supplierName,
        ];
    }

    /**
     * Get summary data prepared for Excel export.
     */
    public function getExcelReportSummary(Collection $purchases): array
    {
        $sumTotalPurchase = $purchases->sum('total');
        $totalItemsPurchased = $purchases->flatMap->purchaseItems->sum('qty');
        $totalTransaction = $purchases->count();

        $suppliers = $purchases
            ->map(function ($item) {
                return (object) [
                    'id' => $item->supplier->id,
                    'name' => $item->supplier->name ?? 'Unknown',
                    'total' => $item->total,
                    'qty' => $item->purchaseItems->sum('qty'),
                ];
            })
            ->groupBy('id')
            ->map(function ($items) {
                return (object) [
                    'name' => $items->first()->name,
                    'total' => $items->sum('total'),
                    'qty' => $items->sum('qty'),
                ];
            })
            ->values();

        return [
            'sumTotalPurchase' => $sumTotalPurchase,
            'totalItemsPurchased' => $totalItemsPurchased,
            'totalTransaction' => $totalTransaction,
            'suppliers' => $suppliers,
        ];
    }

    /**
     * Prepare filters for Excel export view.
     */
    public function prepareExcelFilters(array $filters): array
    {
        $user = ($filters['user'] ?? 'semua-user') === 'semua-user'
            ? 'Semua User'
            : User::find($filters['user'])?->name ?? 'Semua User';

        $supplier = ($filters['supplier'] ?? 'semua-supplier') === 'semua-supplier'
            ? 'Semua Supplier'
            : Supplier::find($filters['supplier'])?->name ?? 'Semua Supplier';

        return [
            'start_date' => $filters['start_date'],
            'end_date' => $filters['end_date'],
            'supplier' => $supplier,
            'user' => $user,
        ];
    }
}
