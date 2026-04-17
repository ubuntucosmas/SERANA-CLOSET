<script setup>
import { computed } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';
import { usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const page = usePage();
const { formatAmount } = useCurrency();
const cart = useCartStore();

const lastItem = computed(() => cart.items[cart.items.length - 1]);

const close = () => {
    cart.showHandoff = false;
};

const goToWhatsApp = () => {
    // Generate WhatsApp link for the specific item or full cart
    const number = page.props.theme_settings?.whatsapp_number || page.props.whatsapp_number;
    const item = lastItem.value;
    const text = encodeURIComponent(
        `◆ ITEM SECURED ◆\n\n` +
        `Ref: ${item.name}\n` +
        `Price: ${formatAmount(item.price, page.props)}\n` +
        `Link: ${window.location.origin}/product/${item.slug}\n\n` +
        `I am ready to buy this item.`
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
                            <h2 class="text-2xl font-headline font-medium dark:text-white text-on-surface uppercase tracking-tighter">Checkout_</h2>
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
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" style="fill:#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.57-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            Quick Checkout via WhatsApp
                        </button>
                        
                        <button 
                            @click="close"
                            class="w-full bg-white/[0.05] dark:text-white text-on-surface py-5 rounded-xl font-headline text-[10px] font-bold uppercase tracking-[0.2em] border border-white/5 hover:bg-white/10 transition-all"
                        >
                            Continue Shopping
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
