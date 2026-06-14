<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
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
import { ArrowLeft, Eye } from 'lucide-vue-next';
import { formatPrice } from '@/lib/utils';
import { Separator } from '@/Components/ui/separator';

defineProps({
    subscriptions: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const statusVariant = (status) => {
    const map = {
        active: 'text-green-600 border-green-600',
        trialing: 'text-yellow-600 border-yellow-600',
        canceled: 'text-red-600 border-red-600',
        expired: 'text-gray-600 border-gray-600',
        pending: 'text-blue-600 border-blue-600',
    };
    return map[status] || '';
};

const statusLabel = (status) => {
    const map = {
        active: 'Aktif',
        trialing: 'Trial',
        canceled: 'Dibatalkan',
        expired: 'Expired',
        pending: 'Pending',
    };
    return map[status] || status;
};

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('id-ID') : '-');
</script>

<template>
    <AuthenticatedLayout>
        <Head><title>Subscription - Admin</title></Head>
        <template #breadcrumb>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('dashboard')"
                    class="text-muted-foreground hover:text-foreground"
                >
                    Dashboard
                </Link>
                <span class="text-muted-foreground">/</span>
                <span class="font-medium">Subscription</span>
            </div>
        </template>

        <Card>
            <CardHeader>
                <CardTitle>Daftar Subscription</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Company</TableHead>
                            <TableHead>Plan</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Siklus</TableHead>
                            <TableHead>Berakhir</TableHead>
                            <TableHead class="w-20">Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="sub in subscriptions.data"
                            :key="sub.id"
                        >
                            <TableCell class="font-medium">
                                {{ sub.company?.name }}
                            </TableCell>
                            <TableCell>{{ sub.plan?.name }}</TableCell>
                            <TableCell>
                                <Badge
                                    variant="outline"
                                    :class="statusVariant(sub.status)"
                                >
                                    {{ statusLabel(sub.status) }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                {{
                                    sub.billing_cycle === 'annual'
                                        ? 'Tahunan'
                                        : 'Bulanan'
                                }}
                            </TableCell>
                            <TableCell>{{ formatDate(sub.ends_at) }}</TableCell>
                            <TableCell>
                                <Link
                                    :href="
                                        route(
                                            'admin.subscriptions.show',
                                            sub.id,
                                        )
                                    "
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Lihat detail"
                                    >
                                        <Eye class="w-4 h-4" />
                                    </Button>
                                </Link>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="subscriptions.data.length === 0">
                            <TableCell
                                colspan="6"
                                class="text-center text-muted-foreground py-8"
                            >
                                Tidak ada subscription
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </AuthenticatedLayout>
</template>
