<?php

namespace App\Http\Controllers\Platform\Owner;

use App\Http\Controllers\Controller;
use App\Services\Platform\Owner\PlanService;
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
}
