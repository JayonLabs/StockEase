<script setup>
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { computed } from 'vue';

const props = defineProps({
    data: { type: Array, required: true },
});

const chartOptions = computed(() => ({
    chart: {
        type: 'donut',
        background: 'transparent',
        foreColor: '#a1a1aa',
    },
    labels: props.data.map((d) => d.plan),
    colors: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#ef4444'],
    legend: {
        position: 'bottom',
        labels: { colors: '#a1a1aa' },
    },
    dataLabels: {
        style: { colors: ['#fff'] },
    },
    plotOptions: {
        pie: {
            donut: {
                size: '65%',
                labels: {
                    show: true,
                    total: {
                        show: true,
                        label: 'Total',
                        color: '#a1a1aa',
                        formatter: (w) =>
                            w.globals.seriesTotals.reduce((a, b) => a + b, 0),
                    },
                },
            },
        },
    },
    responsive: [
        {
            breakpoint: 480,
            options: { chart: { width: 300 }, legend: { position: 'bottom' } },
        },
    ],
}));

const series = computed(() => props.data.map((d) => d.count));
</script>

<template>
    <Card class="border-zinc-800 bg-zinc-900">
        <CardHeader>
            <CardTitle class="text-sm font-medium text-zinc-400">
                Subscription Breakdown
            </CardTitle>
        </CardHeader>
        <CardContent>
            <apexchart
                v-if="series.length > 0"
                type="donut"
                height="300"
                :options="chartOptions"
                :series="series"
            />
            <div
                v-else
                class="flex h-[300px] items-center justify-center text-sm text-zinc-600"
            >
                No subscription data available
            </div>
        </CardContent>
    </Card>
</template>
