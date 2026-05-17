<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';
import { Head, Link } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { DataTable } from '@/Components/ui/data-table';
import { computed } from 'vue';
import { getUserPermissionColumns } from './partials/user-permission-columns';
import { AlertCircle } from 'lucide-vue-next';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    permissions: {
        type: Array,
        required: true,
    },
});

const columns = computed(() => getUserPermissionColumns(props.permissions));
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>User Permission</title>
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
                        <BreadcrumbPage> User Permission </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <h4 class="font-semibold">User Permission</h4>
                </div>
                <Separator class="my-4" />

                <!-- Best Practice Info Banner -->
                <div
                    class="rounded-lg border border-amber-500/20 bg-amber-500/10 p-4 mb-4"
                >
                    <div class="flex items-start gap-3">
                        <AlertCircle
                            class="w-5 h-5 text-amber-500 mt-0.5 shrink-0"
                        />
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-amber-500">
                                Best Practice: Gunakan Role untuk Permission
                            </p>
                            <p class="text-sm text-amber-500/80">
                                Sebaiknya berikan permission melalui Role (Role
                                Permission), bukan langsung ke user. Direct
                                permission hanya untuk kasus exception/override
                                tertentu. User akan tetap mendapat permission
                                dari role-nya.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <DataTable
                        :data="users.data"
                        :columns="columns"
                        :route-name="'user-permissions.index'"
                        :pagination="users"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
