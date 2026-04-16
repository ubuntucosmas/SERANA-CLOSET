<script setup>
import { Link } from '@inertiajs/vue3';
import { useCartStore } from '@/Stores/useCartStore';

const cart = useCartStore();

function isActive(routeName) {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
}
</script>

<template>
    <nav class="lg:hidden fixed bottom-10 left-1/2 -translate-x-1/2 z-[100] w-[90%] max-w-sm">
        <div class="bg-black/60 backdrop-blur-2xl border border-white/5 rounded-full px-8 py-4 flex items-center justify-between shadow-[0_20px_50px_rgba(0,0,0,0.8)]">
            
            <Link :href="route('home')" class="flex flex-col items-center gap-1 transition-all" :class="isActive('home') ? 'text-primary' : 'text-white/40'">
                <span class="material-symbols-outlined text-[24px]">home</span>
                <span class="text-[8px] font-bold uppercase tracking-widest">Home</span>
            </Link>

            <Link :href="route('shop')" class="flex flex-col items-center gap-1 transition-all" :class="isActive('shop*') ? 'text-primary' : 'text-white/40'">
                <span class="material-symbols-outlined text-[24px]">shopping_bag</span>
                <span class="text-[8px] font-bold uppercase tracking-widest">Shop</span>
            </Link>

            <!-- Floating Action: Cart -->
            <button @click="cart.isOpen = true" class="relative -mt-10 w-14 h-14 bg-primary rounded-full flex items-center justify-center shadow-[0_10px_25px_rgba(57,255,20,0.3)] group active:scale-90 transition-all">
                <span class="material-symbols-outlined text-black font-black text-[26px]">shopping_cart</span>
                <span v-if="cart.totalItems > 0" class="absolute -top-1 -right-1 bg-white text-black text-[9px] font-black w-5 h-5 rounded-full flex items-center justify-center border-2 border-primary">
                    {{ cart.totalItems }}
                </span>
            </button>

            <Link :href="route('circle')" class="flex flex-col items-center gap-1 transition-all" :class="isActive('circle') ? 'text-primary' : 'text-white/40'">
                <span class="material-symbols-outlined text-[24px]">auto_awesome</span>
                <span class="text-[8px] font-bold uppercase tracking-widest">Circle</span>
            </Link>

            <a href="https://www.tiktok.com/@serana904" target="_blank" class="flex flex-col items-center gap-1 transition-all text-white/40 hover:text-primary">
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.04-.1z"/>
                </svg>
                <span class="text-[8px] font-bold uppercase tracking-widest">TikTok</span>
            </a>

        </div>
        
        <!-- Safari Safe Area Spacer -->
        <div class="h-safe-area"></div>
    </nav>
</template>

<style scoped>
.h-safe-area {
    height: env(safe-area-inset-bottom, 0px);
}
</style>
