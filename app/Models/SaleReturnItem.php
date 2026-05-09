<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturnItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'qty',
        'price',
        'total',
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
            'total' => 'decimal:4',
        ];
    }

    /**
     * Get the sale return that this item belongs to.
     *
     * @return BelongsTo
     */
    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }

    /**
     * Get the sale item that this return item belongs to.
     *
     * @return BelongsTo
     */
    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }

    /**
     * Get the product that this return item belongs to.
     *
     * @return BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
