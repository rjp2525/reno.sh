import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import * as path from 'path';
import { defineConfig } from 'vite';
import svgLoader from 'vite-svg-loader';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    ssr: {
        define: {
            'process.env.SSR_PORT': 'process.env.SSR_PORT',
        },
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            '@svg': path.resolve(__dirname, './resources/svg'),
            '@img': path.resolve(__dirname, './resources/img'),
        },
    },
    plugins: [
        tailwindcss(),
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
