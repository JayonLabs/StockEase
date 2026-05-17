<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Perform pre-authorization checks.
     *
     * Best practice: super_admin check in before() method.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_users');
    }

    /**
     * Determine whether the user can view a specific user.
     */
    public function view(User $user): bool
    {
        return $user->can('view_users');
    }

    /**
     * Determine whether the user can create a new user.
     */
    public function create(User $user): bool
    {
        return $user->can('create_users');
    }

    /**
     * Determine whether the user can update an existing user.
     */
    public function update(User $user): bool
    {
        return $user->can('edit_users');
    }

    /**
     * Determine whether the user can delete a user.
     */
    public function delete(User $user): bool
    {
        return $user->can('delete_users');
    }

    /**
     * Determine whether the user can reset another user's password.
     */
    public function resetPassword(User $user): bool
    {
        return $user->can('reset_user_password');
    }
}
