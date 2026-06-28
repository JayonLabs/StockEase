<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

const activeFaq = ref(0);

function toggleFaq(index) {
    activeFaq.value = activeFaq.value === index ? null : index;
}

const features = [
    {
        icon: 'inventory_2',
        bg: 'bg-surface-tint/10 dark:bg-inverse-primary/15',
        color: 'text-surface-tint dark:text-inverse-primary',
        title: 'Manajemen Inventaris',
        description:
            'Lacak stok secara real-time dengan notifikasi otomatis saat stok menipis. Kelola produk, kategori, satuan, dan promo dalam satu tempat.',
    },
    {
        icon: 'point_of_sale',
        bg: 'bg-secondary/10 dark:bg-secondary-fixed-dim/20',
        color: 'text-secondary dark:text-secondary-fixed-dim',
        title: 'Point of Sale (POS)',
        description:
            'Transaksi cepat dengan dukungan barcode scanner. Keranjang fleksibel, checkout instan, dan kirim invoice via email atau WhatsApp.',
    },
    {
        icon: 'account_balance_wallet',
        bg: 'bg-primary-container/20 dark:bg-inverse-primary/15',
        color: 'text-surface-tint dark:text-inverse-primary',
        title: 'Multi-Pembayaran',
        description:
            'Dukung pembayaran tunai dan QRIS Midtrans. Riwayat transaksi lengkap dengan pencatatan otomatis untuk rekonsiliasi.',
    },
    {
        icon: 'shopping_cart',
        bg: 'bg-tertiary/10 dark:bg-tertiary-fixed-dim/20',
        color: 'text-tertiary dark:text-tertiary-fixed-dim',
        title: 'Manajemen Pembelian',
        description:
            'Catat pembelian dari supplier, lacak harga pokok, kelola stok masuk per gudang, dan pantau tanggal kedaluwarsa produk.',
    },
    {
        icon: 'warehouse',
        bg: 'bg-error/10',
        color: 'text-error',
        title: 'Multi-Gudang & Transfer',
        description:
            'Kelola banyak gudang sekaligus. Transfer stok antar gudang dengan pencatatan otomatis dan lacak pergerakan stok.',
    },
    {
        icon: 'monitoring',
        bg: 'bg-primary/10 dark:bg-inverse-primary/15',
        color: 'text-primary dark:text-inverse-primary',
        title: 'Laporan & Analitik',
        description:
            'Laporan penjualan, pembelian, stok, laba rugi, dan pergerakan produk. Ekspor ke PDF & Excel untuk analisis mendalam.',
    },
];

const testimonials = [
    {
        name: 'Robin Ahmed',
        role: 'Manajer Ritel',
        title: 'Hadiah Fleksibel',
        quote: '"StockEase telah sepenuhnya mengubah cara kami mengelola penjualan di toko. Sinkronisasi inventarisnya sempurna dan menghemat waktu kerja manual kami berjam-jam setiap minggu."',
        avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAHSiYvEf_rtgAwRepSZHGuEWXSufA_vt0v2qjQHJF18f9fJEXrLHZlWjiSN-69cj1zLPnO1yTDxpWU_ErATHknGPbSR831xY83Lz13klGr170BZAIOeeXVAL0YudJPndoYi2B2t9neD55WMUVl9g_LfKJz8R7rUP29A1hsqCFluRY5gnT_Dh85hXocFs4-q-VVTKRUQ4wakyeeI8ntFgI7firXYS6uF0dh9LMrSq4cSt9-f8IqxnXugjdHGbDWfSpTmeugTg8Hrlg',
    },
    {
        name: 'Khadija',
        role: 'Pemilik Butik',
        title: 'Pengalaman Mulus',
        quote: '"Antarmukanya sangat intuitif, tim saya bahkan tidak perlu buku panduan. Proses checkout kami 40% lebih cepat dari sebelumnya."',
        avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuALzndLgTAZZDd5-6ErJ4fSdG1sDueeJVB7jbAxhqAT0ZnckPyaGDTEredJOPsxjHfniwm5GzX7kIIm_oRaZRboRylODOEMH3O9B2eNjB7WkQrGLd3uOW7FU4QkVjpJ1V-Nx3sncbGn3gd4hlNAmPk8UccpTazHIR8Dhf0tb7S3UsA67cquqndSe5V-DyOr2MEEhUSYOHoVA_sigaB-Hy31bolDyhhq01t-bQoMotPsFBWpu_94woPEaSDMkrNrlQ6YUZQFx71YVIY',
    },
    {
        name: 'Arpita',
        role: 'Kepala Gudang',
        title: 'Analitik Unggul',
        quote: '"Alat pelaporannya luar biasa. Kami bisa melihat dengan tepat barang mana yang bergerak dan di mana kami perlu menyesuaikan pembelian."',
        avatar: 'https://lh3.googleusercontent.com/aida-public/AB6AXuAbvFkugA7-eWJZ5sB64dXwtjqlW4oMK05cfVdGlSM3V1o7UzdtH3CnLWI9zTe2DuwS3hKsREx1w7wy3aLpjmAv_Xu9VBvf6pYcuEYwIveCRlEOwSh9Z3zuBA_tcw4KffGnTw0EP6sosUoMa-5FykfykdxhjlkqGk8yfh74_9jGIXRkNDVFaCAfb_ufFyD8WOQnCecGZ1c8StAbrxAUVg4X44aDNOYAW-r-FzvumldhPfUauy78N_pXMtwN-3Zrc10-PzpAFC18uqE',
    },
];

const faqs = [
    {
        q: 'Bagaimana cara mengelola banyak bisnis dengan satu akun?',
        a: 'Anda dapat mengelola beberapa lokasi atau entitas bisnis dari satu akun yang aman. Beralih antar toko dengan mudah, lacak penjualan gabungan, dan kelola aktivitas staf melalui dasbor terpadu.',
    },
    {
        q: 'Berapa biaya StockEase?',
        a: 'Kami menawarkan paket harga fleksibel mulai dari tingkat dasar untuk toko kecil hingga solusi perusahaan. Silakan kunjungi halaman harga kami untuk perbandingan detail.',
    },
    {
        q: 'Apakah ada versi gratis StockEase?',
        a: 'Ya, paket Pemula menawarkan uji coba gratis 14 hari. Untuk paket Profesional dan Enterprise, Anda bisa langsung berlangganan tanpa perlu uji coba.',
    },
    {
        q: 'Apakah StockEase memiliki biaya bulanan?',
        a: 'Ya, StockEase mengikuti model langganan SaaS yang ditagih bulanan atau tahunan dengan harga diskon.',
    },
];
</script>

<template>
    <Head
        title="Sederhanakan Penjualan, Inventaris & Pembayaran dengan Mudah"
    />

    <!-- Hero -->
    <section
        class="relative pt-32 pb-20 overflow-hidden bg-linear-to-b from-surface-tint/5 to-transparent dark:from-surface-tint/10"
    >
        <div class="max-w-360 mx-auto px-10 text-center">
            <div
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-secondary-container/50 dark:bg-secondary-container/20 border border-secondary/20 dark:border-secondary/30 mb-8"
            >
                <span
                    class="material-symbols-outlined text-[18px] text-secondary dark:text-secondary-fixed-dim"
                    >verified</span
                >
                <span
                    class="text-sm font-medium text-on-secondary-container dark:text-secondary-fixed-dim"
                    >Solusi perangkat lunak POS pintar Anda</span
                >
            </div>

            <h1
                class="text-5xl md:text-[64px] font-bold leading-tight mb-6 max-w-4xl mx-auto text-on-surface dark:text-inverse-on-surface"
                style="letter-spacing: -0.02em"
            >
                Sederhanakan Penjualan, Inventaris &amp;
                <span class="text-surface-tint dark:text-inverse-primary italic"
                    >Pembayaran</span
                >
                dengan Mudah
            </h1>

            <p
                class="text-lg text-on-surface-variant dark:text-surface-variant/80 max-w-2xl mx-auto mb-10"
            >
                Semua yang Anda butuhkan untuk menjalankan toko dengan lancar,
                dalam satu platform cerdas. Pelacakan real-time, laporan
                otomatis, dan transaksi tanpa hambatan.
            </p>

            <div class="flex flex-col sm:flex-row gap-4 justify-center mb-20">
                <Link
                    :href="route('login')"
                    class="bg-surface-tint dark:bg-inverse-primary text-white dark:text-on-primary-fixed px-8 py-4 rounded-full text-sm font-semibold flex items-center justify-center gap-2 hover:shadow-lg hover:shadow-surface-tint/20 transition-all"
                >
                    Dapatkan Demo Gratis
                    <span class="material-symbols-outlined">trending_flat</span>
                </Link>
                <Link
                    :href="`${route('landing')}#fitur`"
                    class="border-2 border-outline-variant dark:border-outline/50 text-on-surface dark:text-inverse-on-surface px-8 py-4 rounded-full text-sm font-semibold hover:bg-surface-variant dark:hover:bg-white/10 transition-all"
                >
                    Lihat Fitur
                </Link>
            </div>

            <!-- Product Mockup -->
            <div class="relative max-w-5xl mx-auto mt-12 group">
                <div
                    class="absolute -inset-4 bg-linear-to-r from-surface-tint/20 to-secondary/20 blur-3xl rounded-4xl opacity-30 group-hover:opacity-50 transition-opacity"
                ></div>
                <div
                    class="relative bg-white dark:bg-inverse-surface rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden floating"
                >
                    <img
                        src="https://lh3.googleusercontent.com/aida-public/AB6AXuC6-MaFCkpApRfcHVYNXmvT_JSPt1VToll1AZMKpBHEMy0kSzmvNS-H0pkIBydFOs6WGovdKnS1uKp9zROWKkdvdG1QRlFFzpU2hKP9OiGKCeV38nod0I5S-L1Zl2nRDDrO5WZsyNAidwsEJKQDclwS9JumyVPJU90X97lmtdqybaUYV4kCdEtO5PgtL-1CiOCBcJKa2y03VmBqF_KmYChxEg9c_Q_RBWc426vvlBz44PUZdR3qTgjfq7_LoMxc1sFJQXi4fFGGFZw"
                        alt="Dasbor StockEase"
                        class="w-full h-auto"
                    />
                </div>
            </div>
        </div>
    </section>

    <!-- Trusted By -->
    <section
        class="py-12 border-y border-outline-variant/10 dark:border-outline/20"
    >
        <div class="max-w-360 mx-auto px-10 text-center">
            <p
                class="text-xs font-semibold text-outline mb-10 uppercase tracking-widest"
            >
                Dipercaya oleh lebih dari 120+ perusahaan di seluruh dunia
            </p>
            <div
                class="flex flex-wrap justify-center items-center gap-x-16 gap-y-8 grayscale opacity-50 dark:invert"
            >
                <img
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuDtjobjmwno3z8sJY2nhe4IkYF_aNZRI2S7Hc7fjk5ky6Xy7WEyN7GxFsPMjGE4P69xkzkvOr85Sn7Y-MVh3YKb5rWn2SgqVAWBZL51jrvB99jYcujpuxh0ZLS7bVJI7BX-2ihRKEx_iVNCO2_5wipmWhOxRR44yIHiX1iravmk7kNxSYq_zP2SemfiTp34sO__x0sosZv7IhXJL6Ta0MmQWtVSNPQ9xuJ2wsXgH0NGJEQnoPS1J6lkQugMbOPjUlODhfAsWRmZ0OQ"
                    alt="Foursquare"
                    class="h-6"
                />
                <img
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuDBb5MRoMTwgIt0YtxIpO6OoxdWpKzEpADWQS331BHtnt3TbMsEcdSf1WfEZ3WTtdYU9mTZGbgbOC9VvC6q1J5iXTEehc31XT2PBNjC9455MNRQqJpqEEoV2e_dC8cOxSKn_SooZgKuEsw4C6MaOrKS920Lnx8wRXrTV70Hzt7McTQtTRJ7iXMLei6ry8t0qD1fnbzvRbOwx7SsBoDdNx6Va8H3BzB9dKN-QEs7VpMHBNwAO95tV_XeYRdRykraLTg8JRt_pmdYJ9M"
                    alt="Tumblr"
                    class="h-6"
                />
                <img
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuAtSpG0m2yst03T_jLu6GVp5s-UaHHjG_zfciHD_--YgsEgvjBnHtxSHDuItDGwXZSadme25xxXpDJcpTZCGDXl949phS8DtaNd6-CDZS4Nr5HPqZ3JkZT2zcBr5JLnEhtCAa4gkTbWoAmt-EYKaDJtxpxdE9vELiflgh2Rm5NOBiY4CpuQa58V_N2TYlXMYnxOh2q68vKD1mgjiXtkSg06Rx82QDwvxOUmm3CRGUnm5xXNNxyrEcDvHO3HVAv2-r__fvjApyUtpc8"
                    alt="Pinterest"
                    class="h-6"
                />
                <img
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuBuLeaixJT5Qcq-_-cNPJU0CG4tUO9ciZ2MpEjn47dqWIsPryi8Ndn_Ud8p4p2-k5XFsCh7WYxnqsV0zEQLcCJk6FqWsulCX2GBgeEeWp3MfsKg1MeuSEOHFcuXKE2TPOMKEPc8ICYEnPVciZjlSxgWPDmPIwBgNhXQBjmvNsFVHQlVNcMFJI6d9mXf7APjnPRfUuBd4KPrmVet0BvGlkO9ya0_Xmor3pCYTW2oFrR2efU7PPNaBeRWYgBm7rLIfzrdXfNJWTLO7i4"
                    alt="Twitch"
                    class="h-6"
                />
                <img
                    src="https://lh3.googleusercontent.com/aida-public/AB6AXuCrm43A2dflNOB5Io2EeAUURpe4AY95NzBTwuqWSxrzeyMWpLJTfUxieaTTpNwbtW6KfDXVSM8K5Z7gSuOU8S5sbmdZddgqUKr3cWumwAY336_ZFSXyKyK3f8DGnaHc1ybfoujCbUbtDw-uVCdzQIGN_Y7pd83lBYsv9YmL8NAPTF_GEZnhQwhjiMyBShF7SRgUhs6KJiXzLtVmOwGHrsgdCTB9wZ5J_b5vHG7rGCVo1vhc5TigqQRr8tdaiBx9PxgKZE2SAlOm8Is"
                    alt="BeReal"
                    class="h-6"
                />
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="fitur" class="py-24 bg-surface dark:bg-inverse-surface">
        <div class="max-w-360 mx-auto px-10">
            <div class="text-center mb-16">
                <h2
                    class="text-3xl font-semibold mb-4 text-on-surface dark:text-inverse-on-surface"
                    style="letter-spacing: -0.01em"
                >
                    Fitur Lengkap untuk Operasional Bisnis Anda
                </h2>
                <p
                    class="text-base text-on-surface-variant dark:text-surface-variant max-w-xl mx-auto"
                >
                    Dari kasir hingga laporan, semua yang Anda butuhkan tersedia
                    dalam satu platform terintegrasi.
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="f in features"
                    :key="f.title"
                    class="p-8 rounded-3xl bg-surface-container-lowest dark:bg-white/10 border border-outline-variant/20 dark:border-outline/20 hover:border-surface-tint/30 transition-all group"
                >
                    <div
                        :class="[
                            'w-14 h-14 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform',
                            f.bg,
                        ]"
                    >
                        <span
                            :class="[
                                'material-symbols-outlined text-[32px]',
                                f.color,
                            ]"
                            >{{ f.icon }}</span
                        >
                    </div>
                    <h3
                        class="text-2xl font-semibold mb-3 text-on-surface dark:text-inverse-on-surface"
                    >
                        {{ f.title }}
                    </h3>
                    <p
                        class="text-base text-on-surface-variant dark:text-surface-variant"
                    >
                        {{ f.description }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section
        id="testimoni"
        class="py-24 bg-surface-container-low dark:bg-inverse-surface/50 overflow-hidden"
    >
        <div class="max-w-360 mx-auto px-10">
            <div
                class="flex flex-col md:flex-row md:items-end justify-between mb-12"
            >
                <div>
                    <h2
                        class="text-3xl font-semibold mb-4 text-on-surface dark:text-inverse-on-surface"
                        style="letter-spacing: -0.01em"
                    >
                        Dipercaya oleh bisnis dari berbagai skala dan jenis.
                    </h2>
                    <div class="flex items-center gap-3">
                        <div class="flex -space-x-3">
                            <img
                                v-for="t in testimonials"
                                :key="t.name"
                                :src="t.avatar"
                                :alt="t.name"
                                class="w-10 h-10 rounded-full border-2 border-white object-cover"
                            />
                        </div>
                        <span
                            class="text-sm font-medium text-on-surface dark:text-inverse-on-surface"
                            >+120</span
                        >
                    </div>
                </div>
                <div class="flex gap-2 mt-6 md:mt-0">
                    <button
                        class="bg-surface-tint dark:bg-inverse-primary text-white dark:text-on-primary-fixed px-6 py-2.5 rounded-full text-sm font-semibold"
                    >
                        Testimoni Video
                    </button>
                    <button
                        class="bg-surface-tint/10 text-surface-tint dark:bg-inverse-primary/10 dark:text-inverse-primary px-6 py-2.5 rounded-full text-sm font-semibold"
                    >
                        Testimoni
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div
                    v-for="t in testimonials"
                    :key="t.name"
                    class="glass-card p-8 rounded-4xl shadow-sm hover:shadow-md transition-all border border-outline-variant/10"
                >
                    <div class="flex items-center gap-4 mb-6">
                        <img
                            :src="t.avatar"
                            :alt="t.name"
                            class="w-12 h-12 rounded-full object-cover"
                        />
                        <div>
                            <p
                                class="text-sm font-medium text-on-surface dark:text-inverse-on-surface"
                            >
                                {{ t.name }}
                            </p>
                            <p class="text-xs font-semibold text-outline">
                                {{ t.role }}
                            </p>
                        </div>
                    </div>
                    <h4
                        class="text-2xl font-semibold mb-3 text-on-surface dark:text-inverse-on-surface"
                    >
                        {{ t.title }}
                    </h4>
                    <p
                        class="text-base text-on-surface-variant dark:text-surface-variant/80 mb-6"
                    >
                        {{ t.quote }}
                    </p>
                    <div
                        class="flex text-surface-tint dark:text-inverse-primary"
                    >
                        <span
                            v-for="i in 5"
                            :key="i"
                            class="material-symbols-outlined"
                            style="font-variation-settings: 'FILL' 1"
                            >star</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-24 bg-surface dark:bg-inverse-surface">
        <div class="max-w-3xl mx-auto px-10">
            <div class="text-center mb-16">
                <h2
                    class="text-3xl font-semibold mb-4 text-on-surface dark:text-inverse-on-surface"
                    style="letter-spacing: -0.01em"
                >
                    Pertanyaan yang Sering Diajukan
                </h2>
                <p
                    class="text-base text-on-surface-variant dark:text-surface-variant"
                >
                    Temukan jawaban cepat untuk pertanyaan umum dari komunitas
                    kami yang terus berkembang.
                </p>
            </div>
            <div class="space-y-4">
                <div
                    v-for="(faq, index) in faqs"
                    :key="index"
                    class="bg-surface-container-lowest dark:bg-white/10 rounded-2xl border border-outline-variant/20 dark:border-outline/20 overflow-hidden"
                >
                    <button
                        class="w-full flex items-center justify-between p-6 text-sm font-medium text-on-surface dark:text-inverse-on-surface text-left"
                        @click="toggleFaq(index)"
                    >
                        {{ faq.q }}
                        <span
                            class="material-symbols-outlined transition-transform shrink-0 ml-4"
                            :class="{ 'rotate-180': activeFaq === index }"
                            >expand_more</span
                        >
                    </button>
                    <div
                        v-show="activeFaq === index"
                        class="px-6 pb-6 text-base text-on-surface-variant dark:text-surface-variant/80"
                    >
                        {{ faq.a }}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-24 px-10">
        <div
            class="max-w-360 mx-auto bg-surface-tint dark:bg-surface-container rounded-[3rem] p-12 md:p-20 text-center text-white dark:text-on-surface relative overflow-hidden"
        >
            <div
                class="absolute top-0 right-0 w-64 h-64 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2 blur-3xl"
            ></div>
            <div
                class="absolute bottom-0 left-0 w-64 h-64 bg-black/10 rounded-full translate-y-1/2 -translate-x-1/2 blur-3xl"
            ></div>
            <h2
                class="text-5xl font-bold mb-6 relative z-10"
                style="letter-spacing: -0.02em"
            >
                Siap Mengembangkan Bisnis Anda?
            </h2>
            <p class="text-lg mb-10 max-w-xl mx-auto opacity-90 relative z-10">
                Bergabunglah dengan 5.000+ pemilik bisnis yang mempercayai
                StockEase untuk operasional harian mereka.
            </p>
            <div
                class="flex flex-col sm:flex-row gap-4 justify-center relative z-10"
            >
                <Link
                    :href="route('login')"
                    class="bg-white text-surface-tint dark:bg-on-surface dark:text-surface-container-lowest px-8 py-4 rounded-full text-sm font-semibold hover:scale-105 transition-transform"
                >
                    Mulai Gratis
                </Link>
                <Link
                    href="#fitur"
                    class="border border-white/30 dark:border-on-surface/30 text-white dark:text-on-surface px-8 py-4 rounded-full text-sm font-semibold hover:bg-white/10 dark:hover:bg-on-surface/10 transition-colors"
                >
                    Pesan Demo
                </Link>
            </div>
        </div>
    </section>
</template>
