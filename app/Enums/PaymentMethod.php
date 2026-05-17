<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case Pending = 'pending';

    /**
     * Get the Indonesian label for the payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai',
            self::Qris => 'QRIS',
            self::Pending => 'Menunggu',
        };
    }
}
