<?php

namespace App\Services\Sale;

use App\Enums\EmailStatus;
use App\Mail\SendSaleInvoice;
use App\Models\Sale;
use App\Models\SaleEmail;
use Illuminate\Support\Facades\Mail;

class SaleEmailService
{
    /**
     * Kirim invoice via email.
     */
    public function sendInvoice(Sale $sale, string $email): SaleEmail
    {
        $saleEmail = SaleEmail::create([
            'sale_id' => $sale->id,
            'email' => $email,
            'status' => EmailStatus::Pending,
        ]);

        try {
            Mail::to($email)->queue(new SendSaleInvoice($sale));

            $saleEmail->update([
                'status' => EmailStatus::Sent,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $th) {
            $saleEmail->update([
                'status' => EmailStatus::Failed,
                'error_message' => $th->getMessage(),
            ]);

            throw $th;
        }

        return $saleEmail;
    }
}
