<script setup>
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
    data: { type: Array, required: true },
});
</script>

<template>
    <Card class="border-zinc-800 bg-zinc-900">
        <CardHeader>
            <CardTitle class="text-sm font-medium text-zinc-400">
                Active Tenants
            </CardTitle>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow class="border-zinc-800 hover:bg-transparent">
                        <TableHead class="text-zinc-500">Name</TableHead>
                        <TableHead class="text-zinc-500">Users</TableHead>
                        <TableHead class="text-zinc-500">Status</TableHead>
                        <TableHead class="text-zinc-500">Created</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="company in data"
                        :key="company.id"
                        class="border-zinc-800"
                    >
                        <TableCell class="font-medium text-zinc-100">
                            {{ company.name }}
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{ company.users_count ?? '-' }}
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant="outline"
                                class="border-emerald-800 bg-emerald-950 text-emerald-400"
                            >
                                Active
                            </Badge>
                        </TableCell>
                        <TableCell class="text-zinc-400">
                            {{
                                new Date(company.created_at).toLocaleDateString(
                                    'id-ID',
                                )
                            }}
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="data.length === 0">
                        <TableCell
                            colspan="4"
                            class="text-center text-zinc-600"
                        >
                            No tenants registered yet
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
