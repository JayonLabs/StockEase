<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import Chart from './partials/Chart.vue';
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
    LayoutDashboard,
    TrendingUp,
    TrendingDown,
    DollarSign,
    ShoppingCart,
    Package,
    ArrowUpRight,
    ArrowDownRight,
    Calendar,
} from 'lucide-vue-next';

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

const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
    }).format(value);
};

const formatNumber = (value) => {
    return new Intl.NumberFormat('id-ID').format(value);
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
                    <div class="pt-5 w-full sm:w-auto">
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
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card class="overflow-hidden border-l-4 border-l-primary">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Total Pendapatan
                        </CardTitle>
                        <div class="bg-primary/10 p-2 rounded-lg">
                            <DollarSign class="h-4 w-4 text-primary" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(summary.total_revenue) }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Bruto dari penjualan
                        </p>
                    </CardContent>
                </Card>

                <Card class="overflow-hidden border-l-4 border-l-orange-500">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Total HPP (COGS)
                        </CardTitle>
                        <div class="bg-orange-500/10 p-2 rounded-lg">
                            <ShoppingCart class="h-4 w-4 text-orange-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(summary.total_cost) }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Modal barang keluar
                        </p>
                    </CardContent>
                </Card>

                <Card
                    class="overflow-hidden border-l-4"
                    :class="
                        summary.gross_profit >= 0
                            ? 'border-l-emerald-500'
                            : 'border-l-red-500'
                    "
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Laba Kotor
                        </CardTitle>
                        <div
                            class="p-2 rounded-lg"
                            :class="
                                summary.gross_profit >= 0
                                    ? 'bg-emerald-500/10'
                                    : 'bg-red-500/10'
                            "
                        >
                            <TrendingUp
                                v-if="summary.gross_profit >= 0"
                                class="h-4 w-4 text-emerald-500"
                            />
                            <TrendingDown v-else class="h-4 w-4 text-red-500" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div
                            class="text-2xl font-bold"
                            :class="
                                summary.gross_profit >= 0
                                    ? 'text-emerald-600'
                                    : 'text-red-600'
                            "
                        >
                            {{ formatCurrency(summary.gross_profit) }}
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Pendapatan - HPP
                        </p>
                    </CardContent>
                </Card>

                <Card class="overflow-hidden border-l-4 border-l-blue-500">
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Margin Profit
                        </CardTitle>
                        <div class="bg-blue-500/10 p-2 rounded-lg">
                            <Badge
                                variant="outline"
                                class="border-blue-200 text-blue-600 font-bold"
                            >
                                {{ summary.profit_margin.toFixed(2) }}%
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">
                            {{ summary.profit_margin.toFixed(1) }}%
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">
                            Efisiensi keuntungan
                        </p>
                    </CardContent>
                </Card>
            </div>

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

                <Card class="md:col-span-3">
                    <CardHeader>
                        <CardTitle>Rangkuman Performa</CardTitle>
                        <CardDescription>
                            Analisis persentase biaya terhadap pendapatan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent
                        class="flex flex-col justify-center gap-6 py-8"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Persentase HPP</span
                                >
                                <span class="font-medium text-orange-600"
                                    >{{
                                        (100 - summary.profit_margin).toFixed(
                                            1,
                                        )
                                    }}%</span
                                >
                            </div>
                            <div
                                class="h-3 w-full rounded-full bg-secondary overflow-hidden"
                            >
                                <div
                                    class="h-full bg-orange-500"
                                    :style="`width: ${100 - summary.profit_margin}%`"
                                />
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="text-muted-foreground"
                                    >Persentase Margin Laba</span
                                >
                                <span class="font-medium text-emerald-600"
                                    >{{
                                        summary.profit_margin.toFixed(1)
                                    }}%</span
                                >
                            </div>
                            <div
                                class="h-3 w-full rounded-full bg-secondary overflow-hidden"
                            >
                                <div
                                    class="h-full bg-emerald-500"
                                    :style="`width: ${summary.profit_margin}%`"
                                />
                            </div>
                        </div>
                        <div
                            class="mt-4 p-4 rounded-lg bg-emerald-50 border border-emerald-100 dark:bg-emerald-950/20 dark:border-emerald-900/30"
                        >
                            <div class="flex gap-3">
                                <div
                                    class="bg-emerald-100 dark:bg-emerald-900/50 p-2 rounded-full self-start"
                                >
                                    <TrendingUp
                                        class="h-4 w-4 text-emerald-600"
                                    />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-semibold text-emerald-800 dark:text-emerald-400 uppercase tracking-wider"
                                    >
                                        Insight
                                    </p>
                                    <p
                                        class="text-sm text-emerald-700 dark:text-emerald-300 mt-1"
                                    >
                                        Rata-rata Anda mendapatkan untung
                                        <strong>{{
                                            formatCurrency(
                                                summary.gross_profit /
                                                    (productBreakdown.total ||
                                                        1),
                                            )
                                        }}</strong>
                                        per jenis produk yang terjual.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
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
