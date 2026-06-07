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
     * Display the subscription management page.
     */
    public function index(): Response
    {
        $company = Auth::user()?->company;
        $activeSubscription = $company?->activeSubscription();

        return Inertia::render('Subscription/Index', [
            'currentSubscription' => $activeSubscription?->load('plan'),
            'plans' => Plan::active()->get(),
        ]);
    }

    /**
     * Upgrade or change the current subscription plan.
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
            $subscription = $this->subscriptionService->createTrial($company, $plan);

            return response()->json([
                'message' => 'Berlangganan plan Pemula.',
                'subscription' => $subscription,
            ]);
        }

        $subscription = $this->subscriptionService->createTrial($company, $plan, $billingCycle);

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
     * Cancel an active subscription.
     */
    public function cancel(Subscription $subscription): RedirectResponse
    {
        Gate::authorize('cancel', $subscription);
        $this->subscriptionService->cancelSubscription($subscription);

        return back()->with('success', 'Subscription dibatalkan.');
    }
}
