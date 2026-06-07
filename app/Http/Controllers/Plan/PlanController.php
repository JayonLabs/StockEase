<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * Display a listing of plans.
     */
    public function index(): Response
    {
        return Inertia::render('Plan/Index', [
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Update the specified plan.
     */
    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->only([
            'price_monthly', 'price_annual',
            'max_products', 'max_users', 'max_warehouses', 'max_shifts',
            'trial_days', 'is_active', 'sort_order',
        ]));

        return redirect()->back();
    }
}
