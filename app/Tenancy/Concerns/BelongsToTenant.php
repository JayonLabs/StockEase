<?php

declare(strict_types=1);

namespace App\Tenancy\Concerns;

use App\Tenancy\TenantScope;
use Stancl\Tenancy\Contracts\Tenant;

/**
 * Drop-in replacement for Stancl\Tenancy\Database\Concerns\BelongsToTenant
 * that avoids PHP 8.4 deprecation of accessing static trait properties
 * directly on the trait name.
 *
 * Only difference: uses static::$tenantIdColumn instead of BelongsToTenant::$tenantIdColumn.
 *
 * @property-read Tenant $tenant
 */
trait BelongsToTenant
{
    public static $tenantIdColumn = 'company_id';

    public function tenant()
    {
        return $this->belongsTo(config('tenancy.tenant_model'), static::$tenantIdColumn);
    }

    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if (! $model->getAttribute(static::$tenantIdColumn) && ! $model->relationLoaded('tenant')) {
                if (tenancy()->initialized) {
                    $model->setAttribute(static::$tenantIdColumn, tenant()->getTenantKey());
                    $model->setRelation('tenant', tenant());
                }
            }
        });
    }
}
