<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

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
        // Disable Spatie cache during seeding — reset once at the end.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Bulk insert permissions (single query, no model events).
        $this->call(PermissionSeeder::class);

        // Bulk insert roles (single query).
        $roleNames = ['super_admin', 'platform_owner', 'admin', 'cashier', 'warehouse'];
        $now = now();
        DB::table(config('permission.table_names.roles', 'roles'))
            ->insertOrIgnore(array_map(fn (string $name) => [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ], $roleNames));

        // Bulk assign permissions to roles via pivot — single insertOrIgnore per role.
        $permissionSeeder = new PermissionSeeder;
        $rolePermissions = $permissionSeeder->getRolePermissions();
        $pivotTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        $roles = Role::whereIn('name', array_keys($rolePermissions))->get()->keyBy('name');
        $permissions = Permission::all()->keyBy('name');

        foreach ($rolePermissions as $roleName => $permNames) {
            $role = $roles->get($roleName);
            if (! $role) {
                continue;
            }

            $pivotRows = collect($permNames)
                ->map(fn (string $perm) => $permissions->get($perm))
                ->filter()
                ->map(fn (Permission $p) => ['permission_id' => $p->id, 'role_id' => $role->id])
                ->values()
                ->all();

            if ($pivotRows) {
                DB::table($pivotTable)->insertOrIgnore($pivotRows);
            }
        }

        // Rebuild cache once after all inserts are done.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
