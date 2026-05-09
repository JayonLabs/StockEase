<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturn extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_id',
        'user_id',
        'shift_id',
        'return_type',
        'total_refund',
        'reason',
        'notes',
        'return_date',
        'status',
    ];

    /**
     * Define the model's castable attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_refund' => 'decimal:4',
            'return_date' => 'date',
        ];
    }

    /**
     * Get the sale that was returned.
     *
     * @return BelongsTo
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the user that processed the return.
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the shift during which the return was processed.
     *
     * @return BelongsTo
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the sale return items.
     *
     * @return HasMany
     */
    public function saleReturnItems()
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
