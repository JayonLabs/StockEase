<?php

namespace Database\Factories;

use App\Enums\PromotionType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the default state for a promotion.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement([PromotionType::Percentage->value, PromotionType::Nominal->value]),
            'discount_value' => $this->faker->randomFloat(2, 5, 50),
            'start_date' => now()->subDays(2),
            'end_date' => now()->addDays(7),
            'is_active' => true,
        ];
    }

    /**
     * Set the promotion type to percentage with a given discount value.
     */
    public function percentage(float $value = 20): static
    {
        return $this->state([
            'type' => PromotionType::Percentage->value,
            'discount_value' => $value,
        ]);
    }

    /**
     * Set the promotion type to nominal with a given discount value.
     */
    public function nominal(float $value = 5000): static
    {
        return $this->state([
            'type' => PromotionType::Nominal->value,
            'discount_value' => $value,
        ]);
    }

    /**
     * Set the promotion type to BOGO (Buy One Get One) with given buy and get quantities.
     */
    public function bogo(int $buyQty = 1, int $getQty = 1): static
    {
        return $this->state([
            'type' => PromotionType::Bogo->value,
            'buy_qty' => $buyQty,
            'get_qty' => $getQty,
            'discount_value' => 0,
        ]);
    }

    /**
     * Set the promotion status to inactive.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Associate the promotion with a specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(['product_id' => $product->id, 'category_id' => null]);
    }

    /**
     * Associate the promotion with a specific category.
     */
    public function forCategory(Category $category): static
    {
        return $this->state(['category_id' => $category->id, 'product_id' => null]);
    }
}
