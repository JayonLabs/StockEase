<script setup>
import { Head, Link } from '@inertiajs/vue3';
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
import { Eye } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';

defineProps({
    subscriptions: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const statusBadge = (status) => {
    const m = {
        active: 'border-emerald-800 bg-emerald-950 text-emerald-400',
        trialing: 'border-yellow-800 bg-yellow-950 text-yellow-400',
        canceled: 'border-red-800 bg-red-950 text-red-400',
        expired: 'border-zinc-700 bg-zinc-800 text-zinc-500',
        pending: 'border-blue-800 bg-blue-950 text-blue-400',
    };
    return m[status] || 'border-zinc-700 bg-zinc-800 text-zinc-500';
};

const statusLabel = (s) => {
    const m = {
        active: 'Active',
        trialing: 'Trial',
        canceled: 'Canceled',
        expired: 'Expired',
        pending: 'Pending',
    };
    return m[s] || s;
};

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('id-ID') : '-');
</script>

<template>
    <Head title="Subscriptions - Platform Owner" />

    <Card class="border-zinc-800 bg-zinc-900">
        <CardHeader>
            <CardTitle class="text-zinc-100"> All Subscriptions </CardTitle>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow class="border-zinc-800 hover:bg-transparent">
                        <TableHead class="text-zinc-500">Company</TableHead>
                        <TableHead class="text-zinc-500">Plan</TableHead>
                        <TableHead class="text-zinc-500">Status</TableHead>
                        <TableHead class="text-zinc-500">Billing</TableHead>
                        <TableHead class="text-zinc-500">Ends At</TableHead>
                        <TableHead class="text-zinc-500 w-20">Action</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="sub in subscriptions.data"
                        :key="sub.id"
                        class="border-zinc-800"
                    >
                        <TableCell class="font-medium text-zinc-100">
                            {{ sub.company?.name ?? '-' }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ sub.plan?.name ?? '-' }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="outline"
                                :class="statusBadge(sub.status)"
                            >
                                {{ statusLabel(sub.status) }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{
                                sub.billing_cycle === 'annual'
                                    ? 'Annual'
                                    : 'Monthly'
                            }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ formatDate(sub.ends_at) }}
                        </TableCell>
                        <TableCell>
                            <Link
                                :href="
                                    route(
                                        'platform.owner.subscriptions.show',
                                        sub.id,
                                    )
                                "
                            >
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="text-zinc-400 hover:text-zinc-100"
                                >
                                    <Eye class="w-4 h-4" />
                                </Button>
                            </Link>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="subscriptions.data.length === 0">
                        <TableCell
                            colspan="6"
                            class="text-center text-zinc-600 py-8"
                        >
                            No subscriptions found
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
