<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { formatPrice, formatDate } from '@/lib/utils';
import { ArrowLeftToLine } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

const props = defineProps({
    saleReturn: {
        type: Object,
        required: true,
    },
});

const returnTypeLabel =
    props.saleReturn.return_type === 'refund'
        ? 'Pengembalian Uang'
        : 'Tukar Barang';
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Detail Retur #{{ props.saleReturn.id }}</title>
        </Head>
        <template #breadcrumb>
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <Link :href="route('dashboard')">
                            <BreadcrumbLink> Dashboard </BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <Link :href="route('sale-return.index')">
                            <BreadcrumbLink> Retur Penjualan </BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>
                            Detail #{{ props.saleReturn.id }}
                        </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <h4 class="font-semibold">Detail Retur Penjualan</h4>
                    <Link :href="route('sale-return.index')">
                        <Button
                            variant="outline"
                            class="dark:border-white border-zinc-600"
                        >
                            <ArrowLeftToLine />
                            Kembali
                        </Button>
                    </Link>
                </div>
                <Separator class="my-4" />

                <!-- Return Info -->
                <div
                    class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3 w-full mb-6"
                >
                    <div
                        class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800"
                    >
                        <h3
                            class="font-medium text-gray-800 text-theme-xl dark:text-white/90"
                        >
                            Informasi Retur
                        </h3>
                        <div class="flex items-center gap-3">
                            <Badge
                                :variant="
                                    props.saleReturn.status === 'completed'
                                        ? 'default'
                                        : 'destructive'
                                "
                            >
                                {{
                                    props.saleReturn.status === 'completed'
                                        ? 'Selesai'
                                        : 'Dibatalkan'
                                }}
                            </Badge>
                            <span
                                class="text-base font-medium text-gray-700 dark:text-gray-400"
                            >
                                ID : #{{ props.saleReturn.id }}
                            </span>
                        </div>
                    </div>

                    <div class="p-5 xl:p-8">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    ID Transaksi
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    #{{ props.saleReturn.sale?.id ?? '-' }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Pelanggan
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{
                                        props.saleReturn.sale?.customer_name ??
                                        '-'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Tanggal Retur
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{
                                        formatDate(props.saleReturn.return_date)
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Tipe Retur
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ returnTypeLabel }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Total Pengembalian
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{
                                        formatPrice(
                                            props.saleReturn.total_refund,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Diproses Oleh
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ props.saleReturn.user?.name ?? '-' }}
                                </p>
                            </div>
                            <div v-if="props.saleReturn.reason">
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Alasan Retur
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ props.saleReturn.reason }}
                                </p>
                            </div>
                            <div v-if="props.saleReturn.notes">
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Catatan
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ props.saleReturn.notes }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Return Items -->
                <div
                    class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3 w-full mb-6"
                >
                    <div
                        class="flex items-center px-6 py-4 border-b border-gray-200 dark:border-gray-800"
                    >
                        <h3
                            class="font-medium text-gray-800 text-theme-xl dark:text-white/90"
                        >
                            Produk yang Diretur
                        </h3>
                    </div>

                    <div class="p-5 xl:p-8">
                        <div class="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow class="hover:bg-transparent">
                                        <TableHead>Produk</TableHead>
                                        <TableHead class="text-center">
                                            Harga
                                        </TableHead>
                                        <TableHead class="text-center">
                                            Qty Retur
                                        </TableHead>
                                        <TableHead class="text-center">
                                            Subtotal
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="item in props.saleReturn
                                            .sale_return_items"
                                        :key="item.id"
                                    >
                                        <TableCell>
                                            <p class="font-medium">
                                                {{ item.product?.name }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                SKU:
                                                {{ item.product?.sku }}
                                            </p>
                                        </TableCell>
                                        <TableCell class="text-center">
                                            {{ formatPrice(item.price) }}
                                        </TableCell>
                                        <TableCell class="text-center">
                                            {{ item.qty }}
                                        </TableCell>
                                        <TableCell class="text-center">
                                            {{ formatPrice(item.total) }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                                <TableFooter>
                                    <TableRow>
                                        <TableCell
                                            colspan="3"
                                            class="text-right font-medium"
                                        >
                                            Total Pengembalian
                                        </TableCell>
                                        <TableCell
                                            class="text-center font-semibold"
                                        >
                                            {{
                                                formatPrice(
                                                    props.saleReturn
                                                        .total_refund,
                                                )
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        </div>
                    </div>
                </div>

                <!-- Original Sale Info -->
                <div
                    v-if="props.saleReturn.sale"
                    class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3 w-full"
                >
                    <div
                        class="flex items-center px-6 py-4 border-b border-gray-200 dark:border-gray-800"
                    >
                        <h3
                            class="font-medium text-gray-800 text-theme-xl dark:text-white/90"
                        >
                            Transaksi Asli
                        </h3>
                    </div>

                    <div class="p-5 xl:p-8">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Tanggal Transaksi
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ formatDate(props.saleReturn.sale.date) }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Total Transaksi
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{
                                        formatPrice(props.saleReturn.sale.total)
                                    }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Metode Pembayaran
                                </p>
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90 uppercase"
                                >
                                    {{ props.saleReturn.sale.payment_method }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
