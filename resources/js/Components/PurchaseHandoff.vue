<script setup>
import { computed } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';

const cart = useCartStore();

const lastItem = computed(() => cart.items[cart.items.length - 1]);

const close = () => {
    cart.showHandoff = false;
};

const goToWhatsApp = () => {
    // Generate WhatsApp link for the specific item or full cart
    const number = "254113167266";
    const item = lastItem.value;
    const text = encodeURIComponent(
        `◆ PIECE SECURED ◆\n\n` +
        `Ref: ${item.name}\n` +
        `Price: ${item.display_price}\n` +
        `Link: ${window.location.origin}/product/${item.slug}\n\n` +
        `I am ready to finalize this acquisition.`
    );
    window.open(`https://wa.me/${number}?text=${text}`, '_blank');
    close();
};
</script>

<template>
    <transition name="handoff">
        <div v-if="cart.showHandoff && lastItem" class="fixed inset-0 z-[100] flex items-center justify-center px-6">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-background/80 backdrop-blur-xl" @click="close"></div>

            <!-- Panel -->
            <div class="relative w-full max-w-md bg-surface border border-primary/20 rounded-2xl shadow-2xl overflow-hidden anime-reveal">
                <div class="p-8 space-y-8">
                    <!-- Header -->
                    <div class="flex justify-between items-start">
                        <div class="space-y-1">
                            <p class="text-[9px] font-headline font-bold text-primary tracking-[0.4em] uppercase">Status: In Bag</p>
                            <h2 class="text-2xl font-headline font-medium dark:text-white text-on-surface uppercase tracking-tighter">Bespoke Handoff_</h2>
                        </div>
                        <button @click="close" class="p-2 dark:text-white/40 text-black/40 hover:text-primary transition-colors">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <!-- Item Preview -->
                    <div class="flex gap-6 items-center bg-white/[0.03] p-4 rounded-xl border border-white/5">
                        <div class="w-20 aspect-[3/4] rounded-lg overflow-hidden border border-white/10 shrink-0">
                            <img :src="lastItem.image" class="w-full h-full object-cover" />
                        </div>
                        <div class="space-y-2">
                            <h3 class="text-sm font-headline font-medium dark:text-white text-on-surface uppercase">{{ lastItem.name }}</h3>
                            <p class="text-xs font-headline font-black text-primary">{{ lastItem.display_price }}</p>
                        </div>
                    </div>

                    <!-- CTAs -->
                    <div class="space-y-4 pt-4">
                        <button 
                            @click="goToWhatsApp"
                            class="w-full bg-primary text-background py-5 rounded-xl font-headline text-[10px] font-black uppercase tracking-[0.2em] hover:bg-white transition-all shadow-[0_0_20px_rgba(182,143,101,0.3)] flex items-center justify-center gap-3"
                        >
                            <span class="material-symbols-outlined text-sm">bolt</span>
                            Quick Checkout via WhatsApp
                        </button>
                        
                        <button 
                            @click="close"
                            class="w-full bg-white/[0.05] dark:text-white text-on-surface py-5 rounded-xl font-headline text-[10px] font-bold uppercase tracking-[0.2em] border border-white/5 hover:bg-white/10 transition-all"
                        >
                            Continue Curation
                        </button>
                    </div>

                    <p class="text-center text-[8px] font-sans font-medium uppercase tracking-[0.4em] dark:text-white/20 text-black/20">
                        Nairobi · Atelier Standard · {{ new Date().getFullYear() }}
                    </p>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.handoff-enter-active, .handoff-leave-active {
    transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
.handoff-enter-from {
    opacity: 0;
    transform: scale(0.9) translateY(20px);
}
.handoff-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>
