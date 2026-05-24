<?php

namespace App\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    use HasFactory, LogsActivity, Sluggable, SoftDeletes;

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
     * Get the activity log options for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
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

    /**
     * Get the warehouses that stock this product.
     *
     * @return BelongsToMany
     */
    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'warehouse_product')
            ->withPivot('stock')
            ->withTimestamps();
    }

    /**
     * Get the stock transfers for this product.
     *
     * @return HasMany
     */
    public function stockTransfers()
    {
        return $this->hasMany(StockTransfer::class);
    }

    /**
     * Sync the global stock field from the sum of all warehouse-specific stocks.
     * Call this after any warehouse_product mutation to keep products.stock accurate.
     */
    public function syncStockFromWarehouses(): void
    {
        $this->update(['stock' => $this->warehouses()->sum('warehouse_product.stock')]);
    }

    /**
     * Get the stock level of this product in a specific warehouse.
     */
    public function stockInWarehouse(int $warehouseId): int
    {
        $pivot = $this->warehouses()->where('warehouses.id', $warehouseId)->first();

        return $pivot ? (int) $pivot->pivot->stock : 0;
    }
}
