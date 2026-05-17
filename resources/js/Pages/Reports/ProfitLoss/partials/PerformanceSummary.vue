<script setup>
import { formatPrice } from '@/lib/utils';
import { TrendingUp } from 'lucide-vue-next';

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/Components/ui/card';

defineProps({
    summary: {
        type: Object,
        required: true,
    },
    productBreakdown: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Card class="md:col-span-3">
        <CardHeader>
            <CardTitle>Rangkuman Performa</CardTitle>
            <CardDescription>
                Analisis persentase biaya terhadap pendapatan.
            </CardDescription>
        </CardHeader>
        <CardContent class="flex flex-col justify-center gap-6 py-8">
            <div class="space-y-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">Persentase HPP</span>
                    <span class="font-medium text-orange-600"
                        >{{ (100 - summary.profit_margin).toFixed(1) }}%</span
                    >
                </div>
                <div
                    class="h-3 w-full rounded-full bg-secondary overflow-hidden"
                >
                    <div
                        class="h-full bg-orange-500"
                        :style="`width: ${100 - summary.profit_margin}%`"
                    />
                </div>
            </div>
            <div class="space-y-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground"
                        >Persentase Margin Laba</span
                    >
                    <span class="font-medium text-emerald-600"
                        >{{ summary.profit_margin.toFixed(1) }}%</span
                    >
                </div>
                <div
                    class="h-3 w-full rounded-full bg-secondary overflow-hidden"
                >
                    <div
                        class="h-full bg-emerald-500"
                        :style="`width: ${summary.profit_margin}%`"
                    />
                </div>
            </div>
            <div
                class="mt-4 p-4 rounded-lg bg-emerald-50 border border-emerald-100 dark:bg-emerald-950/20 dark:border-emerald-900/30"
            >
                <div class="flex gap-3">
                    <div
                        class="bg-emerald-100 dark:bg-emerald-900/50 p-2 rounded-full self-start"
                    >
                        <TrendingUp class="h-4 w-4 text-emerald-600" />
                    </div>
                    <div>
                        <p
                            class="text-xs font-semibold text-emerald-800 dark:text-emerald-400 uppercase tracking-wider"
                        >
                            Insight
                        </p>
                        <p
                            class="text-sm text-emerald-700 dark:text-emerald-300 mt-1"
                        >
                            Rata-rata Anda mendapatkan untung
                            <strong
                                >{{
                                    formatPrice(
                                        summary.gross_profit /
                                            (productBreakdown.total || 1),
                                    )
                                }}
                            </strong>
                            per jenis produk yang terjual.
                        </p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
