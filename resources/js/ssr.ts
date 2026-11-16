import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';

const appName = import.meta.env.VITE_APP_NAME || 'Reno Philibert';

declare const process: { env: Record<string, string | undefined> };
// Must match config('inertia.ssr.url'). Baked in at build time from VITE_SSR_PORT; SSR_PORT overrides at runtime.
const ssrPort = Number(process.env.SSR_PORT || import.meta.env.VITE_SSR_PORT || 13715);

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: (title) => `${title} - ${appName}`,
            resolve: (name) =>
                resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'),
                ),
            setup({ App, props, plugin }) {
                return createSSRApp({ render: () => h(App, props) })
                    .use(plugin)
                    .component('Link', Link)
                    .component('Head', Head);
            },
        }),
    ssrPort,
);
