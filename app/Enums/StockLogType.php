<?php

namespace App\Enums;

enum StockLogType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjust = 'adjust';
    case Transfer = 'transfer';

    /**
     * Get the Indonesian label for the stock log type.
     */
    public function label(): string
    {
        return match ($this) {
            self::In => 'Masuk',
            self::Out => 'Keluar',
            self::Adjust => 'Penyesuaian',
            self::Transfer => 'Pemindahan',
        };
    }
}
