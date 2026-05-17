<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { PackageSearch } from 'lucide-vue-next';
import Filter from './partials/Filter.vue';
import { DataTable } from '@/Components/ui/data-table';
import { filteredStockColumns } from './partials/filtered-stock-column';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/Components/ui/card';

const props = defineProps({
    filteredStocks: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Laporan Stok" />

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
                        <BreadcrumbPage> Laporan Stok </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="flex flex-col gap-6 p-6 pb-12">
            <div
                class="flex flex-col md:flex-row md:items-center justify-between gap-4"
            >
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        Laporan Stok
                    </h1>
                    <p class="text-muted-foreground">
                        Monitoring pergerakan dan ketersediaan stok barang.
                    </p>
                </div>
            </div>

            <Filter />

            <Card v-if="filteredStocks.data?.length > 0">
                <CardHeader>
                    <CardTitle>Data Stok</CardTitle>
                    <CardDescription>
                        Daftar ketersediaan stok barang berdasarkan filter.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :data="filteredStocks.data"
                        :columns="filteredStockColumns"
                        :route-name="'reports.stock.index'"
                        :pagination="filteredStocks"
                    />
                </CardContent>
            </Card>

            <template v-else>
                <div
                    class="flex flex-col items-center justify-center p-12 border-2 border-dashed rounded-xl bg-card text-center"
                >
                    <div class="rounded-full bg-muted p-4 mb-4">
                        <PackageSearch class="w-8 h-8 text-muted-foreground" />
                    </div>
                    <h3 class="text-lg font-semibold mb-1">Belum ada data</h3>
                    <p class="text-sm text-muted-foreground max-w-md mx-auto">
                        Silahkan isi filter di atas untuk melihat laporan stok
                        barang.
                    </p>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
