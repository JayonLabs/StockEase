<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreStockTransferRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Warehouse\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockTransferController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected StockTransferService $stockTransferService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $transfers = $this->stockTransferService->getPaginatedTransfers([
            'search' => $request->search,
            'warehouse_id' => $request->warehouse_id,
            'status' => $request->status,
        ]);

        return Inertia::render('StockTransfer/Index', [
            'transfers' => $transfers,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->search,
                'warehouse_id' => $request->warehouse_id,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStockTransferRequest $request): RedirectResponse
    {
        $this->stockTransferService->storeTransfer($request->validated());

        return redirect()->route('stock-transfer.index')
            ->with('success', 'Berhasil melakukan pemindahan stok.');
    }

    /**
     * Search products for transfer selection.
     */
    public function searchProduct(Request $request)
    {
        if ($request->expectsJson()) {
            $search = $request->search;

            $warehouseId = $request->warehouse_id;

            $products = Product::where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });

            if ($warehouseId) {
                $products->whereHas('warehouses', function ($q) use ($warehouseId) {
                    $q->where('warehouses.id', $warehouseId);
                });
            }

            return response()->json(
                $products->select('id as value', 'name as label', 'stock')
                    ->take(10)
                    ->get()
            );
        }
    }
}
