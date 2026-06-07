<script setup>
import { Head, Link } from '@inertiajs/vue3';

const stats = [
    { value: '99,9%', label: 'Uptime' },
    { value: '50%', label: 'Checkout Lebih Cepat' },
    { value: '24/7', label: 'Dukungan Ahli' },
];

const comparisonRows = [
    {
        feature: 'Manajemen Inventaris',
        ours: 'Prediktif, Real-time',
        theirs: 'Manual, Audit Mingguan',
        icon: 'check_circle',
    },
    {
        feature: 'Kecepatan Checkout',
        ours: '< 10 Detik',
        theirs: '45 – 90 Detik',
        icon: 'bolt',
    },
    {
        feature: 'Wawasan Data',
        ours: 'Prediksi Berbasis AI',
        theirs: 'Struk Penjualan Dasar',
        icon: 'analytics',
    },
    {
        feature: 'Konektivitas Cloud',
        ours: 'Akses Jarak Jauh Penuh',
        theirs: 'Hardware Terikat Toko',
        icon: 'cloud_done',
    },
];
</script>

<template>
    <Head title="Kenapa Kami — Dirancang untuk Presisi" />

    <main class="pt-32 pb-24">
        <!-- Hero -->
        <section class="px-10 max-w-360 mx-auto mb-24">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="space-y-6">
                    <span
                        class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-secondary-container text-on-secondary-container text-xs font-semibold"
                    >
                        <span class="material-symbols-outlined text-[18px]"
                            >verified</span
                        >
                        Mengapa Pengecer Berpikiran Maju Memilih Kami
                    </span>
                    <h1
                        class="text-5xl font-bold text-on-surface leading-tight"
                        style="letter-spacing: -0.02em"
                    >
                        Dirancang untuk
                        <span
                            class="text-surface-tint dark:text-inverse-primary"
                            >Presisi</span
                        >, Dibangun untuk
                        <span
                            class="text-surface-tint dark:text-inverse-primary"
                            >Pertumbuhan</span
                        >.
                    </h1>
                    <p
                        class="text-lg text-on-surface-variant dark:text-surface-variant/80 max-w-xl"
                    >
                        Mesin kasir tradisional menahan Anda. StockEase
                        memberdayakan Anda dengan ekosistem yang menjembatani
                        kesenjangan antara toko fisik dan kecepatan digital.
                    </p>
                    <div class="flex gap-4 pt-4">
                        <div
                            v-for="(stat, i) in stats"
                            :key="stat.label"
                            class="flex items-center gap-4"
                        >
                            <div
                                v-if="i > 0"
                                class="w-px h-12 bg-outline-variant"
                            ></div>
                            <div class="flex flex-col">
                                <span
                                    class="text-2xl font-bold text-surface-tint dark:text-inverse-primary"
                                    >{{ stat.value }}</span
                                >
                                <span
                                    class="text-xs font-semibold text-on-surface-variant uppercase tracking-wider"
                                    >{{ stat.label }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
                <div class="relative">
                    <div
                        class="absolute -inset-4 bg-primary-container/10 blur-3xl rounded-full"
                    ></div>
                    <img
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuDODogNOu9mWTwVYzuXg_EuzEjGyzLGulqqNcRxiY5QNrLrog8XoJ6TZ5P1L9jH2QDn5wEvAur4YDE5ba8DOMyDUcd1Ev7N4hBByCEqEqsM27iOaBp4pbiYqGvZ-rMLW3hrbWAG5AXOnPRmv9mjbfdBT2t2TpLoky0lZJ0H6yrkmtq5QZUrJgu5irYk2owBm9y0Wa1mxZI_TBOyIRxcIwI2dA08W5dOuLBY9mqTv9RQVKfDf6_9EZvBgIRoybGO0_5DKF4BWGnWxL4"
                        alt="Antarmuka Ritel Modern"
                        class="relative rounded-2xl shadow-2xl border border-outline-variant/20 object-cover aspect-4/3"
                    />
                </div>
            </div>
        </section>

        <!-- Bento: The StockEase Edge -->
        <section class="px-10 max-w-360 mx-auto mb-32">
            <div class="text-center mb-16">
                <h2
                    class="text-3xl font-semibold mb-4 text-on-surface"
                    style="letter-spacing: -0.01em"
                >
                    Keunggulan StockEase
                </h2>
                <p
                    class="text-base text-on-surface-variant dark:text-surface-variant/80 max-w-2xl mx-auto"
                >
                    Empat pilar keunggulan kami memastikan bisnis Anda tidak
                    pernah ketinggalan.
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <!-- Built for Scale -->
                <div
                    class="md:col-span-8 group relative bg-surface-container-low dark:bg-surface-container p-8 rounded-3xl border border-outline-variant/30 dark:border-outline/20 overflow-hidden hover:shadow-lg transition-all duration-300"
                >
                    <div
                        class="relative z-10 h-full flex flex-col justify-between"
                    >
                        <div>
                            <div
                                class="w-12 h-12 bg-surface-tint/10 rounded-xl flex items-center justify-center text-surface-tint mb-6"
                            >
                                <span
                                    class="material-symbols-outlined"
                                    style="font-variation-settings: 'FILL' 1"
                                    >trending_up</span
                                >
                            </div>
                            <h3 class="text-2xl font-semibold mb-4">
                                Dibangun untuk Skala Besar
                            </h3>
                            <p
                                class="text-base text-on-surface-variant dark:text-surface-variant/80 max-w-md"
                            >
                                Baik Anda membuka lokasi kedua atau keseratus,
                                infrastruktur kami berkembang bersama Anda
                                dengan mudah.
                            </p>
                        </div>
                        <div class="mt-8 flex gap-3">
                            <span
                                class="px-3 py-1 bg-white/50 rounded-lg text-xs font-semibold border border-outline-variant/20"
                                >Multi-lokasi</span
                            >
                            <span
                                class="px-3 py-1 bg-white/50 rounded-lg text-xs font-semibold border border-outline-variant/20"
                                >Elastic Cloud</span
                            >
                        </div>
                    </div>
                    <div
                        class="absolute right-0 bottom-0 w-1/2 opacity-20 group-hover:scale-110 transition-transform duration-500"
                    >
                        <img
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuDb-YsjaiAA3AahQpeR0h5KZNP3xC4r7B4D0LoiqEi41tN76Ihb9C9FP6OShfOoPlGicuaOTOeQhp_IFLxAbNU5h-hNO3K9wEtsVCswdvBGtIXNcs9AWDeFOG2_1qAYf1QzEvq1-L2W2p-EaDltpODNtToICKHeuk_w96dXOVXRK87V4UmYBPvXZ57KhEp4Sks5y4utHwqnhO-hqPMFKnGxOstHYefy6Ti33W75ULrXELelgvubfCJ6uL0Dn8Hr9nrIU8aqgrCCmC8"
                            alt=""
                            class="rounded-tl-3xl"
                        />
                    </div>
                </div>
                <!-- Security -->
                <div
                    class="md:col-span-4 bg-inverse-surface p-8 rounded-3xl text-white flex flex-col justify-between hover:shadow-xl transition-all"
                >
                    <div>
                        <div
                            class="w-12 h-12 bg-surface-tint rounded-xl flex items-center justify-center text-white mb-6"
                        >
                            <span
                                class="material-symbols-outlined"
                                style="font-variation-settings: 'FILL' 1"
                                >encrypted</span
                            >
                        </div>
                        <h3
                            class="text-2xl font-semibold mb-4 text-primary-fixed"
                        >
                            Keamanan Level Bank
                        </h3>
                        <p class="text-base text-on-surface-variant/80">
                            Enkripsi end-to-end, kepatuhan SOC 2, dan
                            perlindungan PCI-DSS Level 1 untuk setiap transaksi.
                        </p>
                    </div>
                    <ul class="mt-8 space-y-2">
                        <li
                            v-for="item in [
                                'Autentikasi Biometrik',
                                'Backup Harian',
                            ]"
                            :key="item"
                            class="flex items-center gap-2 text-xs font-semibold"
                        >
                            <span
                                class="material-symbols-outlined text-surface-tint text-[16px]"
                                >check_circle</span
                            >
                            {{ item }}
                        </li>
                    </ul>
                </div>
                <!-- Real-time Sync -->
                <div
                    class="md:col-span-4 bg-secondary-container/30 dark:bg-secondary-container/10 p-8 rounded-3xl border border-secondary/10 dark:border-secondary/20 flex flex-col justify-between hover:shadow-lg transition-all"
                >
                    <div>
                        <div
                            class="w-12 h-12 bg-secondary/10 rounded-xl flex items-center justify-center text-secondary mb-6"
                        >
                            <span class="material-symbols-outlined">sync</span>
                        </div>
                        <h3 class="text-2xl font-semibold mb-4">
                            Sinkronisasi Real-time
                        </h3>
                        <p class="text-base text-on-surface-variant">
                            Perbarui harga di kantor pusat, langsung terlihat di
                            setiap terminal. Tanpa jeda inventaris.
                        </p>
                    </div>
                    <div
                        class="mt-8 h-2 bg-outline-variant/20 rounded-full overflow-hidden"
                    >
                        <div
                            class="h-full bg-secondary w-3/4 rounded-full"
                        ></div>
                    </div>
                </div>
                <!-- 24/7 Support -->
                <div
                    class="md:col-span-8 group relative bg-surface-container-highest dark:bg-surface-container p-8 rounded-3xl border border-outline-variant/30 dark:border-outline/20 flex flex-col md:flex-row gap-8 items-center hover:shadow-lg transition-all"
                >
                    <div class="md:w-1/2">
                        <div
                            class="w-12 h-12 bg-primary-container/20 rounded-xl flex items-center justify-center text-surface-tint mb-6"
                        >
                            <span class="material-symbols-outlined"
                                >support_agent</span
                            >
                        </div>
                        <h3 class="text-2xl font-semibold mb-4">
                            Dukungan Ahli 24/7
                        </h3>
                        <p class="text-base text-on-surface-variant">
                            Manusia nyata, ahli ritel nyata. Kami membantu Anda
                            mengoptimalkan alur kerja bisnis Anda jam 3 pagi
                            atau jam 3 sore.
                        </p>
                    </div>
                    <div class="md:w-1/2 flex justify-center">
                        <div class="relative w-48 h-48">
                            <div
                                class="absolute inset-0 bg-surface-tint/10 rounded-full animate-pulse"
                            ></div>
                            <img
                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuBNnjtZuooVq5vOw0Ohkg1uYUY4VHZC5lDmkgmf9bRYu3DJSOAPDPk-EfoXA-73PHDJkf_ZGe3WckTNx6ebHyyBc1-GL9BxxPbjihXtt65I3tivi86PvMkgnYazq2oIJK30w8JXsN0w21XoGwU57aWGVcGHJnW9HyD6i64YazrSFSm-xOwAHRy8Ud8ONHVMV6wkbcxK4e8IzTetoIcoi8bgPljv9zA3KUuDEDsrlnjJA50DUes1csDPOtyu_ecMlmI1vvHGrvCB1Yk"
                                alt="Dukungan Ahli"
                                class="w-full h-full object-cover rounded-full border-4 border-white dark:border-surface-container shadow-lg relative z-10"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Comparison table -->
        <section
            class="bg-surface-container-low dark:bg-inverse-surface/50 py-24 px-10"
        >
            <div class="max-w-360 mx-auto">
                <div class="text-center mb-16">
                    <h2
                        class="text-3xl font-semibold mb-4 text-on-surface"
                        style="letter-spacing: -0.01em"
                    >
                        Peningkatan yang Layak untuk Bisnis Anda
                    </h2>
                    <p class="text-base text-on-surface-variant">
                        Bagaimana StockEase dibandingkan dengan mesin kasir
                        tradisional.
                    </p>
                </div>
                <div
                    class="overflow-hidden rounded-3xl border border-outline-variant/30 shadow-sm glass-card"
                >
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="bg-surface-container dark:bg-surface-container-high"
                            >
                                <th
                                    class="p-6 text-2xl font-semibold border-b border-outline-variant/30"
                                >
                                    Fitur
                                </th>
                                <th
                                    class="p-6 text-2xl font-semibold border-b border-outline-variant/30 bg-surface-tint/5 text-surface-tint"
                                >
                                    StockEase ERP
                                </th>
                                <th
                                    class="p-6 text-2xl font-semibold border-b border-outline-variant/30 text-on-surface-variant opacity-60"
                                >
                                    Mesin Kasir Tradisional
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in comparisonRows"
                                :key="row.feature"
                                class="hover:bg-white/40 dark:hover:bg-white/5 transition-colors"
                            >
                                <td
                                    class="p-6 border-b border-outline-variant/30 font-bold"
                                >
                                    {{ row.feature }}
                                </td>
                                <td
                                    class="p-6 border-b border-outline-variant/30 bg-surface-tint/5"
                                >
                                    <div
                                        class="flex items-center gap-2 text-surface-tint font-bold"
                                    >
                                        <span
                                            class="material-symbols-outlined text-[20px]"
                                            >{{ row.icon }}</span
                                        >
                                        {{ row.ours }}
                                    </div>
                                </td>
                                <td
                                    class="p-6 border-b border-outline-variant/30 text-on-surface-variant"
                                >
                                    {{ row.theirs }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="px-10 py-24 max-w-360 mx-auto text-center">
            <div
                class="bg-surface-tint dark:bg-inverse-primary rounded-[40px] p-16 text-white dark:text-on-primary-fixed relative overflow-hidden"
            >
                <div
                    class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -mr-32 -mt-32 blur-3xl"
                ></div>
                <div
                    class="absolute bottom-0 left-0 w-64 h-64 bg-black/10 rounded-full -ml-32 -mb-32 blur-3xl"
                ></div>
                <h2
                    class="text-5xl font-bold mb-6 relative z-10"
                    style="letter-spacing: -0.02em"
                >
                    Siap untuk modernisasi?
                </h2>
                <p
                    class="text-lg mb-10 text-white/80 dark:text-on-primary-fixed/70 max-w-2xl mx-auto relative z-10"
                >
                    Bergabunglah dengan 5.000+ bisnis yang telah meningkatkan
                    efisiensi mereka rata-rata 35% dalam bulan pertama.
                </p>
                <div
                    class="flex flex-col sm:flex-row gap-4 justify-center relative z-10"
                >
                    <Link
                        :href="route('login')"
                        class="bg-white dark:bg-on-primary-fixed text-surface-tint dark:text-inverse-primary px-8 py-4 rounded-full font-bold hover:opacity-90 transition-all shadow-xl"
                        >Mulai Uji Coba Gratis</Link
                    >
                    <Link
                        href="#"
                        class="bg-primary-container text-on-primary-container px-8 py-4 rounded-full font-bold border border-white/20 hover:bg-white/10 hover:text-white transition-all"
                        >Jadwalkan Demo</Link
                    >
                </div>
            </div>
        </section>
    </main>
</template>
