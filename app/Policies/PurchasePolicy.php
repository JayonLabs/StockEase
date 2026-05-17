<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy
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
     * Determine whether the user can view any purchases.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_purchases');
    }

    /**
     * Determine whether the user can view a specific purchase.
     */
    public function view(User $user, Purchase $purchase): bool
    {
        return $user->can('view_purchases');
    }

    /**
     * Determine whether the user can create a new purchase.
     */
    public function create(User $user): bool
    {
        return $user->can('create_purchases');
    }

    /**
     * Determine whether the user can update a purchase.
     */
    public function update(User $user, Purchase $purchase): bool
    {
        return $user->can('edit_purchases');
    }

    /**
     * Determine whether the user can delete a purchase.
     */
    public function delete(User $user, Purchase $purchase): bool
    {
        return $user->can('delete_purchases');
    }
}
