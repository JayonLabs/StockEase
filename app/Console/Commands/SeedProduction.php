<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;

class SeedProduction extends Command
{
    protected $signature = 'stockease:seed-production
                            {--force : Skip confirmation prompt}';

    protected $description = 'Seed roles, permissions, and admin user without refreshing the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Ini akan menambah/memperbarui role, permission, dan user. Lanjutkan?')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $this->seedRolesAndPermissions();
        $this->seedAdminUser();

        $this->newLine();
        $this->info('✅ Production seed selesai.');

        return self::SUCCESS;
    }

    /**
     * Seed roles and permissions without duplicating existing ones.
     */
    private function seedRolesAndPermissions(): void
    {
        $this->line('📦 Menyiapkan permission...');

        $permissionSeeder = new PermissionSeeder;
        $permissions = $permissionSeeder->getPermissions();

        $bar = $this->output->createProgressBar(count($permissions));
        $bar->start();

        $created = 0;
        $skipped = 0;

        foreach ($permissions as $permissionName) {
            $exists = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                Permission::make(['name' => $permissionName, 'guard_name' => 'web'])
                    ->saveOrFail();
                $created++;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->line("   {$created} permission baru, {$skipped} sudah ada.");

        // Create roles
        $this->line('📦 Menyiapkan role...');
        $roles = ['super_admin', 'admin', 'cashier', 'warehouse'];

        foreach ($roles as $roleName) {
            RoleModel::findOrCreate($roleName, 'web');
        }

        // Assign permissions to roles
        $rolePermissions = $permissionSeeder->getRolePermissions();

        foreach ($rolePermissions as $roleName => $permNames) {
            $role = RoleModel::findByName($roleName, 'web');
            $permModels = Permission::whereIn('name', $permNames)
                ->where('guard_name', 'web')
                ->get();

            foreach ($permModels as $permission) {
                if (! $role->hasPermissionTo($permission)) {
                    $permission->assignRole($role);
                }
            }
        }

        $this->line('   Role: super_admin, admin, cashier, warehouse siap.');
    }

    /**
     * Create or update the admin user with all permissions.
     */
    private function seedAdminUser(): void
    {
        $this->line('👤 Menyiapkan admin user...');

        $user = User::firstOrCreate(
            ['email' => UserSeeder::DEMO_EMAIL],
            [
                'name' => 'Dewa Jayon',
                'password' => Hash::make(UserSeeder::DEMO_PASSWORD),
            ]
        );

        if (! $user->hasRole(Role::SuperAdmin->value)) {
            $user->assignRole(Role::SuperAdmin->value);
            $this->line('   Role super_admin diberikan.');
        }

        // Assign all permissions directly
        $allPermissions = Permission::all();
        $user->syncPermissions($allPermissions);

        $this->line("   {$allPermissions->count()} permission diberikan ke {$user->email}.");
    }
}
