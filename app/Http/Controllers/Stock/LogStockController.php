<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Stock\StockLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogStockController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected StockLogService $stockLogService
    ) {}

    /**
     * Handle log stock filtering and rendering.
     *
     * The function expects search and per_page parameters to be passed in the request.
     * It will filter log stock based on the given parameters and return an Inertia response
     * with the filtered log stock data.
     *
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);

        $filters = [
            'search' => $request->search,
            'end_date' => $request->end,
            'start_date' => $request->start,
        ];

        $logStocks = $this->stockLogService->getPaginatedStockLogs($filters, $perPage);

        return Inertia::render('LogStock/Index', [
            'logStocks' => $logStocks,
            'filters' => [
                'start' => $request->start,
                'end' => $request->end,
                'search' => $request->search,
            ],
        ]);
    }
}
