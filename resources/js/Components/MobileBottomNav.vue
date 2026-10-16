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

// Home and Shop on the left, the bag in the centre, Custom Order and Track on the
// right. Drops, when switched on, takes Track's slot (Track stays in the menu
// and footer). Account lives in the header and menu.
const left = [
    { label: 'Home', icon: 'home', route: 'home', active: 'home' },
    { label: 'Shop', icon: 'storefront', route: 'shop', active: 'shop*' },
];
const right = computed(() => [
    { label: 'Custom Order', icon: 'content_cut', route: 'custom-order', active: 'custom-order*' },
    page.props.drops_enabled
        ? { label: 'Drops', icon: 'bolt', route: 'drops', active: 'drops*' }
        : { label: 'Track', icon: 'local_shipping', route: 'track', active: 'track' },
]);
</script>

<template>
    <nav aria-label="Quick links" class="always-dark lg:hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-[100] w-[94%] max-w-sm">
        <div class="bg-black/80 backdrop-blur-3xl border border-white/5 rounded-full px-3 py-1.5 grid grid-cols-5 items-center shadow-[0_20px_50px_rgba(0,0,0,0.8)]">
            <Link v-for="item in left" :key="item.route" :href="route(item.route)" prefetch class="bottom-link" :class="isActive(item.active) ? 'text-primary' : 'text-white/75'" :aria-current="isActive(item.active) ? 'page' : undefined">
                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">{{ item.icon }}</span>
                <span>{{ item.label }}</span>
            </Link>

            <button :aria-label="`Open bag, ${cart.totalItems} ${cart.totalItems === 1 ? 'piece' : 'pieces'}`" @click="cart.isOpen = true" class="relative -mt-8 mx-auto w-14 h-14 bg-primary rounded-full flex items-center justify-center shadow-[0_10px_25px_rgb(var(--primary-rgb)/0.35)] active:scale-90 transition-transform">
                <span class="material-symbols-outlined text-black text-[26px]" aria-hidden="true">shopping_bag</span>
                <span v-if="cart.totalItems > 0" :key="cart.totalItems" class="bag-pop absolute -top-1 -right-1 bg-white text-black text-[11px] font-bold min-w-5 h-5 px-1 rounded-full flex items-center justify-center border-2 border-primary">
                    {{ cart.totalItems }}
                </span>
            </button>

            <Link v-for="item in right" :key="item.route" :href="route(item.route)" prefetch class="bottom-link" :class="isActive(item.active) ? 'text-primary' : 'text-white/75'" :aria-current="isActive(item.active) ? 'page' : undefined">
                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">{{ item.icon }}</span>
                <span>{{ item.label }}</span>
            </Link>
        </div>

        <!-- Safari Safe Area Spacer -->
        <div class="h-safe-area"></div>
    </nav>
</template>

<style scoped>
.bottom-link {
    display: flex;
    min-height: 48px;
    flex-direction: column;
    align-items: center;
    /* Top-aligned so icons stay level when "Custom Order" wraps to two lines. */
    justify-content: flex-start;
    padding-top: 6px;
    gap: 2px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.1;
    text-align: center;
    transition: color 0.15s;
}
.h-safe-area {
    height: env(safe-area-inset-bottom, 0px);
}
/* The count pops when it changes, so adding to the bag is felt, not just shown. */
.bag-pop {
    animation: bag-pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes bag-pop {
    from { transform: scale(0.4); }
    to { transform: scale(1); }
}
</style>
