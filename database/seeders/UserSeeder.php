<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class UserSeeder extends Seeder
{
    public const DEMO_EMAIL = 'superadmin@dewajayon.my.id';

    public const DEMO_PASSWORD = 'password';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory(10)->create();

        // Best practice: Use firstOrCreate for idempotent seeding
        // — safe to run multiple times without duplicate errors.
        $superAdmin = User::firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Dewa Jayon',
                'password' => Hash::make(self::DEMO_PASSWORD),
            ]
        );

        $superAdmin->assignRole(Role::SuperAdmin->value);

        // Grant ALL permissions directly to this user
        // Ensures access to all pages including permission-gated features
        // like activity logs and queue worker logs
        $allPermissions = Permission::all();
        $superAdmin->syncPermissions($allPermissions);
    }
}
