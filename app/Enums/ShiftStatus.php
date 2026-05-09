<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Buka',
            self::Closed => 'Tutup',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
