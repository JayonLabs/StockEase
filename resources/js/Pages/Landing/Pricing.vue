<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';

const isAnnual = ref(false);

const proPrice = {
    monthly: 'Rp 299.000',
    annual: 'Rp 249.000',
};

const entPrice = {
    monthly: 'Rp 999.000',
    annual: 'Rp 849.000',
};

const plans = [
    {
        name: 'Pemula',
        price: 'Rp 0',
        period: '/bulan',
        description: 'Cocok untuk penjual perorangan dan kios pasar kecil.',
        highlight: false,
        features: [
            { label: 'Hingga 50 Produk', included: true },
            { label: 'POS dengan Transaksi Tunai', included: true },
            { label: 'Laporan Penjualan Dasar', included: true },
            { label: 'Satu Pengguna', included: true },
            { label: 'Notifikasi Stok Rendah', included: false },
            { label: 'Multi-Gudang', included: false },
            { label: 'Laporan Laba Rugi', included: false },
            { label: 'Manajemen Pembelian', included: false },
        ],
        cta: 'Mulai Gratis',
        ctaClass:
            'w-full py-4 rounded-xl border border-outline text-on-surface hover:bg-surface-container-low transition-colors font-semibold',
    },
    {
        name: 'Enterprise',
        price: 'Rp 999.000',
        period: '/bulan',
        description:
            'Untuk jaringan retail besar yang butuh kendali penuh dan skala luas.',
        highlight: false,
        features: [
            { label: 'Semua fitur Profesional', included: true },
            { label: 'Laporan Laba Rugi', included: true },
            { label: 'Analitik Pergerakan Produk', included: true },
            { label: 'Manajemen Pengguna & Role', included: true },
            { label: 'Dukungan Prioritas 24/7', included: true },
            { label: 'Integrasi API Kustom', included: true },
            { label: 'File Manager', included: true },
            { label: 'Log Aktivitas Lengkap', included: true },
        ],
        cta: 'Hubungi Sales',
        ctaClass:
            'w-full py-4 rounded-xl border border-outline text-on-surface hover:bg-surface-container-low transition-colors font-semibold',
    },
];

const comparison = [
    {
        feature: 'Produk, Kategori & Satuan',
        starter: true,
        pro: true,
        enterprise: true,
    },
    {
        feature: 'Point of Sale (POS)',
        starter: true,
        pro: true,
        enterprise: true,
    },
    { feature: 'Barcode Scanner', starter: true, pro: true, enterprise: true },
    {
        feature: 'Pembayaran QRIS Midtrans',
        starter: false,
        pro: true,
        enterprise: true,
    },
    {
        feature: 'Kirim Invoice (Email/WA)',
        starter: false,
        pro: true,
        enterprise: true,
    },
    {
        feature: 'Laporan Penjualan & Ekspor',
        starter: true,
        pro: true,
        enterprise: true,
    },
    {
        feature: 'Laporan Pembelian & Ekspor',
        starter: false,
        pro: true,
        enterprise: true,
    },
    {
        feature: 'Laporan Stok & Ekspor',
        starter: false,
        pro: true,
        enterprise: true,
    },
    {
        feature: 'Laporan Laba Rugi',
        starter: false,
        pro: false,
        enterprise: true,
    },
    {
        feature: 'Manajemen Pembelian & Supplier',
        starter: false,
        pro: true,
        enterprise: true,
    },
    {
        feature: 'Multi-Gudang & Transfer Stok',
        starter: false,
        pro: true,
        enterprise: true,
    },
    {
        feature: 'Notifikasi Stok Rendah',
        starter: false,
        pro: true,
        enterprise: true,
    },
    { feature: 'Shift Kasir', starter: false, pro: true, enterprise: true },
    {
        feature: 'Manajemen Pengguna & Hak Akses',
        starter: false,
        pro: false,
        enterprise: true,
    },
    { feature: 'Log Aktivitas', starter: false, pro: true, enterprise: true },
    { feature: 'File Manager', starter: false, pro: false, enterprise: true },
];

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
        <span class="text-surface-tint dark:text-inverse-primary">Harga</span>
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
        >Bulanan</Label>
        <Switch
          id="billing"
          v-model="isAnnual"
        />
        <Label
          for="billing"
          class="text-sm font-medium text-on-surface-variant"
        >
          Tahunan
          <span
            class="bg-secondary-container text-on-secondary-container dark:bg-secondary-container/30 dark:text-secondary-fixed-dim px-2 py-0.5 rounded-full text-xs font-bold ml-1"
          >Hemat 17%</span>
        </Label>
      </div>
    </section>

    <!-- Plans -->
    <section class="px-10 max-w-360 mx-auto mb-24">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
        <!-- Starter -->
        <div
          class="glass-card border border-outline-variant/30 rounded-3xl p-8 flex flex-col hover:shadow-xl transition-shadow group"
        >
          <div class="mb-8">
            <span
              class="text-surface-tint dark:text-inverse-primary font-bold text-xs uppercase tracking-widest"
            >Pemula</span>
            <div class="flex items-baseline mt-4">
              <span
                class="text-5xl font-bold text-on-surface dark:text-on-surface"
              >Rp 0</span>
              <span
                class="text-on-surface-variant dark:text-surface-variant/70 ml-2"
              >/bulan</span>
            </div>
            <p
              class="text-on-surface-variant dark:text-surface-variant/70 mt-4"
            >
              Cocok untuk penjual perorangan dan kios kecil.
            </p>
          </div>
          <ul class="space-y-4 mb-10 grow">
            <li
              v-for="f in [
                { label: 'Hingga 50 Produk', included: true },
                {
                  label: 'POS Transaksi Tunai',
                  included: true,
                },
                { label: 'Laporan Penjualan', included: true },
                { label: 'Satu Pengguna', included: true },
                {
                  label: 'Notifikasi Stok Rendah',
                  included: false,
                },
                { label: 'Laporan Pembelian', included: false },
                { label: 'Multi-Gudang', included: false },
                { label: 'QRIS Midtrans', included: false },
              ]"
              :key="f.label"
              class="flex items-center gap-3"
              :class="{
                'text-on-surface-variant/50': !f.included,
              }"
            >
              <span
                class="material-symbols-outlined"
                :class="f.included ? 'text-surface-tint' : ''"
                :style="
                  f.included
                    ? `font-variation-settings:'FILL' 1`
                    : ''
                "
              >{{
                f.included ? 'check_circle' : 'cancel'
              }}</span>
              {{ f.label }}
            </li>
          </ul>
          <Link
            :href="$page.props.auth.user ? route('subscription.index') : route('login')"
            class="w-full py-4 rounded-xl border border-outline dark:border-outline-variant text-on-surface dark:text-on-surface hover:bg-surface-container-low dark:hover:bg-surface-container-high transition-colors font-semibold text-center block"
          >
            Mulai Gratis
          </Link>
        </div>

        <!-- Professional (featured) -->
        <div
          class="relative bg-surface-tint text-white rounded-3xl p-10 flex flex-col shadow-2xl scale-105 z-10 overflow-hidden group"
        >
          <div class="absolute top-6 right-6">
            <span
              class="bg-primary-fixed text-on-primary-fixed px-4 py-1 rounded-full text-xs font-bold shadow-lg"
            >TERPOPULER</span>
          </div>
          <div
            class="absolute -top-24 -right-24 w-64 h-64 bg-primary-fixed/20 blur-[80px] rounded-full group-hover:scale-125 transition-transform duration-700"
          />
          <div class="mb-8 relative z-10">
            <span
              class="text-primary-fixed font-bold text-xs uppercase tracking-widest"
            >Profesional</span>
            <div class="flex items-baseline mt-4">
              <span
                class="text-5xl font-bold text-white transition-all"
              >{{
                isAnnual
                  ? proPrice.annual
                  : proPrice.monthly
              }}</span>
              <span class="text-primary-fixed-dim ml-2">{{
                isAnnual ? '/bulan, ditagih tahunan' : '/bulan'
              }}</span>
            </div>
            <p class="text-primary-fixed-dim mt-4">
              Toko retail berkembang dan bisnis yang sedang
              tumbuh.
            </p>
          </div>
          <ul class="space-y-4 mb-10 grow relative z-10">
            <li
              v-for="label in [
                'Produk Tak Terbatas',
                'Pembayaran QRIS Midtrans',
                'Multi-Gudang & Transfer Stok',
                'Manajemen Pembelian & Supplier',
                'Laporan (Penjualan, Pembelian, Stok)',
                'Ekspor Laporan PDF & Excel',
                'Kirim Invoice via Email/WA',
                'Shift Kasir & Log Aktivitas',
              ]"
              :key="label"
              class="flex items-center gap-3"
            >
              <span
                class="material-symbols-outlined text-primary-fixed"
                style="font-variation-settings: 'FILL' 1"
              >check_circle</span>
              <span class="text-white">{{ label }}</span>
            </li>
          </ul>
          <Link
            :href="$page.props.auth.user ? route('subscription.index') : route('login')"
            class="w-full py-4 rounded-xl bg-white text-surface-tint font-bold hover:shadow-lg hover:scale-[1.02] active:scale-95 transition-all relative z-10 text-center block"
          >
            Mulai Uji Coba 14 Hari
          </Link>
        </div>

        <!-- Enterprise -->
        <div
          class="glass-card border border-outline-variant/30 rounded-3xl p-8 flex flex-col hover:shadow-xl transition-shadow"
        >
          <div class="mb-8">
            <span
              class="text-surface-tint dark:text-inverse-primary font-bold text-xs uppercase tracking-widest"
            >Enterprise</span>
            <div class="flex items-baseline mt-4">
              <span
                class="text-5xl font-bold text-on-surface dark:text-on-surface"
              >{{
                isAnnual
                  ? entPrice.annual
                  : entPrice.monthly
              }}</span>
              <span
                class="text-on-surface-variant dark:text-surface-variant/70 ml-2"
              >{{
                isAnnual
                  ? '/bulan, ditagih tahunan'
                  : '/bulan'
              }}</span>
            </div>
            <p
              class="text-on-surface-variant dark:text-surface-variant/70 mt-4"
            >
              Untuk jaringan retail besar dengan kebutuhan skala
              enterprise.
            </p>
          </div>
          <ul class="space-y-4 mb-10 grow">
            <li
              v-for="f in [
                {
                  label: 'Semua fitur Profesional',
                  included: true,
                },
                { label: 'Laporan Laba Rugi', included: true },
                {
                  label: 'Analitik Pergerakan Produk',
                  included: true,
                },
                {
                  label: 'Manajemen Pengguna & Role',
                  included: true,
                },
                {
                  label: 'Dukungan Prioritas 24/7',
                  included: true,
                },
                {
                  label: 'Integrasi API Kustom',
                  included: true,
                },
                { label: 'File Manager', included: true },
                {
                  label: 'Log Aktivitas Lengkap',
                  included: true,
                },
              ]"
              :key="f.label"
              class="flex items-center gap-3"
            >
              <span
                class="material-symbols-outlined text-surface-tint"
                style="font-variation-settings: 'FILL' 1"
              >check_circle</span>
              {{ f.label }}
            </li>
          </ul>
          <Link
            href="#"
            class="w-full py-4 rounded-xl border border-outline dark:border-outline-variant text-on-surface dark:text-on-surface hover:bg-surface-container-low dark:hover:bg-surface-container-high transition-colors font-semibold text-center block"
          >
            Hubungi Sales
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
              <th class="py-6 px-4 font-bold text-sm text-center">
                Pemula
              </th>
              <th
                class="py-6 px-4 font-bold text-sm text-center bg-surface-tint/5 text-surface-tint"
              >
                Profesional
              </th>
              <th class="py-6 px-4 font-bold text-sm text-center">
                Enterprise
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-outline-variant/30">
            <tr
              v-for="row in comparison"
              :key="row.feature"
            >
              <td
                class="py-6 px-4 text-on-surface dark:text-on-surface font-medium"
              >
                {{ row.feature }}
              </td>
              <td class="py-6 px-4 text-center">
                <span
                  v-if="row.starter"
                  class="material-symbols-outlined text-surface-tint"
                >check</span>
                <span
                  v-else
                  class="text-on-surface-variant/30"
                >—</span>
              </td>
              <td
                class="py-6 px-4 text-center bg-surface-tint/5 dark:bg-surface-tint/10"
              >
                <span
                  v-if="row.pro"
                  class="material-symbols-outlined text-surface-tint"
                >check</span>
                <span
                  v-else
                  class="text-on-surface-variant/30"
                >—</span>
              </td>
              <td class="py-6 px-4 text-center">
                <span
                  v-if="row.enterprise"
                  class="material-symbols-outlined text-surface-tint"
                >check</span>
                <span
                  v-else
                  class="text-on-surface-variant/30"
                >—</span>
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
            >{{ faq.q }}</span>
            <span
              class="material-symbols-outlined transition-transform duration-300"
              :class="{ 'rotate-180': activeFaq === i }"
            >expand_more</span>
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
