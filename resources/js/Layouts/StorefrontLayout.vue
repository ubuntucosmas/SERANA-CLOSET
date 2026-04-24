<template>
    <div class="min-h-screen flex flex-col bg-background selection:bg-primary selection:text-background relative overflow-hidden">
        <!-- Global Obsidian Base Layer -->
        <SectionBackground class="fixed !z-[-1]" opacity="opacity-5" :showScan="false" />

        <TopNavBar />
        <CommandPortal ref="portalRef" />
        <MobileBottomNav />
        <CartDrawer />
        <Notification />
        <PurchaseHandoff />
        <ConciergeButton />
        <RegistryPath />

        <main class="flex-grow relative z-10">
            <transition name="fade" mode="out-in" appear>
                <div :key="$page.url">
                    <slot />
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
import PurchaseHandoff from '@/Components/PurchaseHandoff.vue';
import ConciergeButton from '@/Components/ConciergeButton.vue';
import CommandPortal from '@/Components/CommandPortal.vue';
import RegistryPath from '@/Components/RegistryPath.vue';
import { useCartStore } from '@/Stores/useCartStore';

const cart = useCartStore();
const portalRef = ref(null);

onMounted(() => {
    // Cart self-initializes from localStorage in useCartStore.js
});
</script>

<style>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s ease, transform 0.5s ease;
}

.fade-enter-from {
    opacity: 0;
    transform: translateY(10px);
}

.fade-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
</style>
