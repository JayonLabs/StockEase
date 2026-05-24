<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { DataTable } from '@/Components/ui/data-table';
import { stockAdjustmentColumns } from './partials/StockAdjustmentColumn';
import StockAdjustmentCreateForm from './partials/StockAdjustmentCreateForm.vue';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

const props = defineProps({
    adjustments: {
        type: Object,
        required: true,
    },
    warehouses: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Stock Opname" />
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
                        <BreadcrumbPage> Stock Opname </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h4 class="font-semibold text-lg">
                            Stock Opname (Penyesuaian Stok)
                        </h4>
                        <p class="text-sm text-muted-foreground">
                            Riwayat penyesuaian stok produk.
                        </p>
                    </div>
                    <StockAdjustmentCreateForm :warehouses="props.warehouses" />
                </div>
                <Separator class="my-4" />

                <div class="mt-4">
                    <DataTable
                        :data="adjustments.data"
                        :columns="stockAdjustmentColumns"
                        :route-name="'stock-adjustment.index'"
                        :pagination="adjustments"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
