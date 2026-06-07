<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Pencil } from 'lucide-vue-next';
import RolePermissionDetail from './form/RolePermissionDetail.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

const props = defineProps({
    roles: {
        type: Array,
        required: true,
    },
    permissions: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Role Permission</title>
        </Head>
        <template #breadcrumb>
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <Link :href="route('dashboard')">
                            <BreadcrumbLink> Dashboard </BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage> Role Permission </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <h4 class="font-semibold">Role Permission</h4>
                </div>
                <Separator class="my-4" />

                <div class="mt-4 rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow class="hover:bg-transparent">
                                <TableHead class="border-b">
                                    Nama Role
                                </TableHead>
                                <TableHead class="border-b">
                                    Jumlah Permission
                                </TableHead>
                                <TableHead class="border-b text-center w-40">
                                    Aksi
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody class="divide-y">
                            <TableRow
                                v-for="role in roles"
                                :key="role.id"
                                class="hover:bg-transparent"
                            >
                                <TableCell
                                    class="border-b capitalize font-medium"
                                >
                                    {{ role.name }}
                                </TableCell>
                                <TableCell class="border-b">
                                    <Badge variant="secondary" class="text-xs">
                                        {{ role.permissions.length }}
                                        permission{{
                                            role.permissions.length !== 1
                                                ? 's'
                                                : ''
                                        }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="border-b text-center">
                                    <div
                                        class="flex items-center justify-center gap-1"
                                    >
                                        <RolePermissionDetail :role="role" />
                                        <Link
                                            :href="
                                                route(
                                                    'role-permissions.edit',
                                                    role.id,
                                                )
                                            "
                                        >
                                            <Button
                                                aria-label="Kelola permission"
                                                variant="ghost"
                                                size="icon"
                                                class="group"
                                            >
                                                <Pencil
                                                    class="w-4 h-4 text-blue-500 dark:group-hover:text-white"
                                                />
                                            </Button>
                                        </Link>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
