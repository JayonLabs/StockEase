<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
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
     * Determine whether the user can view any shifts.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_shifts');
    }

    /**
     * Determine whether the user can view a specific shift.
     */
    public function view(User $user, Shift $shift): bool
    {
        return $user->can('view_shifts');
    }

    /**
     * Determine whether the user can open a new shift.
     */
    public function create(User $user): bool
    {
        return $user->can('open_shift');
    }

    /**
     * Determine whether the user can close a shift.
     */
    public function close(User $user, Shift $shift): bool
    {
        return $user->can('close_shift');
    }
}
