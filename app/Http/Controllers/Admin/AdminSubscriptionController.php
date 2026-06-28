<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSubscriptionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected SubscriptionService $subscriptionService,
    ) {}

    /**
     * Display a listing of subscriptions.
     */
    public function index(Request $request): Response
    {
        $subscriptions = Subscription::with(['company.owner', 'plan'])
            ->when($request->status,
                fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25);

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'filters' => $request->only('status'),
        ]);
    }

    /**
     * Display the specified subscription.
     */
    public function show(Subscription $subscription): Response
    {
        return Inertia::render('Admin/Subscriptions/Show', [
            'subscription' => $subscription->load(['company.owner', 'plan', 'invoices']),
        ]);
    }

    /**
     * Update the specified subscription.
     */
    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,canceled,expired,trialing,pending'],
            'ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $subscription->update($validated);

        return back()->with('success', 'Subscription diperbarui.');
    }

    /**
     * Assign a subscription plan to a user's company.
     *
     * Admin assignments bypass the payment flow — the subscription is always
     * created as active regardless of whether the plan is free or paid.
     */
    public function assign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $company = User::findOrFail($validated['user_id'])->company;

        if (! $company) {
            return back()->with('error', 'User tidak memiliki company.');
        }

        $subscription = $this->subscriptionService->createTrial($company, $plan);

        // Admin-assigned plans skip payment — activate immediately
        if ($subscription->status === 'pending_payment') {
            $this->subscriptionService->activateSubscription($subscription);
        }

        return back()->with('success', 'Subscription berhasil di-assign.');
    }
}
