<?php

namespace App\Http\Controllers\Shift;

use App\Enums\ShiftStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shift\CloseShiftRequest;
use App\Http\Requests\Shift\StoreShiftRequest;
use App\Models\Shift;
use App\Services\Shift\ShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(
        protected ShiftService $shiftService
    ) {}

    /**
     * Display a listing of shifts.
     */
    public function index(Request $request): Response
    {
        $perPage = $request->integer('per_page', 10);
        $filters = $request->only(['search', 'start', 'end', 'status']);

        // Default to showing open shifts only
        if (! $request->has('status')) {
            $filters['status'] = ShiftStatus::Open->value;
        }

        $shifts = $this->shiftService->getPaginatedShifts(
            $request->user(),
            $filters,
            $perPage
        );

        $hasActiveShift = $this->shiftService->hasActiveShift($request->user());

        return Inertia::render('Shift/Index', [
            'shifts' => $shifts,
            'hasActiveShift' => $hasActiveShift,
            'filters' => [
                'status' => $filters['status'] ?? ShiftStatus::Open->value,
                'start' => $filters['start'] ?? '',
                'end' => $filters['end'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }

    /**
     * Store a newly opened shift.
     */
    public function store(StoreShiftRequest $request): RedirectResponse
    {
        try {
            $this->shiftService->openShift(
                $request->user(),
                (float) $request->validated('starting_cash')
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('shift.index')
            ->with('success', 'Shift berhasil dibuka.');
    }

    /**
     * Display the specified shift.
     */
    public function show(Shift $shift): Response
    {
        return Inertia::render('Shift/Show', [
            'shift' => $this->shiftService->getShiftDetails($shift),
        ]);
    }

    /**
     * Close the specified shift.
     */
    public function close(CloseShiftRequest $request, Shift $shift): RedirectResponse
    {
        try {
            $this->shiftService->closeShift(
                $shift,
                (float) $request->validated('actual_cash'),
                $request->validated('notes')
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('shift.show', $shift)
            ->with('success', 'Shift berhasil ditutup.');
    }
}
