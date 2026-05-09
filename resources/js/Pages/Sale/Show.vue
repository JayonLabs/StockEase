<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { ArrowLeftToLine, PrinterIcon } from 'lucide-vue-next';
import { Separator } from '@/Components/ui/separator';
import { formatPrice } from '@/lib/utils';
import ProductTableSaleDetail from './partials/ProductTableSaleDetail.vue';
import SaleDetailHeader from './partials/SaleDetailHeader.vue';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

const props = defineProps({
    sale: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>Data Penjualan</title>
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
                        <BreadcrumbPage> Detail Penjualan </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <h4 class="font-semibold">Detail Penjualan</h4>
                    <Link :href="route('sale.index')">
                        <Button
                            variant="outline"
                            class="dark:border-white border-zinc-600"
                        >
                            <ArrowLeftToLine />
                            Kembali ke daftar penjualan
                        </Button>
                    </Link>
                </div>
                <Separator class="my-4" />

                <div
                    class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/3 w-full"
                >
                    <div
                        class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800"
                    >
                        <h3
                            class="font-medium text-gray-800 text-theme-xl dark:text-white/90"
                        >
                            Detail Penjualan
                        </h3>

                        <h4
                            class="text-base font-medium text-gray-700 dark:text-gray-400"
                        >
                            ID : #{{ props.sale.id }}
                        </h4>
                    </div>

                    <div class="p-5 xl:p-8">
                        <SaleDetailHeader :sale="props.sale" />

                        <ProductTableSaleDetail :sale="props.sale" />

                        <div
                            class="pb-6 my-6 text-right border-b border-gray-100 dark:border-gray-800"
                        >
                            <p
                                class="mb-2 text-sm text-gray-500 dark:text-gray-400"
                            >
                                Uang Diterima:
                                {{ formatPrice(props.sale.paid) }}
                            </p>
                            <p
                                class="mb-3 text-sm text-gray-500 dark:text-gray-400"
                            >
                                Kembalian:
                                {{ formatPrice(props.sale.change) }}
                            </p>

                            <p
                                class="text-lg font-semibold text-gray-800 dark:text-white/90"
                            >
                                Total : {{ formatPrice(props.sale.total) }}
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <a
                                :href="
                                    route('sale.export-to-pdf', props.sale.id)
                                "
                                target="_blank"
                            >
                                <Button>
                                    <PrinterIcon class="w-4 h-4" />
                                    Print
                                </Button>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
