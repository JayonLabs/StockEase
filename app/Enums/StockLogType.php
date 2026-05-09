<?php

namespace App\Enums;

enum StockLogType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Masuk',
            self::Out => 'Keluar',
            self::Adjust => 'Penyesuaian',
        };
    }
}
