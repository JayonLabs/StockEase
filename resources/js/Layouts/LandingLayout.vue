<script setup>
import { Head } from '@inertiajs/vue3';
import LandingNav from '@/Components/Landing/LandingNav.vue';
import LandingFooter from '@/Components/Landing/LandingFooter.vue';
import { useLandingDarkMode } from '@/composables/useLandingDarkMode';

const { isDark } = useLandingDarkMode();
</script>

<template>
    <div :class="['landing', { dark: isDark }]">
        <Head>
            <link rel="preconnect" href="https://fonts.googleapis.com" />
            <link
                href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
                rel="stylesheet"
            />
            <link
                href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
                rel="stylesheet"
            />
        </Head>

        <div
            class="bg-[#f8f9ff] dark:bg-inverse-surface text-on-surface transition-colors duration-300 min-h-screen"
            style="font-family: 'Inter', sans-serif"
        >
            <LandingNav />

            <main>
                <slot />
            </main>

            <LandingFooter />
        </div>
    </div>
</template>
