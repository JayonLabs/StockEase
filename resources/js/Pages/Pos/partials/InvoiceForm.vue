<script setup>
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Loader2, Send } from 'lucide-vue-next';

defineProps({
    showInvoiceSection: {
        type: Boolean,
        default: false,
    },
    invoiceEmail: {
        type: String,
        default: '',
    },
    isSendingInvoice: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['send-invoice', 'update:invoiceEmail']);
</script>

<template>
    <div v-if="showInvoiceSection" class="border-t pt-3 mt-3">
        <p class="text-sm font-medium mb-2">Kirim Invoice ke Email</p>
        <div class="flex gap-2">
            <Input
                :model-value="invoiceEmail"
                type="email"
                placeholder="email@example.com"
                class="flex-1"
                @update:model-value="$emit('update:invoiceEmail', $event)"
            />
            <Button
                size="sm"
                :disabled="!invoiceEmail || isSendingInvoice"
                @click="$emit('send-invoice')"
            >
                <Loader2
                    v-if="isSendingInvoice"
                    class="w-4 h-4 animate-spin mr-1"
                />
                <Send v-else class="w-4 h-4 mr-1" />
                Kirim
            </Button>
        </div>
    </div>
</template>
