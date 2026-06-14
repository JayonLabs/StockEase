<?php

namespace Database\Factories;

use App\Models\PlatformDailySnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformDailySnapshot>
 */
class PlatformDailySnapshotFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PlatformDailySnapshot>
     */
    protected $model = PlatformDailySnapshot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'snapshot_date' => fake()->date(),
            'total_companies' => fake()->numberBetween(10, 100),
            'active_companies' => fn (array $attrs) => fake()->numberBetween(5, $attrs['total_companies']),
            'total_users' => fake()->numberBetween(50, 500),
            'active_subscriptions' => fake()->numberBetween(5, 50),
            'mrr' => fake()->randomFloat(2, 1000000, 50000000),
            'subscription_breakdown' => [
                ['plan' => 'Pemula', 'slug' => 'pemula', 'count' => 10],
                ['plan' => 'Profesional', 'slug' => 'profesional', 'count' => 5],
            ],
        ];
    }
}
