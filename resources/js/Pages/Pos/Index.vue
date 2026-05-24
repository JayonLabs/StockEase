<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import ProductFilter from './partials/ProductFilter.vue';
import axios from 'axios';
import { ref, computed, onMounted, nextTick } from 'vue';
import Cart from './partials/Cart.vue';
import ProductCard from './partials/ProductCard.vue';
import ProductPagination from './partials/ProductPagination.vue';
import BarcodeScanner from '@/Components/BarcodeScanner.vue';
import { Button } from '@/Components/ui/button';
import {
    ScanBarcode,
    Warehouse as WarehouseIcon,
    AlertCircle,
    ArrowLeft,
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
    products: {
        type: Object,
        required: true,
    },
    cart: {
        type: Object,
        required: true,
    },
    activePromotions: {
        type: Array,
        default: () => [],
    },
    warehouses: {
        type: Array,
        default: () => [],
    },
    activeWarehouseId: {
        type: Number,
        default: null,
    },
    hasActiveShift: {
        type: Boolean,
        default: false,
    },
});

const cart = ref(props.cart);
const products = computed(() => props.products);
const showScanner = ref(false);
const showWarehouseDialog = ref(!props.activeWarehouseId);
const isSettingWarehouse = ref(false);
const selectedWarehouseId = ref(null);
const isRefreshing = ref(false);

const isReady = computed(() => props.hasActiveShift && props.activeWarehouseId);

const activeWarehouseName = computed(() => {
    if (!props.activeWarehouseId) return null;
    const wh = props.warehouses.find((w) => w.id === props.activeWarehouseId);
    return wh?.name ?? null;
});

const fetchCart = async () => {
    const response = await axios.get(route('pos.get-cart'));
    cart.value = response.data.cart;
};

const reloadPage = () => {
    fetchCart();
    router.reload({
        preserveScroll: true,
        preserveState: true,
        only: ['products'],
    });
};

const refreshShiftStatus = () => {
    isRefreshing.value = true;
    router.reload({
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

onMounted(() => {
    if (props.hasActiveShift) {
        sessionStorage.removeItem('pos_shift_stale');
        return;
    }

    if (!sessionStorage.getItem('pos_shift_stale')) {
        sessionStorage.setItem('pos_shift_stale', '1');
        nextTick(() => {
            refreshShiftStatus();
        });
    }
});

const setWarehouse = async () => {
    if (!selectedWarehouseId.value) {
        toast.error('Silakan pilih gudang terlebih dahulu.');
        return;
    }

    isSettingWarehouse.value = true;
    try {
        await axios.post(route('pos.set-warehouse'), {
            warehouse_id: selectedWarehouseId.value,
        });
        showWarehouseDialog.value = false;
        router.reload();
    } catch (error) {
        toast.error(error.response?.data?.message || 'Gagal memilih gudang.');
    } finally {
        isSettingWarehouse.value = false;
    }
};

const onWarehouseDialogOpenChange = (value) => {
    if (!props.activeWarehouseName) {
        return;
    }
    showWarehouseDialog.value = value;
};

const handleScanResult = async (barcode) => {
    if (!isReady.value) {
        toast.error('Silakan buka shift dan pilih gudang terlebih dahulu.');
        return;
    }

    try {
        const response = await axios.post(route('pos.add-to-cart-barcode'), {
            barcode: barcode,
        });

        toast.success(response.data.message);
        fetchCart();
        showScanner.value = false;
    } catch (error) {
        toast.error(error.response?.data?.message || 'Terjadi kesalahan');
    }
};

const handleProductClick = (productId) => {
    if (!isReady.value) {
        toast.error('Silakan buka shift dan pilih gudang terlebih dahulu.');
        return;
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head>
            <title>POS</title>
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
                        <BreadcrumbPage> POS </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>
        <div class="flex flex-1 flex-col gap-4 p-4">
            <!-- No Shift Warning -->
            <div
                v-if="!hasActiveShift"
                class="rounded-xl bg-muted/50 h-full p-4 flex items-center justify-center"
            >
                <div class="text-center max-w-md">
                    <AlertCircle
                        class="w-16 h-16 text-yellow-500 mx-auto mb-4"
                    />
                    <h2 class="text-xl font-semibold mb-2">
                        Shift Belum Dibuka
                    </h2>
                    <p class="text-muted-foreground mb-6">
                        Anda harus membuka shift terlebih dahulu sebelum dapat
                        menggunakan POS.
                    </p>
                    <div class="flex items-center justify-center gap-3">
                        <Link
                            :href="route('shift.index')"
                            class="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring bg-primary text-primary-foreground shadow hover:bg-primary/90 h-10 px-4 py-2"
                        >
                            Buka Shift
                        </Link>
                        <Button
                            variant="outline"
                            :disabled="isRefreshing"
                            @click="refreshShiftStatus"
                        >
                            {{ isRefreshing ? 'Mengecek...' : 'Cek Ulang' }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- POS Content (Shift Open) -->
            <div v-else class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <h1 class="font-semibold">POS</h1>
                        <Button
                            v-if="activeWarehouseName"
                            variant="ghost"
                            size="sm"
                            class="flex items-center gap-1 text-muted-foreground"
                            @click="showWarehouseDialog = true"
                        >
                            <WarehouseIcon class="w-4 h-4" />
                            {{ activeWarehouseName }}
                        </Button>
                        <span
                            v-else
                            class="text-xs px-2 py-1 rounded bg-yellow-500/10 text-yellow-600 border border-yellow-500/30"
                        >
                            Pilih gudang
                        </span>
                    </div>
                    <Button
                        variant="outline"
                        class="flex items-center gap-2"
                        @click="showScanner = true"
                    >
                        <ScanBarcode class="w-4 h-4" />
                        Scan Barcode
                    </Button>
                </div>

                <Separator class="my-4" />

                <div class="mt-4">
                    <div class="flex flex-col lg:flex-row gap-6">
                        <div
                            class="lg:w-2/3 rounded-lg shadow p-4 border dark:border-white/30"
                        >
                            <ProductFilter
                                :categories="categories"
                                :products="products"
                            />

                            <div
                                class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 overflow-y-auto"
                                style="max-height: 70vh"
                            >
                                <template
                                    v-if="
                                        products &&
                                        products.data &&
                                        products.data.length > 0
                                    "
                                >
                                    <ProductCard
                                        v-for="product in products.data"
                                        :key="product.id"
                                        :product="product"
                                        :active-promotions="activePromotions"
                                        :warehouse-stock="
                                            product.warehouse_stock
                                        "
                                        :disabled="!isReady"
                                        @cart-updated="fetchCart()"
                                        @click="handleProductClick(product.id)"
                                    />
                                </template>
                                <div
                                    v-else
                                    class="col-span-4 text-center text-muted-foreground"
                                >
                                    Produk tidak ditemukan.
                                </div>
                            </div>

                            <div class="w-full flex justify-center pt-4">
                                <ProductPagination :products="products" />
                            </div>
                        </div>

                        <Cart
                            v-if="cart"
                            :cart="cart"
                            :disabled="!isReady"
                            @checkout-success="reloadPage"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Warehouse Selection Dialog -->
        <Dialog
            :open="showWarehouseDialog"
            :modal="!activeWarehouseName"
            @update:open="onWarehouseDialogOpenChange"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Pilih Gudang</DialogTitle>
                    <DialogDescription>
                        Pilih gudang tempat Anda bertransaksi hari ini.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-3 py-4">
                    <div
                        v-for="warehouse in warehouses"
                        :key="warehouse.id"
                        class="flex items-center justify-between rounded-lg border p-3 cursor-pointer transition-colors"
                        :class="
                            selectedWarehouseId === warehouse.id
                                ? 'border-primary bg-primary/5'
                                : 'hover:bg-muted'
                        "
                        @click="selectedWarehouseId = warehouse.id"
                    >
                        <div class="flex items-center gap-3">
                            <WarehouseIcon
                                class="w-5 h-5"
                                :class="
                                    selectedWarehouseId === warehouse.id
                                        ? 'text-primary'
                                        : 'text-muted-foreground'
                                "
                            />
                            <span class="font-medium">{{
                                warehouse.name
                            }}</span>
                        </div>
                        <div
                            v-if="selectedWarehouseId === warehouse.id"
                            class="w-4 h-4 rounded-full bg-primary flex items-center justify-center"
                        >
                            <div
                                class="w-2 h-2 rounded-full bg-primary-foreground"
                            />
                        </div>
                    </div>
                </div>
                <div class="flex justify-between gap-2">
                    <Button variant="ghost" as-child>
                        <Link :href="route('dashboard')">
                            <ArrowLeft class="w-4 h-4 mr-1" />
                            Kembali
                        </Link>
                    </Button>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            :disabled="!activeWarehouseName"
                            @click="showWarehouseDialog = false"
                        >
                            Batal
                        </Button>
                        <Button
                            :disabled="
                                !selectedWarehouseId || isSettingWarehouse
                            "
                            @click="setWarehouse"
                        >
                            {{
                                isSettingWarehouse
                                    ? 'Menyimpan...'
                                    : 'Pilih Gudang'
                            }}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <BarcodeScanner v-model:show="showScanner" @result="handleScanResult" />
    </AuthenticatedLayout>
</template>
