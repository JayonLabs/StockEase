<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id', 'user_id', 'amount', 'status',
        'midtrans_order_id', 'midtrans_transaction_id',
        'midtrans_payment_type', 'midtrans_raw_response', 'paid_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'midtrans_raw_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Get the subscription that the invoice belongs to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the user that created the invoice.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
