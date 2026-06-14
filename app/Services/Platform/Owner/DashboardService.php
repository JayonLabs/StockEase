<?php

namespace App\Services\Platform\Owner;

use App\Models\Company;
use App\Models\PlatformDailySnapshot;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    /**
     * Get the overview counts for the platform dashboard.
     *
     * Includes total companies, active companies, total users,
     * active subscriptions, and monthly recurring revenue (MRR).
     * Cached using Cache::flexible for 5–15 minutes.
     *
     * @return array{total_companies: int, active_companies: int, total_users: int, active_subscriptions: int, mrr: float}
     */
    public function getOverview(): array
    {
        return Cache::flexible('platform_dashboard_overview', [300, 900], function () {
            $totalCompanies = Company::query()->count();
            $activeCompanies = Company::query()->where('is_active', true)->count();
            $totalUsers = User::query()->count();

            $activeSubscriptions = Subscription::query()
                ->whereIn('status', ['active', 'trialing'])
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->count();

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

            return [
                'total_companies' => $totalCompanies,
                'active_companies' => $activeCompanies,
                'total_users' => $totalUsers,
                'active_subscriptions' => $activeSubscriptions,
                'mrr' => (float) $mrr,
            ];
        });
    }

    /**
     * Get the subscription breakdown by plan for the donut chart.
     *
     * @return list<array{plan: string, slug: string, count: int}>
     */
    public function getSubscriptionBreakdown(): array
    {
        return Cache::remember('platform_dashboard_subscriptions', 900, function () {
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
        });
    }

    /**
     * Get the most recently registered companies.
     *
     * @return list<array{id: int, name: string, slug: string, created_at: string}>
     */
    public function getRecentRegistrations(int $limit = 10): array
    {
        return Company::query()
            ->latest()
            ->take($limit)
            ->get(['id', 'name', 'slug', 'created_at'])
            ->toArray();
    }

    /**
     * Get active companies with user and subscription counts.
     *
     * @return list<array>
     */
    public function getActiveCompanies(int $limit = 10): array
    {
        return Company::query()
            ->where('is_active', true)
            ->withCount(['users', 'subscription'])
            ->latest()
            ->take($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get the growth trend data from daily snapshots.
     *
     * @return list<array{snapshot_date: string, total_companies: int, active_companies: int, total_users: int, mrr: float}>
     */
    public function getGrowthTrend(int $days = 30): array
    {
        return PlatformDailySnapshot::where('snapshot_date', '>=', now()->subDays($days))
            ->orderBy('snapshot_date')
            ->get(['snapshot_date', 'total_companies', 'active_companies', 'total_users', 'mrr'])
            ->toArray();
    }
}
