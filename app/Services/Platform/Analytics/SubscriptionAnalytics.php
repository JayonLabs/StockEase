<?php

namespace App\Services\Platform\Analytics;

use App\Models\Subscription;

class SubscriptionAnalytics
{
    /**
     * Count total active subscriptions across all tenants.
     *
     * Includes both active and trialing subscriptions
     * that have not passed their end date.
     */
    public function totalActiveSubscriptions(): int
    {
        return Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->count();
    }

    /**
     * Get subscription count broken down by plan.
     *
     * @return list<array{plan: string, slug: string, count: int}>
     */
    public function breakdownByPlan(): array
    {
        $breakdown = Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->selectRaw('plan_id, count(*) as count')
            ->groupBy('plan_id')
            ->with('plan:id,name,slug')
            ->get()
            ->map(fn ($item) => [
                'plan' => $item->plan?->name ?? 'Unknown',
                'slug' => $item->plan?->slug ?? 'unknown',
                'count' => (int) $item->count,
            ])
            ->values()
            ->all();

        return $breakdown;
    }

    /**
     * Calculate Monthly Recurring Revenue across all active subscriptions.
     *
     * Annual subscriptions are converted to monthly (price_annual / 12).
     * Both active and trialing subscriptions are included.
     */
    public function calculateMrr(): float
    {
        $mrr = Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->get()
            ->sum(function ($sub) {
                $price = $sub->billing_cycle === 'annual'
                    ? ($sub->plan->price_annual / 12)
                    : $sub->plan->price_monthly;

                return (float) $price;
            });

        return $mrr;
    }
}
