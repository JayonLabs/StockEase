<?php

namespace App\Services\Stock;

use App\Models\StockLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class StockLogService
{
    /**
     * Get paginated stock logs with filters.
     */
    public function getPaginatedStockLogs(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return StockLog::query()
            ->with('product')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('product', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%");
                    })
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('reference_type', 'like', "%{$search}%")
                        ->orWhere(function ($q) use ($search) {
                            if (is_numeric($search)) {
                                $q->where('reference_id', $search);
                            }
                        })
                        ->orWhere('note', 'like', "%{$search}%");
                });
            })
            ->when(($filters['start_date'] ?? null) && ($filters['end_date'] ?? null), function ($query) use ($filters) {
                $query->whereBetween('created_at', [
                    Carbon::parse($filters['start_date'])->startOfDay(),
                    Carbon::parse($filters['end_date'])->endOfDay(),
                ]);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
