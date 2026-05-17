<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
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
     * Determine whether the user can view any products.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_products');
    }

    /**
     * Determine whether the user can view a specific product.
     */
    public function view(User $user, Product $product): bool
    {
        return $user->can('view_products');
    }

    /**
     * Determine whether the user can create a new product.
     */
    public function create(User $user): bool
    {
        return $user->can('create_products');
    }

    /**
     * Determine whether the user can update a product.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->can('edit_products');
    }

    /**
     * Determine whether the user can delete a product.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->can('delete_products');
    }

    /**
     * Determine whether the user can edit a product's price.
     */
    public function editPrice(User $user, Product $product): bool
    {
        return $user->can('edit_product_price');
    }
}
