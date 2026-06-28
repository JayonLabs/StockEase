<?php

namespace App\Http\Middleware;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionLimit
{
    /**
     * Handle an incoming request and enforce subscription resource limits.
     *
     * Semua role tenant (termasuk super_admin sebagai pemilik toko) tunduk pada batas plan.
     * Hanya user tanpa company (misalnya platform_owner) yang dilewati tanpa pengecekan.
     * Penghitungan resource di-scope per-company agar tidak terpengaruh data company lain.
     *
     * @param  string  $resource  Jenis resource yang dibatasi: 'product', 'user', atau 'warehouse'.
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

        $company = $user->company;
        if (! $company) {
            return $next($request);
        }

        $plan = $company->currentPlan();
        if (! $plan) {
            $message = 'Langganan Anda tidak aktif. Upgrade plan Anda.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 402);
            }

            return back()->with('error', $message);
        }

        $exceeded = match ($resource) {
            'product' => $plan->max_products !== null
                && Product::where('company_id', $company->id)->count() >= $plan->max_products,
            'user' => $plan->max_users !== null
                && User::where('company_id', $company->id)->count() >= $plan->max_users,
            'warehouse' => $plan->max_warehouses !== null
                && Warehouse::where('company_id', $company->id)->count() >= $plan->max_warehouses,
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
