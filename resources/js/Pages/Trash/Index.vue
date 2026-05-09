<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import { DataTable } from '@/Components/ui/data-table';
import { trashColumns } from './partials/trash-column';
import { Trash2 } from 'lucide-vue-next';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

const props = defineProps({
    trashedItems: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Sampah</title>
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
                        <BreadcrumbPage> Sampah </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <Trash2 class="w-5 h-5 text-muted-foreground" />
                        <h4 class="font-semibold">Sampah</h4>
                    </div>
                </div>
                <Separator class="my-4" />

                <div
                    v-if="trashedItems.total === 0 && !filters.search"
                    class="text-center py-12 text-muted-foreground"
                >
                    <Trash2 class="w-12 h-12 mx-auto mb-3 opacity-30" />
                    <p class="text-lg">Tidak ada data di sampah</p>
                    <p class="text-sm">
                        Data yang dihapus akan muncul di sini sebelum dihapus
                        secara permanen.
                    </p>
                </div>

                <div v-else class="mt-4">
                    <DataTable
                        :data="trashedItems.data"
                        :columns="trashColumns"
                        :route-name="'trash.index'"
                        :pagination="trashedItems"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
