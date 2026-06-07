<?php

namespace App\Services\Subscription;

use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

class PlanLimitService
{
    /**
     * Determine if the company can add more products based on their plan limits.
     */
    public function canAddProduct(?Company $company = null): bool
    {
        $company ??= Auth::user()?->company;
        $plan = $company?->activeSubscription()?->plan;
        if (! $plan || $plan->max_products === null) {
            return true;
        }

        return Product::count() < $plan->max_products;
    }

    /**
     * Determine if the company can add more users based on their plan limits.
     */
    public function canAddUser(?Company $company = null): bool
    {
        $company ??= Auth::user()?->company;
        $plan = $company?->activeSubscription()?->plan;
        if (! $plan || $plan->max_users === null) {
            return true;
        }

        return User::where('company_id', $company->id)->count() < $plan->max_users;
    }

    /**
     * Determine if the company can add more warehouses based on their plan limits.
     */
    public function canAddWarehouse(?Company $company = null): bool
    {
        $company ??= Auth::user()?->company;
        $plan = $company?->activeSubscription()?->plan;
        if (! $plan || $plan->max_warehouses === null) {
            return true;
        }

        return Warehouse::count() < $plan->max_warehouses;
    }
}
