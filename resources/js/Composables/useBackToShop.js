import { router } from '@inertiajs/vue3';

// Closing a product should feel like closing a preview: back to the exact list
// the shopper came from (same filters, sort and scroll), not a fresh /shop.
// We keep a short trail of visited URLs for this tab to know where they came from.
const KEY = 'serana_nav_trail';
let installed = false;

const readTrail = () => {
    try { return JSON.parse(sessionStorage.getItem(KEY) || '[]'); } catch { return []; }
};
const writeTrail = (trail) => {
    try { sessionStorage.setItem(KEY, JSON.stringify(trail.slice(-10))); } catch { /* storage unavailable */ }
};
const here = () => window.location.pathname + window.location.search;

/** Call once at app start. */
export function trackNavigation() {
    if (installed || typeof window === 'undefined') return;
    installed = true;
    const record = (url) => {
        const trail = readTrail();
        if (trail.at(-1) !== url) trail.push(url);
        writeTrail(trail);
    };
    record(here());
    router.on('navigate', (event) => record(event.detail.page.url));
}

// Home and the shop grid (any filters) count as "the list".
const isListing = (url) => /^\/(shop)?(\?.*)?$/.test(url || '');

export function useBackToShop() {
    function previous() {
        const trail = readTrail();
        return trail.at(-1) === here() ? trail.at(-2) : trail.at(-1);
    }

    /** Back to where they browsed from, or to `fallback` if they arrived another way. */
    function back(fallback) {
        if (isListing(previous()) && window.history.length > 1) {
            window.history.back(); // Inertia restores the list's state and scroll
            return;
        }
        router.visit(fallback);
    }

    return { back, cameFromList: () => isListing(previous()) };
}
