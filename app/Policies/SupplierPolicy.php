<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
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
     * Determine whether the user can view any suppliers.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_suppliers');
    }

    /**
     * Determine whether the user can view a specific supplier.
     */
    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('view_suppliers');
    }

    /**
     * Determine whether the user can create a new supplier.
     */
    public function create(User $user): bool
    {
        return $user->can('create_suppliers');
    }

    /**
     * Determine whether the user can update a supplier.
     */
    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('edit_suppliers');
    }

    /**
     * Determine whether the user can delete a supplier.
     */
    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('delete_suppliers');
    }
}
