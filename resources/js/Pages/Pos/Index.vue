<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Separator } from '@/Components/ui/separator';
import ProductFilter from './partials/ProductFilter.vue';
import axios from 'axios';
import { ref } from 'vue';
import Cart from './partials/Cart.vue';
import ProductCard from './partials/ProductCard.vue';
import ProductPagination from './partials/ProductPagination.vue';
import { computed } from 'vue';
import BarcodeScanner from '@/Components/BarcodeScanner.vue';
import { Button } from '@/Components/ui/button';
import { ScanBarcode } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/Components/ui/breadcrumb';

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
});

const cart = ref(props.cart);
const products = computed(() => props.products);
const showScanner = ref(false);

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

const handleScanResult = async (barcode) => {
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
            <div class="rounded-xl bg-muted/50 h-full p-4">
                <div class="flex justify-between items-center">
                    <h1 class="font-semibold">POS</h1>
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
                                        @cart-updated="fetchCart()"
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
                            @checkout-success="reloadPage"
                        />
                    </div>
                </div>
            </div>
        </div>

        <BarcodeScanner v-model:show="showScanner" @result="handleScanResult" />
    </AuthenticatedLayout>
</template>
