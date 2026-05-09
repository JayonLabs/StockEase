<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_id',
        'gateway',
        'external_id',
        'status',
        'amount',
        'payment_type',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    /**
     * Check if the payment has been paid.
     */
    public function isPaid(): bool
    {
        return in_array($this->status, [PaymentStatus::Settlement->value, PaymentStatus::Success->value, PaymentStatus::Capture->value]);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
