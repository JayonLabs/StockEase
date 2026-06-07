<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            RoleAndPermissionSeeder::class,
            UserSeeder::class,
            UnitSeeder::class,
            CategorySeeder::class,
            WarehouseSeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            PromotionSeeder::class,
            PurchaseSeeder::class,
            SaleSeeder::class,
            SaleReturnSeeder::class,
            StockAdjustmentSeeder::class,
            PriceHistorySeeder::class,
            ShiftSeeder::class,
        ]);
    }
}
