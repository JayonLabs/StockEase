<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Sale\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaleHistoryController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected SaleService $saleService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);
        $filters = $request->only(['search', 'start', 'end']);

        $sales = $this->saleService->getPaginatedSales(
            $filters,
            $perPage
        );

        return Inertia::render('Sale/Index', [
            'sales' => $sales,
            'filters' => [
                'start' => $filters['start'] ?? '',
                'end' => $filters['end'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }

    /**
     * Display the specified sale details.
     */
    public function show(Sale $sale)
    {
        return Inertia::render('Sale/Show', [
            'sale' => $this->saleService->getSaleDetails($sale),
        ]);
    }

    /**
     * Export the specified sale details to a PDF file.
     */
    public function exportToPdf(Sale $sale)
    {
        $sale = $this->saleService->getSaleDetails($sale);

        $pdf = Pdf::loadView('exports.sales.detail', [
            'sale' => $sale,
        ]);

        $fileName = "invoice-{$sale->id}.pdf";

        return $pdf->download($fileName);
    }
}
