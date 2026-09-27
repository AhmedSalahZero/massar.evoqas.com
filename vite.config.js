// ══════════════════════════════════════════════════════════════════
//  Massar — Vite Configuration
//  Location: vite.config.js
//
//  Builds the frontend: resources/css/app.css (the Massar design
//  system — plain CSS, no Tailwind) and resources/js/app.js (Vue 3 +
//  Inertia). `npm run dev` for development with hot reload,
//  `npm run build` for production (output: public/build).
//
//  '@' is an alias for resources/js, so imports read
//  `import AppIcon from '@/Components/AppIcon.vue'`.
// ══════════════════════════════════════════════════════════════════

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';
import { fileURLToPath } from 'url';

const __dirname = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },

    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
});
