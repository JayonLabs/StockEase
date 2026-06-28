<script setup>
import { Head, Link } from '@inertiajs/vue3';

const reviews = [
    {
        initials: 'RJ',
        name: 'Robert Janson',
        company: 'Hardware Central',
        quote: '"Sinkronisasi multi-lokasi berjalan mulus. Kami mengelola 14 toko di 3 negara bagian tanpa satu pun ketidakcocokan inventaris."',
    },
    {
        initials: 'EL',
        name: 'Elena Lopez',
        company: 'Cantina Social',
        quote: '"Onboarding sangat cepat. Staf saya mempelajari sistem dalam waktu kurang dari sehari. Sangat direkomendasikan untuk restoran sibuk mana pun."',
    },
    {
        initials: 'TP',
        name: 'Tom Peterson',
        company: 'Gadget World',
        quote: '"Notifikasi real-time untuk stok rendah menyelamatkan penjualan liburan kami. Kami tidak pernah kehabisan barang terlaris kami."',
    },
];
</script>

<template>
    <Head title="Testimoni — Cerita Nyata dari Bisnis Nyata" />

    <main class="pt-32 pb-24">
        <!-- Hero -->
        <section class="max-w-360 mx-auto px-10 mb-20 text-center">
            <span
                class="text-surface-tint dark:text-inverse-primary font-semibold text-xs uppercase tracking-widest mb-4 block"
                >Kesuksesan Terbukti</span
            >
            <h1
                class="text-5xl font-bold text-on-surface dark:text-inverse-on-surface mb-6 max-w-3xl mx-auto"
                style="letter-spacing: -0.02em"
            >
                Cerita Nyata dari Bisnis yang Didukung StockEase
            </h1>
            <p
                class="text-lg text-on-surface-variant dark:text-surface-variant/80 max-w-2xl mx-auto"
            >
                Bergabunglah dengan lebih dari 5.000 pemimpin industri ritel,
                F&amp;B, dan jasa yang telah mentransformasi operasional mereka
                dengan platform ERP presisi kami.
            </p>
        </section>

        <!-- Bento Success Stories -->
        <section class="max-w-360 mx-auto px-10 mb-24">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <!-- Large card -->
                <div
                    class="md:col-span-8 group relative overflow-hidden rounded-xl bg-white dark:bg-white/10 border border-outline-variant/30 dark:border-outline/20 shadow-sm transition-all hover:shadow-lg"
                >
                    <div class="grid md:grid-cols-2 h-full">
                        <div class="p-8 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-4">
                                    <span
                                        class="bg-primary-container text-on-primary-container px-3 py-1 rounded-full text-xs uppercase font-semibold"
                                        >Ritel Fashion</span
                                    >
                                    <div class="flex text-yellow-500">
                                        <span
                                            v-for="i in 5"
                                            :key="i"
                                            class="material-symbols-outlined text-[18px]"
                                            style="
                                                font-variation-settings: 'FILL'
                                                    1;
                                            "
                                            >star</span
                                        >
                                    </div>
                                </div>
                                <h3
                                    class="text-3xl font-semibold mb-4 text-on-surface dark:text-inverse-on-surface"
                                >
                                    Bagaimana Butik X Meningkatkan Penjualan
                                    Tahunan 30%
                                </h3>
                                <p
                                    class="text-base text-on-surface-variant dark:text-surface-variant/80 mb-6 leading-relaxed"
                                >
                                    "Beralih ke StockEase adalah titik balik
                                    untuk manajemen inventaris kami. Kami
                                    menghilangkan kehabisan stok sepenuhnya
                                    selama musim puncak, memungkinkan kami
                                    menangkap setiap peluang penjualan."
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-12 h-12 rounded-full overflow-hidden bg-surface-variant"
                                >
                                    <img
                                        alt="Sarah Jenkins"
                                        class="w-full h-full object-cover"
                                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuDT7d5XzL79eFDAo86S5wOupvEQ-xdaXiDEnmnsP_RWP_l0rEOW2EJBB1egWeWui2-fVgg45FE2ou2l9mWoDhEAdl2-0HLd2Kxhmr53xcDKjCxufdBAVx0gQNNdoVcXIw5i9DB9lAAq_rnQ7aYiwzjtxkbcqjqwhBDh7ynbLJlOVpZxhBcteOqVj61g05Z45wen3Kok32U9MQk7RxlOvvBhyL_RSST83xz_5uWDEwDhhOdERwBsPIyBCUaSf_2sdNfs5g-A9Q-VPhI"
                                    />
                                </div>
                                <div>
                                    <p
                                        class="font-bold text-on-surface dark:text-inverse-on-surface"
                                    >
                                        Sarah Jenkins
                                    </p>
                                    <p class="text-xs text-on-surface-variant">
                                        CEO, Butik X
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="relative min-h-75">
                            <img
                                class="absolute inset-0 w-full h-full object-cover"
                                src="https://lh3.googleusercontent.com/aida-public/AB6AXuCzjE7DRSB4MYHKoN1VrfQBQMcxg7qHSkh5QxvsVyhW4zwADCd1Ip0Url1C89CaFLnzw7aMGslHNrAJmFfF9HGS1gimLbq3fVrKA1M8nbXUfDgq8MjJcwGsqHJUMx6LfMwZq-neFuqDwmhIh0UBBS9uGGQ86gcVAXyzQ4GWJq71lD8zMrCIYdYv3OloMe-pKTt44_ChwNBSzp_d-tOcH5AH1y-nU68fwWeSDsu5TjrD2VooUe7Vn8XFY9n0Ga82aLKc7ZWhy1oFI0Y"
                                alt=""
                            />
                            <div
                                class="absolute inset-0 bg-linear-to-t from-on-surface/40 to-transparent"
                            ></div>
                            <div
                                class="absolute bottom-6 right-6 flex flex-col items-end"
                            >
                                <span class="text-white text-5xl font-bold"
                                    >+30%</span
                                >
                                <span class="text-white/90 text-sm"
                                    >Pertumbuhan Pendapatan</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Video card -->
                <div
                    class="md:col-span-4 bg-inverse-surface rounded-xl p-1 relative overflow-hidden group"
                >
                    <div
                        class="relative h-full w-full rounded-lg overflow-hidden"
                    >
                        <img
                            class="w-full h-full object-cover opacity-60 group-hover:scale-105 transition-transform duration-700"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuBTMChPsPeOrvwDzxlad5F1D17PRUt0QJNlZq0B4TPDGGpWeTlDTWzMlyQUs9TAAqml8eeU9jbj3v3MS0lavmpzYhzDzqiMFTz17DzmFTUJkLOnFhh-kvZGDV2bjndWzylSaQpCIZODyziQc07rPqV9xNZEIoGKK2TCBXswFiwMS2dhrbzga10m2WyPXOkz1x8Jt3v5FB2vqYmFO4aBNfTznRtm3a8RIws3TaDqkgvky4sG7WtcfmZyVMmZQX_0AiF00MmPn4Cbnqc"
                            alt=""
                        />
                        <div
                            class="absolute inset-0 flex flex-col items-center justify-center"
                        >
                            <button
                                class="w-16 h-16 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center hover:bg-surface-tint transition-colors"
                            >
                                <span
                                    class="material-symbols-outlined text-white text-[32px]"
                                    style="font-variation-settings: 'FILL' 1"
                                    >play_arrow</span
                                >
                            </button>
                            <p
                                class="mt-4 text-white font-bold text-2xl px-6 text-center"
                            >
                                Perjalanan Gourmet Haven
                            </p>
                        </div>
                        <div class="absolute bottom-4 left-4">
                            <span
                                class="bg-white/10 backdrop-blur-sm text-white px-3 py-1 rounded text-xs font-semibold"
                                >Industri F&amp;B</span
                            >
                        </div>
                    </div>
                </div>
                <!-- Metric card 1 -->
                <div
                    class="md:col-span-4 bg-white dark:bg-white/10 border border-outline-variant/30 dark:border-outline/20 rounded-xl p-8 shadow-sm flex flex-col justify-between"
                >
                    <div>
                        <div class="text-surface-tint mb-4">
                            <span class="material-symbols-outlined text-[40px]"
                                >trending_down</span
                            >
                        </div>
                        <h4
                            class="text-5xl font-bold text-on-surface dark:text-inverse-on-surface"
                        >
                            45%
                        </h4>
                        <p
                            class="text-base text-on-surface-variant dark:text-surface-variant/80"
                        >
                            Pengurangan limbah operasional untuk FreshMart
                            Groceries sejak implementasi.
                        </p>
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <div class="h-px grow bg-outline-variant/30"></div>
                        <span
                            class="text-xs font-bold text-surface-tint dark:text-inverse-primary"
                            >Studi Kasus FreshMart</span
                        >
                    </div>
                </div>
                <!-- Small testimonial -->
                <div
                    class="md:col-span-4 bg-surface-container-low dark:bg-white/10 rounded-xl p-8 relative"
                >
                    <span
                        class="material-symbols-outlined text-primary-container absolute top-6 right-8 opacity-20 text-[64px]"
                        >format_quote</span
                    >
                    <p
                        class="text-base italic text-on-surface-variant dark:text-surface-variant/80 relative z-10 mb-6"
                    >
                        "Alat pelaporannya tak tertandingi. Saya sekarang dapat
                        memperkirakan permintaan untuk pusat layanan saya dengan
                        akurasi 98%."
                    </p>
                    <div class="flex items-center gap-4">
                        <div
                            class="w-10 h-10 rounded-full bg-secondary-fixed"
                        ></div>
                        <div>
                            <p class="font-bold text-sm">Marcus Thorne</p>
                            <p
                                class="text-xs text-on-surface-variant dark:text-surface-variant/70"
                            >
                                Pemilik, Elite Auto Care
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Metric card 2 -->
                <div
                    class="md:col-span-4 bg-surface-tint text-white rounded-xl p-8 flex flex-col justify-between overflow-hidden relative"
                >
                    <div class="absolute -right-10 -bottom-10 opacity-10">
                        <span class="material-symbols-outlined text-[200px]"
                            >bolt</span
                        >
                    </div>
                    <div>
                        <h4 class="text-5xl font-bold">120+</h4>
                        <p class="text-base opacity-90">
                            Jam yang dihemat per bulan dari rekonsiliasi manual
                            oleh Urban Collective.
                        </p>
                    </div>
                    <button
                        class="mt-6 flex items-center gap-2 text-sm font-bold hover:gap-4 transition-all"
                    >
                        Baca Cerita
                        <span class="material-symbols-outlined"
                            >arrow_forward</span
                        >
                    </button>
                </div>
            </div>
        </section>

        <!-- Featured quote -->
        <section class="bg-inverse-surface py-20 overflow-hidden relative">
            <div
                class="max-w-360 mx-auto px-10 grid md:grid-cols-2 items-center gap-16"
            >
                <div class="relative">
                    <div
                        class="aspect-square rounded-2xl overflow-hidden shadow-2xl relative z-10"
                    >
                        <img
                            class="w-full h-full object-cover"
                            src="https://lh3.googleusercontent.com/aida-public/AB6AXuBi8VVv3dWDifhE_rKXZuV6QsDX1Lz-lU23T6VsI4Jp7a8KcvZyHVwn9HzlaMta97UhEihlF_V0734tmygDr7IW5dAdRx3v4oYrOUrHjymQe4Bl0CZGIvOz_ImBSpVq1keGze7sSb4-E_LH-eR23yQoeku3wUcqFcZlyRvdvjeXO1dbYIF0DH9mOkHzgy-9MhXVVJ6fs9vToGPGSEPNhNKAMVb7TLJp5UlreigAAPr336dZP1INol-JrvCxCXIpdcLXdSVMCEawPUo"
                            alt="Diana Rossini"
                        />
                    </div>
                    <div
                        class="absolute -top-6 -left-6 w-24 h-24 bg-surface-tint rounded-xl z-0"
                    ></div>
                </div>
                <div>
                    <div class="flex mb-6">
                        <span
                            class="material-symbols-outlined text-primary-fixed text-[48px]"
                            style="font-variation-settings: 'FILL' 1"
                            >format_quote</span
                        >
                    </div>
                    <blockquote
                        class="text-3xl font-semibold text-white mb-8 leading-tight"
                        style="letter-spacing: -0.01em"
                    >
                        "StockEase tidak hanya memberi kami alat; mereka memberi
                        kami keunggulan kompetitif. Kecepatan skala kami
                        meningkat tiga kali lipat karena backend kami akhirnya
                        solid."
                    </blockquote>
                    <div class="text-white">
                        <p class="text-2xl font-bold">Diana Rossini</p>
                        <p
                            class="text-on-surface-variant/70 dark:text-surface-variant/70"
                        >
                            COO, Global Retail Partners
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Industry Voices -->
        <section class="max-w-360 mx-auto px-10 mt-24">
            <div class="text-center mb-16">
                <h2
                    class="text-3xl font-semibold text-on-surface dark:text-inverse-on-surface mb-2"
                    style="letter-spacing: -0.01em"
                >
                    Suara Industri
                </h2>
                <div
                    class="h-1 w-20 bg-surface-tint dark:bg-inverse-primary mx-auto rounded-full"
                ></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div
                    v-for="r in reviews"
                    :key="r.name"
                    class="bg-white dark:bg-white/10 p-6 rounded-xl border border-outline-variant/30 dark:border-outline/20 hover:border-surface-tint/50 dark:hover:border-inverse-primary/50 transition-colors"
                >
                    <div class="flex text-surface-tint mb-4">
                        <span
                            v-for="i in 5"
                            :key="i"
                            class="material-symbols-outlined text-[18px]"
                            style="font-variation-settings: 'FILL' 1"
                            >star</span
                        >
                    </div>
                    <p
                        class="text-base text-on-surface-variant dark:text-surface-variant/80 mb-6"
                    >
                        {{ r.quote }}
                    </p>
                    <div class="flex items-center gap-3">
                        <div
                            class="w-8 h-8 rounded-full bg-surface-variant flex items-center justify-center text-surface-tint font-bold text-xs"
                        >
                            {{ r.initials }}
                        </div>
                        <div>
                            <p
                                class="text-xs font-semibold text-on-surface dark:text-inverse-on-surface"
                            >
                                {{ r.name }}
                            </p>
                            <p
                                class="text-[10px] text-on-surface-variant dark:text-surface-variant/70 uppercase"
                            >
                                {{ r.company }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="max-w-360 mx-auto px-10 mt-32 mb-16">
            <div
                class="bg-surface-container-highest dark:bg-white/10 rounded-3xl p-12 md:p-20 text-center relative overflow-hidden"
            >
                <div
                    class="absolute inset-0 opacity-10 pointer-events-none"
                    style="
                        background-image: radial-gradient(
                            #006c49 2px,
                            transparent 2px
                        );
                        background-size: 32px 32px;
                    "
                ></div>
                <div class="relative z-10">
                    <h2
                        class="text-5xl font-bold text-on-surface dark:text-inverse-on-surface mb-6"
                        style="letter-spacing: -0.02em"
                    >
                        Siap Menulis Cerita Sukses Anda?
                    </h2>
                    <p
                        class="text-lg text-on-surface-variant dark:text-surface-variant/80 mb-10 max-w-xl mx-auto"
                    >
                        Bergabunglah dengan 5.000+ bisnis yang puas yang
                        menyederhanakan operasi mereka dan meningkatkan
                        keuntungan dengan StockEase.
                    </p>
                    <div class="flex flex-col md:flex-row gap-4 justify-center">
                        <Link
                            :href="route('login')"
                            class="bg-surface-tint text-white px-10 py-4 rounded-full font-bold hover:scale-105 transition-all shadow-lg flex items-center justify-center gap-2"
                        >
                            Gabung 5.000+ Bisnis
                            <span class="material-symbols-outlined"
                                >rocket_launch</span
                            >
                        </Link>
                        <Link
                            href="#"
                            class="bg-white dark:bg-white/15 text-surface-tint dark:text-inverse-primary border border-surface-tint/20 dark:border-inverse-primary/20 px-10 py-4 rounded-full font-bold hover:bg-surface-tint/5 dark:hover:bg-inverse-primary/10 transition-all"
                            >Hubungi Sales</Link
                        >
                    </div>
                    <p
                        class="mt-8 text-xs font-semibold text-on-surface-variant dark:text-surface-variant/60"
                    >
                        Tanpa kartu kredit. Uji coba gratis 14 hari untuk
                        Pemula.
                    </p>
                </div>
            </div>
        </section>
    </main>
</template>
