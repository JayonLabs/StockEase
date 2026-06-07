<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Models\Unit;
use App\Services\Product\UnitService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnitController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected UnitService $unitService
    ) {}

    /**
     * Display a listing of units with optional search and pagination.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);

        $units = $this->unitService->getPaginatedUnits(
            $request->only('search'),
            $perPage
        );

        return Inertia::render('Unit/Index', [
            'units' => $units,
        ]);
    }

    /**
     * Store a newly created unit in storage.
     */
    public function store(StoreUnitRequest $request)
    {
        $this->unitService->storeUnit($request->validated());

        return redirect()->back()->with('success', 'Satuan berhasil ditambahkan');
    }

    /**
     * Update an existing unit.
     */
    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $this->unitService->updateUnit($unit, $request->validated());

        return redirect()->back()->with('success', 'Satuan berhasil diupdate');
    }

    /**
     * Hapus satuan yang telah dipilih.
     */
    public function destroy(Unit $unit)
    {
        $this->unitService->deleteUnit($unit);

        return redirect()->back()->with('success', 'Satuan berhasil dihapus');
    }
}
