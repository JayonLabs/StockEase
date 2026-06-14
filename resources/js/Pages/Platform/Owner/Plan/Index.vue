<script setup>
import { Head } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';

defineProps({
    plans: { type: Array, required: true },
});

const formatPrice = (value) => {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};
</script>

<template>
    <Head title="Plans - Platform Owner" />

    <Card class="border-zinc-800 bg-zinc-900">
        <CardHeader>
            <CardTitle class="text-zinc-100"> Manage Plans </CardTitle>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow class="border-zinc-800 hover:bg-transparent">
                        <TableHead class="text-zinc-500">Plan</TableHead>
                        <TableHead class="text-zinc-500">Price/Month</TableHead>
                        <TableHead class="text-zinc-500">Price/Year</TableHead>
                        <TableHead class="text-zinc-500"
                            >Max Products</TableHead
                        >
                        <TableHead class="text-zinc-500">Max Users</TableHead>
                        <TableHead class="text-zinc-500"
                            >Max Warehouses</TableHead
                        >
                        <TableHead class="text-zinc-500">Trial</TableHead>
                        <TableHead class="text-zinc-500">Subscribers</TableHead>
                        <TableHead class="text-zinc-500">Active</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="plan in plans"
                        :key="plan.id"
                        class="border-zinc-800"
                    >
                        <TableCell class="font-medium text-zinc-100">
                            {{ plan.name }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ formatPrice(plan.price_monthly) }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ formatPrice(plan.price_annual) }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ plan.max_products ?? 'Unlimited' }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ plan.max_users ?? 'Unlimited' }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ plan.max_warehouses ?? 'Unlimited' }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ plan.trial_days }} days
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ plan.subscriptions_count ?? 0 }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="outline"
                                :class="
                                    plan.is_active
                                        ? 'border-emerald-800 bg-emerald-950 text-emerald-400'
                                        : 'border-zinc-700 bg-zinc-800 text-zinc-500'
                                "
                            >
                                {{ plan.is_active ? 'Yes' : 'No' }}
                            </Badge>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="plans.length === 0">
                        <TableCell
                            colspan="9"
                            class="text-center text-zinc-600 py-8"
                        >
                            No plans configured yet
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
