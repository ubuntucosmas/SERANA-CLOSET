<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/useCartStore';

const cart = useCartStore();
const page = usePage();

function isActive(routeName) {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
}

// Home and Shop on the left, a labelled bag in the centre, Custom and Track on the
// right. Drops, when switched on, takes Track's slot (Track stays in the menu
// and footer). Account lives in the desktop header and mobile menu.
const left = [
    { label: 'Home', icon: 'home', route: 'home', active: 'home' },
    { label: 'Shop', icon: 'storefront', route: 'shop', active: 'shop*' },
];
const right = computed(() => [
    { label: 'Custom', icon: 'content_cut', route: 'custom-order', active: 'custom-order*' },
    page.props.drops_enabled
        ? { label: 'Drops', icon: 'bolt', route: 'drops', active: 'drops*' }
        : { label: 'Track', icon: 'local_shipping', route: 'track', active: 'track' },
]);
</script>

<template>
    <nav aria-label="Quick links" class="mobile-quick-nav lg:hidden fixed inset-x-3 z-[100] mx-auto max-w-md">
        <div class="quick-nav-grid">
            <Link v-for="item in left" :key="item.route" :href="route(item.route)" prefetch class="bottom-link" :class="{ 'is-active': isActive(item.active) }" :aria-current="isActive(item.active) ? 'page' : undefined">
                <span class="material-symbols-outlined" aria-hidden="true">{{ item.icon }}</span>
                <span>{{ item.label }}</span>
            </Link>
            <button type="button" :aria-label="`Open bag, ${cart.totalItems} ${cart.totalItems === 1 ? 'piece' : 'pieces'}`" @click="cart.isOpen = true" class="bottom-link" :class="{ 'is-active': cart.isOpen }" :aria-expanded="cart.isOpen">
                <span class="relative inline-flex"><span class="material-symbols-outlined" aria-hidden="true">shopping_bag</span><span v-if="cart.totalItems > 0" class="bag-count">{{ cart.totalItems > 99 ? '99+' : cart.totalItems }}</span></span>
                <span>Bag</span>
            </button>
            <Link v-for="item in right" :key="item.route" :href="route(item.route)" prefetch class="bottom-link" :class="{ 'is-active': isActive(item.active) }" :aria-current="isActive(item.active) ? 'page' : undefined">
                <span class="material-symbols-outlined" aria-hidden="true">{{ item.icon }}</span>
                <span>{{ item.label }}</span>
            </Link>
        </div>
    </nav>
</template>

<style scoped>
.mobile-quick-nav { bottom: calc(12px + env(safe-area-inset-bottom, 0px)); }
.quick-nav-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 2px; padding: 5px; border-radius: 20px; border: 1px solid var(--card-border); background: var(--bg-glass); backdrop-filter: blur(24px); box-shadow: 0 8px 28px #0000001f; }
.bottom-link { display: flex; min-width: 0; min-height: 52px; flex-direction: column; align-items: center; justify-content: center; gap: 4px; padding: 5px 2px; border-radius: 14px; font-size: 11px; font-weight: 600; line-height: 1.2; text-align: center; color: var(--on-surface-variant); transition: color .15s, background-color .15s; }
.bottom-link > span:last-child { white-space: nowrap; }
.bottom-link .material-symbols-outlined { font-size: 22px; line-height: 1; }
.bottom-link:hover, .bottom-link.is-active { color: var(--primary); background: var(--tint-bg); }
.bottom-link.is-active .material-symbols-outlined { font-variation-settings: 'FILL' 1, 'wght' 400; }
.bag-count { position: absolute; top: -5px; right: -10px; display: flex; align-items: center; justify-content: center; min-width: 16px; height: 16px; padding: 0 3px; border-radius: 8px; background: var(--primary); color: var(--background); font-size: 10px; font-weight: 700; line-height: 1; }
</style>
