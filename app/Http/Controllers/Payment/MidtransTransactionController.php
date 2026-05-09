<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MidtransTransactionController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);
        $filters = $request->only(['search', 'start', 'end']);

        $midtransTransactions = $this->paymentService->getPaginatedTransactions(
            $filters,
            $perPage
        );

        return Inertia::render('MidtransTransaction/Index', [
            'midtransTransactions' => $midtransTransactions,
            'filters' => [
                'start' => $filters['start'] ?? '',
                'end' => $filters['end'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }
}
