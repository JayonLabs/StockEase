<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useLandingDarkMode } from '@/composables/useLandingDarkMode';

const { isDark, toggle } = useLandingDarkMode();

const page = usePage();

const navLinks = [
    { label: 'Beranda', routeName: 'landing', component: 'Landing/Index' },
    {
        label: 'Harga',
        routeName: 'landing.pricing',
        component: 'Landing/Pricing',
    },
    {
        label: 'Kenapa Kami',
        routeName: 'landing.why',
        component: 'Landing/Why',
    },
    {
        label: 'Testimoni',
        routeName: 'landing.testimonials',
        component: 'Landing/Testimonials',
    },
    {
        label: 'Kontak',
        routeName: 'landing.contact',
        component: 'Landing/Contact',
    },
];

const hrefs = computed(() => ({
    landing: route('landing'),
    'landing.pricing': route('landing.pricing'),
    'landing.why': route('landing.why'),
    'landing.testimonials': route('landing.testimonials'),
    'landing.contact': route('landing.contact'),
}));

function isActive(component) {
    return page.component === component;
}
</script>

<template>
    <header
        class="fixed top-0 w-full z-50 bg-surface/80 dark:bg-inverse-surface/80 backdrop-blur-md border-b border-outline-variant/30 dark:border-outline/20 shadow-sm"
    >
        <div
            class="flex justify-between items-center h-20 px-10 max-w-360 mx-auto"
        >
            <Link
                :href="route('landing')"
                class="flex items-center gap-2.5 shrink-0"
            >
                <img
                    src="/img/StockEase-Logo.png"
                    alt="StockEase"
                    class="h-10 w-auto"
                />
                <span
                    class="text-2xl font-bold text-surface-tint dark:text-inverse-primary tracking-tight"
                    >StockEase</span
                >
            </Link>

            <nav class="hidden md:flex items-center space-x-1">
                <Link
                    v-for="link in navLinks"
                    :key="link.label"
                    :href="hrefs[link.routeName]"
                    :class="[
                        'text-sm font-medium transition-colors px-3 py-2',
                        isActive(link.component)
                            ? 'text-surface-tint dark:text-inverse-primary font-semibold border-b-2 border-surface-tint dark:border-inverse-primary'
                            : 'text-on-surface-variant dark:text-surface-variant hover:text-surface-tint dark:hover:text-inverse-primary rounded-lg hover:bg-surface-tint/5',
                    ]"
                >
                    {{ link.label }}
                </Link>
            </nav>

            <div class="flex items-center gap-4">
                <button
                    class="p-2 rounded-full hover:bg-surface-variant dark:hover:bg-white/10 transition-colors"
                    :aria-label="
                        isDark
                            ? 'Beralih ke mode terang'
                            : 'Beralih ke mode gelap'
                    "
                    @click="toggle"
                >
                    <span class="material-symbols-outlined">{{
                        isDark ? 'light_mode' : 'dark_mode'
                    }}</span>
                </button>

                <Link
                    :href="route('login')"
                    class="bg-primary-container text-on-primary-container px-6 py-2.5 rounded-full text-sm font-semibold hover:scale-95 transition-transform duration-150 active:scale-90"
                >
                    Mulai
                </Link>
            </div>
        </div>
    </header>
</template>
