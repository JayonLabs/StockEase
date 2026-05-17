<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
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
     * Determine whether the user can view any stock transfers.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_stock_transfers');
    }

    /**
     * Determine whether the user can view a specific stock transfer.
     */
    public function view(User $user, StockTransfer $stockTransfer): bool
    {
        return $user->can('view_stock_transfers');
    }

    /**
     * Determine whether the user can create a new stock transfer.
     */
    public function create(User $user): bool
    {
        return $user->can('create_stock_transfers');
    }
}
