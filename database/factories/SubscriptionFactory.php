<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Subscription>
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'plan_id' => Plan::factory(),
            'status' => fake()->randomElement(['active', 'trialing', 'expired', 'canceled']),
            'billing_cycle' => fake()->randomElement(['monthly', 'annual']),
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'auto_renew' => true,
        ];
    }

    /**
     * Indicate that the subscription is active.
     */
    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    /**
     * Indicate that the subscription is in trial.
     */
    public function trialing(): static
    {
        return $this->state(fn () => ['status' => 'trialing']);
    }

    /**
     * Indicate that the subscription has expired.
     */
    public function expired(): static
    {
        return $this->state(fn () => ['status' => 'expired']);
    }

    /**
     * Indicate that the subscription has been canceled.
     */
    public function canceled(): static
    {
        return $this->state(fn () => ['status' => 'canceled']);
    }

    /**
     * Indicate that the subscription is billed monthly.
     */
    public function monthly(): static
    {
        return $this->state(fn () => ['billing_cycle' => 'monthly']);
    }

    /**
     * Indicate that the subscription is billed annually.
     */
    public function annual(): static
    {
        return $this->state(fn () => ['billing_cycle' => 'annual']);
    }
}
