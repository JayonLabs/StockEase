<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
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
     * Determine whether the user can view any categories.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_categories');
    }

    /**
     * Determine whether the user can view a specific category.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->can('view_categories');
    }

    /**
     * Determine whether the user can create a new category.
     */
    public function create(User $user): bool
    {
        return $user->can('create_categories');
    }

    /**
     * Determine whether the user can update a category.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->can('edit_categories');
    }

    /**
     * Determine whether the user can delete a category.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->can('delete_categories');
    }
}
