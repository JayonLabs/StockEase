<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Purchase extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'warehouse_id',
        'user_id',
        'total',
        'date',
    ];

    /**
     * Get the casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:4',
        ];
    }

    /**
     * Get the supplier that the purchase belongs to.
     *
     * @return BelongsTo
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the warehouse that received this purchase.
     *
     * @return BelongsTo
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get the user that created the purchase.
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the purchase items that belong to the purchase.
     *
     * @return HasMany
     */
    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
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
}
