<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreStockTransferRequest;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Warehouse\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    public function searchProduct(Request $request): JsonResponse
    {
        $search = $request->string('search');
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $products = Product::select('id as value', 'name as label', 'stock')
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });

        if ($warehouseId) {
            $products->addSelect([
                'warehouse_stock' => DB::table('warehouse_product')
                    ->select('stock')
                    ->whereColumn('product_id', 'products.id')
                    ->where('warehouse_id', $warehouseId)
                    ->limit(1),
            ]);
        }

        return response()->json($products->take(10)->get());
    }
}
