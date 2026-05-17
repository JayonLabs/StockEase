<script setup>
import { formatPrice } from '@/lib/utils';
import { onBeforeUnmount, onMounted, ref, computed } from 'vue';

import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

import {
    TrendingUp,
    Calendar,
    PackageSearch,
    CreditCard,
} from 'lucide-vue-next';

const props = defineProps({
    salesSummary: Object,
    lowStock: Array,
    activities: Array,
    weeklySalesChart: Object,
    priceUpdateChart: Object,
});

const isDarkMode = ref(document.documentElement.classList.contains('dark'));
let observer = null;

onMounted(() => {
    observer = new MutationObserver(() => {
        isDarkMode.value = document.documentElement.classList.contains('dark');
    });
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

onBeforeUnmount(() => {
    if (observer) observer.disconnect();
});

const salesChartOptions = computed(() => ({
    theme: {
        mode: isDarkMode.value ? 'dark' : 'light',
    },
    chart: {
        type: 'bar',
        toolbar: { show: false },
        background: 'transparent',
        fontFamily: 'inherit',
    },
    colors: ['#3b82f6'],
    dataLabels: {
        enabled: false,
    },
    plotOptions: {
        bar: {
            borderRadius: 4,
            columnWidth: '40%',
        },
    },
    legend: { show: false },
    grid: {
        show: true,
        borderColor: isDarkMode.value ? '#333' : '#e5e7eb',
        strokeDashArray: 4,
    },
    xaxis: {
        categories: props.weeklySalesChart?.categories ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            formatter: (value) => `Rp ${value.toLocaleString()}`,
        },
    },
    tooltip: {
        theme: isDarkMode.value ? 'dark' : 'light',
        y: {
            formatter: (value) => `Rp ${value.toLocaleString()}`,
        },
        marker: { show: false },
    },
}));

const salesChartSeries = computed(() => [
    {
        name: 'Penjualan',
        data: props.weeklySalesChart?.data ?? [],
    },
]);

const priceChartOptions = computed(() => ({
    theme: {
        mode: isDarkMode.value ? 'dark' : 'light',
    },
    chart: {
        type: 'area',
        toolbar: { show: false },
        background: 'transparent',
        fontFamily: 'inherit',
    },
    colors: ['#10b981'],
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.4,
            opacityTo: 0.05,
            stops: [0, 90, 100],
        },
    },
    stroke: {
        curve: 'smooth',
        width: 3,
    },
    dataLabels: {
        enabled: false,
    },
    legend: { show: false },
    grid: {
        show: true,
        borderColor: isDarkMode.value ? '#333' : '#e5e7eb',
        strokeDashArray: 4,
    },
    xaxis: {
        categories: props.priceUpdateChart?.categories ?? [],
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            formatter: (value) => `${value} Update`,
        },
    },
    tooltip: {
        theme: isDarkMode.value ? 'dark' : 'light',
        y: {
            formatter: (value) => `${value} Perubahan Harga`,
        },
    },
}));

const priceChartSeries = computed(() => [
    {
        name: 'Update Harga',
        data: props.priceUpdateChart?.data ?? [],
    },
]);
</script>

<template>
    <div class="flex flex-1 flex-col gap-4 p-4">
        <!-- Summary Cards (4 Grid) -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium">
                        Penjualan Hari Ini
                    </CardTitle>
                    <TrendingUp class="h-5 w-5 text-blue-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ formatPrice(salesSummary.today) }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium">
                        Penjualan Bulan Ini
                    </CardTitle>
                    <Calendar class="h-5 w-5 text-green-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ formatPrice(salesSummary.month) }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium">
                        Pembelian Bulan Ini
                    </CardTitle>
                    <CreditCard class="h-5 w-5 text-purple-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ formatPrice(salesSummary.monthPurchases) }}
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-2"
                >
                    <CardTitle class="text-sm font-medium">
                        Total Produk Aktif
                    </CardTitle>
                    <PackageSearch class="h-5 w-5 text-orange-500" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">
                        {{ salesSummary.activeProducts.toLocaleString() }}
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Stok Kritis & Aktivitas (Shadcn Tables) -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-2">
            <Card>
                <CardHeader>
                    <CardTitle>Stok Kritis</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Produk</TableHead>
                                <TableHead class="text-right"> Stok </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="lowStock.length === 0">
                                <TableCell
                                    colspan="2"
                                    class="h-24 text-center text-muted-foreground"
                                >
                                    Tidak ada produk dengan stok menipis
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="product in lowStock"
                                :key="product.id"
                            >
                                <TableCell>
                                    <div class="font-medium text-sm">
                                        {{ product.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ product.sku }}
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Badge
                                        variant="destructive"
                                        class="text-[10px] h-5"
                                    >
                                        Sisa {{ product.stock }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Aktivitas Terbaru</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Keterangan</TableHead>
                                <TableHead class="text-right"> Tipe </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="activities.length === 0">
                                <TableCell
                                    colspan="2"
                                    class="h-24 text-center text-muted-foreground"
                                >
                                    Belum ada aktivitas
                                </TableCell>
                            </TableRow>
                            <TableRow
                                v-for="activity in activities"
                                :key="activity.id"
                            >
                                <TableCell>
                                    <div
                                        class="text-xs font-medium leading-normal"
                                    >
                                        {{ activity.desc }}
                                    </div>
                                    <div
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        {{ activity.time }}
                                    </div>
                                </TableCell>
                                <TableCell class="text-right">
                                    <Badge
                                        variant="outline"
                                        class="capitalize text-[10px] h-5"
                                    >
                                        {{ activity.type }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>

        <!-- Grafik -->
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mt-2">
            <Card>
                <CardHeader>
                    <CardTitle>Grafik Penjualan Mingguan</CardTitle>
                </CardHeader>
                <CardContent>
                    <apexchart
                        type="bar"
                        height="300"
                        :options="salesChartOptions"
                        :series="salesChartSeries"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Aktivitas Update Harga</CardTitle>
                </CardHeader>
                <CardContent>
                    <apexchart
                        type="area"
                        height="300"
                        :options="priceChartOptions"
                        :series="priceChartSeries"
                    />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
