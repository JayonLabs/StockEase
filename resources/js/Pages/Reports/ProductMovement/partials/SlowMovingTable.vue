<script setup>
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
import { PackageSearch } from 'lucide-vue-next';

defineProps({
    items: {
        type: Array,
        required: true,
    },
});

const formatDate = (date) => {
    if (!date) return '—';
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date));
};
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center gap-2">
                <CardTitle>Slow Moving</CardTitle>
            </div>
            <CardDescription>
                Produk ber-stok yang paling sedikit terjual — perlu perhatian.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div
                v-if="items.length === 0"
                class="flex flex-col items-center justify-center py-10 text-muted-foreground gap-2"
            >
                <PackageSearch class="h-8 w-8 opacity-30" />
                <p class="text-sm">Tidak ada produk slow moving ditemukan.</p>
            </div>
            <div v-else class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow class="hover:bg-transparent">
                            <TableHead class="w-8"> # </TableHead>
                            <TableHead>Produk</TableHead>
                            <TableHead class="text-center">
                                Stok Mengendap
                            </TableHead>
                            <TableHead class="text-center">
                                Qty Terjual
                            </TableHead>
                            <TableHead class="text-center">
                                Terakhir Terjual
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
                                    class="bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300 font-mono"
                                >
                                    {{ item.current_stock }} pcs
                                </Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <span
                                    :class="
                                        item.total_qty_sold === 0
                                            ? 'text-red-500 font-medium'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        item.total_qty_sold === 0
                                            ? 'Belum terjual'
                                            : `${item.total_qty_sold} pcs`
                                    }}
                                </span>
                            </TableCell>
                            <TableCell
                                class="text-center text-sm text-muted-foreground"
                            >
                                {{ formatDate(item.last_sold_at) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </CardContent>
    </Card>
</template>
