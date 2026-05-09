<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { DataTable } from '@/Components/ui/data-table';
import { midtransTransactionColumns } from './partials/midtrans-transaction-column';
import DateRangePicker from '@/Components/DateRangePicker.vue';
import { Button } from '@/Components/ui/button';

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
    midtransTransactions: {
        type: Object,
        required: true,
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
        route('midtrans.index'),
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
        route('midtrans.index'),
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
            <title>Transaksi Midtrans</title>
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
                        <BreadcrumbPage> Transaksi Midtrans </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <h4 class="font-semibold">Transaksi Midtrans</h4>
                </div>
                <Separator class="my-4" />

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

                    <Button size="sm" variant="outline" @click="applyFilters">
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

                <div class="mt-4">
                    <DataTable
                        :data="midtransTransactions.data"
                        :columns="midtransTransactionColumns"
                        :route-name="'midtrans.index'"
                        :pagination="midtransTransactions"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
