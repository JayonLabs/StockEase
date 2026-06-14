<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Building2, ArrowLeft } from 'lucide-vue-next';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Separator } from '@/Components/ui/separator';
import { Button } from '@/Components/ui/button';
import { formatPrice } from '@/lib/utils';

defineProps({
    subscription: { type: Object, required: true },
});

const formatDate = (d) => {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusBadge = (s) => {
    const m = {
        active: 'border-emerald-800 bg-emerald-950 text-emerald-400',
        trialing: 'border-yellow-800 bg-yellow-950 text-yellow-400',
        canceled: 'border-red-800 bg-red-950 text-red-400',
        expired: 'border-zinc-700 bg-zinc-800 text-zinc-500',
        pending: 'border-blue-800 bg-blue-950 text-blue-400',
    };
    return m[s] || 'border-zinc-700 bg-zinc-800 text-zinc-500';
};

const invoiceStatusBadge = (s) => {
    const m = {
        paid: 'border-emerald-800 bg-emerald-950 text-emerald-400',
        pending: 'border-blue-800 bg-blue-950 text-blue-400',
        failed: 'border-red-800 bg-red-950 text-red-400',
        expired: 'border-zinc-700 bg-zinc-800 text-zinc-500',
    };
    return m[s] || 'border-zinc-700 bg-zinc-800 text-zinc-500';
};
</script>

<template>
    <Head title="Subscription Detail - Platform Owner" />

    <div>
        <Link :href="route('platform.owner.subscriptions.index')">
            <Button
                variant="ghost"
                size="sm"
                class="text-zinc-400 hover:text-zinc-100 mb-4"
            >
                <ArrowLeft class="w-4 h-4 mr-2" />
                Back to Subscriptions
            </Button>
        </Link>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <Card class="border-zinc-800 bg-zinc-900">
            <CardHeader>
                <CardTitle class="text-zinc-100">Subscription Info</CardTitle>
                <CardDescription class="text-zinc-500">
                    Detail langganan company
                </CardDescription>
            </CardHeader>
            <CardContent>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-zinc-500">Company</dt>
                        <dd class="font-medium text-zinc-100">
                            {{ subscription.company?.name ?? '-' }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Plan</dt>
                        <dd class="font-medium text-zinc-100">
                            {{ subscription.plan?.name ?? '-' }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Status</dt>
                        <dd>
                            <Badge
                                variant="outline"
                                :class="statusBadge(subscription.status)"
                            >
                                {{ subscription.status }}
                            </Badge>
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Billing Cycle</dt>
                        <dd class="text-zinc-400">
                            {{
                                subscription.billing_cycle === 'annual'
                                    ? 'Annual'
                                    : 'Monthly'
                            }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Starts At</dt>
                        <dd class="text-zinc-400">
                            {{ formatDate(subscription.starts_at) }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Ends At</dt>
                        <dd class="text-zinc-400">
                            {{ formatDate(subscription.ends_at) }}
                        </dd>
                    </div>
                    <template v-if="subscription.trial_ends_at">
                        <Separator class="bg-zinc-800" />
                        <div>
                            <dt class="text-sm text-zinc-500">Trial Ends At</dt>
                            <dd class="text-zinc-400">
                                {{ formatDate(subscription.trial_ends_at) }}
                            </dd>
                        </div>
                    </template>
                    <template v-if="subscription.canceled_at">
                        <Separator class="bg-zinc-800" />
                        <div>
                            <dt class="text-sm text-zinc-500">Canceled At</dt>
                            <dd class="text-zinc-400">
                                {{ formatDate(subscription.canceled_at) }}
                            </dd>
                        </div>
                    </template>
                    <template v-if="subscription.notes">
                        <Separator class="bg-zinc-800" />
                        <div>
                            <dt class="text-sm text-zinc-500">Notes</dt>
                            <dd class="text-zinc-400">
                                {{ subscription.notes }}
                            </dd>
                        </div>
                    </template>
                </dl>
            </CardContent>
        </Card>

        <Card v-if="subscription.company" class="border-zinc-800 bg-zinc-900">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-zinc-100">
                    <Building2 class="w-5 h-5 text-emerald-400" />
                    Company
                </CardTitle>
                <CardDescription class="text-zinc-500">
                    Organisasi pemilik langganan
                </CardDescription>
            </CardHeader>
            <CardContent>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-sm text-zinc-500">Name</dt>
                        <dd class="font-medium text-zinc-100">
                            {{ subscription.company.name }}
                        </dd>
                    </div>
                    <Separator class="bg-zinc-800" />
                    <div>
                        <dt class="text-sm text-zinc-500">Slug</dt>
                        <dd class="text-zinc-400">
                            {{ subscription.company.slug }}
                        </dd>
                    </div>
                    <template v-if="subscription.company.owner">
                        <Separator class="bg-zinc-800" />
                        <div>
                            <dt class="text-sm text-zinc-500">Owner</dt>
                            <dd class="text-zinc-100">
                                {{ subscription.company.owner.name }}
                            </dd>
                        </div>
                        <Separator class="bg-zinc-800" />
                        <div>
                            <dt class="text-sm text-zinc-500">Email</dt>
                            <dd class="text-zinc-400">
                                {{ subscription.company.owner.email }}
                            </dd>
                        </div>
                    </template>
                </dl>
            </CardContent>
        </Card>
    </div>

    <Card
        v-if="subscription.invoices?.length"
        class="mt-6 border-zinc-800 bg-zinc-900"
    >
        <CardHeader>
            <CardTitle class="text-zinc-100">Invoice History</CardTitle>
            <CardDescription class="text-zinc-500"
                >Riwayat pembayaran</CardDescription
            >
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow class="border-zinc-800 hover:bg-transparent">
                        <TableHead class="text-zinc-500">Order ID</TableHead>
                        <TableHead class="text-zinc-500">Amount</TableHead>
                        <TableHead class="text-zinc-500">Status</TableHead>
                        <TableHead class="text-zinc-500"
                            >Payment Method</TableHead
                        >
                        <TableHead class="text-zinc-500">Date</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="inv in subscription.invoices"
                        :key="inv.id"
                        class="border-zinc-800"
                    >
                        <TableCell class="font-mono text-xs text-zinc-400">
                            {{ inv.midtrans_order_id ?? '-' }}
                        </TableCell>
                        <TableCell class="text-zinc-400">{{
                            formatPrice(inv.amount)
                        }}</TableCell>
                        <TableCell>
                            <Badge
                                variant="outline"
                                :class="invoiceStatusBadge(inv.status)"
                            >
                                {{ inv.status }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-zinc-400">{{
                            inv.midtrans_payment_type ?? '-'
                        }}</TableCell>
                        <TableCell class="text-zinc-400">{{
                            formatDate(inv.created_at)
                        }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
