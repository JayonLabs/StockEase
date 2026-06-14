<?php

namespace App\Services\Platform\Analytics;

use App\Models\SubscriptionInvoice;

class RevenueAnalytics
{
    /**
     * Calculate total revenue from paid invoices across all tenants.
     */
    public function totalRevenue(): float
    {
        return (float) SubscriptionInvoice::query()
            ->where('status', 'paid')
            ->sum('amount');
    }

    /**
     * Get monthly revenue totals for the last N months.
     *
     * @return list<array{month: string, total: float}>
     */
    public function revenueByMonth(int $months = 3): array
    {
        $revenue = SubscriptionInvoice::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', now()->subMonths($months))
            ->selectRaw('DATE_FORMAT(paid_at, \'%Y-%m\') as month, sum(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        return $revenue;
    }
}
