<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates a demo company with a platform owner user.
     * Only runs in local and testing environments — skipped in production.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $demoCompany = Company::create([
            'name' => 'Demo Perusahaan',
            'slug' => 'demo-perusahaan',
            'is_active' => true,
        ]);

        $platformOwner = User::factory()->create([
            'name' => 'Platform Owner',
            'email' => 'platform@stockease.app',
            'password' => bcrypt('password'),
            'company_id' => $demoCompany->id,
        ]);

        $platformOwner->syncRoles(['super_admin', 'platform_owner']);
    }
}
