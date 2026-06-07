<script setup>
import { Link } from '@inertiajs/vue3';

const columns = [
    { title: 'Produk', links: ['Fitur', 'Harga', 'Keamanan'] },
    { title: 'Sumber Daya', links: ['Dokumentasi', 'Pusat Bantuan', 'API'] },
    { title: 'Perusahaan', links: ['Tentang Kami', 'Karir', 'Kontak'] },
    { title: 'Hukum', links: ['Kebijakan Privasi', 'Ketentuan Layanan'] },
];

const year = new Date().getFullYear();
</script>

<template>
    <footer
        class="bg-inverse-surface dark:bg-white/5 text-[#4edea3] w-full py-16 border-t border-outline/10"
    >
        <div
            class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-6 px-10 max-w-360 mx-auto"
        >
            <!-- Brand -->
            <div class="col-span-2">
                <div class="flex items-center gap-2.5 mb-4">
                    <img
                        src="/img/StockEase-Logo.png"
                        alt="StockEase"
                        class="h-10 w-auto brightness-0 invert"
                    />
                    <span class="text-2xl font-black text-white"
                        >StockEase</span
                    >
                </div>
                <p class="text-surface-variant/70 text-base mb-6 max-w-xs">
                    &copy; {{ year }} StockEase ERP. Seluruh hak cipta. Presisi
                    dalam setiap transaksi.
                </p>
                <div class="flex gap-4">
                    <Link
                        v-for="icon in ['public', 'chat', 'campaign']"
                        :key="icon"
                        href="#"
                        class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-surface-tint transition-colors text-white"
                    >
                        <span class="material-symbols-outlined text-[20px]">{{
                            icon
                        }}</span>
                    </Link>
                </div>
            </div>

            <!-- Link columns -->
            <div v-for="col in columns" :key="col.title">
                <h5 class="text-white font-bold mb-6">{{ col.title }}</h5>
                <ul class="space-y-4 text-xs font-semibold">
                    <li v-for="link in col.links" :key="link">
                        <Link
                            href="#"
                            class="text-surface-variant/70 hover:text-[#4edea3] transition-all underline-offset-4 hover:underline"
                        >
                            {{ link }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </footer>
</template>
