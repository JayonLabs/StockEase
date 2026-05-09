<?php

namespace App\Enums;

enum SaleReturnType: string
{
    case Refund = 'refund';
    case Exchange = 'exchange';

    public function label(): string
    {
        return match ($this) {
            self::Refund => 'Pengembalian Uang',
            self::Exchange => 'Tukar Barang',
        };
    }
}
