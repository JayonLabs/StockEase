<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    /**
     * Get the Indonesian label for the shift status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Buka',
            self::Closed => 'Tutup',
        };
    }

    /**
     * Determine if the shift is currently open.
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
