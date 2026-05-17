<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'qty' => fake()->numberBetween(1, 100),
            'note' => fake()->sentence(),
            'status' => 'completed',
            'date' => fake()->date(),
        ];
    }
}
