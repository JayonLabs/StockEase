<script setup>
import { formatNumber } from '@/lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Zap, PackageSearch, ShoppingBag, AlertCircle } from 'lucide-vue-next';

defineProps({
    summary: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card class="overflow-hidden border-l-4 border-l-primary gap-2">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium">
                    Total Produk
                </CardTitle>
                <div class="bg-primary/10 p-2 rounded-lg">
                    <PackageSearch class="h-4 w-4 text-primary" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold">
                    {{ formatNumber(summary.total_products_analyzed) }}
                </div>
                <p class="text-xs text-muted-foreground mt-1">
                    Produk dianalisis
                </p>
            </CardContent>
        </Card>

        <Card class="overflow-hidden border-l-4 border-l-blue-500 gap-2">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium">
                    Total Terjual
                </CardTitle>
                <div class="bg-blue-500/10 p-2 rounded-lg">
                    <ShoppingBag class="h-4 w-4 text-blue-500" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-blue-600">
                    {{ formatNumber(summary.total_qty_sold) }} pcs
                </div>
                <p class="text-xs text-muted-foreground mt-1">
                    Total unit terjual pada periode ini
                </p>
            </CardContent>
        </Card>

        <Card class="overflow-hidden border-l-4 border-l-emerald-500 gap-2">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium"> Fast Moving </CardTitle>
                <div class="bg-emerald-500/10 p-2 rounded-lg">
                    <Zap class="h-4 w-4 text-emerald-500" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-emerald-600">
                    {{ formatNumber(summary.fast_moving_count) }}
                </div>
                <p class="text-xs text-muted-foreground mt-1">
                    Produk dengan penjualan aktif
                </p>
            </CardContent>
        </Card>

        <Card class="overflow-hidden border-l-4 border-l-orange-500 gap-2">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium">
                    Tidak Terjual
                </CardTitle>
                <div class="bg-orange-500/10 p-2 rounded-lg">
                    <AlertCircle class="h-4 w-4 text-orange-500" />
                </div>
            </CardHeader>
            <CardContent>
                <div class="text-2xl font-bold text-orange-500">
                    {{ formatNumber(summary.unsold_products_count) }}
                </div>
                <p class="text-xs text-muted-foreground mt-1">
                    Produk ber-stok tapi tidak terjual
                </p>
            </CardContent>
        </Card>
    </div>
</template>
