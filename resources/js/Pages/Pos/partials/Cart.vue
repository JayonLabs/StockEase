<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Loader2, Trash2 } from 'lucide-vue-next';
import { formatPrice, formatNumber } from '@/lib/utils';
import CartItemList from './CartItemList.vue';
import PaymentSection from './PaymentSection.vue';
import InvoiceForm from './InvoiceForm.vue';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';

const props = defineProps({
    cart: {
        type: Object,
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const qtyRefs = ref({});
const totalCart = ref(props.cart?.total ?? 0);
const cartItems = ref(props.cart?.sale_items ?? []);
const cartData = ref(props.cart);
const loadingItemId = ref(null);

watch(
    () => props.cart,
    (newCart) => {
        cartData.value = newCart;
        if (newCart?.sale_items) {
            cartItems.value = newCart.sale_items;
            totalCart.value = newCart.total;

            qtyRefs.value = {};
            newCart.sale_items.forEach((item) => {
                qtyRefs.value[item.product_id] = item.qty;
            });
        }
    },
    { immediate: true },
);

if (props.cart?.sale_items?.length) {
    props.cart.sale_items.forEach((item) => {
        qtyRefs.value[item.product_id] = item.qty;
    });
}

const removeItemFromCart = (productId) => {
    loadingItemId.value = productId;
    axios
        .delete(route('pos.remove-from-cart', { product_id: productId }))
        .then((response) => {
            toast.success(response.data.message);
            totalCart.value = response.data.total;
            cartItems.value = response.data.cart.sale_items;
        })
        .catch(() => {})
        .finally(() => {
            loadingItemId.value = null;
        });
};

const changingQtyProductIds = ref(new Set());

const changeQty = (id, qty) => {
    if (changingQtyProductIds.value.has(id)) return;

    changingQtyProductIds.value.add(id);

    axios
        .patch(route('pos.change-qty', { product_id: id, qty: qty }))
        .then((response) => {
            if (response.data.cart) {
                cartItems.value = response.data.cart.sale_items;
            }
            totalCart.value = response.data.total;
        })
        .catch(() => {})
        .finally(() => {
            changingQtyProductIds.value.delete(id);
        });
};

const isClearCartLoading = ref(false);
const clearCart = () => {
    isClearCartLoading.value = true;

    if (cartItems.value.length > 0) {
        axios
            .delete(route('pos.empty-cart'))
            .then((response) => {
                toast.success(response.data.message);
                totalCart.value = response.data.total;
                cartItems.value = response.data.cart.sale_items;
            })
            .catch((error) => {
                toast.error(error.data.message);
            })
            .finally(() => {
                isClearCartLoading.value = false;
            });
    } else {
        toast.error('Keranjang belanja masih kosong');
        isClearCartLoading.value = false;
    }
};

const cashPayment = ref(0);
const displayCashPayment = ref('');
const paymentMethod = ref('cash');

const change = computed(() => {
    if (paymentMethod.value === 'qris') return 0;
    return cashPayment.value - totalCart.value;
});

watch(displayCashPayment, (newValue) => {
    if (newValue === null || newValue === undefined) return;

    const numericString = String(newValue).replace(/\D/g, '');
    const numericValue = numericString ? parseInt(numericString, 10) : 0;

    cashPayment.value = numericValue;

    const formatted = numericValue > 0 ? formatNumber(numericValue) : '';

    if (String(newValue) !== formatted) {
        displayCashPayment.value = formatted;
    }
});

watch(paymentMethod, (newValue) => {
    if (newValue === 'qris') {
        cashPayment.value = 0;
        displayCashPayment.value = '';
    }
});

const customerName = ref(null);

const isCheckoutLoading = ref(false);
const showInvoiceSection = ref(false);
const completedSaleId = ref(null);
const invoiceEmail = ref('');
const isSendingInvoice = ref(false);

const emit = defineEmits(['checkout-success']);

watch(
    () => cartItems.value.length,
    (newLength) => {
        if (newLength > 0 && showInvoiceSection.value) {
            showInvoiceSection.value = false;
            invoiceEmail.value = '';
        }
    },
);

const handleCheckoutSuccess = (response) => {
    toast.success(response.data.message);
    totalCart.value = response.data.total;
    cartItems.value = response.data.cart.sale_items;
    cashPayment.value = 0;
    displayCashPayment.value = '';
    customerName.value = null;

    if (response.data.completed_sale_id) {
        completedSaleId.value = response.data.completed_sale_id;
        showInvoiceSection.value = true;
    }

    emit('checkout-success');
};

const checkout = () => {
    isCheckoutLoading.value = true;

    if (paymentMethod.value === 'cash') {
        if (cashPayment.value < totalCart.value) {
            toast.error('Uang pembayaran kurang');
            isCheckoutLoading.value = false;
            return;
        }

        axios
            .put(
                route('pos.checkout'),
                {
                    payment_method: paymentMethod.value,
                    customer_name: customerName.value,
                    paid: cashPayment.value,
                },
                { headers: { Accept: 'application/json' } },
            )
            .then(handleCheckoutSuccess)
            .catch(() => toast.error('Gagal checkout'))
            .finally(() => {
                isCheckoutLoading.value = false;
            });
    } else if (paymentMethod.value === 'qris') {
        axios
            .post(
                route('pos.qris-token', {
                    amount: totalCart.value,
                    customer_name: customerName.value,
                }),
            )
            .then((response) => {
                const snapToken = response.data.snap_token;

                window.snap.pay(snapToken, {
                    onSuccess: function (result) {
                        axios
                            .put(
                                route('pos.checkout'),
                                {
                                    payment_method: paymentMethod.value,
                                    customer_name: customerName.value,
                                    paid: cashPayment.value,
                                    order_id: result.order_id,
                                },
                                { headers: { Accept: 'application/json' } },
                            )
                            .then(handleCheckoutSuccess)
                            .catch(() => toast.error('Gagal checkout'))
                            .finally(() => {
                                isCheckoutLoading.value = false;
                            });
                    },
                    onPending: function () {
                        toast.info('Menunggu pembayaran QRIS');
                    },
                    onError: function (result) {
                        toast.error('Pembayaran gagal');
                        console.error(result);
                    },
                });
            })
            .catch(() => {
                toast.error('Gagal mendapatkan token pembayaran QRIS');
                isCheckoutLoading.value = false;
            });
    }
};

const sendInvoice = () => {
    if (!invoiceEmail.value || !completedSaleId.value) {
        toast.error('Email tidak boleh kosong');
        return;
    }

    isSendingInvoice.value = true;

    axios
        .post(route('pos.send-invoice'), {
            sale_id: completedSaleId.value,
            email: invoiceEmail.value,
        })
        .then((response) => {
            toast.success(response.data.message);
            invoiceEmail.value = '';
            showInvoiceSection.value = false;
        })
        .catch((error) => {
            toast.error(
                error.response?.data?.message ?? 'Gagal mengirim invoice',
            );
        })
        .finally(() => {
            isSendingInvoice.value = false;
        });
};
</script>

<template>
    <div class="lg:w-1/3 rounded-lg shadow p-4 border dark:border-white/30">
        <h2 class="text-xl font-bold mb-4">Keranjang Belanja</h2>

        <CartItemList
            :cart-items="cartItems"
            :qty-refs="qtyRefs"
            :loading-item-id="loadingItemId"
            :changing-qty-product-ids="changingQtyProductIds"
            :disabled="disabled"
            @change-qty="changeQty"
            @remove-item="removeItemFromCart"
        />

        <div class="space-y-2 border-t pt-3">
            <div class="flex justify-between text-lg font-bold mt-2">
                <span>TOTAL:</span>
                <span>{{ formatPrice(totalCart) }}</span>
            </div>

            <PaymentSection
                :payment-method="paymentMethod"
                :display-cash-payment="displayCashPayment"
                :total-cart="totalCart"
                :change="change"
                @update:payment-method="paymentMethod = $event"
                @update:display-cash-payment="displayCashPayment = $event"
            />

            <div class="flex mt-2">
                <Input
                    id="customer_name"
                    v-model="customerName"
                    name="customer_name"
                    type="text"
                    class="w-full mt-2 [&::-webkit-inner-spin-button]:appearance-none"
                    placeholder="Nama Pelanggan (Opsional)"
                    autocomplete="off"
                />
            </div>

            <Button
                class="w-full disabled:cursor-not-allowed"
                :disabled="
                    disabled ||
                    !paymentMethod ||
                    (cartItems?.length ?? 0) === 0 ||
                    isCheckoutLoading
                "
                @click="checkout"
            >
                <Loader2
                    v-if="isCheckoutLoading"
                    class="w-4 h-4 animate-spin"
                />
                {{ isCheckoutLoading ? 'Loading...' : 'Proses Pembayaran' }}
            </Button>

            <div class="grid grid-cols-3 gap-2 mt-3">
                <TooltipProvider :delay-duration="0">
                    <Tooltip>
                        <TooltipTrigger>
                            <Button
                                aria-label="Hapus semua"
                                size="icon"
                                class="w-full"
                                :disabled="
                                    (cartItems?.length ?? 0) === 0 ||
                                    isClearCartLoading
                                "
                                @click="clearCart"
                            >
                                <Loader2
                                    v-if="isClearCartLoading"
                                    class="w-4 h-4 animate-spin"
                                />
                                <Trash2 v-else />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            <p>Hapus Semua</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </div>

            <InvoiceForm
                :show-invoice-section="showInvoiceSection"
                :invoice-email="invoiceEmail"
                :is-sending-invoice="isSendingInvoice"
                @send-invoice="sendInvoice"
                @update:invoice-email="invoiceEmail = $event"
            />
        </div>
    </div>
</template>
