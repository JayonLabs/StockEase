<?php

namespace App\Services\Sale;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SaleReportService
{
    /**
     * Get filtered sales based on provided criteria.
     */
    public function getFilteredSales(array $filters): Collection
    {
        return Sale::with('user', 'saleItems', 'saleItems.product', 'paymentTransaction')
            ->where('status', '!=', SaleStatus::Draft->value)
            ->when($filters['start'] ?? null, function ($query, $start) {
                return $query->whereDate('date', '>=', $start);
            })
            ->when($filters['end'] ?? null, function ($query, $end) {
                return $query->whereDate('date', '<=', $end);
            })
            ->when(($filters['cashier'] ?? null) && $filters['cashier'] !== 'semua-cashier', function ($query) use ($filters) {
                return $query->where('user_id', $filters['cashier']);
            })
            ->when(($filters['payment'] ?? null) && $filters['payment'] !== 'semua-metode', function ($query) use ($filters) {
                return $query->where('payment_method', $filters['payment']);
            })
            ->when(($filters['warehouse'] ?? null) && $filters['warehouse'] !== 'semua-gudang', function ($query) use ($filters) {
                return $query->where('warehouse_id', $filters['warehouse']);
            })
            ->get();
    }

    /**
     * Get data summary for the report index page.
     */
    public function getIndexReportData(Collection $sales): array
    {
        if ($sales->isEmpty()) {
            return [];
        }

        $sumTotalSale = $sales->sum('total');
        $transactionCount = $sales->where('status', SaleStatus::Completed->value)->count();
        $countProductSale = $sales->flatMap->saleItems->sum('qty');

        $bestSellingProduct = $sales
            ->flatMap->saleItems
            ->groupBy('product_id')
            ->map(function ($items) {
                return [
                    'product_id' => $items->first()->product_id,
                    'product_name' => $items->first()->product->name ?? 'Unknown',
                    'total_sold' => $items->sum('qty'),
                ];
            })
            ->sortByDesc('total_sold')
            ->first();

        Carbon::setLocale('id');

        $salesTrend = $sales
            ->groupBy(function ($sale) {
                return Carbon::parse($sale->created_at)->translatedFormat('M');
            })
            ->map(function ($sales) {
                return $sales->sum('total');
            });

        $productSalesShare = $sales
            ->flatMap->saleItems
            ->groupBy('product_id')
            ->map(function ($items) {
                return [
                    'product_name' => $items->first()->product->name ?? 'Unknown',
                    'total_sold' => $items->sum('qty'),
                ];
            })
            ->values();

        return [
            'sales' => $sales,
            'sumTotalSale' => $sumTotalSale,
            'transactionCount' => $transactionCount,
            'countProductSale' => $countProductSale,
            'bestSellingProduct' => $bestSellingProduct,
            'salesTrend' => [
                'labels' => $salesTrend->keys()->values(),
                'data' => $salesTrend->values(),
            ],
            'productSalesShare' => $productSalesShare,
        ];
    }

    /**
     * Get data prepared for PDF export.
     */
    public function getPdfReportData(Collection $sales, array $filters): array
    {
        $cashierUser = User::find($filters['cashier'] ?? null);

        $totalSale = $sales->sum('total');
        $transactionCount = $sales->where('status', SaleStatus::Completed->value)->count();
        $productSold = $sales->flatMap->saleItems->sum('qty');

        $bestSellingProduct = $sales
            ->flatMap->saleItems
            ->groupBy('product_id')
            ->map(function ($items) {
                return (object) [
                    'product_id' => $items->first()->product_id,
                    'product_name' => $items->first()->product->name ?? 'Unknown',
                    'total_sold' => $items->sum('qty'),
                ];
            })
            ->sortByDesc('total_sold')
            ->first();

        $saleProducts = $sales->flatMap->saleItems
            ->groupBy('product_id')
            ->map(function ($items) {
                $firstItem = $items->first();

                return (object) [
                    'date' => $firstItem->sale->created_at,
                    'product_name' => $firstItem->product->name ?? 'Unknown',
                    'quantity' => $items->sum('qty'),
                    'total' => $items->sum(function ($i) {
                        return $i->qty * $i->price;
                    }),
                ];
            })
            ->values();

        return [
            'start_date' => Carbon::parse($filters['start'])->translatedFormat('d F Y'),
            'end_date' => Carbon::parse($filters['end'])->translatedFormat('d F Y'),
            'cashier_name' => $cashierUser?->name ?? 'Semua Cashier',
            'payment' => $filters['payment'],
            'total_sales' => $totalSale,
            'transaction_count' => $transactionCount,
            'product_sold' => $productSold,
            'best_selling_product' => $bestSellingProduct,
            'sales' => $saleProducts,
        ];
    }

    /**
     * Get summary data prepared for Excel export.
     *
     * @param  array  $filters
     */
    public function getExcelReportSummary(Collection $sales): array
    {
        $bestProductId = $sales->flatMap->saleItems
            ->groupBy('product_id')
            ->map->sum('qty')
            ->sortDesc()
            ->keys()
            ->first();

        $bestProduct = '-';
        if ($bestProductId) {
            $bestProductItem = $sales->flatMap->saleItems->firstWhere('product_id', $bestProductId);
            $bestProduct = $bestProductItem?->product?->name ?? '-';
        }

        return [
            'total_sales' => number_format($sales->sum('total')),
            'transaction_count' => $sales->count(),
            'product_count' => $sales->flatMap->saleItems->sum('qty'),
            'best_product' => $bestProduct,
        ];
    }

    /**
     * Prepare filters for Excel export view.
     */
    public function prepareExcelFilters(array $filters): array
    {
        if (($filters['cashier'] ?? 'semua-cashier') !== 'semua-cashier') {
            $cashier = User::find($filters['cashier']);
            $cashierName = $cashier ? $cashier->name : 'Kasir Tidak Ditemukan';
        } else {
            $cashierName = 'Semua Cashier';
        }

        $filters['cashier'] = $cashierName;

        return $filters;
    }
}
