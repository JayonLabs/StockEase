<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    /**
     * Perform pre-authorization checks for super admin.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any warehouses.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_warehouses');
    }

    /**
     * Determine whether the user can view a specific warehouse.
     */
    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('view_warehouses');
    }

    /**
     * Determine whether the user can create a new warehouse.
     */
    public function create(User $user): bool
    {
        return $user->can('create_warehouses');
    }

    /**
     * Determine whether the user can update a warehouse.
     */
    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('edit_warehouses');
    }

    /**
     * Determine whether the user can delete a warehouse.
     */
    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('delete_warehouses');
    }
}
