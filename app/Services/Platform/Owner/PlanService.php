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
     * Not cached — this page is accessed only by platform_owner and caching
     * Eloquent model instances is fragile due to PHP deserialization errors.
     *
     * @return Collection<int, Plan>
     */
    public function getAll(): Collection
    {
        return Plan::withCount('subscriptions')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Create a new plan and invalidate the pricing page cache.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Plan
    {
        $plan = Plan::create($data);
        Cache::forget('plans_pricing');

        return $plan;
    }

    /**
     * Update an existing plan and invalidate the pricing page cache.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data): bool
    {
        $result = $plan->update($data);
        Cache::forget('plans_pricing');

        return $result;
    }

    /**
     * Soft-delete a plan and invalidate the pricing page cache.
     *
     * Returns false (without deleting) if the plan still has active or trialing
     * subscribers, to prevent breaking existing subscriptions.
     */
    public function delete(Plan $plan): bool
    {
        if ($plan->subscriptions()->whereIn('status', ['active', 'trialing'])->exists()) {
            return false;
        }

        Cache::forget('plans_pricing');

        return (bool) $plan->delete();
    }
}
