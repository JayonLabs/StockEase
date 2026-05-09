<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Midtrans = 'midtrans';

    public function label(): string
    {
        return match ($this) {
            self::Midtrans => 'Midtrans',
        };
    }
}
