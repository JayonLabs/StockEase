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
    companies: { type: Object, required: true },
});

const formatDate = (d) => (d ? new Date(d).toLocaleDateString('id-ID') : '-');
</script>

<template>
    <Head title="Companies - Platform Owner" />

    <Card class="border-zinc-800 bg-zinc-900">
        <CardHeader>
            <CardTitle class="text-zinc-100"> All Companies </CardTitle>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow class="border-zinc-800 hover:bg-transparent">
                        <TableHead class="text-zinc-500">Name</TableHead>
                        <TableHead class="text-zinc-500">Plan</TableHead>
                        <TableHead class="text-zinc-500">Status</TableHead>
                        <TableHead class="text-zinc-500">Users</TableHead>
                        <TableHead class="text-zinc-500">Created</TableHead>
                        <TableHead class="text-zinc-500 w-20">Action</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="company in companies.data"
                        :key="company.id"
                        class="border-zinc-800"
                    >
                        <TableCell class="font-medium text-zinc-100">
                            {{ company.name }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ company.subscription?.plan?.name ?? '-' }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                v-if="company.subscription"
                                variant="outline"
                                class="border-emerald-800 bg-emerald-950 text-emerald-400"
                            >
                                {{ company.subscription.status }}
                            </Badge>
                            <Badge
                                v-else
                                variant="outline"
                                class="border-zinc-700 bg-zinc-800 text-zinc-500"
                            >
                                No subscription
                            </Badge>
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ company.users_count ?? 0 }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ formatDate(company.created_at) }}
                        </TableCell>
                        <TableCell>
                            <Link
                                :href="
                                    route(
                                        'platform.owner.companies.show',
                                        company.id,
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
                    <TableRow v-if="companies.data.length === 0">
                        <TableCell
                            colspan="6"
                            class="text-center text-zinc-600 py-8"
                        >
                            No companies registered yet
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
