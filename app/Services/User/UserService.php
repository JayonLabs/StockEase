<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Get paginated users with searching.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedUsers(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Store a new user.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeUser(array $data): User
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        $data['email_verified_at'] = now();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    /**
     * Update an existing user.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateUser(User $user, array $data): bool
    {
        if (isset($data['role'])) {
            $user->syncRoles($data['role']);
            unset($data['role']);
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $user->update($data);
    }

    /**
     * Delete a user.
     */
    public function deleteUser(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Reset user password.
     */
    public function resetPassword(User $user, string $password): bool
    {
        return $user->update([
            'password' => Hash::make($password),
        ]);
    }
}
