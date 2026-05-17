<?php

namespace App\Http\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\UpdateUserPermissionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class UserPermissionController extends Controller
{
    /**
     * Display users with their direct permissions and roles.
     *
     * Best practice: Users should rarely be given direct permissions.
     * Permissions are best assigned to roles, and users inherit via roles.
     * Direct permissions should only be used for exception cases.
     */
    public function index(Request $request)
    {
        $perPage = $request->integer('per_page', 10);

        $users = User::query()
            ->with(['permissions', 'roles'])
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $permissions = Permission::all();

        return Inertia::render('UserPermission/Index', [
            'users' => $users,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show the form for editing direct permissions for the specified user.
     */
    public function edit(User $user)
    {
        $user->load(['permissions', 'roles']);

        $permissions = Permission::all();

        return Inertia::render('UserPermission/Edit', [
            'user' => $user,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Sync direct permissions for the specified user.
     *
     * WARNING: Direct permissions should be used sparingly.
     * Prefer assigning roles to users instead.
     */
    public function update(UpdateUserPermissionRequest $request, User $user)
    {
        $user->syncPermissions($request->input('permissions', []));

        return redirect()->back()->with('success', 'Permission user berhasil diperbarui');
    }
}
