<script setup>
import { Button } from '@/Components/ui/button';
import { Loader2, Trash2 } from 'lucide-vue-next';
import { formatPrice } from '@/lib/utils';
import {
    NumberField,
    NumberFieldContent,
    NumberFieldDecrement,
    NumberFieldIncrement,
    NumberFieldInput,
} from '@/Components/ui/number-field';

const props = defineProps({
    cartItems: {
        type: Array,
        default: () => [],
    },
    qtyRefs: {
        type: Object,
        default: () => ({}),
    },
    loadingItemId: {
        type: [Number, null],
        default: null,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    changingQtyProductIds: {
        type: Set,
        default: () => new Set(),
    },
});

const emit = defineEmits(['change-qty', 'remove-item']);
</script>

<template>
    <div class="space-y-3 mb-4" style="max-height: 50vh; overflow-y: auto">
        <div v-if="cartItems && cartItems.length > 0">
            <div
                v-for="cartItem in cartItems"
                :key="cartItem.id"
                class="flex justify-between items-center border-b pb-2"
            >
                <div>
                    <h4 class="font-medium">
                        {{ cartItem.product.name }}
                    </h4>
                    <p class="text-gray-500 text-sm">
                        {{ formatPrice(cartItem.price) }} x
                        {{ qtyRefs[cartItem.product_id] }}
                    </p>
                    <p
                        v-if="cartItem.discount_amount > 0"
                        class="text-green-600 text-xs font-medium"
                    >
                        Diskon: -{{ formatPrice(cartItem.discount_amount) }}
                    </p>
                </div>
                <div class="flex items-center">
                    <NumberField
                        :model-value="qtyRefs[cartItem.product_id]"
                        :min="0"
                    >
                        <NumberFieldContent>
                            <NumberFieldDecrement
                                :disabled="
                                    disabled ||
                                    changingQtyProductIds.has(
                                        cartItem.product_id,
                                    )
                                "
                                @click="
                                    qtyRefs[cartItem.product_id]--;
                                    $emit(
                                        'change-qty',
                                        cartItem.product_id,
                                        qtyRefs[cartItem.product_id],
                                    );
                                "
                            />
                            <NumberFieldInput
                                class="w-24 border rounded"
                                readonly
                            />
                            <NumberFieldIncrement
                                :disabled="
                                    disabled ||
                                    changingQtyProductIds.has(
                                        cartItem.product_id,
                                    )
                                "
                                @click="
                                    qtyRefs[cartItem.product_id]++;
                                    $emit(
                                        'change-qty',
                                        cartItem.product_id,
                                        qtyRefs[cartItem.product_id],
                                    );
                                "
                            />
                        </NumberFieldContent>
                    </NumberField>
                    <Button
                        aria-label="Hapus item"
                        variant="destructive"
                        size="icon"
                        class="ml-2 disabled:cursor-not-allowed"
                        :disabled="
                            disabled || loadingItemId === cartItem.product_id
                        "
                        @click="$emit('remove-item', cartItem.product_id)"
                    >
                        <Loader2
                            v-if="loadingItemId === cartItem.product_id"
                            class="w-4 h-4 animate-spin"
                        />
                        <Trash2 v-else class="w-4 h-4" />
                    </Button>
                </div>
            </div>
        </div>

        <div v-else>
            <p class="text-center">Keranjang belanja kosong</p>
        </div>
    </div>
</template>
