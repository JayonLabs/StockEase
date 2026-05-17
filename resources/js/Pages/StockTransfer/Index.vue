<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { DataTable } from '@/Components/ui/data-table';
import { stockTransferColumns } from './partials/stock-transfer-column';
import StockTransferCreateForm from './partials/StockTransferCreateForm.vue';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

const props = defineProps({
    transfers: {
        type: Object,
        required: true,
    },
    warehouses: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        required: true,
    },
});

const search = ref(props.filters.search || '');
const warehouseId = ref(props.filters.warehouse_id || '');

let searchTimer;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            route('stock-transfer.index'),
            {
                search: val || undefined,
                warehouse_id: warehouseId.value || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 300);
});

watch(warehouseId, (val) => {
    router.get(
        route('stock-transfer.index'),
        {
            search: search.value || undefined,
            warehouse_id: val || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
});
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Pemindahan Stok</title>
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
                        <BreadcrumbPage> Pemindahan Stok </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h4 class="font-semibold text-lg">
                            Pemindahan Stok Antar Gudang
                        </h4>
                        <p class="text-sm text-muted-foreground">
                            Riwayat pemindahan stok produk antar gudang.
                        </p>
                    </div>
                    <StockTransferCreateForm :warehouses="warehouses" />
                </div>
                <Separator class="my-4" />

                <div
                    class="flex flex-col gap-3 md:flex-row md:items-center mb-4"
                >
                    <div class="w-full md:w-55 shrink-0">
                        <Select v-model="warehouseId">
                            <SelectTrigger>
                                <SelectValue placeholder="Semua Gudang" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="warehouse in warehouses"
                                    :key="warehouse.id"
                                    :value="warehouse.id"
                                    class="cursor-pointer"
                                >
                                    {{ warehouse.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="mt-4">
                    <DataTable
                        :data="transfers.data"
                        :columns="stockTransferColumns"
                        :route-name="'stock-transfer.index'"
                        :pagination="transfers"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
