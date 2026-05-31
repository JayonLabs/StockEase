<?php

namespace App\Enums;

enum EmailStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * Get the Indonesian label for the email status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal',
        };
    }

    /**
     * Determine if the email is in a final state.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Sent, self::Failed], true);
    }
}
