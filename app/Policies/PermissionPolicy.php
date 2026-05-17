<?php

namespace App\Policies;

use App\Models\User;

class PermissionPolicy
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
     * Determine whether the user can view any permissions.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_permissions');
    }

    /**
     * Determine whether the user can view a specific permission.
     */
    public function view(User $user): bool
    {
        return $user->can('view_permissions');
    }

    /**
     * Determine whether the user can create a new permission.
     */
    public function create(User $user): bool
    {
        return $user->can('create_permissions');
    }

    /**
     * Determine whether the user can update a permission.
     */
    public function update(User $user): bool
    {
        return $user->can('edit_permissions');
    }

    /**
     * Determine whether the user can delete a permission.
     */
    public function delete(User $user): bool
    {
        return $user->can('delete_permissions');
    }
}
