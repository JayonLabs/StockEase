<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
            'old_stock' => $this->faker->numberBetween(10, 50),
            'new_stock' => $this->faker->numberBetween(10, 50),
            'reason' => $this->faker->sentence(),
            'date' => $this->faker->date(),
        ];
    }
}
