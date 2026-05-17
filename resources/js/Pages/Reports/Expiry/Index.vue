<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { DataTable } from '@/Components/ui/data-table';
import { Badge } from '@/Components/ui/badge';
import { h, ref, watch } from 'vue';
import dayjs from 'dayjs';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/Components/ui/card';

const props = defineProps({
    expiryData: Object,
    filters: Object,
});

const status = ref(props.filters.status || 'all');

watch(status, (val) => {
    router.get(
        route('reports.expiry.index'),
        {
            ...Object.fromEntries(new URLSearchParams(window.location.search)),
            status: val,
            page: 1,
        },
        { preserveState: true, replace: true },
    );
});

const columns = [
    {
        accessorKey: 'expiry_date',
        header: 'Tanggal Kedaluwarsa',
        cell: ({ row }) => {
            const date = dayjs(row.getValue('expiry_date'));
            const isExpired = date.isBefore(dayjs());
            const isNear = date.isBefore(dayjs().add(30, 'day')) && !isExpired;

            return h('div', { class: 'flex items-center gap-2' }, [
                h(
                    'span',
                    { class: 'font-medium' },
                    date.format('DD MMMM YYYY'),
                ),
                isExpired
                    ? h(Badge, { variant: 'destructive' }, 'Kedaluwarsa')
                    : null,
                isNear
                    ? h(
                          Badge,
                          {
                              class: 'bg-yellow-500 hover:bg-yellow-600 text-white border-none',
                          },
                          'Mendekati',
                      )
                    : null,
            ]);
        },
    },
    {
        accessorKey: 'product.name',
        header: 'Produk',
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.original.product.name),
    },
    {
        accessorKey: 'product.sku',
        header: 'SKU',
        cell: ({ row }) =>
            h(
                'div',
                { class: 'font-mono text-xs uppercase' },
                row.original.product.sku,
            ),
    },
    {
        accessorKey: 'purchase.supplier.name',
        header: 'Supplier',
        cell: ({ row }) =>
            h('div', row.original.purchase?.supplier?.name ?? '-'),
    },
    {
        accessorKey: 'qty',
        header: 'Stok Masuk',
        cell: ({ row }) => h('div', row.getValue('qty')),
    },
];
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Laporan Kedaluwarsa" />
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
                        <BreadcrumbPage> Laporan Kedaluwarsa </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="flex flex-col gap-6 p-6">
            <div
                class="flex flex-col md:flex-row md:items-center justify-between gap-4"
            >
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        Laporan Kedaluwarsa
                    </h1>
                    <p class="text-muted-foreground">
                        Monitoring tanggal kedaluwarsa produk dari pembelian.
                    </p>
                </div>
                <div
                    class="flex flex-col sm:flex-row items-start sm:items-center gap-2 bg-card p-2 rounded-lg border shadow-sm w-full sm:w-auto"
                >
                    <span
                        class="text-xs font-semibold uppercase text-muted-foreground px-2 pt-1 sm:pt-0"
                    >
                        Filter Status:
                    </span>
                    <Select v-model="status" class="w-full sm:w-48">
                        <SelectTrigger
                            class="h-9 border-none shadow-none focus:ring-0"
                        >
                            <SelectValue placeholder="Pilih Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all"> Semua </SelectItem>
                            <SelectItem value="near_expired">
                                Mendekati (30 Hari)
                            </SelectItem>
                            <SelectItem value="expired">
                                Kedaluwarsa
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Data Produk</CardTitle>
                    <CardDescription>
                        Daftar produk yang memiliki tanggal kedaluwarsa.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :data="expiryData.data"
                        :columns="columns"
                        :route-name="'reports.expiry.index'"
                        :pagination="expiryData"
                    />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
