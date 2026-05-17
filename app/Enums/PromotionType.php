<?php

namespace App\Enums;

enum PromotionType: string
{
    case Percentage = 'percentage';
    case Nominal = 'nominal';
    case Bogo = 'bogo';

    /**
     * Get the display label for the promotion type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Persentase',
            self::Nominal => 'Nominal',
            self::Bogo => 'Buy One Get One',
        };
    }
}
