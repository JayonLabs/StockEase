<?php

namespace Database\Factories;

use App\Enums\EmailStatus;
use App\Models\Sale;
use App\Models\SaleEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleEmail>
 */
class SaleEmailFactory extends Factory
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
            'email' => fake()->safeEmail(),
            'status' => EmailStatus::Pending,
        ];
    }

    /**
     * Indicate that the sale email has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmailStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the sale email has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmailStatus::Failed,
            'error_message' => fake()->sentence(),
        ]);
    }
}
