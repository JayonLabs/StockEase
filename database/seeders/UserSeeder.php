<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory(10)->create();

        $superAdmin = User::create([
            'name' => 'Dewa Jayon',
            'email' => 'dewajayon3@gmail.com',
            'password' => Hash::make('password'),
        ]);

        $superAdmin->assignRole(Role::SuperAdmin->value);

        // Grant ALL permissions directly to this user
        // Ensures access to all pages including permission-gated features
        // like activity logs and queue worker logs
        $allPermissions = Permission::all();
        $superAdmin->syncPermissions($allPermissions);
    }
}
