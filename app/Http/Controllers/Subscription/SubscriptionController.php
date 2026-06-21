<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\SubscriptionUpgradeRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Payment\PaymentService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected SubscriptionService $subscriptionService,
        protected PaymentService $paymentService,
    ) {}

    /**
     * Tampilkan halaman manajemen langganan beserta daftar semua plan aktif.
     *
     * Setiap plan disertai array `features` agar halaman dapat menampilkan
     * daftar fitur dengan ikon ✓/✗ secara konsisten dengan halaman pricing.
     */
    public function index(): Response
    {
        $company = Auth::user()?->company;
        $activeSubscription = $company?->activeSubscription();

        $plans = Plan::active()->get()->map(fn (Plan $plan) => [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'price_monthly' => (int) $plan->price_monthly,
            'price_annual' => (int) $plan->price_annual,
            'annual_per_month' => $plan->annualPerMonth(),
            'annual_savings_percent' => $plan->annualSavingsPercent(),
            'max_products' => $plan->max_products,
            'max_users' => $plan->max_users,
            'max_warehouses' => $plan->max_warehouses,
            'trial_days' => $plan->trial_days,
            'features' => $plan->features ?? [],
        ]);

        return Inertia::render('Subscription/Index', [
            'currentSubscription' => $activeSubscription?->load('plan'),
            'plans' => $plans,
        ]);
    }

    /**
     * Upgrade atau ganti plan langganan company yang sedang aktif.
     *
     * Untuk plan gratis: langsung aktifkan tanpa pembayaran.
     * Untuk plan berbayar: mulai trial jika plan memiliki trial_days,
     * atau buat invoice dan Snap Token Midtrans untuk pembayaran langsung.
     */
    public function upgrade(SubscriptionUpgradeRequest $request): JsonResponse
    {
        $user = Auth::user();
        $plan = Plan::findOrFail($request->plan_id);
        $billingCycle = $request->billing_cycle ?? 'monthly';

        if (! $user->company) {
            abort(403, 'Anda tidak terhubung ke organisasi.');
        }

        $company = $user->company;

        if ($plan->isFree()) {
            $subscription = $this->subscriptionService->upgradePlan($company, $plan);

            return response()->json([
                'message' => 'Berlangganan plan Pemula.',
                'subscription' => $subscription,
            ]);
        }

        $subscription = $this->subscriptionService->upgradePlan($company, $plan, $billingCycle);

        if ($subscription->status === 'trialing') {
            return response()->json([
                'message' => 'Trial 14 hari dimulai!',
                'subscription' => $subscription,
            ]);
        }

        $invoice = $this->subscriptionService->createInvoice($subscription);
        $orderId = $this->subscriptionService->generateMidtransOrderId($invoice);

        if (config('midtrans.server_key')) {
            $snapToken = $this->paymentService->createSnapTokenForSubscription($invoice, $orderId, $user);
        } else {
            $snapToken = null;
        }

        $invoice->update(['midtrans_order_id' => $orderId]);

        return response()->json([
            'snap_token' => $snapToken,
            'order_id' => $orderId,
        ]);
    }

    /**
     * Batalkan subscription yang sedang aktif atau dalam masa trial.
     *
     * Aksi ini diotorisasi via SubscriptionPolicy; hanya super_admin company
     * yang dapat membatalkan langganan mereka sendiri.
     */
    public function cancel(Subscription $subscription): RedirectResponse
    {
        Gate::authorize('cancel', $subscription);
        $this->subscriptionService->cancelSubscription($subscription);

        return back()->with('success', 'Subscription dibatalkan.');
    }
}
