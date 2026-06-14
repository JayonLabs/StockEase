<?php

namespace App\Http\Controllers\Platform\Owner;

use App\Http\Controllers\Controller;
use App\Services\Platform\Owner\SubscriptionService;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {}

    /**
     * Display a paginated, filterable list of subscriptions.
     */
    public function index(): Response
    {
        $status = request()->query('status');

        return Inertia::render('Platform/Owner/Subscription/Index', [
            'subscriptions' => $this->subscriptionService->getAll($status),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    /**
     * Display a single subscription with company, plan, and invoices.
     *
     * @param  int  $id  The subscription ID.
     */
    public function show(int $id): Response
    {
        $subscription = $this->subscriptionService->findById($id);

        abort_if(is_null($subscription), 404);

        return Inertia::render('Platform/Owner/Subscription/Show', [
            'subscription' => $subscription,
        ]);
    }
}
