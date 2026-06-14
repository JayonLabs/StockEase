<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionInvoice>
 */
class SubscriptionInvoiceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SubscriptionInvoice>
     */
    protected $model = SubscriptionInvoice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subscription = Subscription::factory()->create();

        return [
            'subscription_id' => $subscription->id,
            'user_id' => User::factory()->create(['company_id' => $subscription->company_id])->id,
            'amount' => fake()->numberBetween(50000, 500000),
            'status' => fake()->randomElement(['paid', 'pending', 'failed', 'refunded']),
            'paid_at' => fake()->optional()->dateTimeThisYear(),
        ];
    }

    /**
     * Indicate that the invoice has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the invoice is pending payment.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending', 'paid_at' => null]);
    }
}
