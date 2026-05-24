<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'purchase_id',
        'warehouse_id',
        'product_id',
        'qty',
        'remaining_qty',
        'price',
        'expiry_date',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    /**
     * Get the purchase that the purchase item belongs to.
     *
     * @return BelongsTo
     */
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Get the product that the purchase item belongs to.
     *
     * @return BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the warehouse that received this purchase item.
     *
     * @return BelongsTo
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
