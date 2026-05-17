<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Performance best practices applied:
     * - Permission::make()->saveOrFail() instead of Permission::create()
     * - $permission->assignRole($role) instead of $role->givePermissionTo()
     * - super_admin does NOT get explicit permissions (handled by Gate::before)
     */
    public function run(): void
    {
        // Create all permissions first
        $this->call(PermissionSeeder::class);

        // Create roles
        $roles = ['super_admin', 'admin', 'cashier', 'warehouse'];
        foreach ($roles as $roleName) {
            Role::findOrCreate($roleName, 'web');
        }

        // Assign permissions to roles (excluding super_admin)
        $permissionSeeder = new PermissionSeeder;
        $rolePermissions = $permissionSeeder->getRolePermissions();

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findByName($roleName, 'web');
            $permissionModels = Permission::whereIn('name', $permissions)
                ->where('guard_name', 'web')
                ->get();

            // Performance best practice: assignRole on permission is faster
            // than givePermissionTo on role for bulk operations
            foreach ($permissionModels as $permission) {
                $permission->assignRole($role);
            }
        }
    }
}
