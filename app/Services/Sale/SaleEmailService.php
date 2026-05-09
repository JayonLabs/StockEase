<?php

namespace App\Services\Sale;

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
            'status' => 'pending',
        ]);

        try {
            Mail::to($email)->queue(new SendSaleInvoice($sale));

            $saleEmail->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $th) {
            $saleEmail->update([
                'status' => 'failed',
                'error_message' => $th->getMessage(),
            ]);

            throw $th;
        }

        return $saleEmail;
    }
}
