<?php

namespace App\Enums;

enum SaleReturnStatus: string
{
    case Completed = 'completed';
    case Canceled = 'canceled';

    /**
     * Get the Indonesian label for the sale return status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Selesai',
            self::Canceled => 'Dibatalkan',
        };
    }

    /**
     * Determine if the sale return has been completed.
     */
    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    /**
     * Determine if the sale return has been canceled.
     */
    public function isCanceled(): bool
    {
        return $this === self::Canceled;
    }
}
