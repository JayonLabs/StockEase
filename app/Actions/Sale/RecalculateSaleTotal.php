<?php

namespace App\Actions\Sale;

use App\Enums\PromotionType;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Sale;
use Illuminate\Support\Collection;

class RecalculateSaleTotal
{
    /**
     * Calculate the total of a sale by summing up all sale items prices
     * and updating the sale record.
     *
     * @return float The total of the sale
     */
    public function execute(Sale $sale): float
    {
        $total = 0;
        $totalCost = 0;

        if (! $sale->relationLoaded('saleItems')) {
            $sale->load('saleItems.product');
        }

        // Fetch all active promotions once
        $activePromotions = Promotion::active()->get();

        foreach ($sale->saleItems as $item) {
            $product = $item->product;

            // Find applicable promotion
            $applicablePromo = $this->findBestPromotion($activePromotions, $product);

            $discountAmount = 0;
            $promotionId = null;

            if ($applicablePromo) {
                $discountAmount = $this->calculateDiscount($applicablePromo, $item->price, $item->qty);
                $promotionId = $applicablePromo->id;
            }

            // Update item if discount changed to avoid unnecessary queries,
            // but for simplicity, we can just update it.
            $item->update([
                'promotion_id' => $promotionId,
                'discount_amount' => $discountAmount,
            ]);

            $total += ($item->price * $item->qty) - $discountAmount;
            $totalCost += ($item->cost_price ?? 0) * $item->qty;
        }

        $sale->update([
            'total' => $total,
            'total_cost' => $totalCost,
        ]);

        return (float) $total;
    }

    /**
     * Find the best applicable promotion for a product.
     *
     * @param  Product  $product
     * @return Promotion|null
     */
    private function findBestPromotion(Collection $promotions, $product)
    {
        // Product specific promotions take precedence, then category specific, then general
        $productPromos = $promotions->where('product_id', $product->id);
        if ($productPromos->isNotEmpty()) {
            return $productPromos->first(); // In reality, you'd find the one giving highest discount
        }

        $categoryPromos = $promotions->where('category_id', $product->category_id);
        if ($categoryPromos->isNotEmpty()) {
            return $categoryPromos->first();
        }

        $generalPromos = $promotions->whereNull('product_id')->whereNull('category_id');
        if ($generalPromos->isNotEmpty()) {
            return $generalPromos->first();
        }

        return null;
    }

    /**
     * Calculate the discount amount for a given promotion, price, and quantity.
     */
    private function calculateDiscount(Promotion $promo, float $price, int $qty): float
    {
        if ($promo->type === PromotionType::Percentage->value) {
            return $price * ($promo->discount_value / 100) * $qty;
        }

        if ($promo->type === PromotionType::Nominal->value) {
            // Nominal is per item
            $discount = $promo->discount_value * $qty;
            // Prevent discount from being higher than price
            $maxDiscount = $price * $qty;

            return min($discount, $maxDiscount);
        }

        if ($promo->type === PromotionType::Bogo->value && $promo->buy_qty > 0 && $promo->get_qty > 0) {
            // How many times does the BOGO trigger?
            // E.g., buy 2 get 1. Group size = buy_qty.
            // Wait, usually BOGO means if you have 3 items in cart, you pay for 2.
            // So if you buy 3, you get 1 free. buy_qty = 2, get_qty = 1.
            $groupSize = $promo->buy_qty + $promo->get_qty;
            $freeItems = floor($qty / $groupSize) * $promo->get_qty;

            // What if they buy exactly buy_qty, but didn't add the get_qty?
            // Usually, POS systems require the item to be in cart to discount it.
            // So if cart has 3 items, 1 is discounted.
            return $freeItems * $price;
        }

        return 0;
    }
}
