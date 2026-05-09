<?php

namespace App\Enums;

enum PaymentType: string
{
    case Qris = 'qris';

    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
        };
    }
}
