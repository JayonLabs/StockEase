<?php

namespace App\Services\Platform\Owner;

use App\Models\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubscriptionService
{
    /**
     * Get a paginated, filterable list of all subscriptions with company and plan relations.
     *
     * Optionally filter by subscription status (active, trialing, expired, cancelled).
     *
     * @param  string|null  $status  Filter by subscription status.
     * @param  int  $perPage  Number of subscriptions per page.
     * @return LengthAwarePaginator<Subscription>
     */
    public function getAll(?string $status = null, int $perPage = 25): LengthAwarePaginator
    {
        return Subscription::with(['company', 'plan'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get a single subscription by ID with company owner, plan, and invoices.
     *
     * @param  int  $id  The subscription ID.
     */
    public function findById(int $id): ?Subscription
    {
        return Subscription::with(['company.owner', 'plan', 'invoices'])
            ->find($id);
    }
}
