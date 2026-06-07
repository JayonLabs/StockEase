<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Get paginated users with search filter.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getPaginatedUsers(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when($companyId = Auth::user()?->company_id, function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Store a newly created user.
     *
     * @param  array<string, mixed>  $data
     */
    public function storeUser(array $data): User
    {
        $role = $data['role'] ?? null;
        unset($data['role']);

        $data['email_verified_at'] = now();
        $data['password'] = Hash::make($data['password']);
        $data['company_id'] = Auth::user()?->company_id;

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
     * Reset the password for a user.
     */
    public function resetPassword(User $user, string $password): bool
    {
        return $user->update([
            'password' => Hash::make($password),
        ]);
    }
}
