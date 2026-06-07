<script setup>
import { Button } from '@/Components/ui/button';
import { Eye } from 'lucide-vue-next';
import { formatDate, formatPrice } from '@/lib/utils';
import { computed } from 'vue';

import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/Components/ui/dialog';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

const props = defineProps({
    row: { type: Object, required: true },
});

const formattedJson = computed(() => {
    try {
        return JSON.stringify(JSON.parse(props.row.raw_response), null, 2);
    } catch (error) {
        return props.row.raw_response;
    }
});
</script>

<template>
    <div class="flex items-center justify-start">
        <Dialog>
            <DialogTrigger>
                <Button aria-label="Lihat detail" variant="ghost" size="icon">
                    <Eye
                        class="w-4 h-4 text-blue-500 dark:group-hover:text-white"
                    />
                </Button>
            </DialogTrigger>
            <DialogContent class="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Detail Pembayaran Midtrans</DialogTitle>
                    <DialogDescription>
                        Detail dari pembayaran midtrans
                    </DialogDescription>
                </DialogHeader>

                <span class="text-muted-foreground text-sm">
                    Pembayaran Midtrans
                </span>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead> ID Transaksi </TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Total Pembayaran</TableHead>
                            <TableHead> Tipe Pembayaran </TableHead>
                            <TableHead> Tanggal Pembayaran </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow>
                            <TableCell>
                                {{ row.external_id }}
                            </TableCell>
                            <TableCell class="capitalize">
                                {{ row.status }}
                            </TableCell>
                            <TableCell>
                                {{ formatPrice(row.amount) }}
                            </TableCell>
                            <TableCell class="capitalize">
                                {{ row.payment_type }}
                            </TableCell>
                            <TableCell>
                                {{ formatDate(row.created_at) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <span class="text-muted-foreground text-sm">
                    Response Midtrans
                </span>
                <div
                    class="bg-secondary/50 text-white rounded-md p-4 overflow-x-auto overflow-y-auto text-sm max-h-64 max-w-full"
                >
                    <pre>
                        <code>{{ formattedJson }}</code>
                    </pre>
                </div>

                <span class="text-muted-foreground text-sm">
                    Detail Produk Pembayaran
                </span>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead> # </TableHead>
                            <TableHead>Nama Produk</TableHead>
                            <TableHead>Harga Jual</TableHead>
                            <TableHead> Harga Beli </TableHead>
                            <TableHead> SKU </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(product, index) in props.row.sale
                                .sale_items"
                            :key="product.product.slug"
                        >
                            <TableCell>
                                {{ index + 1 }}
                            </TableCell>
                            <TableCell>
                                {{ product.product.name }}
                            </TableCell>
                            <TableCell>
                                {{ formatPrice(product.product.selling_price) }}
                            </TableCell>
                            <TableCell>
                                {{
                                    formatPrice(product.product.purchase_price)
                                }}
                            </TableCell>
                            <TableCell>
                                {{ product.product.sku }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Close
                        </Button>
                    </DialogClose>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
