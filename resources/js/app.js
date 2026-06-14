import '../css/app.css';
import './bootstrap';
import VueApexCharts from 'vue3-apexcharts';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import LandingLayout from '@/Layouts/LandingLayout.vue';
import MainLayout from '@/Layouts/MainLayout.vue';
import PlatformOwnerLayout from '@/Layouts/PlatformOwnerLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const page = resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        );

        page.then((module) => {
            if (name.startsWith('Auth/')) {
                module.default.layout = undefined;
            } else if (name.startsWith('Landing/')) {
                module.default.layout = LandingLayout;
            } else if (name.startsWith('Platform/Owner/')) {
                module.default.layout = PlatformOwnerLayout;
            } else if (!module.default.layout) {
                module.default.layout = MainLayout;
            }
        });

        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(VueApexCharts)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
}).then(() => {
    document.getElementById('app').removeAttribute('data-page');
});
