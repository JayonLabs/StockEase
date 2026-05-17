<?php

namespace App\Http\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Permission\UpdateRolePermissionRequest;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    /**
     * Display roles with their permissions.
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        return Inertia::render('RolePermission/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Show the form for editing permissions for the specified role.
     */
    public function edit(Role $role)
    {
        $role->load('permissions');

        $permissions = Permission::all();

        return Inertia::render('RolePermission/Edit', [
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Sync permissions for the specified role.
     */
    public function update(UpdateRolePermissionRequest $request, Role $role)
    {
        $role->syncPermissions($request->input('permissions', []));

        return redirect()->back()->with('success', 'Permission role berhasil diperbarui');
    }
}
