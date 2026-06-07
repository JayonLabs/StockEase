<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Pemula',
                'slug' => 'pemula',
                'description' => 'Untuk usaha kecil yang baru memulai.',
                'price_monthly' => 0,
                'price_annual' => 0,
                'max_products' => 100,
                'max_users' => 3,
                'max_warehouses' => 1,
                'max_shifts' => null,
                'features' => json_encode(['barcode' => false, 'reports' => false, 'multi_role' => false]),
                'trial_days' => 0,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Profesional',
                'slug' => 'profesional',
                'description' => 'Untuk usaha menengah yang berkembang pesat.',
                'price_monthly' => 299000,
                'price_annual' => 249000,
                'max_products' => 1000,
                'max_users' => 10,
                'max_warehouses' => 3,
                'max_shifts' => null,
                'features' => json_encode(['barcode' => true, 'reports' => true, 'multi_role' => true]),
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Untuk bisnis besar dengan kebutuhan lengkap.',
                'price_monthly' => 999000,
                'price_annual' => 849000,
                'max_products' => null,
                'max_users' => null,
                'max_warehouses' => null,
                'max_shifts' => null,
                'features' => json_encode(['barcode' => true, 'reports' => true, 'multi_role' => true, 'priority_support' => true]),
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::create($plan);
        }
    }
}
