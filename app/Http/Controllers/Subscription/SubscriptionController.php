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
     *
     * Jika subscription saat ini berstatus pending_payment, invoice yang
     * masih pending beserta snap_token (jika tersedia) juga dikirim ke
     * frontend agar user dapat melanjutkan atau membatalkan pembayaran.
     */
    public function index(): Response
    {
        $company = Auth::user()?->company;
        $activeSubscription = $company?->activeSubscription();

        // Jika tidak ada subscription aktif/trial, cek apakah ada yang
        // pending_payment — user mungkin sedang dalam proses pembayaran.
        $pendingSubscription = null;
        $pendingInvoice = null;

        if (! $activeSubscription && $company) {
            $pendingSubscription = $company->subscription()
                ->where('status', 'pending_payment')
                ->with('plan')
                ->latest()
                ->first();

            if ($pendingSubscription) {
                $pendingInvoice = $this->subscriptionService->getPendingInvoice($pendingSubscription);
            }
        }

        $plans = Plan::active()->get()->map(fn(Plan $plan) => [
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
            'is_free' => $plan->isFree(),
            'features' => $plan->features ?? [],
        ]);

        return Inertia::render('Subscription/Index', [
            'currentSubscription' => $activeSubscription?->load('plan'),
            'pendingSubscription' => $pendingSubscription ? [
                'id' => $pendingSubscription->id,
                'plan' => $pendingSubscription->plan,
                'status' => $pendingSubscription->status,
                'billing_cycle' => $pendingSubscription->billing_cycle,
                'starts_at' => $pendingSubscription->starts_at,
                'invoice' => $pendingInvoice ? [
                    'id' => $pendingInvoice->id,
                    'amount' => (int) $pendingInvoice->amount,
                    'midtrans_order_id' => $pendingInvoice->midtrans_order_id,
                    'status' => $pendingInvoice->status,
                    'created_at' => $pendingInvoice->created_at,
                ] : null,
            ] : null,
            'plans' => $plans,
            'hadTrial' => (bool) $company?->hadTrial(),
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
                'message' => 'Trial ' . $plan->trial_days . ' hari dimulai!',
                'subscription' => $subscription,
            ]);
        }

        if ($subscription->status === 'pending_payment') {
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
                'message' => 'Silakan selesaikan pembayaran.',
            ]);
        }

        return response()->json([
            'message' => 'Langganan berhasil diaktifkan.',
            'subscription' => $subscription,
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

    /**
     * Lanjutkan pembayaran untuk subscription yang masih pending_payment.
     *
     * Membuat Snap Token baru untuk invoice yang masih pending agar user
     * dapat membuka popup Midtrans tanpa harus memulai proses upgrade dari
     * awal.
     */
    public function retryPayment(): JsonResponse
    {
        $user = Auth::user();

        if (! $user->company) {
            abort(403, 'Anda tidak terhubung ke organisasi.');
        }

        $pendingSubscription = $user->company->subscription()
            ->where('status', 'pending_payment')
            ->with('plan')
            ->latest()
            ->first();

        if (! $pendingSubscription) {
            return response()->json(['message' => 'Tidak ada pembayaran yang tertunda.'], 404);
        }

        $pendingInvoice = $this->subscriptionService->getPendingInvoice($pendingSubscription);

        if (! $pendingInvoice) {
            // Invoice sudah expired/dihapus — buat ulang
            $pendingInvoice = $this->subscriptionService->createInvoice($pendingSubscription);
            $orderId = $this->subscriptionService->generateMidtransOrderId($pendingInvoice);
            $pendingInvoice->update(['midtrans_order_id' => $orderId]);
        } else {
            $orderId = $pendingInvoice->midtrans_order_id;
        }

        if (config('midtrans.server_key')) {
            $snapToken = $this->paymentService->createSnapTokenForSubscription(
                $pendingInvoice,
                $orderId,
                $user
            );
        } else {
            $snapToken = null;
        }

        return response()->json([
            'snap_token' => $snapToken,
            'order_id' => $orderId,
        ]);
    }
}
