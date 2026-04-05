<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';
import { Link } from '@inertiajs/vue3';
import { animate, stagger } from 'animejs';

const cart = useCartStore();
const drawerRef = ref(null);
const overlayRef = ref(null);
const itemsRef = ref(null);

function formatPrice(price) {
    return 'KSh ' + Number(price).toLocaleString();
}

const hasBespokeItems = computed(() => cart.items.some(item => item.is_bespoke));

// Cinematic Animation Logic
watch(() => cart.isOpen, async (open) => {
    await nextTick();
    if (open) {
        animate(overlayRef.value, {
            opacity: [0, 1],
            duration: 400,
            easing: 'easeOutQuad'
        });

        animate(drawerRef.value, {
            translateX: ['100%', '0%'],
            duration: 600,
            easing: 'easeOutExpo'
        });

        const items = itemsRef.value?.querySelectorAll('.cart-item-anim');
        if (items?.length) {
            animate(items, {
                translateX: [50, 0],
                opacity: [0, 1],
                delay: stagger(80, { start: 200 }),
                duration: 800,
                easing: 'easeOutExpo'
            });
        }
    }
});

const closeDrawer = () => {
    animate(drawerRef.value, {
        translateX: '100%',
        duration: 400,
        easing: 'easeInQuad'
    });
    animate(overlayRef.value, {
        opacity: 0,
        duration: 300,
        easing: 'easeInQuad',
        onComplete: () => {
            cart.isOpen = false;
        }
    });
};
</script>

<template>
    <div v-if="cart.isOpen" class="fixed inset-0 z-[100] overflow-hidden flex justify-end">
        <!-- Backdrop -->
        <div 
            ref="overlayRef"
            @click="closeDrawer" 
            class="absolute inset-0 dark:bg-black bg-white/50 backdrop-blur-sm opacity-0"
        ></div>

        <!-- Semantic Shell: Mini Cart Slide-out Component -->
        <div 
            ref="drawerRef"
            class="relative z-10 w-full max-w-lg dark:bg-black bg-white border-l dark:border-white/10 border-black/10 shadow-[0_40px_100px_rgba(0,0,0,1)] flex flex-col h-full transform translate-x-full"
        >
            <!-- Header -->
            <div class="px-8 py-10 flex justify-between items-center border-b dark:border-white/5 border-black/5">
                <div>
                    <h2 class="font-headline text-2xl tracking-widest dark:text-white text-on-surface">YOUR SELECTION</h2>
                    <p class="font-label text-[10px] uppercase tracking-[0.2em] dark:text-white/70 text-black/70 mt-1">{{ cart.totalItems }} {{ cart.totalItems === 1 ? 'Item' : 'Items' }} in Bag</p>
                </div>
                <button @click="closeDrawer" class="dark:text-white text-on-surface hover:text-primary transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <!-- Scrollable Items Area -->
            <div ref="itemsRef" class="flex-grow overflow-y-auto px-8 no-scrollbar">
                <div v-if="cart.items.length === 0" class="h-full flex flex-col items-center justify-center text-center space-y-6 pt-20">
                    <span class="material-symbols-outlined text-6xl dark:text-white text-on-surface/5">shopping_bag</span>
                    <div>
                        <h3 class="font-headline text-xl dark:text-white/70 text-black/70 tracking-widest uppercase mb-2">Bag is Empty</h3>
                        <button @click="closeDrawer" class="text-[10px] font-label font-black uppercase tracking-[0.2em] text-primary hover:dark:text-white text-on-surface transition-all">Start Curating</button>
                    </div>
                </div>

                <!-- Bag Items -->
                <div v-for="item in cart.items" :key="item.id" class="cart-item-anim flex gap-6 py-8 border-b dark:border-white/5 border-black/5 opacity-0">
                    <div class="w-24 h-32 flex-shrink-0 bg-surface-container overflow-hidden border dark:border-white/5 border-black/5">
                        <img :src="item.image" :alt="item.name" class="w-full h-full object-cover grayscale hover:grayscale-0 transition-all duration-500" />
                    </div>
                    <div class="flex flex-grow flex-col justify-between py-1">
                        <div>
                            <div class="flex justify-between items-start">
                                <h3 class="font-headline text-lg dark:text-white text-on-surface pr-4">{{ item.name }}</h3>
                                <span class="font-body text-sm font-black text-primary shrink-0">{{ formatPrice(item.price * item.quantity) }}</span>
                            </div>
                            <p v-if="item.is_bespoke || item.is_customizable" class="font-label text-[10px] text-primary uppercase tracking-widest mt-1">CUSTOM TAILORING</p>
                            <p v-else class="font-label text-[10px] dark:text-white/70 text-black/70 uppercase tracking-widest mt-1">READY-TO-WEAR</p>
                            
                            <p v-if="item.selected_size || item.selected_color" class="font-body text-xs dark:text-white/70 text-black/70 mt-2 leading-relaxed">
                                <span v-if="item.selected_size">Size: {{ item.selected_size }}</span>
                                <span v-if="item.selected_size && item.selected_color"> | </span>
                                <span v-if="item.selected_color">Color: {{ item.selected_color }}</span>
                            </p>
                            <p v-else-if="item.is_bespoke" class="font-body text-xs dark:text-white/70 text-black/70 mt-2 leading-relaxed">Made to measure to your exact dimensions.</p>
                        </div>
                        <div class="flex justify-between items-end mt-4">
                            <div class="flex items-center gap-4 text-xs font-label">
                                <span class="dark:text-white/70 text-black/70 uppercase">Qty</span>
                                <div class="flex items-center gap-3 border dark:border-white/10 border-black/10 px-3 py-1">
                                    <button @click="cart.updateQuantity(item.id, -1)" class="hover:text-primary dark:text-white text-on-surface transition-colors">-</button>
                                    <span class="dark:text-white text-on-surface">{{ item.quantity }}</span>
                                    <button @click="cart.updateQuantity(item.id, 1)" class="hover:text-primary dark:text-white text-on-surface transition-colors">+</button>
                                </div>
                            </div>
                            <button @click="cart.removeItem(item.id)" class="text-[10px] font-label uppercase tracking-widest dark:text-white/70 text-black/70 hover:text-error transition-colors">Remove</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Checkout Footer Section -->
            <div v-if="cart.items.length > 0" class="px-8 py-10 bg-[#050505] border-t dark:border-white/5 border-black/5">
                <div class="space-y-3 mb-8">
                    <div class="flex justify-between font-body text-sm dark:text-white/70 text-black/70">
                        <span>Subtotal</span>
                        <span class="dark:text-white text-on-surface">{{ formatPrice(cart.totalPrice) }}</span>
                    </div>
                    <div class="flex justify-between font-body text-sm dark:text-white/70 text-black/70">
                        <span>Shipping</span>
                        <span class="text-primary uppercase text-[10px] tracking-widest">Complimentary</span>
                    </div>
                    <div class="pt-4 border-t dark:border-white/5 border-black/5 flex justify-between items-end">
                        <span class="font-headline text-lg dark:text-white text-on-surface">Total</span>
                        <span class="font-headline text-2xl text-primary">{{ formatPrice(cart.totalPrice) }}</span>
                    </div>
                </div>

                <!-- Logistics Info -->
                <div v-if="hasBespokeItems" class="mb-8 flex gap-3 p-4 bg-white/5 border dark:border-white/5 border-black/5">
                    <span class="material-symbols-outlined text-primary text-xl">auto_awesome</span>
                    <div class="font-body text-xs leading-relaxed">
                        <p class="font-semibold dark:text-white text-on-surface">Estimated Delivery for Custom Pieces</p>
                        <p class="dark:text-white/70 text-black/70 mt-1">Due to the bespoke nature of your tailored pieces, please allow 14–21 business days for master tailoring and delivery.</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex flex-col gap-4">
                    <Link 
                        :href="route('checkout')"
                        @click="closeDrawer"
                        class="w-full bg-primary text-black py-4 font-label text-xs uppercase tracking-[0.2em] font-bold hover:brightness-110 transition-all text-center block"
                    >
                        Proceed to Secure Checkout
                    </Link>
                    <a 
                        href="https://wa.me/254700000000" 
                        target="_blank"
                        class="w-full flex items-center justify-center gap-2 border dark:border-white/10 border-black/10 py-4 font-label text-xs uppercase tracking-[0.2em] dark:text-white text-on-surface hover:bg-white/5 transition-colors"
                    >
                        <span class="material-symbols-outlined text-lg">chat</span>
                        Quick Order via WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
