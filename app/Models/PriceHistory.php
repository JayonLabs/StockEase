<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PriceHistory extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'user_id',
        'old_purchase_price',
        'new_purchase_price',
        'old_selling_price',
        'new_selling_price',
        'reason',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_purchase_price' => 'decimal:4',
            'new_purchase_price' => 'decimal:4',
            'old_selling_price' => 'decimal:4',
            'new_selling_price' => 'decimal:4',
        ];
    }

    /**
     * Get the product that the price history belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user that changed the price.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
