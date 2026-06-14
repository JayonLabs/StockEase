<script setup>
import { ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';

defineProps({
    plans: {
        type: Array,
        required: true,
    },
    comparison: {
        type: Array,
        required: true,
    },
});

const isAnnual = ref(false);

function formatPrice(amount) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
}

function monthlyPrice(plan) {
    return formatPrice(plan.price_monthly);
}

function annualPrice(plan) {
    return formatPrice(plan.annual_per_month);
}

function displayPrice(plan) {
    return isAnnual.value ? annualPrice(plan) : monthlyPrice(plan);
}

function displayPeriod(plan) {
    if (isAnnual.value) return '/bulan, ditagih tahunan';
    return '/bulan';
}

function ctaText(plan) {
    if (plan.is_free) return 'Mulai Gratis';
    if (plan.slug === 'enterprise') return 'Hubungi Sales';
    if (plan.trial_days > 0)
        return 'Mulai Uji Coba ' + plan.trial_days + ' Hari';
    return 'Langganan Sekarang';
}

function ctaHref(plan) {
    if (plan.slug === 'enterprise') return '#';
    const auth = usePage().props.auth;
    return auth?.user ? route('subscription.index') : route('login');
}

const faqs = [
    {
        q: 'Bisakah saya mengganti paket nanti?',
        a: 'Tentu! Anda dapat meningkatkan atau menurunkan paket kapan saja. Saat meningkatkan, biaya akan dihitung secara proporsional untuk siklus penagihan Anda saat ini.',
    },
    {
        q: 'Metode pembayaran apa yang diterima?',
        a: 'Kami menerima transfer bank (BCA, Mandiri, BNI, BRI), QRIS, GoPay, OVO, dan kartu kredit utama untuk pelanggan Enterprise.',
    },
    {
        q: 'Apakah ada uji coba gratis?',
        a: 'Ya, paket Pemula gratis selamanya. Untuk paket Profesional, kami menawarkan uji coba gratis 14 hari dengan akses penuh ke semua fitur premium.',
    },
    {
        q: 'Apa yang terjadi jika saya melampaui batas produk di paket Pemula?',
        a: 'Anda akan menerima notifikasi saat mendekati batas. Jika perlu menambah produk, Anda bisa langsung meningkatkan ke paket Profesional.',
    },
];

const activeFaq = ref(null);

function toggleFaq(i) {
    activeFaq.value = activeFaq.value === i ? null : i;
}
</script>

<template>
    <Head title="Harga — Paket Sederhana & Transparan" />

    <main class="pt-32 pb-20">
        <!-- Hero -->
        <section class="px-10 max-w-360 mx-auto text-center mb-16">
            <h1
                class="text-5xl font-bold text-on-surface mb-6"
                style="letter-spacing: -0.02em"
            >
                <span class="text-surface-tint dark:text-inverse-primary"
                    >Harga</span
                >
                Sederhana & Transparan
            </h1>
            <p
                class="text-lg text-on-surface-variant dark:text-surface-variant/80 max-w-2xl mx-auto mb-12"
            >
                Mulai gratis, tingkatkan seiring pertumbuhan bisnis Anda. Pilih
                paket yang sesuai dengan kebutuhan operasional Anda.
            </p>
            <div class="flex items-center justify-center gap-4 mb-8">
                <Label
                    for="billing"
                    class="text-sm font-medium text-on-surface-variant"
                    >Bulanan</Label
                >
                <Switch id="billing" v-model="isAnnual" />
                <Label
                    for="billing"
                    class="text-sm font-medium text-on-surface-variant"
                >
                    Tahunan
                    <span
                        class="bg-secondary-container text-on-secondary-container dark:bg-secondary-container/30 dark:text-secondary-fixed-dim px-2 py-0.5 rounded-full text-xs font-bold ml-1"
                        >Hemat 17%</span
                    >
                </Label>
            </div>
        </section>

        <!-- Plans -->
        <section class="px-10 max-w-360 mx-auto mb-24">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
                <div
                    v-for="plan in plans"
                    :key="plan.slug"
                    class="relative rounded-3xl p-8 flex flex-col transition-shadow"
                    :class="
                        plan.sort_order === 2
                            ? 'bg-surface-tint text-white shadow-2xl scale-105 z-10 overflow-hidden group'
                            : 'glass-card border border-outline-variant/30 hover:shadow-xl'
                    "
                >
                    <!-- Badge -->
                    <div
                        v-if="plan.sort_order === 2"
                        class="absolute top-6 right-6"
                    >
                        <span
                            class="bg-primary-fixed text-on-primary-fixed px-4 py-1 rounded-full text-xs font-bold shadow-lg"
                            >TERPOPULER</span
                        >
                    </div>

                    <!-- Decorative blob -->
                    <div
                        v-if="plan.sort_order === 2"
                        class="absolute -top-24 -right-24 w-64 h-64 bg-primary-fixed/20 blur-[80px] rounded-full group-hover:scale-125 transition-transform duration-700"
                    />

                    <div
                        class="mb-8"
                        :class="{ 'relative z-10': plan.sort_order === 2 }"
                    >
                        <span
                            class="font-bold text-xs uppercase tracking-widest"
                            :class="
                                plan.sort_order === 2
                                    ? 'text-primary-fixed'
                                    : 'text-surface-tint dark:text-inverse-primary'
                            "
                            >{{ plan.name }}</span
                        >
                        <div class="flex items-baseline mt-4">
                            <span
                                class="text-5xl font-bold transition-all"
                                :class="
                                    plan.sort_order === 2
                                        ? 'text-white'
                                        : 'text-on-surface dark:text-on-surface'
                                "
                                >{{ displayPrice(plan) }}</span
                            >
                            <span
                                class="ml-2"
                                :class="
                                    plan.sort_order === 2
                                        ? 'text-primary-fixed-dim'
                                        : 'text-on-surface-variant dark:text-surface-variant/70'
                                "
                                >{{ displayPeriod(plan) }}</span
                            >
                        </div>
                        <p
                            class="mt-4"
                            :class="
                                plan.sort_order === 2
                                    ? 'text-primary-fixed-dim'
                                    : 'text-on-surface-variant dark:text-surface-variant/70'
                            "
                        >
                            {{ plan.description }}
                        </p>
                    </div>

                    <ul
                        class="space-y-4 mb-10 grow"
                        :class="{ 'relative z-10': plan.sort_order === 2 }"
                    >
                        <li
                            v-for="f in plan.features"
                            :key="f.key"
                            class="flex items-center gap-3"
                            :class="{
                                'text-on-surface-variant/50 dark:text-surface-variant/40':
                                    !f.included,
                            }"
                        >
                            <span
                                class="material-symbols-outlined"
                                :class="
                                    f.included
                                        ? plan.sort_order === 2
                                            ? 'text-primary-fixed'
                                            : 'text-surface-tint'
                                        : ''
                                "
                                :style="
                                    f.included
                                        ? `font-variation-settings:'FILL' 1`
                                        : ''
                                "
                                >{{
                                    f.included ? 'check_circle' : 'cancel'
                                }}</span
                            >
                            <span
                                :class="
                                    plan.sort_order === 2 ? 'text-white' : ''
                                "
                                >{{ f.label }}</span
                            >
                        </li>
                    </ul>

                    <Link
                        :href="ctaHref(plan)"
                        :class="
                            plan.sort_order === 2
                                ? 'w-full py-4 rounded-xl bg-white text-surface-tint font-bold hover:shadow-lg hover:scale-[1.02] active:scale-95 transition-all relative z-10 text-center block'
                                : 'w-full py-4 rounded-xl border border-outline dark:border-outline-variant text-on-surface dark:text-on-surface hover:bg-surface-container-low dark:hover:bg-surface-container-high transition-colors font-semibold text-center block'
                        "
                    >
                        {{ ctaText(plan) }}
                    </Link>
                </div>
            </div>
        </section>

        <!-- Comparison table -->
        <section class="px-10 max-w-360 mx-auto mb-24 overflow-hidden">
            <h2
                class="text-center text-3xl font-semibold text-on-surface dark:text-on-surface mb-12"
                style="letter-spacing: -0.01em"
            >
                Bandingkan fitur lengkap
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="border-b border-outline-variant/30 dark:border-outline/20"
                        >
                            <th
                                class="py-6 px-4 font-bold text-sm text-on-surface dark:text-on-surface"
                            >
                                Fitur
                            </th>
                            <th
                                v-for="plan in plans"
                                :key="plan.slug"
                                class="py-6 px-4 font-bold text-sm text-center"
                                :class="{
                                    'bg-surface-tint/5 text-surface-tint':
                                        plan.sort_order === 2,
                                }"
                            >
                                {{ plan.name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/30">
                        <tr v-for="row in comparison" :key="row.key">
                            <td
                                class="py-6 px-4 text-on-surface dark:text-on-surface font-medium"
                            >
                                {{ row.label }}
                            </td>
                            <td
                                v-for="plan in plans"
                                :key="plan.slug"
                                class="py-6 px-4 text-center"
                                :class="{
                                    'bg-surface-tint/5 dark:bg-surface-tint/10':
                                        plan.sort_order === 2,
                                }"
                            >
                                <span
                                    v-if="row.plans[plan.slug]"
                                    class="material-symbols-outlined text-surface-tint"
                                    >check</span
                                >
                                <span v-else class="text-on-surface-variant/30"
                                    >—</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Billing FAQ -->
        <section class="px-10 max-w-3xl mx-auto mb-24">
            <h2
                class="text-center text-3xl font-semibold text-on-surface dark:text-on-surface mb-12"
                style="letter-spacing: -0.01em"
            >
                FAQ Penagihan
            </h2>
            <div class="space-y-4">
                <div
                    v-for="(faq, i) in faqs"
                    :key="i"
                    class="glass-card border border-outline-variant/30 rounded-2xl overflow-hidden"
                >
                    <button
                        class="w-full p-6 flex justify-between items-center cursor-pointer text-left"
                        @click="toggleFaq(i)"
                    >
                        <span
                            class="font-semibold text-on-surface dark:text-on-surface"
                            >{{ faq.q }}</span
                        >
                        <span
                            class="material-symbols-outlined transition-transform duration-300"
                            :class="{ 'rotate-180': activeFaq === i }"
                            >expand_more</span
                        >
                    </button>
                    <div
                        v-show="activeFaq === i"
                        class="px-6 pb-6 text-base text-on-surface-variant dark:text-surface-variant/80"
                    >
                        {{ faq.a }}
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="px-10 max-w-360 mx-auto">
            <div
                class="bg-inverse-surface text-inverse-on-surface rounded-[40px] p-12 md:p-20 text-center relative overflow-hidden"
            >
                <div class="relative z-10">
                    <h2
                        class="text-3xl font-semibold mb-6"
                        style="letter-spacing: -0.01em"
                    >
                        Siap menyederhanakan operasional bisnis Anda?
                    </h2>
                    <p class="text-lg mb-12 max-w-xl mx-auto opacity-80">
                        Bergabunglah dengan ribuan bisnis yang menggunakan
                        StockEase untuk mengelola inventaris, penjualan, dan
                        pembelian dalam satu platform.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link
                            :href="route('login')"
                            class="bg-surface-tint text-white px-10 py-4 rounded-xl font-bold hover:scale-105 transition-transform"
                        >
                            Mulai Gratis
                        </Link>
                        <Link
                            href="#"
                            class="bg-white/10 backdrop-blur-md text-white border border-white/20 px-10 py-4 rounded-xl font-bold hover:bg-white/20 transition-all"
                        >
                            Hubungi Kami
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
