<template>
    <div class="min-h-screen flex flex-col bg-background selection:bg-primary selection:text-background relative overflow-hidden">
        <!-- Global Background -->
        <SectionBackground class="fixed !z-[-1]" opacity="opacity-5" :showScan="false" />
        
        <TopNavBar />
        <MobileBottomNav />
        <CartDrawer />
        <Notification />
        <SitePath />

        <main class="flex-grow relative z-10">
            <transition @enter="pageEnter" @leave="pageLeave" :css="false" mode="out-in" appear>
                <div :key="$page.url" :class="!hideMobileCard ? 'px-0 pt-16 pb-20 md:p-0 md:pt-0 md:pb-0' : ''">
                    <div :class="!hideMobileCard ? 'mobile-floating-card md:!bg-transparent md:!border-none md:!shadow-none md:!p-0 md:!m-0' : ''">
                        <slot />
                    </div>
                </div>
            </transition>
        </main>

        <AppFooter class="relative z-10" />
    </div>
</template>

<script setup>
import TopNavBar from '@/Components/TopNavBar.vue';
import AppFooter from '@/Components/Footer.vue';
import MobileBottomNav from '@/Components/MobileBottomNav.vue';
import CartDrawer from '@/Components/CartDrawer.vue';
import SectionBackground from '@/Components/SectionBackground.vue';
import SitePath from '@/Components/SitePath.vue';
import Notification from '@/Components/Notification.vue';
import { animate } from 'animejs';

// Cart self-initializes from localStorage in useCartStore.js
defineProps({
    hideMobileCard: { type: Boolean, default: false }
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
        translateY: [12, 0],
        easing: 'easeOutQuad',
        duration: 420,
        onComplete: done,
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
        duration: 220,
        onComplete: done,
    });
};
</script>

