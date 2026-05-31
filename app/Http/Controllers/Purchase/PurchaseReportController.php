<?php

namespace App\Http\Controllers\Purchase;

use App\Enums\Role;
use App\Exports\PurchaseExportExcel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\PurchaseReportExportRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchase\PurchaseReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseReportController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected PurchaseReportService $reportService
    ) {}

    /**
     * Handles purchase report filtering and rendering.
     *
     * The function expects start_date, end_date, supplier, and user parameters
     * to be passed in the request. It will filter purchases based on the given
     * parameters and return an Inertia response with the filtered purchases data.
     *
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $filters = $request->only(['start_date', 'end_date', 'supplier', 'user']);

        $filteredPurchases = [];

        if (array_filter($filters)) {
            $purchases = $this->reportService->getFilteredPurchases($filters);
            $filteredPurchases = $this->reportService->getIndexReportData($purchases);
        }

        return Inertia::render('Reports/Purchase/Index', [
            'filters' => $filteredPurchases,
        ]);
    }

    /**
     * Search suppliers by name
     *
     *
     * @return JsonResponse
     */
    public function searchSupplier(Request $request)
    {
        if ($request->expectsJson()) {

            if (blank($request->search)) {
                return response()->json([
                    'message' => 'empty search',
                    'data' => [],
                ], 200);
            }

            $supplier = Supplier::where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");

                if (is_numeric($request->search)) {
                    $q->orWhere('id', $request->search);
                }
            })->select('id as value', 'name as label')->get();

            if ($supplier->isEmpty()) {
                return response()->json([
                    'message' => 'supplier not found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'message' => 'success',
                'data' => $supplier,
            ], 200);
        }
    }

    /**
     * Search users by name or ID
     *
     *
     * @return JsonResponse
     */
    public function searchUser(Request $request)
    {
        if ($request->expectsJson()) {

            if (blank($request->search)) {
                return response()->json([
                    'message' => 'empty search',
                    'data' => [],
                ], 200);
            }

            $user = User::where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");

                if (is_numeric($request->search)) {
                    $q->orWhere('id', $request->search);
                }
            })
                ->role([Role::Warehouse->value, Role::Admin->value, Role::SuperAdmin->value])
                ->select('id as value', 'name as label')
                ->get();

            if ($user->isEmpty()) {
                return response()->json([
                    'message' => 'user not found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'message' => 'success',
                'data' => $user,
            ], 200);
        }
    }

    /**
     * Export purchase report to PDF
     *
     * @return BinaryFileResponse
     */
    public function exportToPdf(PurchaseReportExportRequest $request)
    {
        $filters = $request->validated();
        $purchases = $this->reportService->getFilteredPurchases($filters);
        $data = $this->reportService->getPdfReportData($purchases, $filters);

        $pdf = Pdf::loadView('exports.purchase-report.export-pdf', $data);

        $fileName = 'Laporan Pembelian '
            .Carbon::parse($filters['start_date'])->translatedFormat('d F Y').' - '
            .Carbon::parse($filters['end_date'])->translatedFormat('d F Y').' StockEase.pdf';

        $filePath = 'reports/purchase/'
            .Carbon::now('Asia/Shanghai')->format('Y').'/'
            .Carbon::now('Asia/Shanghai')->translatedFormat('F').'/'
            .$fileName;

        Storage::put($filePath, $pdf->output());

        return $pdf->download($fileName);
    }

    /**
     * Export the purchase report to an Excel file.
     *
     * @return BinaryFileResponse
     */
    public function exportToExcel(PurchaseReportExportRequest $request)
    {
        $filters = $request->validated();
        $purchases = $this->reportService->getFilteredPurchases($filters);
        $summary = $this->reportService->getExcelReportSummary($purchases);
        $preparedFilters = $this->reportService->prepareExcelFilters($filters);

        $fileName = 'Laporan Pembelian '
            .Carbon::parse($filters['start_date'])->translatedFormat('d F Y').' - '
            .Carbon::parse($filters['end_date'])->translatedFormat('d F Y').' StockEase.xlsx';

        $filePath = 'reports/purchase/'
            .Carbon::now('Asia/Shanghai')->format('Y').'/'
            .Carbon::now('Asia/Shanghai')->translatedFormat('F').'/'
            .$fileName;

        $export = new PurchaseExportExcel($purchases, $preparedFilters, $summary);

        Excel::store($export, $filePath, 'local');

        return Excel::download($export, $fileName);
    }
}
