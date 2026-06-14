<?php

namespace App\Services\Platform\Owner;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class CompanyService
{
    /**
     * Get a paginated list of all companies with user and subscription counts.
     *
     * Eager-loads the subscription plan relation.
     *
     * @param  int  $perPage  Number of companies per page.
     * @return LengthAwarePaginator<Company>
     */
    public function getAll(int $perPage = 25): LengthAwarePaginator
    {
        return Company::query()
            ->withCount(['users', 'subscription'])
            ->with('subscription.plan')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get a single company by ID with owner, users, subscription plan, and invoices.
     *
     * @param  int  $id  The company ID.
     */
    public function findById(int $id): ?Company
    {
        return Company::with(['owner', 'users', 'subscription.plan', 'subscription.invoices'])
            ->find($id);
    }

    /**
     * Get the total number of companies, cached for 5–15 minutes.
     *
     * Uses Cache::flexible for stale-while-revalidate behaviour.
     */
    public function getTotalCount(): int
    {
        return Cache::flexible('platform_company_count', [300, 900], function () {
            return Company::query()->count();
        });
    }
}
