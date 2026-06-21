<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

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
     * Check if the plan is free.
     */
    public function isFree(): bool
    {
        return (float) $this->price_monthly === 0.0
            && (float) $this->price_annual === 0.0;
    }

    /**
     * Calculate the annual savings percentage compared to monthly billing.
     */
    public function annualSavingsPercent(): int
    {
        if ($this->isFree() || (float) $this->price_monthly === 0.0) {
            return 0;
        }

        $monthlyTotal = (float) $this->price_monthly * 12;
        $annualTotal = (float) $this->price_annual;

        if ($monthlyTotal <= 0) {
            return 0;
        }

        return (int) round((1 - ($annualTotal / $monthlyTotal)) * 100);
    }

    /**
     * Get the monthly equivalent price for annual billing,
     * rounded up to the nearest 100.
     */
    public function annualPerMonth(): int
    {
        if ((float) $this->price_annual === 0.0) {
            return 0;
        }

        return (int) (ceil((float) $this->price_annual / 12 / 100) * 100);
    }

    /**
     * Get the price for a given billing cycle.
     */
    public function priceForBilling(string $billingCycle = 'monthly'): int
    {
        return $billingCycle === 'annual'
            ? (int) $this->price_annual
            : (int) $this->price_monthly;
    }

    /**
     * Get the features filtered and sorted for display on pricing cards.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cardFeatures(): array
    {
        return collect($this->features ?? [])
            ->filter(fn (array $feature) => isset($feature['card_order']))
            ->sortBy('card_order')
            ->values()
            ->all();
    }

    /**
     * Get the comparison data for all active plans.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function comparisonFeatures(): array
    {
        $plans = static::active()->get();

        $keys = $plans->flatMap(fn (Plan $plan) => collect($plan->features ?? [])->pluck('key'))->unique();

        return $keys->map(function (string $key) use ($plans) {
            $label = $plans->first()->featureLabelByKey($key);

            return [
                'key' => $key,
                'label' => $label,
                'plans' => $plans->mapWithKeys(fn (Plan $plan) => [
                    $plan->slug => $plan->featureIncludedByKey($key),
                ])->all(),
            ];
        })->values()->all();
    }

    /**
     * Ambil data halaman pricing yang sudah di-cache untuk semua plan aktif.
     *
     * Cache key: 'plans_pricing' dengan flexible TTL 1 hari (stale) / 7 hari (expired).
     * Wajib diinvalidasi via Cache::forget('plans_pricing') setiap kali data plan
     * diperbarui melalui PlanSeeder atau admin panel agar perubahan segera terlihat.
     *
     * @return array<string, mixed>
     */
    public static function forPricingPage(): array
    {
        return Cache::flexible('plans_pricing', [86400, 604800], function () {
            $plans = static::active()->get();

            return [
                'plans' => $plans->map(fn (Plan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price_monthly' => (int) $plan->price_monthly,
                    'price_annual' => (int) $plan->price_annual,
                    'annual_per_month' => $plan->annualPerMonth(),
                    'annual_savings_percent' => $plan->annualSavingsPercent(),
                    'features' => $plan->cardFeatures(),
                    'trial_days' => $plan->trial_days,
                    'is_free' => $plan->isFree(),
                    'sort_order' => $plan->sort_order,
                    'max_products' => $plan->max_products,
                    'max_users' => $plan->max_users,
                    'max_warehouses' => $plan->max_warehouses,
                ])->values()->all(),
                'comparison' => static::comparisonFeatures(),
            ];
        });
    }

    /**
     * Cek apakah fitur dengan kunci tertentu tersedia di plan ini.
     *
     * Digunakan oleh middleware CheckPlanFeature untuk memvalidasi
     * akses pengguna ke route yang dibatasi berdasarkan plan.
     */
    public function hasFeature(string $key): bool
    {
        foreach ($this->features ?? [] as $feature) {
            if (($feature['key'] ?? null) === $key) {
                return (bool) ($feature['included'] ?? false);
            }
        }

        return false;
    }

    /**
     * Get the label for a feature by its key.
     */
    private function featureLabelByKey(string $key): string
    {
        foreach ($this->features ?? [] as $feature) {
            if (($feature['key'] ?? null) === $key) {
                return $feature['label'] ?? $key;
            }
        }

        return $key;
    }

    /**
     * Check if a feature is included by its key.
     *
     * @deprecated Gunakan hasFeature() yang bersifat publik.
     */
    private function featureIncludedByKey(string $key): bool
    {
        return $this->hasFeature($key);
    }
}
