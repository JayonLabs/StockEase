<?php

namespace App\Services\General;

use App\Enums\Role;
use App\Enums\SaleStatus;
use App\Enums\StockLogType;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLog;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get dashboard data based on user or role string.
     */
    public function getDashboardData(User|string $role): array
    {
        if (is_string($role)) {
            return match ($role) {
                Role::SuperAdmin->value, Role::Admin->value => $this->adminData(),
                Role::Cashier->value => $this->cashierData(),
                Role::Warehouse->value => $this->warehouseData(),
                default => [],
            };
        }

        if ($role->hasRole([Role::SuperAdmin->value, Role::Admin->value])) {
            return $this->adminData();
        }

        if ($role->hasRole(Role::Cashier->value)) {
            return $this->cashierData();
        }

        if ($role->hasRole(Role::Warehouse->value)) {
            return $this->warehouseData();
        }

        return [];
    }

    /**
     * Get dashboard data for admin.
     */
    private function adminData(): array
    {
        $todaySales = (float) Sale::where('status', SaleStatus::Completed->value)
            ->whereDate('date', Carbon::today())
            ->sum('total');

        $monthSales = (float) Sale::where('status', SaleStatus::Completed->value)
            ->whereYear('date', Carbon::now()->year)
            ->whereMonth('date', Carbon::now()->month)
            ->sum('total');

        $activeProducts = Product::count();

        $monthPurchases = (float) Purchase::whereYear('date', Carbon::now()->year)
            ->whereMonth('date', Carbon::now()->month)
            ->sum('total');

        $lowStock = Product::whereColumn('stock', '<=', 'alert_stock')
            ->select('id', 'name', 'sku', 'stock')
            ->latest()
            ->take(5)
            ->get();

        return [
            'salesSummary' => [
                'today' => $todaySales,
                'month' => $monthSales,
                'activeProducts' => $activeProducts,
                'monthPurchases' => $monthPurchases,
            ],
            'lowStock' => $lowStock,
            'activities' => $this->getActivityHistory(),
            'weeklySalesChart' => $this->getWeeklySalesChart(),
            'priceUpdateChart' => $this->getPriceUpdateChartData(),
        ];
    }

    /**
     * Get dashboard data for cashier.
     */
    private function cashierData(): array
    {
        $totalTransactionPerWeek = Sale::where('status', SaleStatus::Completed->value)
            ->whereBetween('date', [
                Carbon::now()->startOfWeek()->toDateString(),
                Carbon::now()->endOfWeek()->toDateString(),
            ])->count();

        $todaysIncome = (float) Sale::where('status', SaleStatus::Completed->value)
            ->whereDate('date', Carbon::today())
            ->sum('total');

        $bestSellingProductItem = SaleItem::whereHas('sale', function ($q) {
            $q->whereDate('date', Carbon::today())
                ->where('status', SaleStatus::Completed->value);
        })
            ->select('product_id', DB::raw('SUM(qty) as total_qty'))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->with('product')
            ->first();

        $bestSellingProduct = $bestSellingProductItem ? $bestSellingProductItem->product->name : 'Tidak ada transaksi hari ini';

        $averagePerCustomer = Sale::where('status', SaleStatus::Completed->value)
            ->whereDate('date', Carbon::today())
            ->avg('total');
        $averagePerCustomer = $averagePerCustomer !== null ? (float) $averagePerCustomer : null;

        $recentTransaction = Sale::where('status', SaleStatus::Completed->value)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($sale) => [
                'customer' => $sale->customer_name ?? 'Umum',
                'total' => (float) $sale->total,
                'payment_method' => $sale->payment_method,
                'date' => $sale->created_at->format('d M Y'),
            ]);

        return [
            'cashierSalesSummary' => [
                'totalTransactionPerWeek' => $totalTransactionPerWeek,
                'todaysIncome' => $todaysIncome,
                'bestSellingProduct' => $bestSellingProduct,
                'averagePerCustomer' => $averagePerCustomer,
            ],
            'recentTransaction' => $recentTransaction,
            'weeklySalesChart' => $this->getWeeklySalesChart(),
        ];
    }

    /**
     * Get dashboard data for warehouse.
     */
    private function warehouseData(): array
    {
        $totalProduct = Product::count();

        $lowStockCount = Product::whereColumn('stock', '<=', 'alert_stock')->count();

        $newProductThisMonth = Product::whereMonth('created_at', Carbon::now()->month)->count();

        $activeSupplier = Supplier::count();

        $activityLogWarehouse = StockLog::with('product')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($log) => [
                'product_name' => $log->product->name,
                'type' => $log->type,
                'qty' => $log->qty,
                'date' => $log->created_at->format('d M Y'),
            ]);

        return [
            'warehouseSummary' => [
                'totalProduct' => $totalProduct,
                'lowStock' => $lowStockCount,
                'newProductThisMonth' => $newProductThisMonth,
                'activeSupplier' => $activeSupplier,
            ],
            'activityLogWarehouse' => $activityLogWarehouse,
            'warehouseChart' => $this->getWarehouseChart(),
        ];
    }

    /**
     * Get unified activity history.
     */
    public function getActivityHistory(): array
    {
        Carbon::setLocale('id');

        $latestSales = Sale::where('status', SaleStatus::Completed->value)
            ->select(['id', 'total', 'created_at'])
            ->with([
                'saleItems:id,sale_id,qty,product_id',
                'saleItems.product:id,name',
            ])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($sale) {
                $items = $sale->saleItems->map(fn ($item) => "{$item->qty} {$item->product?->name}")->join(', ');

                return [
                    'id' => 'sale_'.$sale->id,
                    'type' => 'sale',
                    'desc' => "Penjualan {$items} sebesar Rp ".number_format($sale->total, 0, ',', '.'),
                    'time' => $sale->created_at->diffForHumans(),
                    'created_at' => $sale->created_at,
                ];
            });

        $latestPurchases = Purchase::latest()
            ->select(['id', 'total', 'created_at'])
            ->with([
                'purchaseItems:id,purchase_id,qty,product_id',
                'purchaseItems.product:id,name',
            ])
            ->take(10)
            ->get()
            ->map(function ($purchase) {
                $items = $purchase->purchaseItems->map(fn ($item) => "{$item->qty} {$item->product?->name}")->join(', ');

                return [
                    'id' => 'purchase_'.$purchase->id,
                    'type' => 'purchase',
                    'desc' => "Pembelian menambahkan {$items} produk sebesar Rp ".number_format($purchase->total, 0, ',', '.'),
                    'time' => $purchase->created_at->diffForHumans(),
                    'created_at' => $purchase->created_at,
                ];
            });

        $latestStockLogs = StockLog::select(['id', 'type', 'qty', 'created_at', 'product_id'])
            ->with('product:id,name')
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($log) {
                $isIncreasing = $log->type === StockLogType::In->value || ($log->type === StockLogType::Adjust->value && $log->qty > 0);
                $action = $isIncreasing ? 'bertambah' : 'berkurang';
                $absQty = abs($log->qty);

                return [
                    'id' => 'stock_'.$log->id,
                    'type' => 'stock',
                    'desc' => "Stok {$log->product?->name} {$action} sebanyak {$absQty}",
                    'time' => $log->created_at->diffForHumans(),
                    'created_at' => $log->created_at,
                ];
            });

        $latestPriceUpdates = PriceHistory::select(['id', 'created_at', 'product_id', 'user_id'])
            ->with(['product:id,name', 'user:id,name'])
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($history) {
                return [
                    'id' => 'price_'.$history->id,
                    'type' => 'price',
                    'desc' => "Harga {$history->product?->name} diperbarui oleh {$history->user?->name}",
                    'time' => $history->created_at->diffForHumans(),
                    'created_at' => $history->created_at,
                ];
            });

        return collect()
            ->merge($latestSales)
            ->merge($latestPurchases)
            ->merge($latestStockLogs)
            ->merge($latestPriceUpdates)
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->map(fn ($a) => collect($a)->except('created_at'))
            ->toArray();
    }

    /**
     * Get weekly sales chart data.
     */
    public function getWeeklySalesChart(): array
    {
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        $weeklySales = Sale::select(
            'date',
            DB::raw('SUM(total) as total')
        )
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->pluck('total', 'date');

        $chartData = [];
        $chartCategories = [];

        Carbon::setLocale('id');

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $chartCategories[] = $date->isoFormat('ddd');
            $chartData[] = (float) ($weeklySales[$date->toDateString()] ?? 0);
        }

        return [
            'categories' => $chartCategories,
            'data' => $chartData,
        ];
    }

    /**
     * Get price update chart data (Volume of updates per day).
     */
    public function getPriceUpdateChartData(): array
    {
        Carbon::setLocale('id');
        $startDate = Carbon::now()->subDays(6)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $rawUpdates = PriceHistory::selectRaw('
            DATE(created_at) as date,
            COUNT(*) as count
        ')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $chartData = [];
        $chartCategories = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->toDateString();
            $chartCategories[] = $date->isoFormat('DD MMM');
            $chartData[] = $rawUpdates[$dateKey] ?? 0;
        }

        return [
            'categories' => $chartCategories,
            'data' => $chartData,
        ];
    }

    /**
     * Get warehouse specific chart data.
     */
    public function getWarehouseChart(): array
    {
        Carbon::setLocale('id');

        $startDate = Carbon::now()->subDays(6)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $rawMovement = StockLog::selectRaw('
            DATE(created_at) as date,
            SUM(CASE WHEN type = ? THEN qty ELSE 0 END) as masuk,
            SUM(CASE WHEN type = ? THEN qty ELSE 0 END) as keluar
        ', [StockLogType::In->value, StockLogType::Out->value])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $stockMovement = collect();
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $row = $rawMovement->get($dateKey);

            $stockMovement->push([
                'date' => $date->isoFormat('ddd'),
                'masuk' => $row ? (int) $row->masuk : 0,
                'keluar' => $row ? (int) $row->keluar : 0,
            ]);
        }

        $categoryDistribution = Product::selectRaw('
            categories.name as category_name,
            SUM(products.stock) as total_stock
        ')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->groupBy('categories.name')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category_name,
                'total' => (int) $row->total_stock,
            ]);

        return [
            'stockMovement' => $stockMovement,
            'categoryDistribution' => $categoryDistribution,
        ];
    }
}
