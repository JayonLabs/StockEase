<script setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { computed } from 'vue';

const props = defineProps({
    data: { type: Array, required: true },
});

const chartOptions = computed(() => ({
    chart: {
        type: 'line',
        background: 'transparent',
        foreColor: '#a1a1aa',
        toolbar: { show: false },
    },
    colors: ['#10b981'],
    stroke: {
        curve: 'smooth',
        width: 2,
    },
    xaxis: {
        type: 'datetime',
        labels: { style: { colors: '#a1a1aa' } },
    },
    yaxis: {
        labels: {
            style: { colors: '#a1a1aa' },
            formatter: (val) => `Rp ${(val / 1000).toFixed(0)}k`,
        },
    },
    grid: {
        borderColor: '#27272a',
        strokeDashArray: 4,
    },
    tooltip: {
        theme: 'dark',
        y: { formatter: (val) => `Rp ${val.toLocaleString('id-ID')}` },
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.3,
            opacityTo: 0,
            stops: [0, 90, 100],
        },
    },
}));

const series = computed(() => [
    {
        name: 'MRR',
        data: props.data.map((d) => ({
            x: new Date(d.snapshot_date).getTime(),
            y: d.mrr,
        })),
    },
]);
</script>

<template>
    <Card class="border-zinc-800 bg-zinc-900">
        <CardHeader>
            <CardTitle class="text-sm font-medium text-zinc-400">
                Revenue Trend (MRR)
            </CardTitle>
        </CardHeader>
        <CardContent>
            <apexchart
                v-if="series[0].data.length > 0"
                type="area"
                height="300"
                :options="chartOptions"
                :series="series"
            />
            <div
                v-else
                class="flex h-[300px] items-center justify-center text-sm text-zinc-600"
            >
                No revenue data available
            </div>
        </CardContent>
    </Card>
</template>
