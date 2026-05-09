<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleReturnRequest;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Services\Sale\SaleReturnService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaleReturnController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected SaleReturnService $saleReturnService
    ) {}

    /**
     * Display a listing of sale returns.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);
        $filters = $request->only(['search', 'start', 'end']);

        $returns = $this->saleReturnService->getPaginatedReturns(
            $filters,
            $perPage
        );

        return Inertia::render('SaleReturn/Index', [
            'returns' => $returns,
            'filters' => [
                'start' => $filters['start'] ?? '',
                'end' => $filters['end'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }

    /**
     * Show the return form for a specific sale.
     */
    public function show(Sale $sale)
    {
        $saleWithItems = $this->saleReturnService->getSaleForReturn($sale);

        return Inertia::render('SaleReturn/Show', [
            'sale' => $saleWithItems,
        ]);
    }

    /**
     * Display the specified sale return details.
     */
    public function detail(SaleReturn $saleReturn)
    {
        $saleReturn->load(
            'saleReturnItems.product',
            'saleReturnItems.saleItem',
            'sale.saleItems.product',
            'user',
            'shift'
        );

        return Inertia::render('SaleReturn/Detail', [
            'saleReturn' => $saleReturn,
        ]);
    }

    /**
     * Process the sale return.
     */
    public function store(StoreSaleReturnRequest $request, Sale $sale)
    {
        try {
            $saleReturn = $this->saleReturnService->processReturn(
                $sale,
                $request->validated()
            );

            return redirect()->route('sale-return.index')
                ->with('success', 'Retur penjualan berhasil diproses.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }
}
