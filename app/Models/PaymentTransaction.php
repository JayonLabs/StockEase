<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTransaction extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'sale_id',
        'gateway',
        'external_id',
        'status',
        'amount',
        'payment_type',
        'raw_response',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'company_id' => 'integer',
        ];
    }

    /**
     * Check if the payment has been paid.
     */
    public function isPaid(): bool
    {
        return in_array($this->status, [PaymentStatus::Settlement->value, PaymentStatus::Success->value, PaymentStatus::Capture->value]);
    }

    /**
     * Get the sale that the payment transaction belongs to.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
