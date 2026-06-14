<?php

namespace App\Http\Controllers\Platform\Owner;

use App\Http\Controllers\Controller;
use App\Services\Platform\Owner\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Create a new dashboard controller instance.
     */
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /**
     * Display the platform owner dashboard with aggregated data.
     */
    public function index(): Response
    {

        $data = [
            'overview' => $this->dashboardService->getOverview(),
            'subscription_breakdown' => $this->dashboardService->getSubscriptionBreakdown(),
            'recent_companies' => $this->dashboardService->getRecentRegistrations(),
            'active_companies' => $this->dashboardService->getActiveCompanies(),
            'growth_trend' => $this->dashboardService->getGrowthTrend(),
        ];

        return Inertia::render('Platform/Owner/Dashboard/Index', [
            'data' => $data,
        ]);
    }
}
