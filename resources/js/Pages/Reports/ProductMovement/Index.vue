<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import DateRangePicker from '@/Components/DateRangePicker.vue';
import MovementChart from './partials/MovementChart.vue';
import SummaryCards from './partials/SummaryCards.vue';
import FastMovingTable from './partials/FastMovingTable.vue';
import SlowMovingTable from './partials/SlowMovingTable.vue';
import { BarChart2 } from 'lucide-vue-next';

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
    fastMoving: Array,
    slowMoving: Array,
    chartData: Object,
    summary: Object,
    filters: Object,
});

const chartReady = ref(false);

onMounted(() => {
    chartReady.value = true;
});

const form = useForm({
    start: props.filters.start,
    end: props.filters.end,
});

const submit = () => {
    form.get(route('reports.product-movement'), {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Analisis Produk Fast & Slow Moving" />

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
                        <BreadcrumbPage>Analisis Produk</BreadcrumbPage>
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
                        Analisis Produk
                    </h1>
                    <p class="text-muted-foreground">
                        Identifikasi produk fast moving &amp; slow moving untuk
                        optimasi stok.
                    </p>
                </div>

                <form
                    class="flex flex-col sm:flex-row items-center gap-3 bg-card p-4 rounded-xl border shadow-sm"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-1.5 w-full sm:w-auto">
                        <label
                            class="text-xs font-semibold uppercase text-muted-foreground px-1"
                        >
                            Rentang Tanggal
                        </label>
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
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <BarChart2 class="h-5 w-5 text-primary" />
                        <CardTitle>Perbandingan Pergerakan Produk</CardTitle>
                    </div>
                    <CardDescription>
                        Top 10 produk terlaris vs top 10 produk paling lambat
                        terjual dalam periode ini.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- ApexCharts only renders after DOM is mounted -->
                    <MovementChart v-if="chartReady" :chart-data="chartData" />
                    <div
                        v-else
                        class="h-72 animate-pulse bg-muted rounded-lg"
                    />
                </CardContent>
            </Card>

            <!-- Tables Row -->
            <div class="grid gap-6 lg:grid-cols-2">
                <FastMovingTable :items="fastMoving" />
                <SlowMovingTable :items="slowMoving" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
