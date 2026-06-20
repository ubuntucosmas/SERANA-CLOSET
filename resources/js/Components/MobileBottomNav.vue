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
    <nav class="lg:hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-[100] w-[94%] max-w-sm">
        <div class="bg-black/80 backdrop-blur-3xl border border-white/5 rounded-full px-5 py-2.5 flex items-center justify-between shadow-[0_20px_50px_rgba(0,0,0,0.8)]">
            
            <Link :href="route('home')" class="flex flex-col items-center gap-0.5 transition-all" :class="isActive('home') ? 'text-primary' : 'text-white/40'">
                <span class="material-symbols-outlined text-[22px]">home</span>
                <span class="text-[8px] font-bold tracking-widest">Home</span>
            </Link>

            <Link :href="route('shop')" class="flex flex-col items-center gap-1 transition-all" :class="isActive('shop*') ? 'text-primary' : 'text-white/40'">
                <span class="material-symbols-outlined text-[24px]">shopping_bag</span>
                <span class="text-[8px] font-bold tracking-widest">Shop</span>
            </Link>

            <!-- Floating Action: Cart -->
            <button @click="cart.isOpen = true" class="relative -mt-10 w-14 h-14 bg-primary rounded-full flex items-center justify-center shadow-[0_10px_25px_rgba(57,255,20,0.3)] group active:scale-90 transition-all">
                <span class="material-symbols-outlined text-black font-black text-[26px]">shopping_cart</span>
                <span v-if="cart.totalItems > 0" class="absolute -top-1 -right-1 bg-on-surface text-background text-[9px] font-black w-5 h-5 rounded-full flex items-center justify-center border-2 border-primary">
                    {{ cart.totalItems }}
                </span>
            </button>

            <Link :href="route('circle')" class="flex flex-col items-center gap-1 transition-all" :class="isActive('circle') ? 'text-primary' : 'text-white/40'">
                <span class="material-symbols-outlined text-[24px]">auto_awesome</span>
                <span class="text-[8px] font-bold tracking-widest">Circle</span>
            </Link>

            <Link :href="route('drops')" class="flex flex-col items-center gap-1 transition-all" :class="isActive('drops*') ? 'text-primary' : 'text-white/40'">
                <span class="material-symbols-outlined text-[24px]">bolt</span>
                <span class="text-[8px] font-bold tracking-widest">Drops</span>
            </Link>

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
