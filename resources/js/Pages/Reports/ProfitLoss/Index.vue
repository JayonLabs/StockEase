<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import Chart from './partials/Chart.vue';
import SummaryCards from './partials/SummaryCards.vue';
import PerformanceSummary from './partials/PerformanceSummary.vue';
import DateRangePicker from '@/Components/DateRangePicker.vue';
import { DataTable } from '@/Components/ui/data-table';
import { columns } from './partials/ProductBreakdownColumns';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/Components/ui/card';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

const props = defineProps({
    summary: Object,
    productBreakdown: Object,
    chartData: Array,
    filters: Object,
});

const form = useForm({
    start: props.filters.start,
    end: props.filters.end,
});

const submit = () => {
    form.get(route('reports.profit-loss'), {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Laporan Laba/Rugi" />

        <template #breadcrumb>
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <Link :href="route('dashboard')">
                            <BreadcrumbLink>Dashboard</BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>Laporan Laba/Rugi</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="flex flex-1 flex-col gap-6 p-6">
            <!-- Header Section -->
            <div
                class="flex flex-col md:flex-row md:items-center justify-between gap-4"
            >
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        Laporan Laba / Rugi
                    </h1>
                    <p class="text-muted-foreground">
                        Analisis performa keuangan bisnis Anda.
                    </p>
                </div>

                <form
                    class="flex flex-col sm:flex-row items-center gap-3 bg-card p-4 rounded-xl border shadow-sm"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-1.5 w-full sm:w-auto">
                        <label
                            class="text-xs font-semibold uppercase text-muted-foreground px-1"
                            >Rentang Tanggal</label
                        >
                        <DateRangePicker
                            v-model:start="form.start"
                            v-model:end="form.end"
                        />
                    </div>
                    <div class="pt-0 sm:pt-5 w-full sm:w-auto">
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="form.processing"
                        >
                            Filter
                        </Button>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <SummaryCards :summary="summary" />

            <!-- Chart Section -->
            <div class="grid gap-4 md:grid-cols-7">
                <Card class="md:col-span-4">
                    <CardHeader>
                        <CardTitle>Tren Keuangan</CardTitle>
                        <CardDescription>
                            Visualisasi harian Pendapatan vs HPP.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="h-87.5">
                        <Chart :chart-data="chartData" />
                    </CardContent>
                </Card>

                <PerformanceSummary
                    :summary="summary"
                    :product-breakdown="productBreakdown"
                />
            </div>

            <!-- Product Breakdown Table -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle>Rincian Per Produk</CardTitle>
                        <CardDescription>
                            Analisis margin dan profitabilitas setiap produk.
                        </CardDescription>
                    </div>
                </CardHeader>
                <CardContent>
                    <DataTable
                        :data="productBreakdown.data"
                        :columns="columns"
                        :pagination="productBreakdown"
                        route-name="reports.profit-loss"
                    />
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
