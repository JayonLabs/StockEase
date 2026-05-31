<?php

namespace App\Http\Controllers\Stock;

use App\Exports\StockExportExcel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StockReportExportRequest;
use App\Models\Category;
use App\Models\Supplier;
use App\Services\Stock\StockReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockReportController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected StockReportService $reportService
    ) {}

    /**
     * Handle stock report filtering and rendering.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $filters = $request->only(['category', 'supplier', 'start_date', 'end_date']);

        $filteredStocks = [];

        if (array_filter($filters)) {
            $filteredStocks = $this->reportService->getPaginatedFilteredStocks($filters);
        }

        return Inertia::render('Reports/Stock/Index', [
            'filteredStocks' => $filteredStocks,
        ]);
    }

    /**
     * Search category by name
     *
     * @return JsonResponse
     */
    public function searchCategory(Request $request)
    {
        if ($request->expectsJson()) {
            $query = Category::query()
                ->when(is_numeric($request->search), function ($q) use ($request) {
                    $q->where('id', $request->search);
                }, function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->search.'%');
                })
                ->limit(5)
                ->get();

            if ($query->isEmpty()) {
                return response()->json([
                    'message' => 'category not found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'message' => 'success',
                'data' => $query,
            ], 200);
        }
    }

    /**
     * Search supplier by name.
     *
     * @return JsonResponse
     */
    public function searchSupplier(Request $request)
    {
        if ($request->expectsJson()) {
            $query = Supplier::query()
                ->when(is_numeric($request->search), function ($q) use ($request) {
                    $q->where('id', $request->search);
                }, function ($q) use ($request) {
                    $q->where('name', 'like', '%'.$request->search.'%');
                })
                ->limit(5)
                ->get();

            if ($query->isEmpty()) {
                return response()->json([
                    'message' => 'supplier not found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'message' => 'success',
                'data' => $query,
            ]);
        }
    }

    /**
     * Export the stock report to a PDF file.
     *
     * @return BinaryFileResponse
     */
    public function exportToPdf(StockReportExportRequest $request)
    {
        $filters = $request->validated();
        $filteredStocks = $this->reportService->getFilteredStocksForExport($filters);
        $preparedFilters = $this->reportService->prepareExportFilters($filters);

        $pdf = Pdf::loadView('exports.stock-report.export-pdf', [
            'filters' => $preparedFilters,
            'filteredStocks' => $filteredStocks,
        ]);

        $fileName = 'Laporan Stock '
            .Carbon::parse($filters['start_date'])->translatedFormat('d F Y').' - '
            .Carbon::parse($filters['end_date'])->translatedFormat('d F Y').' StockEase.pdf';

        $filePath = 'reports/stock/'
            .Carbon::now('Asia/Shanghai')->format('Y').'/'
            .Carbon::now('Asia/Shanghai')->translatedFormat('F').'/'
            .$fileName;

        Storage::put($filePath, $pdf->output());

        return $pdf->download($fileName);
    }

    /**
     * Export the stock report to an Excel file.
     *
     * @return BinaryFileResponse
     */
    public function exportToExcel(StockReportExportRequest $request)
    {
        $filters = $request->validated();
        $filteredStocks = $this->reportService->getFilteredStocksForExport($filters);
        $preparedFilters = $this->reportService->prepareExportFilters($filters);

        $fileName = 'Laporan Stock '
            .Carbon::parse($filters['start_date'])->translatedFormat('d F Y').' - '
            .Carbon::parse($filters['end_date'])->translatedFormat('d F Y').' StockEase.xlsx';

        $filePath = 'reports/stock/'
            .Carbon::now('Asia/Shanghai')->format('Y').'/'
            .Carbon::now('Asia/Shanghai')->translatedFormat('F').'/'
            .$fileName;

        $export = new StockExportExcel($preparedFilters, $filteredStocks);

        Excel::store($export, $filePath, 'local');

        return Excel::download($export, $fileName);
    }
}
