<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Company extends BaseTenant
{
    use HasDomains, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'companies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'address',
        'phone',
        'is_active',
        'had_trial',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'had_trial' => 'boolean',
        ];
    }

    /**
     * Get the custom columns for the tenant model.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'slug', 'owner_id', 'address', 'phone', 'is_active', 'had_trial', 'created_at', 'updated_at'];
    }

    /**
     * Get the after listeners for the tenant model.
     *
     * @return array<int, mixed>
     */
    protected function getAfterListeners(): array
    {
        return [];
    }

    /**
     * Encode the attributes for tenant storage.
     */
    protected function encodeAttributes(): void
    {
        //
    }

    /**
     * Decode the virtual column for tenant storage.
     */
    protected function decodeVirtualColumn(): void
    {
        //
    }

    /**
     * Get the owner of the company.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the users that belong to the company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }

    /**
     * Get the subscription for the company.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Get the active subscription for the company.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscription()
            ->whereIn('status', ['active', 'trialing'])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->first();
    }

    /**
     * Get the current active plan for the company.
     */
    public function currentPlan(): ?Plan
    {
        return $this->activeSubscription()?->plan;
    }

    /**
     * Determine whether this company has ever started a trial subscription.
     * Reads from the `had_trial` column set by SubscriptionService when a trial is created.
     */
    public function hadTrial(): bool
    {
        return (bool) $this->had_trial;
    }
}
