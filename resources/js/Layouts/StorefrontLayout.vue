<template>
    <div class="min-h-screen flex flex-col bg-background selection:bg-primary selection:text-background relative overflow-hidden">
        <!-- Global Background -->
        <SectionBackground class="fixed !z-[-1]" opacity="opacity-5" :showScan="false" />
        
        <TopNavBar />
        <CommandPortal ref="portalRef" />
        <MobileBottomNav />
        <CartDrawer />
        <Notification />
        <ConciergeButton />
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
import { onMounted, ref } from 'vue';
import TopNavBar from '@/Components/TopNavBar.vue';
import AppFooter from '@/Components/Footer.vue';
import MobileBottomNav from '@/Components/MobileBottomNav.vue';
import CartDrawer from '@/Components/CartDrawer.vue';
import SectionBackground from '@/Components/SectionBackground.vue';
import ConciergeButton from '@/Components/ConciergeButton.vue';
import CommandPortal from '@/Components/CommandPortal.vue';
import SitePath from '@/Components/SitePath.vue';
import Notification from '@/Components/Notification.vue';
import { useCartStore } from '@/Stores/useCartStore';
import { animate, stagger } from 'animejs';

const props = defineProps({
    hideMobileCard: { type: Boolean, default: false }
});

const cart = useCartStore();
const portalRef = ref(null);

onMounted(() => {
    // Cart self-initializes from localStorage in useCartStore.js
});

const pageEnter = (el, done) => {
    // Main container slide up with bounce
    animate(el, {
        opacity: [0, 1],
        translateY: ['-110%', 0],
        easing: 'bounce.out',
        duration: 1200,
        onComplete: () => {
            // Then animate text words with stagger
            const splitElements = el.querySelectorAll('.split-text');
            if (splitElements.length > 0) {
                animate(splitElements, {
                    opacity: [0, 1],
                    translateY: [30, 0],
                    delay: stagger(30, { start: 200 }),
                    easing: 'easeOutCubic',
                    duration: 600,
                    onComplete: done
                });
            } else {
                done();
            }
        }
    });
};

const pageLeave = (el, done) => {
    animate(el, {
        opacity: [1, 0],
        translateY: [0, 20],
        easing: 'easeInExpo',
        duration: 400,
        onComplete: done
    });
};
</script>

