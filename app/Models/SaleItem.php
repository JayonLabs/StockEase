<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_id',
        'product_id',
        'warehouse_id',
        'qty',
        'price',
        'cost_price',
        'promotion_id',
        'discount_amount',
    ];

    /**
     * Define the model's castable attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'cost_price' => 'decimal:4',
            'discount_amount' => 'decimal:4',
        ];
    }

    /**
     * Get the promotion applied to this sale item.
     *
     * @return BelongsTo
     */
    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    /**
     * Get the sale that this sale item belongs to.
     *
     * @return BelongsTo
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the product that this sale item belongs to.
     *
     * @return BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse that this sale item belongs to.
     *
     * @return BelongsTo
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
