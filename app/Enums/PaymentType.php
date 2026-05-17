<?php

namespace App\Enums;

enum PaymentType: string
{
    case Qris = 'qris';

    /**
     * Get the display label for the payment type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Qris => 'QRIS',
        };
    }
}
