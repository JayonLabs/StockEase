<?php

namespace Database\Factories;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
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
            'opened_at' => now(),
            'starting_cash' => fake()->numberBetween(100000, 1000000),
            'status' => 'open',
        ];
    }

    /**
     * Indicate that the shift is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'closed_at' => now()->addHours(8),
            'actual_cash' => fake()->numberBetween(200000, 2000000),
            'expected_cash' => fake()->numberBetween(200000, 2000000),
            'cash_difference' => fake()->numberBetween(-50000, 50000),
            'notes' => fake()->optional()->sentence(),
        ]);
    }
}
