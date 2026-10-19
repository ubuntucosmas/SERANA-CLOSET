<template>
    <SiteSeo />
    <div class="relative flex min-h-screen flex-col overflow-x-clip bg-background selection:bg-primary selection:text-background">
        <!-- Global Background -->
        <SectionBackground class="fixed !z-[-1]" opacity="opacity-5" :showScan="false" />
        
        <TopNavBar />
        <MobileBottomNav v-if="!focus" />
        <CartDrawer />
        <Notification />

        <!-- The header is sticky and in flow, so pages start right below it;
             .nav-clearance adds the shared top breathing gap (flushTop opts out). -->
        <main class="flex-grow relative z-10" :class="flushTop ? '' : 'nav-clearance'">
            <SitePath />
            <transition @enter="pageEnter" @leave="pageLeave" :css="false" mode="out-in" appear>
                <!-- Pages own their gutters via .page-container; pb-20 keeps the
                     last content clear of the mobile bottom nav. -->
                <div :key="$page.url" :class="focus ? '' : 'pb-24 lg:pb-0'">
                    <slot />
                </div>
            </transition>
        </main>

        <AppFooter class="relative z-10" :compact="focus" />
    </div>
</template>

<script setup>
import SiteSeo from '@/Components/SiteSeo.vue';
import TopNavBar from '@/Components/TopNavBar.vue';
import AppFooter from '@/Components/Footer.vue';
import MobileBottomNav from '@/Components/MobileBottomNav.vue';
import CartDrawer from '@/Components/CartDrawer.vue';
import SectionBackground from '@/Components/SectionBackground.vue';
import SitePath from '@/Components/SitePath.vue';
import Notification from '@/Components/Notification.vue';
import { animate } from 'animejs';
import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/useCartStore';

// The bag lives in a store that outlives pages; any navigation (a link, the
// Back button) closes it so it never reappears over the next page.
const cart = useCartStore();
let stopNav = null;
// Inertia also fires "start" for hover prefetches: ignore those, or touching
// the bag's Checkout link as the drawer slides in would shut it instantly.
onMounted(() => {
    stopNav = router.on('start', (event) => {
        if (event.detail.visit.prefetch || !cart.isOpen) return;
        cart.isOpen = false;
        document.body.style.overflow = '';
    });
});
onUnmounted(() => stopNav?.());

// Cart self-initializes from localStorage in useCartStore.js
defineProps({
    // Checkout: no bottom bar, no newsletter. Nothing competes with paying.
    focus: { type: Boolean, default: false },
    // Full-bleed hero pages (Home, About) render under the transparent nav
    // and skip the standard nav clearance.
    flushTop: { type: Boolean, default: false },
});

const prefersReducedMotion =
    typeof window !== 'undefined' &&
    window.matchMedia &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Editorial restraint: a quiet fade + a few px of rise. No bounce, no slide.
const pageEnter = (el, done) => {
    if (prefersReducedMotion) {
        el.style.opacity = '1';
        done();
        return;
    }
    animate(el, {
        opacity: [0, 1],
        translateY: [8, 0],
        easing: 'easeOutQuad',
        duration: 260,
        // A leftover transform would make position:fixed children anchor to
        // this wrapper instead of the screen.
        onComplete: () => { el.style.transform = ''; done(); },
    });
};

const pageLeave = (el, done) => {
    if (prefersReducedMotion) {
        done();
        return;
    }
    animate(el, {
        opacity: [1, 0],
        translateY: [0, 6],
        easing: 'easeInQuad',
        duration: 140,
        onComplete: done,
    });
};
</script>

