<?php

namespace App\Services\Platform\Owner;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class PlanService
{
    /**
     * Get all plans with subscription counts, ordered by sort_order.
     *
     * Results are cached for 5–15 minutes using Cache::flexible.
     *
     * @return Collection<int, Plan>
     */
    public function getAll(): Collection
    {
        return Cache::flexible('platform_plans_all', [300, 900], function () {
            return Plan::withCount('subscriptions')
                ->orderBy('sort_order')
                ->get();
        });
    }
}
