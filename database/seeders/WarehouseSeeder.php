<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = [
            [
                'name' => 'Gudang Pusat',
                'address' => 'Jl. Raya No. 1, Jakarta Pusat',
                'phone' => '021-1234567',
                'is_active' => true,
            ],
            [
                'name' => 'Toko A',
                'address' => 'Jl. Melati No. 10, Jakarta Selatan',
                'phone' => '021-2345678',
                'is_active' => true,
            ],
            [
                'name' => 'Toko B',
                'address' => 'Jl. Anggrek No. 5, Jakarta Timur',
                'phone' => '021-3456789',
                'is_active' => true,
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::firstOrCreate(
                ['name' => $warehouse['name']],
                $warehouse
            );
        }
    }
}
