<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturn>
 */
class SaleReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'user_id' => User::factory(),
            'shift_id' => null,
            'return_type' => fake()->randomElement(['refund', 'exchange']),
            'total_refund' => 0,
            'reason' => fake()->sentence(),
            'notes' => null,
            'return_date' => now(),
            'status' => 'completed',
        ];
    }
}
