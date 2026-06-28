<?php

namespace App\Services\Subscription;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class SubscriptionService
{
    /**
     * Create a trial, pending_payment, or active subscription for a company.
     *
     * - trialing if the plan offers trial days, is not free, and the company
     *   has never used a trial before (`had_trial = false`).
     * - pending_payment if the plan is paid and no trial is available.
     * - active if the plan is free.
     *
     * After a trial is created the `had_trial` flag on the company is set to
     * `true` so subsequent calls always produce a pending-payment or active
     * subscription.
     */
    public function createTrial(
        Company $company,
        Plan $plan,
        string $billingCycle = 'monthly'
    ): Subscription {

        $existingActive = $company->activeSubscription();
        if ($existingActive) {
            throw new RuntimeException('Company sudah memiliki subscription aktif.');
        }

        $willUseTrial = $plan->trial_days > 0 && ! $plan->isFree() && ! $company->hadTrial();

        $status = match (true) {
            $willUseTrial => 'trialing',
            $plan->isFree() => 'active',
            default => 'pending_payment',
        };

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'billing_cycle' => $billingCycle,
            'trial_ends_at' => $willUseTrial ? now()->addDays($plan->trial_days) : null,
            'starts_at' => now(),
            'ends_at' => ($status === 'pending_payment' || $plan->isFree())
                ? null
                : now()->addDays($billingCycle === 'annual' ? 365 : 30),
        ]);

        if ($willUseTrial) {
            $company->update(['had_trial' => true]);
        }

        activity()
            ->performedOn($subscription)
            ->withProperties(['plan' => $plan->name, 'cycle' => $billingCycle])
            ->log('subscription_created');

        return $subscription;
    }

    /**
     * Switch a company to a different plan, cancelling any existing active,
     * trialing, or pending_payment subscription first so there is never more
     * than one concurrent subscription per company.
     */
    public function upgradePlan(
        Company $company,
        Plan $plan,
        string $billingCycle = 'monthly'
    ): Subscription {
        $existing = $company->activeSubscription();

        if ($existing) {
            $this->cancelSubscription($existing);
        }

        // Cancel any pending_payment subscription that activeSubscription()
        // does not return so the company does not accumulate orphan records.
        $pending = $company->subscription()
            ->where('status', 'pending_payment')
            ->first();

        if ($pending) {
            $this->cancelSubscription($pending);
        }

        return $this->createTrial($company->fresh(), $plan, $billingCycle);
    }

    /**
     * Assign the free "Pemula" plan to a company (always active, no trial).
     * Used for downgrades and fallback assignments.
     */
    public function assignFreeSubscription(Company $company): Subscription
    {
        $plan = Plan::where('slug', 'pemula')->firstOrFail();

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => null,
        ]);

        activity()
            ->performedOn($subscription)
            ->withProperties(['plan' => $plan->name])
            ->log('free_subscription_assigned');

        return $subscription;
    }

    /**
     * Create an invoice for a subscription.
     */
    public function createInvoice(Subscription $subscription): SubscriptionInvoice
    {
        $plan = $subscription->plan;
        $amount = $subscription->billing_cycle === 'annual'
            ? $plan->price_annual
            : $plan->price_monthly;

        return SubscriptionInvoice::create([
            'subscription_id' => $subscription->id,
            'user_id' => Auth::id(),
            'amount' => (float) $amount,
            'status' => 'pending',
        ]);
    }

    /**
     * Generate a Midtrans order ID for a subscription invoice.
     */
    public function generateMidtransOrderId(SubscriptionInvoice $invoice): string
    {
        return 'SUB-'.$invoice->id.'-'.now()->timestamp;
    }

    /**
     * Activate a subscription after payment is confirmed.
     */
    public function activateSubscription(Subscription $subscription, ?string $billingCycle = null): void
    {
        $cycle = $billingCycle ?? $subscription->billing_cycle;
        $cycleDays = $cycle === 'annual' ? 365 : 30;

        $subscription->update([
            'status' => 'active',
            'starts_at' => $subscription->starts_at ?? now(),
            'ends_at' => now()->addDays($cycleDays),
            'trial_ends_at' => null,
        ]);
    }

    /**
     * Get the latest pending invoice for a subscription, if any.
     */
    public function getPendingInvoice(Subscription $subscription): ?SubscriptionInvoice
    {
        return $subscription->invoices()
            ->where('status', 'pending')
            ->latest()
            ->first();
    }

    /**
     * Handle a failed payment notification from the payment gateway. The
     * invoice is always marked as failed. If the subscription is still in
     * `pending_payment` state it is also cancelled and the company is
     * reverted to the free plan so the user can retry from a clean slate.
     */
    public function handleFailedPayment(SubscriptionInvoice $invoice): void
    {
        $invoice->update(['status' => 'failed']);

        if ($invoice->subscription->status !== 'pending_payment') {
            return;
        }

        $this->cancelSubscription($invoice->subscription);
        $this->assignFreeSubscription($invoice->subscription->company);
    }

    /**
     * Cancel an active subscription.
     */
    public function cancelSubscription(Subscription $subscription): void
    {
        $subscription->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }

    /**
     * Expire a subscription and downgrade to the free plan.
     */
    public function expireSubscription(Subscription $subscription): void
    {
        $subscription->update(['status' => 'expired']);
        $this->assignFreeSubscription($subscription->company);
    }

    /**
     * Downgrade all expired subscriptions to the free plan.
     */
    public function downgradeExpiredSubscriptions(): int
    {
        $count = 0;

        Subscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->each(function (Subscription $sub) use (&$count) {
                $this->expireSubscription($sub);
                $count++;
            });

        Subscription::where('status', 'trialing')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->each(function (Subscription $sub) use (&$count) {
                $this->expireSubscription($sub);
                $count++;
            });

        return $count;
    }
}
