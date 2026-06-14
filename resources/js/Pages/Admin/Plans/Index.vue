<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
import { formatPrice } from '@/lib/utils';

defineProps({
    plans: { type: Array, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head><title>Plans - Admin</title></Head>
        <template #breadcrumb>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('dashboard')"
                    class="text-muted-foreground hover:text-foreground"
                >
                    Dashboard
                </Link>
                <span class="text-muted-foreground">/</span>
                <span class="font-medium">Plans</span>
            </div>
        </template>

        <Card>
            <CardHeader>
                <CardTitle>Kelola Plans</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Plan</TableHead>
                            <TableHead>Harga/Bulan</TableHead>
                            <TableHead>Harga/Tahun</TableHead>
                            <TableHead>Max Produk</TableHead>
                            <TableHead>Max User</TableHead>
                            <TableHead>Max Gudang</TableHead>
                            <TableHead>Trial</TableHead>
                            <TableHead>Aktif</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="plan in plans" :key="plan.id">
                            <TableCell class="font-medium">
                                {{ plan.name }}
                            </TableCell>
                            <TableCell>{{
                                formatPrice(plan.price_monthly)
                            }}</TableCell>
                            <TableCell>{{
                                formatPrice(plan.price_annual)
                            }}</TableCell>
                            <TableCell>{{
                                plan.max_products ?? 'Unlimited'
                            }}</TableCell>
                            <TableCell>{{
                                plan.max_users ?? 'Unlimited'
                            }}</TableCell>
                            <TableCell>{{
                                plan.max_warehouses ?? 'Unlimited'
                            }}</TableCell>
                            <TableCell>{{ plan.trial_days }} hari</TableCell>
                            <TableCell>{{
                                plan.is_active ? 'Ya' : 'Tidak'
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </AuthenticatedLayout>
</template>
