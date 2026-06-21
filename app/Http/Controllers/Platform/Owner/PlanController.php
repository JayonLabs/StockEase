<?php

namespace App\Http\Controllers\Platform\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\Owner\StorePlanRequest;
use App\Http\Requests\Platform\Owner\UpdatePlanRequest;
use App\Models\Plan;
use App\Services\Platform\Owner\PlanService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private readonly PlanService $planService
    ) {}

    /**
     * Display a list of all plans with subscription counts.
     */
    public function index(): Response
    {
        return Inertia::render('Platform/Owner/Plan/Index', [
            'plans' => $this->planService->getAll(),
        ]);
    }

    /**
     * Store a newly created plan.
     *
     * Validates input via StorePlanRequest, delegates to PlanService,
     * and invalidates the pricing page cache on success.
     */
    public function store(StorePlanRequest $request): RedirectResponse
    {
        $this->planService->create($request->validated());

        return redirect()->route('platform.owner.plans.index')
            ->with('success', 'Plan berhasil dibuat.');
    }

    /**
     * Update an existing plan.
     *
     * Validates input via UpdatePlanRequest (slug uniqueness excludes current plan),
     * delegates to PlanService, and invalidates the pricing page cache on success.
     */
    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $this->planService->update($plan, $request->validated());

        return redirect()->route('platform.owner.plans.index')
            ->with('success', 'Plan berhasil diperbarui.');
    }

    /**
     * Soft-delete a plan.
     *
     * Blocked if the plan has active or trialing subscribers to prevent
     * breaking existing subscriptions. Invalidates the pricing page cache on success.
     */
    public function destroy(Plan $plan): RedirectResponse
    {
        if (! $this->planService->delete($plan)) {
            return redirect()->route('platform.owner.plans.index')
                ->with('error', 'Plan tidak bisa dihapus karena masih memiliki subscriber aktif.');
        }

        return redirect()->route('platform.owner.plans.index')
            ->with('success', 'Plan berhasil dihapus.');
    }
}
