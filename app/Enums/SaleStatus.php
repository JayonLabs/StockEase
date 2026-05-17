<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Completed = 'completed';
    case Canceled = 'canceled';

    /**
     * Get the Indonesian label for the sale status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Menunggu Pembayaran',
            self::Completed => 'Selesai',
            self::Canceled => 'Dibatalkan',
        };
    }
}
