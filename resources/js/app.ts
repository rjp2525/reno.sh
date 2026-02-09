import '../css/app.css';
import './bootstrap';

import { createInertiaApp, Head, Link, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';

// Prevent Inertia from showing raw HTML error modals for non-Inertia responses
router.on('invalid', (event) => {
    event.preventDefault();
});

const appName = import.meta.env.VITE_APP_NAME || 'Reno Philibert';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createSSRApp({ render: () => h(App, props) })
            .use(plugin)
            .component('Link', Link)
            .component('Head', Head)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
