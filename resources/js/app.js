import '../css/app.css';
import { useCsrfToken } from './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue, route } from '../../vendor/tightenco/ziggy';

// The page carries only the route list (config/ziggy.php skips Ziggy's inline copy of
// this function); scripts call route() globally, so expose the bundled one.
globalThis.route = route;

import { createPinia } from 'pinia';
import { trackNavigation } from './Composables/useBackToShop';

const pinia = createPinia();
const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    // Pages already name the brand ("Shop | Serana Closet"); only add it when missing.
    title: (title) => (!title ? 'Serana Closet' : /serana/i.test(title) ? title : `${title} | Serana Closet`),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            pages,
        ),
    setup({ el, App, props, plugin }) {
        // Sign-in and sign-out start a new session token; every page carries the current one.
        useCsrfToken(props.initialPage.props.csrf_token);
        router.on('success', (event) => useCsrfToken(event.detail.page.props.csrf_token));
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(pinia)
            .mount(el);
    },
    progress: {
        color: '#00d2ff',
        // Only show the bar for slow visits; fast ones feel instant without it.
        delay: 250,
    },
});

trackNavigation();

// Warm the storefront pages a shopper is most likely to open next, once the
// browser is idle. Studio and auth chunks are never fetched for shoppers.
const warm = () => ['Shop', 'ProductDetail', 'Checkout', 'CustomOrder', 'TrackOrder']
    .forEach((name) => pages[`./Pages/${name}.vue`]?.().catch(() => {}));
if (typeof window !== 'undefined' && !navigator.connection?.saveData) {
    window.addEventListener('load', () => (window.requestIdleCallback || ((cb) => setTimeout(cb, 1500)))(warm), { once: true });
}
