<?php

namespace App\Http\Controllers\Sale;

use App\Enums\Role;
use App\Exports\SalesReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\SaleReportExportRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Sale\SaleReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SaleReportController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected SaleReportService $reportService
    ) {}

    /**
     * Index function handles report sales data based on given request
     * parameters. It will return Inertia response with filtered sales data.
     *
     * @param  Request  $request  The request object containing start_date, end_date,
     *                            cashier, and payment parameters.
     * @return Response
     */
    public function index(Request $request)
    {
        $filters = $request->only(['start', 'end', 'cashier', 'payment', 'warehouse']);

        $filteredSales = [];

        if (array_filter($filters)) {
            $sales = $this->reportService->getFilteredSales($filters);
            $filteredSales = $this->reportService->getIndexReportData($sales);
        }

        $warehouses = Warehouse::select('id', 'name')->get();

        return Inertia::render('Reports/Sale/Index', [
            'sales' => $filteredSales,
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Search cashier by name.
     *
     * @return JsonResponse
     */
    public function searchCashier(Request $request)
    {
        if ($request->expectsJson()) {

            if (blank($request->search)) {
                return response()->json([
                    'message' => 'empty search',
                    'data' => [],
                ], 200);
            }

            $cashier = User::where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('id', 'like', "%{$request->search}%");
            })
                ->role([Role::Cashier->value, Role::Admin->value, Role::SuperAdmin->value])
                ->select('id as value', 'name as label')
                ->get();

            if ($cashier->isEmpty()) {
                return response()->json([
                    'message' => 'cashier not found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'message' => 'Success get cashier',
                'data' => $cashier,
            ], 200);
        }
    }

    /**
     * Generate a PDF report for a given date range, cashier, and payment method.
     *
     * @return BinaryFileResponse
     */
    public function exportToPdf(SaleReportExportRequest $request)
    {
        $filters = $request->validated();
        $sales = $this->reportService->getFilteredSales($filters);
        $data = $this->reportService->getPdfReportData($sales, $filters);

        $pdf = Pdf::loadView('exports.sales.report', $data);

        $fileName = 'Laporan Penjualan '
            .Carbon::parse($filters['start'])->translatedFormat('d F Y').' - '
            .Carbon::parse($filters['end'])->translatedFormat('d F Y').' StockEase.pdf';

        $filePath = 'reports/sales/'
            .Carbon::now('Asia/Shanghai')->format('Y').'/'
            .Carbon::now('Asia/Shanghai')->translatedFormat('F').'/'
            .$fileName;

        Storage::put($filePath, $pdf->output());

        return $pdf->download($fileName);
    }

    /**
     * Generate an Excel report for a given date range, cashier, and payment method.
     *
     * @return BinaryFileResponse
     */
    public function exportToExcel(SaleReportExportRequest $request)
    {
        $filters = $request->validated();
        $sales = $this->reportService->getFilteredSales($filters);
        $summary = $this->reportService->getExcelReportSummary($sales);
        $preparedFilters = $this->reportService->prepareExcelFilters($filters);

        $fileName = 'Laporan Penjualan '
            .Carbon::parse($filters['start'])->translatedFormat('d F Y').' - '
            .Carbon::parse($filters['end'])->translatedFormat('d F Y').' StockEase.xlsx';

        $filePath = 'reports/sales/'
            .Carbon::now('Asia/Shanghai')->format('Y').'/'
            .Carbon::now('Asia/Shanghai')->translatedFormat('F').'/'
            .$fileName;

        Excel::store(new SalesReportExport($sales, $preparedFilters, $summary), $filePath, 'local');

        return Excel::download(new SalesReportExport($sales, $preparedFilters, $summary), $fileName);
    }
}
