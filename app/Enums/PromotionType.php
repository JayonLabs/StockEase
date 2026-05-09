<?php

namespace App\Enums;

enum PromotionType: string
{
    case Percentage = 'percentage';
    case Nominal = 'nominal';
    case Bogo = 'bogo';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Persentase',
            self::Nominal => 'Nominal',
            self::Bogo => 'Buy One Get One',
        };
    }
}
