<script setup>
import { formatPrice, formatNumber } from '@/lib/utils';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    TrendingUp,
    TrendingDown,
    DollarSign,
    ShoppingCart,
} from 'lucide-vue-next';

defineProps({
    summary: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card class="overflow-hidden border-l-4 border-l-primary gap-3">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium">
                    Total Pendapatan
                </CardTitle>
                <div class="bg-primary/10 p-2 rounded-lg">
                    <DollarSign class="h-4 w-4 text-primary" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">
                    {{ formatPrice(summary.total_revenue) }}
                </div>
                <p class="text-xs text-muted-foreground mt-1">
                    Bruto dari penjualan
                </p>
            </CardContent>
        </Card>

        <Card class="overflow-hidden border-l-4 border-l-orange-500 gap-3">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium">
                    Total HPP (COGS)
                </CardTitle>
                <div class="bg-orange-500/10 p-2 rounded-lg">
                    <ShoppingCart class="h-4 w-4 text-orange-500" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">
                    {{ formatPrice(summary.total_cost) }}
                </div>
                <p class="text-xs text-muted-foreground mt-1">
                    Modal barang keluar
                </p>
            </CardContent>
        </Card>

        <Card
            class="overflow-hidden border-l-4 gap-3"
            :class="
                summary.gross_profit >= 0
                    ? 'border-l-emerald-500'
                    : 'border-l-red-500'
            "
        >
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium"> Laba Kotor </CardTitle>
                <div
                    class="p-2 rounded-lg"
                    :class="
                        summary.gross_profit >= 0
                            ? 'bg-emerald-500/10'
                            : 'bg-red-500/10'
                    "
                >
                    <TrendingUp
                        v-if="summary.gross_profit >= 0"
                        class="h-4 w-4 text-emerald-500"
                    />
                    <TrendingDown v-else class="h-4 w-4 text-red-500" />
                </div>
            </CardHeader>
            <CardContent>
                <div
                    class="text-2xl font-bold"
                    :class="
                        summary.gross_profit >= 0
                            ? 'text-emerald-600'
                            : 'text-red-600'
                    "
                >
                    {{ formatPrice(summary.gross_profit) }}
                </div>
                <p class="text-xs text-muted-foreground mt-1">
                    Pendapatan - HPP
                </p>
            </CardContent>
        </Card>

        <Card class="overflow-hidden border-l-4 border-l-blue-500 gap-3">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium">
                    Margin Profit
                </CardTitle>
                <div class="bg-blue-500/10 p-2 rounded-lg">
                    <Badge
                        variant="outline"
                        class="border-blue-200 text-blue-600 font-bold"
                    >
                        {{ summary.profit_margin.toFixed(2) }}%
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-blue-600">
                    {{ summary.profit_margin.toFixed(1) }}%
                </div>
                <p class="text-xs text-muted-foreground mt-1">
                    Efisiensi keuntungan
                </p>
            </CardContent>
        </Card>
    </div>
</template>
