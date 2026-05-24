<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Stock\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StockAdjustmentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected StockAdjustmentService $stockAdjustmentService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $adjustments = $this->stockAdjustmentService->getPaginatedAdjustments([
            'search' => $request->search,
        ]);

        return Inertia::render('StockAdjustment/Index', [
            'adjustments' => $adjustments,
            'warehouses' => Warehouse::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'product_id' => ['required', 'exists:products,id'],
            'new_stock' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
        ]);

        $this->stockAdjustmentService->storeAdjustment($validated);

        return redirect()->route('stock-adjustment.index')
            ->with('success', 'Berhasil melakukan penyesuaian stok.');
    }

    /**
     * Search products for selection.
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
