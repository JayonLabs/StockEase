<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequest;
use App\Models\Purchase;
use App\Models\Warehouse;
use App\Services\Purchase\PurchaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    /**
     * Display a listing of the purchases.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);
        $filters = $request->only(['search', 'start', 'end']);

        $purchases = $this->purchaseService->getPaginatedPurchases(
            $filters,
            $perPage
        );

        return Inertia::render('Purchase/Index', [
            'purchases' => $purchases,
            'warehouses' => Warehouse::where('is_active', true)->select('id', 'name')->orderBy('name')->get(),
            'filters' => [
                'start' => $filters['start'] ?? '',
                'end' => $filters['end'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }

    /**
     * Searches for suppliers based on the given search query.
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

            $suppliers = $this->purchaseService->searchSuppliers($request->search);

            if ($suppliers->isEmpty()) {
                return response()->json([
                    'message' => 'supplier not found',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'message' => 'success search supplier',
                'data' => $suppliers,
            ], 200);
        }

        return back();
    }

    /**
     * Search products by name.
     */
    public function searchProduct(Request $request)
    {
        if ($request->expectsJson()) {
            if (blank($request->search)) {
                return response()->json([
                    'message' => 'empty search',
                    'data' => [],
                ], 200);
            }

            $products = $this->purchaseService->searchProducts($request->search);

            if ($products->isEmpty()) {
                return response()->json([
                    'message' => 'product not found',
                    'data' => [],
                ], 200);
            }

            return response()->json([
                'message' => 'success search product',
                'data' => $products,
            ], 200);
        }

        return back();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePurchaseRequest $request)
    {
        try {
            $this->purchaseService->storePurchase($request->validated());

            return redirect()->route('purchase.index')->with('success', 'Pembelian berhasil disimpan');
        } catch (\Throwable $th) {
            return back()->withErrors([
                'error' => 'Gagal menyimpan data: '.$th->getMessage(),
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePurchaseRequest $request, Purchase $purchase)
    {
        try {
            $this->purchaseService->updatePurchase($purchase, $request->validated());

            return redirect()->route('purchase.index')->with('success', 'Pembelian berhasil diubah');
        } catch (\Throwable $th) {
            return back()->withErrors([
                'error' => 'Gagal menyimpan data: '.$th->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Purchase $purchase)
    {
        try {
            $this->purchaseService->deletePurchase($purchase);

            return redirect()->route('purchase.index')->with('success', 'Data pembelian berhasil dihapus!');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', 'Gagal menghapus data pembelian.');
        }
    }
}
