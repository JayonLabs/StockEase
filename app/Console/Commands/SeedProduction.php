<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as RoleModel;

class SeedProduction extends Command
{
    public const PLATFORM_OWNER_EMAIL = 'dewajayon3@gmail.com';

    protected $signature = 'stockease:seed-production
                            {--force : Skip confirmation prompt}';

    protected $description = 'Seed roles, permissions, admin user, and platform owner without refreshing the database';

    /**
     * Execute the console command.
     *
     * Runs all seed steps idempotently — safe to call multiple times
     * without creating duplicates.
     */
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Ini akan menambah/memperbarui role, permission, dan user. Lanjutkan?')) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $this->seedRolesAndPermissions();
        $this->seedAdminUser();
        $this->seedPlatformOwner();

        $this->newLine();
        $this->info('✅ Production seed selesai.');

        return self::SUCCESS;
    }

    /**
     * Seed all permissions and role assignments without duplicating existing ones.
     *
     * The platform_owner role carries no explicit permission assignments —
     * access is enforced at the route level via the role:platform_owner middleware.
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

        $this->line('📦 Menyiapkan role...');
        $roles = ['super_admin', 'platform_owner', 'admin', 'cashier', 'warehouse'];

        foreach ($roles as $roleName) {
            RoleModel::findOrCreate($roleName, 'web');
        }

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

        $this->line('   Role: super_admin, platform_owner, admin, cashier, warehouse siap.');
    }

    /**
     * Create or update the super-admin tenant user and grant all permissions.
     *
     * All permissions are synced directly so this user bypasses per-role
     * restrictions and can access every feature, including permission-gated
     * pages such as activity logs.
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

        $allPermissions = Permission::all();
        $user->syncPermissions($allPermissions);

        $this->line("   {$allPermissions->count()} permission diberikan ke {$user->email}.");
    }

    /**
     * Create or update the platform owner user.
     *
     * The platform owner requires a company record (used as the platform's
     * own tenant context) and must hold both the super_admin and
     * platform_owner roles so the role:platform_owner middleware passes.
     */
    private function seedPlatformOwner(): void
    {
        $this->line('🏢 Menyiapkan platform owner...');

        $company = Company::firstOrCreate(
            ['slug' => 'stockease-platform'],
            [
                'name' => 'StockEase Platform',
                'is_active' => true,
            ]
        );

        $owner = User::firstOrCreate(
            ['email' => self::PLATFORM_OWNER_EMAIL],
            [
                'name' => 'Dewa Jayon',
                'password' => Hash::make(UserSeeder::DEMO_PASSWORD),
                'company_id' => $company->id,
            ]
        );

        if ($owner->company_id !== $company->id) {
            $owner->update(['company_id' => $company->id]);
        }

        $roles = [Role::SuperAdmin->value, Role::PlatformOwner->value];

        foreach ($roles as $roleName) {
            if (! $owner->hasRole($roleName)) {
                $owner->assignRole($roleName);
            }
        }

        $this->line("   Platform owner {$owner->email} siap (company: {$company->name}).");
    }
}
