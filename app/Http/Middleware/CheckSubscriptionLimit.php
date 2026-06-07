<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Subscription\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionLimit
{
    /**
     * Handle an incoming request and check subscription limits for the given resource.
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        if (! $user->company_id) {
            return $next($request);
        }

        if ($user->hasRole(Role::SuperAdmin->value)) {
            return $next($request);
        }

        $company = $user->company;
        if (! $company) {
            return $next($request);
        }

        $plan = $company->currentPlan();
        if (! $plan) {
            app(SubscriptionService::class)->assignFreeSubscription($company);
            $plan = $company->fresh()->currentPlan();
        }

        $exceeded = match ($resource) {
            'product' => $plan->max_products !== null
                && Product::count() >= $plan->max_products,
            'user' => $plan->max_users !== null
                && User::where('company_id', $company->id)->count() >= $plan->max_users,
            'warehouse' => $plan->max_warehouses !== null
                && Warehouse::count() >= $plan->max_warehouses,
            default => false,
        };

        if ($exceeded) {
            $message = match ($resource) {
                'product' => 'Batas maksimal produk untuk plan '.$plan->name.' telah tercapai. Upgrade plan Anda.',
                'user' => 'Batas maksimal user untuk plan '.$plan->name.' telah tercapai. Upgrade plan Anda.',
                'warehouse' => 'Batas maksimal gudang untuk plan '.$plan->name.' telah tercapai. Upgrade plan Anda.',
                default => 'Limit plan tercapai.',
            };

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }
}
