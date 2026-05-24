<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { DataTable } from '@/Components/ui/data-table';
import { Info } from 'lucide-vue-next';
import { purchaseColumns } from './partials/purchase-columns';
import PurchaseCreateForm from './form/PurchaseCreateForm.vue';
import DateRangePicker from '@/Components/DateRangePicker.vue';
import { Button } from '@/Components/ui/button';

import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

import { ref } from 'vue';

const props = defineProps({
    purchases: {
        type: Object,
        required: true,
    },
    warehouses: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ start: '', end: '', search: '' }),
    },
});

const dateStart = ref(props.filters.start || '');
const dateEnd = ref(props.filters.end || '');

function applyFilters() {
    router.get(
        route('purchase.index'),
        {
            search: props.filters.search || undefined,
            start: dateStart.value || undefined,
            end: dateEnd.value || undefined,
            page: 1,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function resetFilters() {
    dateStart.value = '';
    dateEnd.value = '';
    router.get(
        route('purchase.index'),
        {
            search: props.filters.search || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Pembelian</title>
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
                        <BreadcrumbPage> Data Pembelian </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <h4 class="font-semibold">Data Pembelian</h4>
                    <PurchaseCreateForm :warehouses="props.warehouses" />
                </div>
                <Separator class="my-4" />

                <Card>
                    <CardHeader>
                        <CardTitle>Penting!</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div
                            class="flex items-center space-x-4 rounded-md border p-4"
                        >
                            <Info />
                            <div class="flex-1 space-y-1">
                                <p class="text-sm font-medium leading-none">
                                    Jangan tambahkan data pembelian jika produk
                                    belum ada!
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Silahkan tambahkan produk terlebih dahulu!
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="mt-4">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <DateRangePicker
                            :start="dateStart"
                            :end="dateEnd"
                            placeholder="Filter tanggal"
                            @update:start="
                                (val) => {
                                    dateStart = val || '';
                                }
                            "
                            @update:end="
                                (val) => {
                                    dateEnd = val || '';
                                }
                            "
                        />

                        <Button
                            size="sm"
                            variant="outline"
                            @click="applyFilters"
                        >
                            Terapkan
                        </Button>

                        <Button
                            v-if="dateStart || dateEnd"
                            size="sm"
                            variant="ghost"
                            @click="resetFilters"
                        >
                            Reset
                        </Button>
                    </div>
                    <DataTable
                        :data="purchases.data"
                        :columns="purchaseColumns"
                        :route-name="'purchase.index'"
                        :pagination="purchases"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
