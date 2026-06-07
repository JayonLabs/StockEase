<?php

namespace App\Services\Subscription;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use RuntimeException;

class SubscriptionService
{
    /**
     * Create a trial subscription for a company.
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

        $subscription = Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => $plan->trial_days > 0 && ! $plan->isFree() ? 'trialing' : 'active',
            'billing_cycle' => $billingCycle,
            'trial_ends_at' => $plan->trial_days > 0 && ! $plan->isFree()
                ? now()->addDays($plan->trial_days) : null,
            'starts_at' => now(),
            'ends_at' => $plan->isFree() ? null : now()->addDays(
                $billingCycle === 'annual' ? 365 : 30
            ),
        ]);

        activity()
            ->performedOn($subscription)
            ->withProperties(['plan' => $plan->name, 'cycle' => $billingCycle])
            ->log('subscription_created');

        return $subscription;
    }

    /**
     * Assign the free "Pemula" plan to a company.
     */
    public function assignFreeSubscription(Company $company): Subscription
    {
        $plan = Plan::where('slug', 'pemula')->firstOrFail();

        return $this->createTrial($company, $plan);
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
            'user_id' => auth()->id(),
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
