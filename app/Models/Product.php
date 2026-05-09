<?php

namespace App\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, Sluggable, SoftDeletes;

    protected $fillable = [
        'category_id',
        'slug',
        'name',
        'sku',
        'barcode',
        'unit_id',
        'stock',
        'purchase_price',
        'selling_price',
        'alert_stock',
        'expiry_date',
        'image_path',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'expiry_date' => 'date',
        ];
    }

    /**
     * Return the sluggable configuration for the model.
     *
     * @return array<string, mixed>
     *
     * @see Sluggable
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Get the category that the product belongs to.
     *
     * @return BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the unit that the product belongs to.
     *
     * @return BelongsTo
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the purchase items that belong to the product.
     *
     * @return HasMany
     */
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Get the sale items that belong to the product.
     *
     * @return HasMany
     */
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Get the price histories for the product.
     *
     * @return HasMany
     */
    public function priceHistories()
    {
        return $this->hasMany(PriceHistory::class);
    }

    /**
     * Get the stock logs that belong to the product.
     *
     * @return HasMany
     */
    public function stockLogs()
    {
        return $this->hasMany(StockLog::class);
    }
}
