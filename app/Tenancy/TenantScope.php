<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the tenant scope to the query.
     *
     * Adds a where clause filtering by the tenant key column
     * to the current tenant's key, unless tenancy is not initialized.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! tenancy()->initialized) {
            return;
        }

        $builder->where(
            $model->qualifyColumn(config('tenancy.tenant_key_column', 'company_id')),
            tenant()->getTenantKey(),
        );
    }

    /**
     * Extend the query builder with a `withoutTenancy` macro.
     *
     * Allows temporarily removing the tenant scope from a query.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withoutTenancy', function (Builder $builder) {
            return $builder->withoutGlobalScope($this);
        });
    }
}
