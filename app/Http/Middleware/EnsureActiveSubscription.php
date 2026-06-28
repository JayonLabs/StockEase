<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Pastikan setiap user tenant memiliki langganan aktif sebelum mengakses aplikasi.
     *
     * Middleware ini berjalan secara global pada semua route web dan memblokir akses
     * ke seluruh fitur aplikasi ketika langganan tidak aktif (dibatalkan atau kedaluwarsa),
     * lalu mengarahkan user ke halaman langganan agar dapat berlangganan kembali.
     *
     * Aturan bypass:
     * - Request tanpa user terautentikasi dilewati.
     * - Platform owner dilewati (tidak memiliki langganan company).
     * - User tanpa company dilewati.
     * - Route langganan (subscription.*) dilewati agar user dapat memilih plan.
     * - Route pembayaran (payment.*) dilewati untuk kelanjutan proses pembayaran.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        if ($user->hasRole(Role::PlatformOwner->value)) {
            return $next($request);
        }

        if (! $user->company_id) {
            return $next($request);
        }

        if ($request->routeIs('subscription.*', 'payment.*')) {
            return $next($request);
        }

        $company = $user->company;

        if (! $company) {
            return $next($request);
        }

        if (! $company->activeSubscription()) {
            $message = 'Langganan Anda tidak aktif. Pilih plan untuk melanjutkan menggunakan aplikasi.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'subscription_required' => true,
                ], 402);
            }

            return redirect()->route('subscription.index')->with('error', $message);
        }

        return $next($request);
    }
}
