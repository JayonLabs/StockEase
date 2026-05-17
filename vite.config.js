import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import vue from "@vitejs/plugin-vue";
import path from "path";

export default defineConfig({
    resolve: {
        alias: {
            // top-level cropperjs is v2 (no CSS, incompatible API with vue-cropperjs)
            // prefix alias covers both JS and sub-paths like /dist/cropper.css
            cropperjs: path.resolve(
                __dirname,
                "node_modules/vue-cropperjs/node_modules/cropperjs",
            ),
        },
    },
    plugins: [
        laravel({
            input: "resources/js/app.js",
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('reka-ui') || id.includes('@vueuse') || id.includes('@floating-ui')) {
                            return 'vendor-ui';
                        }
                        if (id.includes('vue') || id.includes('@inertiajs') || id.includes('axios')) {
                            return 'vendor-vue';
                        }
                        if (id.includes('dayjs') || id.includes('chart')) {
                            return 'vendor-utils';
                        }
                        if (id.includes('lucide')) {
                            return 'vendor-icons';
                        }
                        return 'vendor';
                    }
                },
                // JS utama langsung hash
                entryFileNames: () => `assets/[hash].js`,
                // Chunk JS hasil split
                chunkFileNames: () => `assets/[hash].js`,
                // Asset (CSS, images, fonts)
                assetFileNames: () => `assets/[hash][extname]`,
            },
        },
    },
});
