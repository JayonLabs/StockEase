<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import {
    ArrowLeft,
    Pencil,
    Trash,
    Loader2,
    Package,
    Warehouse,
    TrendingUp,
    Barcode,
    Calendar,
    Tag,
    AlertTriangle,
} from 'lucide-vue-next';
import { Separator } from '@/Components/ui/separator';
import { formatPrice, formatDate } from '@/lib/utils';
import { Badge } from '@/Components/ui/badge';
import { toast } from 'vue-sonner';
import { ref, computed } from 'vue';
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
    product: {
        type: Object,
        required: true,
    },
});

const stockStatus = computed(() => {
    if (props.product.stock <= 0)
        return {
            label: 'Habis',
            variant: 'destructive',
            color: 'text-red-600',
        };
    if (props.product.stock <= props.product.alert_stock)
        return {
            label: 'Stok Rendah',
            variant: 'warning',
            color: 'text-amber-600',
        };
    return { label: 'Tersedia', variant: 'success', color: 'text-green-600' };
});

const isLoading = ref(false);
const isDialogOpen = ref(false);

const user = usePage().props.auth.user.name;

const destroy = () => {
    isLoading.value = true;

    router.delete(route('product.destroy', props.product.slug), {
        preserveScroll: true,
        showProgress: false,
        onSuccess: () => {
            toast.success('Produk dihapus', {
                description: `Produk ${props.product.name} berhasil dihapus oleh ${user}.`,
            });
        },
        onError: () => {
            toast.error('Gagal menghapus produk');
        },
        onFinish: () => {
            isLoading.value = false;
            isDialogOpen.value = false;
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Detail Produk - {{ product.name }}</title>
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
                        <Link :href="route('product.index')">
                            <BreadcrumbLink> Produk </BreadcrumbLink>
                        </Link>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage> {{ product.name }} </BreadcrumbPage>
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
                            :href="route('product.index')"
                            class="text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                        <h2 class="text-2xl font-bold tracking-tight">
                            Detail Produk
                        </h2>
                    </div>
                    <p class="text-muted-foreground">
                        Informasi lengkap mengenai produk pilihan Anda.
                    </p>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    <Link
                        :href="route('product.edit', product.slug)"
                        class="flex-1 md:flex-none"
                    >
                        <Button variant="outline" class="w-full gap-2">
                            <Pencil class="h-4 w-4" />
                            Edit
                        </Button>
                    </Link>

                    <AlertDialog v-model:open="isDialogOpen">
                        <AlertDialogTrigger as-child>
                            <Button
                                variant="destructive"
                                class="flex-1 md:flex-none gap-2"
                            >
                                <Trash class="h-4 w-4" />
                                Hapus
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Hapus Produk?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    Apakah Anda yakin ingin menghapus produk
                                    <span class="font-bold text-foreground">{{
                                        product.name
                                    }}</span
                                    >? Tindakan ini tidak dapat dibatalkan.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction
                                    class="bg-red-600 hover:bg-red-700 text-white"
                                    :disabled="isLoading"
                                    @click="destroy"
                                >
                                    <Loader2
                                        v-if="isLoading"
                                        class="mr-2 h-4 w-4 animate-spin"
                                    />
                                    Hapus Produk
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Product Image -->
                <div class="lg:col-span-1">
                    <Card class="overflow-hidden">
                        <div class="aspect-square relative group bg-muted/30">
                            <img
                                v-if="product.image_path"
                                :src="`/${product.image_path}`"
                                :alt="product.name"
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            />
                            <div
                                v-else
                                class="w-full h-full flex flex-col items-center justify-center text-muted-foreground gap-3"
                            >
                                <Package class="h-16 w-16 opacity-20" />
                                <span class="text-sm font-medium"
                                    >Tidak ada gambar</span
                                >
                            </div>

                            <div class="absolute top-4 left-4 flex gap-2">
                                <Badge
                                    :variant="
                                        stockStatus.variant === 'destructive'
                                            ? 'destructive'
                                            : 'secondary'
                                    "
                                    class="shadow-sm"
                                >
                                    {{ stockStatus.label }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="bg-white/90 dark:bg-zinc-900/90 shadow-sm backdrop-blur-sm"
                                >
                                    {{ product.category.name }}
                                </Badge>
                            </div>
                        </div>
                        <CardHeader class="p-6">
                            <CardTitle
                                class="text-xl line-clamp-2 leading-tight"
                            >
                                {{ product.name }}
                            </CardTitle>
                            <CardDescription class="font-mono text-xs">
                                {{ product.sku }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="px-6 pb-6 pt-0">
                            <div
                                class="p-4 rounded-xl bg-primary/5 dark:bg-primary/10 border border-primary/10 flex flex-col items-center justify-center text-center gap-1"
                            >
                                <span
                                    class="text-xs text-muted-foreground uppercase font-semibold tracking-wider"
                                    >Harga Jual</span
                                >
                                <span class="text-2xl font-bold text-primary">{{
                                    formatPrice(product.selling_price)
                                }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column: Details -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Inventory Stats -->
                        <Card>
                            <CardHeader class="pb-3">
                                <div class="flex items-center gap-2">
                                    <Warehouse
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <CardTitle class="text-sm font-medium">
                                        Inventaris
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent class="grid gap-4">
                                <div
                                    class="flex justify-between items-center py-2 border-b"
                                >
                                    <span class="text-sm text-muted-foreground"
                                        >Stok Saat Ini</span
                                    >
                                    <span
                                        class="text-lg font-bold"
                                        :class="stockStatus.color"
                                    >
                                        {{ product.stock }}
                                        {{ product.unit?.name ?? '-' }}
                                    </span>
                                </div>
                                <div
                                    class="flex justify-between items-center py-2 border-b"
                                >
                                    <span class="text-sm text-muted-foreground"
                                        >Batas Peringatan</span
                                    >
                                    <span class="font-medium"
                                        >{{ product.alert_stock }}
                                        {{ product.unit?.name ?? '-' }}</span
                                    >
                                </div>
                                <div
                                    class="flex justify-between items-center py-2"
                                >
                                    <span class="text-sm text-muted-foreground"
                                        >Satuan</span
                                    >
                                    <Badge variant="outline" class="capitalize">
                                        {{ product.unit?.name ?? '-' }}
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Pricing Stats -->
                        <Card>
                            <CardHeader class="pb-3">
                                <div class="flex items-center gap-2">
                                    <TrendingUp
                                        class="h-4 w-4 text-muted-foreground"
                                    />
                                    <CardTitle class="text-sm font-medium">
                                        Informasi Harga
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent class="grid gap-4">
                                <div
                                    class="flex justify-between items-center py-2 border-b"
                                >
                                    <span class="text-sm text-muted-foreground"
                                        >Harga Beli</span
                                    >
                                    <span class="font-mono font-medium">{{
                                        formatPrice(product.purchase_price)
                                    }}</span>
                                </div>
                                <div
                                    class="flex justify-between items-center py-2 border-b text-primary font-bold"
                                >
                                    <span class="text-sm">Harga Jual</span>
                                    <span class="font-mono">{{
                                        formatPrice(product.selling_price)
                                    }}</span>
                                </div>
                                <div
                                    class="flex justify-between items-center py-2"
                                >
                                    <span class="text-sm text-muted-foreground"
                                        >Margin</span
                                    >
                                    <span
                                        class="text-xs font-semibold px-2 py-0.5 rounded bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400"
                                    >
                                        {{
                                            formatPrice(
                                                product.selling_price -
                                                    product.purchase_price,
                                            )
                                        }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- General Info Card -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-sm font-medium">
                                Spesifikasi Lainnya
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div
                                class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4"
                            >
                                <div
                                    class="flex items-center justify-between py-2 border-b md:border-b-0"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="p-2 rounded-lg bg-muted flex items-center justify-center"
                                        >
                                            <Barcode
                                                class="h-4 w-4 text-muted-foreground"
                                            />
                                        </div>
                                        <span
                                            class="text-sm text-muted-foreground"
                                            >Barcode</span
                                        >
                                    </div>
                                    <span
                                        class="text-sm font-mono bg-muted/50 px-2 py-0.5 rounded"
                                        >{{ product.barcode || '-' }}</span
                                    >
                                </div>

                                <div
                                    class="flex items-center justify-between py-2 border-b md:border-b-0"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="p-2 rounded-lg bg-muted flex items-center justify-center"
                                        >
                                            <Tag
                                                class="h-4 w-4 text-muted-foreground"
                                            />
                                        </div>
                                        <span
                                            class="text-sm text-muted-foreground"
                                            >Kategori</span
                                        >
                                    </div>
                                    <span class="text-sm font-medium">{{
                                        product.category.name
                                    }}</span>
                                </div>

                                <div
                                    class="flex items-center justify-between py-2"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="p-2 rounded-lg bg-muted flex items-center justify-center"
                                        >
                                            <Calendar
                                                class="h-4 w-4 text-muted-foreground"
                                            />
                                        </div>
                                        <div class="flex flex-col">
                                            <span
                                                class="text-sm text-muted-foreground"
                                                >Tanggal Kedaluwarsa</span
                                            >
                                            <span
                                                class="text-[10px] text-muted-foreground italic"
                                                >(FEFO Batch Terdekat)</span
                                            >
                                        </div>
                                    </div>
                                    <span class="text-sm font-medium">
                                        {{
                                            product.expiry_date
                                                ? formatDate(
                                                      product.expiry_date,
                                                  )
                                                : '-'
                                        }}
                                    </span>
                                </div>

                                <div
                                    v-if="
                                        product.expiry_date &&
                                        new Date(product.expiry_date) <
                                            new Date()
                                    "
                                    class="col-span-full mt-2"
                                >
                                    <div
                                        class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30 flex items-center gap-3"
                                    >
                                        <AlertTriangle
                                            class="h-5 w-5 text-red-600 shrink-0"
                                        />
                                        <span
                                            class="text-xs text-red-800 dark:text-red-200"
                                        >
                                            Peringatan: Produk ini telah
                                            melewati tanggal kedaluwarsa.
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
