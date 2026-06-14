<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\PlatformDailySnapshot;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;

class TakePlatformSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:snapshot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Take a daily snapshot of platform-wide stats';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = now()->startOfDay();

        $existing = PlatformDailySnapshot::whereDate('snapshot_date', $today)->first();

        if ($existing) {
            $this->info('Snapshot already exists for today.');

            return self::SUCCESS;
        }

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

        $breakdown = Subscription::query()
            ->whereIn('status', ['active', 'trialing'])
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

        PlatformDailySnapshot::create([
            'snapshot_date' => $today,
            'total_companies' => $totalCompanies,
            'active_companies' => $activeCompanies,
            'total_users' => $totalUsers,
            'active_subscriptions' => $activeSubscriptions,
            'mrr' => $mrr,
            'subscription_breakdown' => $breakdown,
        ]);

        $this->info('Platform daily snapshot created successfully.');

        return self::SUCCESS;
    }
}
