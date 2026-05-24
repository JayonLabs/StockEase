<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'warehouse_id' => Warehouse::factory(),
            'user_id' => User::factory(),
            'total' => 0,
            'date' => now(),
        ];
    }
}
