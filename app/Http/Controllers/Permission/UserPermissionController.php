<?php

namespace App\Http\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\UpdateUserPermissionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            ->where('company_id', Auth::user()->company_id)
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
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
        $this->authorizeCompanyAccess($user);
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
        $this->authorizeCompanyAccess($user);
        $user->syncPermissions($request->input('permissions', []));

        return redirect()->back()->with('success', 'Permission user berhasil diperbarui');
    }

    /**
     * Ensure the user belongs to the same company as the authenticated user.
     */
    private function authorizeCompanyAccess(User $user): void
    {
        $currentUser = Auth::user();

        if ($currentUser->company_id && $user->company_id !== $currentUser->company_id) {
            abort(403, 'Unauthorized access.');
        }
    }
}
