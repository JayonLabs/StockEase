<?php

namespace App\Http\Controllers\Platform\Owner;

use App\Http\Controllers\Controller;
use App\Services\General\QueueWorkerLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QueueWorkerLogController extends Controller
{
    /**
     * Inject the log service for reading and parsing queue worker log data.
     */
    public function __construct(
        private readonly QueueWorkerLogService $queueWorkerLogService
    ) {}

    /**
     * Display the queue worker log viewer for the platform owner.
     *
     * Accepts optional query parameters:
     *   - search (string): case-insensitive text filter applied to log lines.
     *   - level  (string): filter by detected level — "error", "warning", or "info".
     */
    public function index(Request $request): Response
    {
        $data = $this->queueWorkerLogService->getLogData(
            $request->query('search'),
            $request->query('level')
        );

        return Inertia::render('Platform/Owner/QueueWorkerLog/Index', [
            'stats' => $data['stats'],
            'lines' => $data['lines'],
            'filters' => $request->only(['search', 'level']),
        ]);
    }
}
