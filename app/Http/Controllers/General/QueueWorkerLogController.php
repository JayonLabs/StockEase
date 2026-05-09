<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Services\General\QueueWorkerLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QueueWorkerLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function __construct(
        protected QueueWorkerLogService $queueWorkerLogService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = $this->queueWorkerLogService->getLogData(
            $request->query('search'),
            $request->query('level')
        );

        return Inertia::render('QueueWorkerLog/Index', [
            'stats' => $data['stats'],
            'lines' => $data['lines'],
            'filters' => $request->only(['search', 'level']),
        ]);
    }
}
