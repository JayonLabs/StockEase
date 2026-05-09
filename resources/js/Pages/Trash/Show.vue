<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Separator } from '@/Components/ui/separator';
import { formatDate } from '@/lib/utils';
import { Badge } from '@/Components/ui/badge';
import { toast } from 'vue-sonner';
import { ref } from 'vue';

import {
    ArrowLeft,
    RotateCcw,
    Trash2,
    Loader2,
    Info,
    Calendar,
    Tag,
} from 'lucide-vue-next';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';

const props = defineProps({
    trashedItem: {
        type: Object,
        required: true,
    },
});

const user = usePage().props.auth.user.name;
const isRestoring = ref(false);
const isForceDeleting = ref(false);
const isRestoreOpen = ref(false);
const isForceDeleteOpen = ref(false);

const handleRestore = () => {
    isRestoring.value = true;

    router.post(
        route('trash.restore'),
        {
            type: props.trashedItem.type,
            id: props.trashedItem.id,
        },
        {
            preserveScroll: true,
            showProgress: false,
            onSuccess: () => {
                toast.success(
                    `${props.trashedItem.type_label} berhasil dipulihkan`,
                    {
                        description: `${props.trashedItem.name} berhasil dipulihkan oleh ${user}`,
                    },
                );
            },
            onError: () => {
                toast.error(`${props.trashedItem.type_label} gagal dipulihkan`);
            },
            onFinish: () => {
                isRestoring.value = false;
                isRestoreOpen.value = false;
            },
        },
    );
};

const handleForceDelete = () => {
    isForceDeleting.value = true;

    router.delete(route('trash.force-destroy'), {
        data: {
            type: props.trashedItem.type,
            id: props.trashedItem.id,
        },
        preserveScroll: true,
        showProgress: false,
        onSuccess: () => {
            toast.success(
                `${props.trashedItem.type_label} berhasil dihapus permanen`,
                {
                    description: `${props.trashedItem.name} berhasil dihapus permanen oleh ${user}`,
                },
            );
        },
        onError: () => {
            toast.error(`${props.trashedItem.type_label} gagal dihapus`);
        },
        onFinish: () => {
            isForceDeleting.value = false;
            isForceDeleteOpen.value = false;
        },
    });
};

const formatValue = (value) => {
    if (value === null || value === undefined) return '-';
    if (typeof value === 'boolean') return value ? 'Ya' : 'Tidak';
    return String(value);
};
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Detail Sampah - {{ trashedItem.name }}</title>
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
                        <Link :href="route('trash.index')">
                            <BreadcrumbLink> Sampah </BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>
                            {{ trashedItem.name }}
                        </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="flex flex-1 flex-col gap-6 p-6 w-full">
            <div
                class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4"
            >
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link
                            :href="route('trash.index')"
                            class="text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                        <h2 class="text-2xl font-bold tracking-tight">
                            Detail Sampah
                        </h2>
                    </div>
                    <p class="text-muted-foreground">
                        Detail data yang telah dihapus.
                    </p>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    <!-- Restore Button -->
                    <AlertDialog v-model:open="isRestoreOpen">
                        <AlertDialogTrigger as-child>
                            <Button
                                variant="outline"
                                class="flex-1 md:flex-none gap-2 text-green-600 border-green-300 hover:bg-green-50 dark:hover:bg-green-950"
                            >
                                <RotateCcw class="h-4 w-4" />
                                Pulihkan
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Pulihkan data ini?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    Data
                                    <strong>{{ trashedItem.name }}</strong> ({{
                                        trashedItem.type_label
                                    }}) akan dikembalikan ke tempat semula.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction
                                    class="bg-green-500 hover:bg-green-600 text-white"
                                    @click="handleRestore"
                                >
                                    <Loader2
                                        v-if="isRestoring"
                                        class="w-4 h-4 animate-spin"
                                    />
                                    {{
                                        isRestoring
                                            ? 'Memulihkan...'
                                            : 'Pulihkan'
                                    }}
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>

                    <!-- Force Delete Button -->
                    <AlertDialog v-model:open="isForceDeleteOpen">
                        <AlertDialogTrigger as-child>
                            <Button
                                variant="destructive"
                                class="flex-1 md:flex-none gap-2"
                            >
                                <Trash2 class="h-4 w-4" />
                                Hapus Permanen
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Hapus permanen?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    Data
                                    <strong>{{ trashedItem.name }}</strong> ({{
                                        trashedItem.type_label
                                    }}) akan dihapus secara permanen. Tindakan
                                    ini <strong>tidak dapat dibatalkan</strong>!
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction
                                    class="bg-red-500 hover:bg-red-600 text-white"
                                    @click="handleForceDelete"
                                >
                                    <Loader2
                                        v-if="isForceDeleting"
                                        class="w-4 h-4 animate-spin"
                                    />
                                    {{
                                        isForceDeleting
                                            ? 'Menghapus...'
                                            : 'Hapus Permanen'
                                    }}
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Item Summary -->
                <div class="lg:col-span-1">
                    <Card class="overflow-hidden">
                        <CardHeader class="p-6">
                            <div class="flex items-center gap-2 mb-2">
                                <Info class="h-4 w-4 text-muted-foreground" />
                                <CardTitle class="text-sm font-medium">
                                    Informasi Item
                                </CardTitle>
                            </div>
                            <CardDescription
                                class="text-lg font-semibold text-foreground line-clamp-2"
                            >
                                {{ trashedItem.name }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="px-6 pb-6 pt-0 space-y-4">
                            <div
                                class="flex justify-between items-center py-2 border-b"
                            >
                                <span class="text-sm text-muted-foreground"
                                    >Tipe</span
                                >
                                <Badge variant="secondary">
                                    {{ trashedItem.type_label }}
                                </Badge>
                            </div>
                            <div
                                class="flex justify-between items-center py-2 border-b"
                            >
                                <span class="text-sm text-muted-foreground"
                                    >ID</span
                                >
                                <span class="text-sm font-mono">{{
                                    trashedItem.id
                                }}</span>
                            </div>
                            <div class="flex justify-between items-center py-2">
                                <span class="text-sm text-muted-foreground"
                                    >Dihapus Pada</span
                                >
                                <span class="text-sm font-medium">{{
                                    formatDate(trashedItem.deleted_at)
                                }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column: Attributes -->
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader class="pb-3">
                            <div class="flex items-center gap-2">
                                <Tag class="h-4 w-4 text-muted-foreground" />
                                <CardTitle class="text-sm font-medium">
                                    Atribut Data
                                </CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="trashedItem.attributes.length === 0"
                                class="text-center py-8 text-muted-foreground text-sm"
                            >
                                Tidak ada atribut yang tersedia.
                            </div>
                            <div
                                v-else
                                class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4"
                            >
                                <div
                                    v-for="attr in trashedItem.attributes"
                                    :key="attr.key"
                                    class="flex items-center justify-between py-2 border-b"
                                >
                                    <span
                                        class="text-sm text-muted-foreground"
                                        >{{ attr.key }}</span
                                    >
                                    <span
                                        class="text-sm font-medium text-right max-w-[60%] truncate"
                                    >
                                        {{ formatValue(attr.value) }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
