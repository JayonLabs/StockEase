<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected WarehouseService $warehouseService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);

        $warehouses = $this->warehouseService->getPaginatedWarehouses(
            $request->only('search'),
            $perPage
        );

        return Inertia::render('Warehouse/Index', [
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWarehouseRequest $request)
    {
        $this->warehouseService->storeWarehouse($request->validated());

        return redirect()->back()->with('success', 'Gudang berhasil ditambahkan');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $this->warehouseService->updateWarehouse($warehouse, $request->validated());

        return redirect()->back()->with('success', 'Gudang berhasil diupdate');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warehouse $warehouse)
    {
        $this->warehouseService->deleteWarehouse($warehouse);

        return redirect()->back()->with('success', 'Gudang berhasil dihapus');
    }
}
