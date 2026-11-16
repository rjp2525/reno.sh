import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import * as path from 'path';
import { defineConfig } from 'vite';
import svgLoader from 'vite-svg-loader';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: Number(process.env.VITE_PORT ?? 5188),
        strictPort: true,
        hmr: { host: 'localhost' },
        cors: true,
    },
    ssr: {
        define: {
            'process.env.SSR_PORT': 'process.env.SSR_PORT',
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './resources/js'),
            '@svg': path.resolve(import.meta.dirname, './resources/svg'),
            '@img': path.resolve(import.meta.dirname, './resources/img'),
        },
    },
    plugins: [
        tailwindcss(),
        wayfinder(),
        laravel({
            input: [
                'resources/js/app.ts',
                'resources/css/filament/admin/theme.css',
            ],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        svgLoader({
            svgoConfig: {
                plugins: [
                    {
                        name: 'preset-default',
                        params: {
                            overrides: {
                                removeViewBox: false,
                            },
                        },
                    },
                ],
            },
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
