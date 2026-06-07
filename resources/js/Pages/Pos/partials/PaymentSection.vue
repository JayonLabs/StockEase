<script setup>
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { formatPrice } from '@/lib/utils';

const props = defineProps({
    paymentMethod: {
        type: String,
        default: 'cash',
    },
    displayCashPayment: {
        type: String,
        default: '',
    },
    totalCart: {
        type: Number,
        default: 0,
    },
    change: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['update:paymentMethod', 'update:displayCashPayment']);
</script>

<template>
    <div>
        <span>Metode Pembayaran:</span>
        <div class="flex items-center space-x-2 mt-2">
            <RadioGroup
                :model-value="paymentMethod"
                @update:model-value="$emit('update:paymentMethod', $event)"
            >
                <div class="flex items-center space-x-2">
                    <RadioGroupItem id="cash" value="cash" />
                    <Label for="cash">Cash</Label>
                </div>
                <div class="flex items-center space-x-2">
                    <RadioGroupItem id="qris" value="qris" />
                    <Label for="qris">Qris</Label>
                </div>
            </RadioGroup>
        </div>
    </div>

    <div v-if="paymentMethod === 'cash'" class="flex flex-col mt-2">
        <Input
            id="cashPayment"
            :model-value="displayCashPayment"
            name="cashPayment"
            type="text"
            class="w-full mt-2"
            placeholder="Uang Pembayaran"
            autocomplete="off"
            @update:model-value="$emit('update:displayCashPayment', $event)"
        />
    </div>

    <div class="flex justify-between text-lg font-bold mt-2">
        <span class="text-muted-foreground">Kembalian:</span>
        <span>{{ formatPrice(change) }}</span>
    </div>
</template>
