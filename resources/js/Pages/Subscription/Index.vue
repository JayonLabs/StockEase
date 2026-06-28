<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import { ref, computed } from 'vue';
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
import axios from 'axios';
import { toast } from 'vue-sonner';
import { formatPrice } from '@/lib/utils';
import { Check, X, Loader2 } from 'lucide-vue-next';

import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
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

const props = defineProps({
    currentSubscription: { type: Object, default: null },
    pendingSubscription: { type: Object, default: null },
    plans: { type: Array, required: true },
    hadTrial: { type: Boolean, default: false },
});

const isUpgrading = ref(null);
const isRetrying = ref(false);
const selectedBilling = ref('monthly');
const snapScriptLoaded = ref(false);
const cancelDialogOpen = ref(false);

const currentPlan = computed(() => props.currentSubscription?.plan);
const subscriptionStatus = computed(() => props.currentSubscription?.status);
const isTrialing = computed(() => subscriptionStatus.value === 'trialing');
const hasPendingPayment = computed(() => !!props.pendingSubscription);

const resourceSummary = (plan) => {
    const items = [];
    items.push(
        plan.max_products === null
            ? 'Produk Unlimited'
            : `${plan.max_products} Produk`,
    );
    items.push(
        plan.max_users === null ? 'User Unlimited' : `${plan.max_users} User`,
    );
    items.push(
        plan.max_warehouses === null
            ? 'Gudang Unlimited'
            : `${plan.max_warehouses} Gudang`,
    );
    if (plan.trial_days > 0 && !props.hadTrial)
        items.push(`Trial ${plan.trial_days} Hari`);
    return items;
};

const sortedFeatures = (plan) =>
    [...(plan.features ?? [])]
        .filter((f) => f.card_order !== undefined)
        .sort((a, b) => a.card_order - b.card_order);

const openSnapPopup = (snapToken) => {
    window.snap.pay(snapToken, {
        onSuccess: () => {
            toast.success('Pembayaran berhasil!');
            setTimeout(() => window.location.reload(), 1500);
        },
        onPending: () => toast.info('Menunggu pembayaran...'),
        onError: () => toast.error('Pembayaran gagal'),
        onClose: () => toast.info('Popup pembayaran ditutup. Lanjutkan nanti di halaman ini.'),
    });
};

const upgrade = async (planId, billingCycle) => {
    isUpgrading.value = planId;
    try {
        const response = await axios.post(route('subscription.upgrade'), {
            plan_id: planId,
            billing_cycle: billingCycle,
        });

        if (response.data.message) {
            toast.success(response.data.message);
        }

        if (response.data.snap_token) {
            if (!snapScriptLoaded.value) {
                await loadSnapScript();
            }
            openSnapPopup(response.data.snap_token);
        } else {
            setTimeout(() => window.location.reload(), 1000);
        }
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Gagal upgrade');
    } finally {
        isUpgrading.value = null;
    }
};

const retryPayment = async () => {
    isRetrying.value = true;
    try {
        const response = await axios.post(route('subscription.retry-payment'));

        if (response.data.snap_token) {
            if (!snapScriptLoaded.value) {
                await loadSnapScript();
            }
            openSnapPopup(response.data.snap_token);
        } else {
            toast.error('Gagal memuat pembayaran. Coba refresh halaman.');
        }
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Gagal melanjutkan pembayaran');
    } finally {
        isRetrying.value = false;
    }
};

const loadSnapScript = () => {
    return new Promise((resolve) => {
        const script = document.createElement('script');
        script.src = 'https://app.sandbox.midtrans.com/snap/snap.js';
        script.setAttribute(
            'data-client-key',
            import.meta.env.VITE_MIDTRANS_CLIENT_KEY || '',
        );
        script.onload = () => {
            snapScriptLoaded.value = true;
            resolve();
        };
        document.head.appendChild(script);
    });
};

const cancelSubscription = async (subscriptionId) => {
    cancelDialogOpen.value = false;
    try {
        await axios.post(
            route('subscription.cancel', { subscription: subscriptionId }),
        );
        toast.success('Subscription dibatalkan');
        setTimeout(() => window.location.reload(), 1000);
    } catch {
        toast.error('Gagal membatalkan subscription');
    }
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const getPlanPrice = (plan) => {
    return selectedBilling.value === 'annual'
        ? plan.price_annual
        : plan.price_monthly;
};

const getBillingLabel = (plan) => {
    if (selectedBilling.value === 'annual') {
        return `/tahun  •  ${formatPrice(plan.annual_per_month)}/bulan`;
    }
    return '/bulan';
};

const planButtonLabel = (plan) => {
    if (hasPendingPayment.value) return 'Menunggu Pembayaran';
    if (currentPlan.value?.id === plan.id) return 'Plan Saat Ini';
    if (plan.slug === 'enterprise') return 'Hubungi Sales';
    if (plan.is_free) return 'Mulai Gratis';
    if (plan.slug === 'pemula' && !props.hadTrial) return 'Mulai Gratis';
    if (plan.trial_days > 0 && !props.hadTrial)
        return `Mulai Trial ${plan.trial_days} Hari`;
    return 'Langganan Sekarang';
};
</script>

<template>
    <AuthenticatedLayout>
        <Head><title>Langganan</title></Head>
        <template #breadcrumb>
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <Link
                            :href="route('dashboard')"
                            class="text-muted-foreground hover:text-foreground"
                            >Dashboard</Link
                        >
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbPage>Langganan </BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>
        </template>

        <div class="flex flex-1 flex-col gap-4 p-4">
            <div class="space-y-6">
                <!-- Pending Payment -->
                <Card
                    v-if="hasPendingPayment && !currentSubscription"
                    class="border-yellow-400 bg-yellow-50 dark:bg-yellow-950/20"
                >
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-yellow-800 dark:text-yellow-200">
                                    Menunggu Pembayaran
                                </CardTitle>
                                <CardDescription
                                    >Pembayaran untuk
                                    {{ pendingSubscription.plan?.name }} sedang
                                    diproses</CardDescription
                                >
                            </div>
                            <Badge
                                variant="outline"
                                class="text-yellow-600 border-yellow-600"
                                >Pending</Badge
                            >
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Plan
                                </p>
                                <p class="text-lg font-semibold">
                                    {{ pendingSubscription.plan?.name }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Tagihan
                                </p>
                                <p class="text-lg font-semibold">
                                    {{ formatPrice(pendingSubscription.invoice?.amount ?? 0) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Siklus
                                </p>
                                <p class="text-lg font-semibold">
                                    {{
                                        pendingSubscription.billing_cycle ===
                                        'annual'
                                            ? 'Tahunan'
                                            : 'Bulanan'
                                    }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter class="flex gap-2">
                        <Button
                            :disabled="isRetrying"
                            @click="retryPayment"
                        >
                            <Loader2
                                v-if="isRetrying"
                                class="w-4 h-4 animate-spin mr-2"
                            />
                            Bayar Sekarang
                        </Button>
                        <Button
                            variant="outline"
                            :disabled="isRetrying"
                            @click="
                                cancelSubscription(
                                    pendingSubscription.id,
                                )
                            "
                        >
                            Batalkan
                        </Button>
                    </CardFooter>
                </Card>

                <!-- Current Subscription -->
                <Card v-if="currentSubscription">
                    <CardHeader>
                        <CardTitle>Langganan Saat Ini</CardTitle>
                        <CardDescription
                            >Status dan detail langganan Anda</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Plan
                                </p>
                                <p
                                    class="text-lg font-semibold flex items-center gap-2"
                                >
                                    {{ currentPlan?.name }}
                                    <Badge
                                        v-if="isTrialing"
                                        variant="outline"
                                        class="text-yellow-600 border-yellow-600"
                                        >Trial</Badge
                                    >
                                    <Badge
                                        v-else-if="
                                            subscriptionStatus === 'active'
                                        "
                                        variant="outline"
                                        class="text-green-600 border-green-600"
                                        >Aktif</Badge
                                    >
                                    <Badge
                                        v-else-if="
                                            subscriptionStatus === 'canceled'
                                        "
                                        variant="outline"
                                        class="text-red-600 border-red-600"
                                        >Dibatalkan</Badge
                                    >
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Siklus
                                </p>
                                <p class="text-lg font-semibold">
                                    {{
                                        currentSubscription.billing_cycle ===
                                        'annual'
                                            ? 'Tahunan'
                                            : 'Bulanan'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Berakhir
                                </p>
                                <p class="text-lg font-semibold">
                                    {{
                                        formatDate(currentSubscription.ends_at)
                                    }}
                                </p>
                            </div>
                            <div v-if="currentSubscription.trial_ends_at">
                                <p class="text-sm text-muted-foreground">
                                    Trial Berakhir
                                </p>
                                <p class="text-lg font-semibold">
                                    {{
                                        formatDate(
                                            currentSubscription.trial_ends_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                    <CardFooter
                        v-if="
                            subscriptionStatus === 'active' ||
                            subscriptionStatus === 'trialing'
                        "
                    >
                        <AlertDialog
                            v-model:open="cancelDialogOpen"
                        >
                            <AlertDialogTrigger as-child>
                                <Button variant="destructive">
                                    Batalkan Langganan
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Batalkan Langganan
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        Apakah Anda yakin ingin membatalkan
                                        langganan? Tindakan ini tidak dapat
                                        dibatalkan. Semua akses premium akan
                                        dihentikan.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel>
                                        Batal
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        class="bg-destructive text-white hover:bg-destructive/90 dark:bg-destructive/60"
                                        @click="
                                            cancelSubscription(
                                                currentSubscription.id,
                                            )
                                        "
                                    >
                                        Ya, Batalkan
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </CardFooter>
                </Card>

                <!-- Available Plans -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-2xl font-bold">Pilih Plan</h2>
                            <p class="text-muted-foreground">
                                {{
                                    currentSubscription
                                        ? 'Upgrade plan Anda'
                                        : 'Pilih plan untuk memulai'
                                }}
                            </p>
                        </div>
                        <div
                            class="flex items-center gap-2 bg-muted rounded-lg p-1"
                        >
                            <Button
                                size="sm"
                                :variant="
                                    selectedBilling === 'monthly'
                                        ? 'default'
                                        : 'ghost'
                                "
                                @click="selectedBilling = 'monthly'"
                                >Bulanan</Button
                            >
                            <Button
                                size="sm"
                                :variant="
                                    selectedBilling === 'annual'
                                        ? 'default'
                                        : 'ghost'
                                "
                                @click="selectedBilling = 'annual'"
                                >Tahunan
                                <span
                                    class="ml-1 text-xs text-green-500 font-medium"
                                    >Hemat 17%</span
                                ></Button
                            >
                        </div>
                    </div>

                    <div class="grid md:grid-cols-3 gap-6">
                        <Card
                            v-for="plan in plans"
                            :key="plan.id"
                            :class="[
                                currentPlan?.id === plan.id
                                    ? 'ring-2 ring-primary'
                                    : '',
                            ]"
                        >
                            <CardHeader>
                                <CardTitle>{{ plan.name }}</CardTitle>
                                <div class="mt-2">
                                    <span class="text-3xl font-bold">{{
                                        formatPrice(getPlanPrice(plan))
                                    }}</span>
                                    <span
                                        class="text-muted-foreground text-sm"
                                        >{{ getBillingLabel(plan) }}</span
                                    >
                                </div>
                                <CardDescription v-if="plan.description">{{
                                    plan.description
                                }}</CardDescription>
                            </CardHeader>
                            <CardContent class="flex-1">
                                <ul class="space-y-2">
                                    <!-- Ringkasan batas resource -->
                                    <li
                                        v-for="item in resourceSummary(plan)"
                                        :key="item"
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <Check
                                            class="w-4 h-4 shrink-0 text-green-500"
                                        />
                                        {{ item }}
                                    </li>

                                    <Separator class="my-2" />

                                    <!-- Daftar fitur dari plan.features dengan ikon ✓/✗ -->
                                    <li
                                        v-for="f in sortedFeatures(plan)"
                                        :key="f.key"
                                        class="flex items-center gap-2 text-sm"
                                        :class="
                                            f.included
                                                ? ''
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        <Check
                                            v-if="f.included"
                                            class="w-4 h-4 shrink-0 text-green-500"
                                        />
                                        <X
                                            v-else
                                            class="w-4 h-4 shrink-0 text-red-400"
                                        />
                                        {{ f.label }}
                                    </li>
                                </ul>
                            </CardContent>
                            <CardFooter class="mt-auto">
                                    <Button
                                        class="w-full"
                                        :disabled="
                                            isUpgrading === plan.id ||
                                            currentPlan?.id === plan.id ||
                                            hasPendingPayment
                                        "
                                        :variant="
                                            currentPlan?.id === plan.id
                                                ? 'outline'
                                                : 'default'
                                        "
                                        @click="upgrade(plan.id, selectedBilling)"
                                    >
                                        <Loader2
                                            v-if="isUpgrading === plan.id"
                                            class="w-4 h-4 animate-spin mr-2"
                                        />
                                        {{ planButtonLabel(plan) }}
                                    </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
