<?php

namespace App\Enums;

enum SaleReturnStatus: string
{
    case Completed = 'completed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Selesai',
            self::Canceled => 'Dibatalkan',
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    public function isCanceled(): bool
    {
        return $this === self::Canceled;
    }
}
