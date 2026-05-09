<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
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
            'shift_id' => null,
            'customer_name' => fake()->name(),
            'total' => 0,
            'payment_method' => 'cash',
            'paid' => 0,
            'change' => 0,
            'date' => now(),
            'status' => 'completed',
        ];
    }
}
