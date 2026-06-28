<?php

namespace App\Http\Controllers\Payment;

use App\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreateMidtransTransactionRequest;
use App\Models\Sale;
use App\Models\SubscriptionInvoice;
use App\Services\Payment\PaymentService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected PaymentService $paymentService,
        protected SubscriptionService $subscriptionService,
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

            $orderId = $notificationData['order_id'];

            if (str_starts_with($orderId, 'SUB-')) {
                $this->handleSubscriptionNotification((object) $notificationData);

                return;
            }

            $result = $this->paymentService->handleNotification($notificationData, $rawBody);

            return response()->json(['message' => $result['message']], $result['status']);
        } catch (\Throwable $th) {
            $code = $th->getCode() ?: 500;

            return response()->json(['message' => $th->getMessage()], is_numeric($code) && $code >= 100 && $code < 600 ? $code : 500);
        }
    }

    /**
     * Handle a Midtrans subscription notification.
     *
     * On settlement/capture the invoice is marked as paid and the subscription
     * is activated. On deny/cancel/expire the invoice is marked as failed and
     * the pending subscription is cancelled — the company falls back to the
     * free plan so the user can retry from a clean state.
     */
    private function handleSubscriptionNotification(object $notification): void
    {
        $invoice = SubscriptionInvoice::where(
            'midtrans_order_id',
            $notification->order_id
        )->firstOrFail();

        $transactionStatus = $notification->transaction_status;

        if (in_array($transactionStatus, ['settlement', 'capture'])) {
            $invoice->update([
                'status' => 'paid',
                'midtrans_transaction_id' => $notification->transaction_id,
                'midtrans_payment_type' => $notification->payment_type,
                'midtrans_raw_response' => (array) $notification,
                'paid_at' => now(),
            ]);

            $this->subscriptionService->activateSubscription(
                $invoice->subscription
            );
        }

        if (in_array($transactionStatus, ['deny', 'cancel', 'expire'])) {
            $this->subscriptionService->handleFailedPayment($invoice);
        }
    }
}
