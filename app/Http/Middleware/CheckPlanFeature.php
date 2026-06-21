<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    /**
     * Tangani request masuk dan verifikasi apakah fitur tersedia di plan aktif pengguna.
     *
     * Middleware ini digunakan sebagai: `plan.feature:{nama_fitur}`
     * Contoh: `plan.feature:purchasing`, `plan.feature:cashier_shift`
     *
     * Aturan:
     * - Platform owner melewati semua pengecekan fitur.
     * - Semua role tenant (super_admin, admin, cashier, warehouse) dicek sesuai plan company-nya.
     * - Jika fitur tidak tersedia di plan, redirect ke halaman langganan (atau 403 JSON untuk API).
     * - Jika belum ada langganan aktif, plan gratis (Pemula) akan otomatis ditetapkan.
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        if ($user->hasRole(Role::PlatformOwner->value)) {
            return $next($request);
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
            app(SubscriptionService::class)->assignFreeSubscription($company);
            $plan = $company->fresh()->currentPlan();
        }

        if (! $plan->hasFeature($feature)) {
            $message = 'Fitur ini tidak tersedia di plan '.$plan->name.'. Upgrade plan Anda untuk mengakses fitur ini.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()->route('subscription.index')->with('error', $message);
        }

        return $next($request);
    }
}
