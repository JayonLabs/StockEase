<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
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
import { ArrowLeft } from 'lucide-vue-next';
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

const statusVariant = (s) => {
    const m = {
        active: 'green',
        trialing: 'yellow',
        canceled: 'red',
        expired: 'gray',
    };
    const c = m[s] || 'gray';
    return `text-${c}-600 border-${c}-600`;
};

const invoiceStatusVariant = (s) => {
    const m = {
        paid: 'green',
        pending: 'blue',
        failed: 'red',
        expired: 'gray',
    };
    const c = m[s] || 'gray';
    return `text-${c}-600 border-${c}-600`;
};
</script>

<template>
    <AuthenticatedLayout>
        <Head><title>Detail Subscription - Admin</title></Head>
        <template #breadcrumb>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('dashboard')"
                    class="text-muted-foreground hover:text-foreground"
                >
                    Dashboard
                </Link>
                <span class="text-muted-foreground">/</span>
                <Link
                    :href="route('admin.subscriptions.index')"
                    class="text-muted-foreground hover:text-foreground"
                >
                    Subscription
                </Link>
                <span class="text-muted-foreground">/</span>
                <span class="font-medium">Detail</span>
            </div>
        </template>

        <div class="space-y-6">
            <div>
                <Link :href="route('admin.subscriptions.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="w-4 h-4 mr-2" />
                        Kembali
                    </Button>
                </Link>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Informasi Subscription</CardTitle>
                        <CardDescription>
                            Detail langganan company
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm text-muted-foreground">
                                    Company
                                </dt>
                                <dd class="font-medium">
                                    {{ subscription.company?.name }}
                                </dd>
                            </div>
                            <Separator />
                            <div>
                                <dt class="text-sm text-muted-foreground">
                                    Plan
                                </dt>
                                <dd class="font-medium">
                                    {{ subscription.plan?.name }}
                                </dd>
                            </div>
                            <Separator />
                            <div>
                                <dt class="text-sm text-muted-foreground">
                                    Status
                                </dt>
                                <dd>
                                    <Badge
                                        variant="outline"
                                        :class="
                                            statusVariant(subscription.status)
                                        "
                                    >
                                        {{ subscription.status }}
                                    </Badge>
                                </dd>
                            </div>
                            <Separator />
                            <div>
                                <dt class="text-sm text-muted-foreground">
                                    Siklus
                                </dt>
                                <dd>
                                    {{
                                        subscription.billing_cycle === 'annual'
                                            ? 'Tahunan'
                                            : 'Bulanan'
                                    }}
                                </dd>
                            </div>
                            <Separator />
                            <div>
                                <dt class="text-sm text-muted-foreground">
                                    Mulai
                                </dt>
                                <dd>
                                    {{ formatDate(subscription.starts_at) }}
                                </dd>
                            </div>
                            <Separator />
                            <div>
                                <dt class="text-sm text-muted-foreground">
                                    Berakhir
                                </dt>
                                <dd>{{ formatDate(subscription.ends_at) }}</dd>
                            </div>
                            <template v-if="subscription.trial_ends_at">
                                <Separator />
                                <div>
                                    <dt class="text-sm text-muted-foreground">
                                        Trial Berakhir
                                    </dt>
                                    <dd>
                                        {{
                                            formatDate(
                                                subscription.trial_ends_at,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </template>
                        </dl>
                    </CardContent>
                </Card>

                <Card v-if="subscription.company?.owner">
                    <CardHeader>
                        <CardTitle>Pemilik Company</CardTitle>
                        <CardDescription
                            >Detail pemilik organisasi</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm text-muted-foreground">
                                    Nama
                                </dt>
                                <dd class="font-medium">
                                    {{ subscription.company.owner.name }}
                                </dd>
                            </div>
                            <Separator />
                            <div>
                                <dt class="text-sm text-muted-foreground">
                                    Email
                                </dt>
                                <dd>{{ subscription.company.owner.email }}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>
            </div>

            <Card v-if="subscription.invoices?.length">
                <CardHeader>
                    <CardTitle>Invoice History</CardTitle>
                    <CardDescription>Riwayat pembayaran</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Order ID</TableHead>
                                <TableHead>Jumlah</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Pembayaran</TableHead>
                                <TableHead>Tanggal</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="inv in subscription.invoices"
                                :key="inv.id"
                            >
                                <TableCell class="font-mono text-xs">
                                    {{ inv.midtrans_order_id ?? '-' }}
                                </TableCell>
                                <TableCell>{{
                                    formatPrice(inv.amount)
                                }}</TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        :class="
                                            invoiceStatusVariant(inv.status)
                                        "
                                    >
                                        {{ inv.status }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{
                                    inv.midtrans_payment_type ?? '-'
                                }}</TableCell>
                                <TableCell>{{
                                    formatDate(inv.created_at)
                                }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
