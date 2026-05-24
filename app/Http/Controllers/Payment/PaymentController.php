<?php

namespace App\Http\Controllers\Payment;

use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreateMidtransTransactionRequest;
use App\Models\Sale;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Create Snap Token Midtrans.
     */
    public function createMidtransTransaction(CreateMidtransTransactionRequest $request): JsonResponse
    {
        try {
            $cart = Sale::where('user_id', Auth::id())
                ->where('status', SaleStatus::Draft->value)
                ->firstOrFail();

            $snapToken = $this->paymentService->createSnapToken(
                (float) $cart->total,
                $request->validated('customer_name')
            );

            return response()->json(['snap_token' => $snapToken]);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Gagal membuat token pembayaran: '.$th->getMessage()], 500);
        }
    }

    /**
     * Midtrans Notification handler.
     */
    public function midtransNotification(Request $request)
    {
        try {
            $rawBody = $request->getContent();
            $notificationData = json_decode($rawBody, true);

            $result = $this->paymentService->handleNotification($notificationData, $rawBody);

            return response()->json(['message' => $result['message']], $result['status']);
        } catch (\Throwable $th) {
            $code = $th->getCode() ?: 500;

            return response()->json(['message' => $th->getMessage()], is_numeric($code) && $code >= 100 && $code < 600 ? $code : 500);
        }
    }
}
