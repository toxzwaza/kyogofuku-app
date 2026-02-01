import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';
import path from 'path';
import fs from 'fs';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
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
        VitePWA({
            buildBase: '/build/',
            scope: '/',
            registerType: 'autoUpdate',
            injectRegister: null,
            manifest: {
                name: 'Kyogofuku App',
                short_name: 'Kyogofuku',
                description: 'Kyogofuku Application',
                theme_color: '#1f2937',
                background_color: '#ffffff',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                icons: [
                    { src: '/build/icons/icon-192.svg', sizes: '192x192', type: 'image/svg+xml', purpose: 'any' },
                    { src: '/build/icons/icon-512.svg', sizes: '512x512', type: 'image/svg+xml', purpose: 'any' },
                ],
            },
            workbox: {
                globPatterns: [],
            },
            devOptions: { enabled: true },
        }),
        {
            name: 'copy-pwa-icons',
            closeBundle() {
                const outDir = path.resolve(__dirname, 'public/build');
                const iconsDir = path.join(outDir, 'icons');
                const srcDir = path.resolve(__dirname, 'public/icons');
                if (!fs.existsSync(srcDir)) return;
                fs.mkdirSync(iconsDir, { recursive: true });
                for (const name of ['icon-192.svg', 'icon-512.svg']) {
                    const src = path.join(srcDir, name);
                    const dest = path.join(iconsDir, name);
                    if (fs.existsSync(src)) fs.copyFileSync(src, dest);
                }
            },
        },
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});
