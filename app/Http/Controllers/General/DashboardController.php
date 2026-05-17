<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Services\General\DashboardService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected DashboardService $dashboardService
    ) {}

    /**
     * Get dashboard data for admin, cashier, warehouse
     */
    public function index()
    {
        $data = $this->dashboardService->getDashboardData(Auth::user());

        return Inertia::render('Dashboard/Index', [
            'data' => $data,
        ]);
    }
}
