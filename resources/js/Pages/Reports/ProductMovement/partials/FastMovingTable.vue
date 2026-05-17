<script setup>
import { formatPrice } from '@/lib/utils';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { ShoppingBag } from 'lucide-vue-next';

defineProps({
    items: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center gap-2">
                <CardTitle>Fast Moving</CardTitle>
            </div>
            <CardDescription>
                Produk dengan penjualan tertinggi — prioritaskan restock.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div
                v-if="items.length === 0"
                class="flex flex-col items-center justify-center py-10 text-muted-foreground gap-2"
            >
                <ShoppingBag class="h-8 w-8 opacity-30" />
                <p class="text-sm">Tidak ada penjualan dalam periode ini.</p>
            </div>
            <div v-else class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow class="hover:bg-transparent">
                            <TableHead class="w-8"> # </TableHead>
                            <TableHead>Produk</TableHead>
                            <TableHead class="text-center">
                                Qty Terjual
                            </TableHead>
                            <TableHead class="text-center"> Stok </TableHead>
                            <TableHead class="text-right">
                                Pendapatan
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(item, i) in items"
                            :key="item.product_id"
                            class="hover:bg-muted/40"
                        >
                            <TableCell class="font-bold text-muted-foreground">
                                {{ i + 1 }}
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-col">
                                    <span class="font-medium">{{
                                        item.product_name
                                    }}</span>
                                    <span
                                        class="text-xs text-muted-foreground"
                                        >{{ item.sku }}</span
                                    >
                                </div>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge
                                    class="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 font-mono"
                                >
                                    {{ item.total_qty_sold }} pcs
                                </Badge>
                            </TableCell>
                            <TableCell
                                class="text-center text-muted-foreground"
                            >
                                {{ item.current_stock }} pcs
                            </TableCell>
                            <TableCell class="text-right font-medium">
                                {{ formatPrice(item.total_revenue) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </CardContent>
    </Card>
</template>
