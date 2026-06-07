<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /**
     * Perform pre-authorization checks for super admin.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(Role::SuperAdmin->value)) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can send an invoice for the sale.
     *
     * Admins may send invoices for any sale; cashiers only for their own.
     */
    public function sendInvoice(User $user, Sale $sale): bool
    {
        if ($user->hasRole(Role::Admin->value)) {
            return true;
        }

        return $user->id === $sale->user_id;
    }
}
