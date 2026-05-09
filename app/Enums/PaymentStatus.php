<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Settlement = 'settlement';
    case Success = 'success';
    case Capture = 'capture';
    case Pending = 'pending';
    case Deny = 'deny';
    case Expired = 'expired';
    case Cancel = 'cancel';
    case Challenge = 'challenge';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Settlement => 'Selesai',
            self::Success => 'Sukses',
            self::Capture => 'Tertangkap',
            self::Pending => 'Menunggu',
            self::Deny => 'Ditolak',
            self::Expired => 'Kedaluwarsa',
            self::Cancel => 'Dibatalkan',
            self::Challenge => 'Tantangan',
            self::Unknown => 'Tidak Diketahui',
        };
    }

    /**
     * Check if this status means the payment was successful.
     */
    public function isPaid(): bool
    {
        return in_array($this, [self::Settlement, self::Success, self::Capture], true);
    }
}
