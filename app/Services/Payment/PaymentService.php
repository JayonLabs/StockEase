<?php

namespace App\Services\Payment;

use App\Actions\Product\ReduceProductStock;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Models\PaymentTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Notification;
use Midtrans\Snap;

class PaymentService
{
    /**
     * Create a new service instance.
     */
    public function __construct()
    {
        $this->initMidtrans();
    }

    /**
     * Initialize Midtrans configuration.
     */
    protected function initMidtrans(): void
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    /**
     * Create Midtrans Snap Token for POS transaction.
     */
    public function createSnapToken(float $amount, ?string $customerName = null): string
    {
        $orderId = 'ORDER-'.Str::random(5).time();

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $amount,
            ],
            'payment_type' => 'qris',
            'qris' => [],
            'customer_details' => [
                'first_name' => $customerName ?? 'Customer POS',
            ],
        ];

        return Snap::getSnapToken($params);
    }

    /**
     * Handle Midtrans notification and update transaction status.
     *
     * @param  array<string, mixed>  $notificationData
     */
    public function handleNotification(array $notificationData, string $rawBody): array
    {
        $orderId = $notificationData['order_id'];
        $statusCode = $notificationData['status_code'];
        $grossAmount = $notificationData['gross_amount'];
        $signatureKey = $notificationData['signature_key'];

        $validSignatureKey = hash('sha512', $orderId.$statusCode.$grossAmount.config('midtrans.server_key'));

        if ($signatureKey !== $validSignatureKey) {
            throw new \Exception('Invalid signature', 403);
        }

        $paymentTransaction = PaymentTransaction::where('external_id', $orderId)->first();

        if (! $paymentTransaction) {
            throw new \Exception('Transaksi tidak ditemukan', 404);
        }

        // Validate that the amount matches our database record
        if ((float) $grossAmount !== (float) $paymentTransaction->amount) {
            throw new \Exception('Nominal pembayaran tidak sesuai', 400);
        }

        if ($paymentTransaction->isPaid()) {
            return ['message' => 'Orderan sudah dibayar', 'status' => 200];
        }

        return DB::transaction(function () use ($notificationData, $rawBody, $paymentTransaction) {
            $transactionStatus = $notificationData['transaction_status'];
            $type = $notificationData['payment_type'] ?? 'unknown';
            $fraud = $notificationData['fraud_status'] ?? null;

            $paymentStatus = match ($transactionStatus) {
                'capture' => ($type === 'credit_card' && $fraud === 'challenge') ? 'challenge' : 'success',
                'settlement' => 'settlement',
                'pending' => 'pending',
                'deny' => 'deny',
                'expire' => 'expired',
                'cancel' => 'cancel',
                default => 'unknown',
            };

            $paymentTransaction->update([
                'payment_type' => $type,
                'status' => $paymentStatus,
                'raw_response' => $rawBody,
            ]);

            if (in_array($paymentStatus, [PaymentStatus::Settlement->value, PaymentStatus::Success->value, PaymentStatus::Capture->value])) {
                $sale = $paymentTransaction->sale;
                if ($sale && $sale->status !== SaleStatus::Completed->value) {
                    $sale->update(['status' => SaleStatus::Completed->value]);
                    resolve(ReduceProductStock::class)->execute($sale->saleItems, $sale->warehouse_id);
                }
            }

            return ['message' => 'Success', 'status' => 200];
        });
    }

    /**
     * Get paginated payment transactions with filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedTransactions(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $startDate = $filters['start'] ?? null;
        $endDate = $filters['end'] ?? null;

        return PaymentTransaction::with([
            'sale',
            'sale.saleItems',
            'sale.saleItems.product',
        ])
            ->when($filters['search'] ?? null, function ($query, $search) {
                return $query->where('external_id', 'like', '%'.$search.'%');
            })
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay(),
                ]);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
