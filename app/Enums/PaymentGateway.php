<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Midtrans = 'midtrans';

    /**
     * Get the label for the payment method.
     */
    public function label(): string
    {
        return match ($this) {
            self::Midtrans => 'Midtrans',
        };
    }
}
