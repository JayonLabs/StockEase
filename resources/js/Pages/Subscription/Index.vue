<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Separator } from '@/Components/ui/separator';
import { ref, computed } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { formatPrice } from '@/lib/utils';
import { Check, Loader2 } from 'lucide-vue-next';

const props = defineProps({
    currentSubscription: { type: Object, default: null },
    plans: { type: Array, required: true },
});

const isUpgrading = ref(null);
const selectedBilling = ref('monthly');
const snapScriptLoaded = ref(false);

const currentPlan = computed(() => props.currentSubscription?.plan);
const subscriptionStatus = computed(() => props.currentSubscription?.status);
const isTrialing = computed(() => subscriptionStatus.value === 'trialing');

const planFeatures = (plan) => {
    const features = [];
    if (plan.max_products === null) features.push('Produk Unlimited');
    else features.push(`${plan.max_products} Produk`);
    if (plan.max_users === null) features.push('User Unlimited');
    else features.push(`${plan.max_users} User`);
    if (plan.max_warehouses === null) features.push('Gudang Unlimited');
    else features.push(`${plan.max_warehouses} Gudang`);
    if (plan.trial_days > 0) features.push(`Trial ${plan.trial_days} Hari`);
    return features;
};

const upgrade = async (planId, billingCycle) => {
    isUpgrading.value = planId;
    try {
        const response = await axios.post(route('subscription.upgrade'), {
            plan_id: planId,
            billing_cycle: billingCycle,
        });
        toast.success(response.data.message);

        if (response.data.snap_token) {
            if (!snapScriptLoaded.value) {
                await loadSnapScript();
            }
            window.snap.pay(response.data.snap_token, {
                onSuccess: () => {
                    toast.success('Pembayaran berhasil!');
                    setTimeout(() => window.location.reload(), 1500);
                },
                onPending: () => toast.info('Menunggu pembayaran...'),
                onError: () => toast.error('Pembayaran gagal'),
            });
        } else {
            setTimeout(() => window.location.reload(), 1000);
        }
    } catch (error) {
        toast.error(error.response?.data?.message ?? 'Gagal upgrade');
    } finally {
        isUpgrading.value = null;
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
    if (!confirm('Yakin ingin membatalkan subscription?')) return;
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
</script>

<template>
    <AuthenticatedLayout>
        <Head><title>Langganan</title></Head>
        <template #breadcrumb>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('dashboard')"
                    class="text-muted-foreground hover:text-foreground"
                    >Dashboard</Link
                >
                <span class="text-muted-foreground">/</span>
                <span class="font-medium">Langganan</span>
            </div>
        </template>

        <div class="space-y-6">
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
                            <p class="text-sm text-muted-foreground">Plan</p>
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
                                    v-else-if="subscriptionStatus === 'active'"
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
                            <p class="text-sm text-muted-foreground">Siklus</p>
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
                                {{ formatDate(currentSubscription.ends_at) }}
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
                    <Button
                        variant="destructive"
                        @click="cancelSubscription(currentSubscription.id)"
                    >
                        Batalkan Langganan
                    </Button>
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
                                <span class="text-muted-foreground text-sm">{{
                                    getBillingLabel(plan)
                                }}</span>
                            </div>
                            <CardDescription v-if="plan.description">{{
                                plan.description
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul class="space-y-2">
                                <li
                                    v-for="feature in planFeatures(plan)"
                                    :key="feature"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <Check class="w-4 h-4 text-green-500" />
                                    {{ feature }}
                                </li>
                            </ul>
                        </CardContent>
                        <CardFooter>
                            <Button
                                class="w-full"
                                :disabled="
                                    isUpgrading === plan.id ||
                                    currentPlan?.id === plan.id
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
                                {{
                                    currentPlan?.id === plan.id
                                        ? 'Plan Saat Ini'
                                        : plan.slug === 'pemula'
                                          ? 'Mulai Gratis'
                                          : plan.slug === 'enterprise'
                                            ? 'Hubungi Sales'
                                            : `Mulai Trial ${plan.trial_days} Hari`
                                }}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
