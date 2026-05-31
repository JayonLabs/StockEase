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
     * Recalculate the total for a given sale by applying applicable promotions to each sale item.
     */
    public function execute(Sale $sale): float
    {
        $total = 0;
        $totalCost = 0;

        if (! $sale->relationLoaded('saleItems')) {
            $sale->load('saleItems.product');
        }

        $activePromotions = once(fn () => Promotion::active()->get());

        foreach ($sale->saleItems as $item) {
            $product = $item->product;

            $applicablePromo = $this->findBestPromotion($activePromotions, $product);

            $discountAmount = 0;
            $promotionId = null;

            if ($applicablePromo) {
                $discountAmount = $this->calculateDiscount($applicablePromo, $item->price, $item->qty);
                $promotionId = $applicablePromo->id;
            }

            $item->fill([
                'promotion_id' => $promotionId,
                'discount_amount' => $discountAmount,
            ]);

            if ($item->isDirty(['promotion_id', 'discount_amount'])) {
                $item->save();
            }

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
     * Find the best applicable promotion for a product, prioritizing product-specific promotions
     * over category-specific and general promotions.
     */
    private function findBestPromotion(Collection $promotions, Product $product): ?Promotion
    {
        $productPromos = $promotions->where('product_id', $product->id);
        if ($productPromos->isNotEmpty()) {
            return $productPromos->first();
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
     * Calculate the discount amount based on the promotion type and quantity.
     */
    private function calculateDiscount(Promotion $promo, float $price, int $qty): float
    {
        if ($promo->type === PromotionType::Percentage->value) {
            return $price * ($promo->discount_value / 100) * $qty;
        }

        if ($promo->type === PromotionType::Nominal->value) {
            return min($promo->discount_value * $qty, $price * $qty);
        }

        if ($promo->type === PromotionType::Bogo->value && $promo->buy_qty > 0 && $promo->get_qty > 0) {
            $groupSize = $promo->buy_qty + $promo->get_qty;
            $freeItems = floor($qty / $groupSize) * $promo->get_qty;

            return $freeItems * $price;
        }

        return 0;
    }
}
