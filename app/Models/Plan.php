<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_annual',
        'max_products',
        'max_users',
        'max_warehouses',
        'max_shifts',
        'features',
        'trial_days',
        'is_active',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_annual' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'trial_days' => 'integer',
        ];
    }

    /**
     * Get the subscriptions for this plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Scope a query to only include active plans.
     */
    public function scopeActive($query): void
    {
        $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Determine if the plan is free.
     */
    public function isFree(): bool
    {
        return (float) $this->price_monthly === 0.0
            && (float) $this->price_annual === 0.0;
    }
}
