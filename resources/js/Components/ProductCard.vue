<script setup>
import { ref, computed, onMounted } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';
import { Link } from '@inertiajs/vue3';
import XRayOverlay from '@/Components/XRayOverlay.vue';
import { animate } from 'animejs';

const cart = useCartStore();
const isHovered = ref(false);
const cardElement = ref(null);

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    phoneNumber: {
        type: String,
        default: '254700000000',
    },
    layout: {
        type: String,
        default: 'grid' // 'grid' or 'editorial'
    }
});

const handleHover = (hovering) => {
    isHovered.value = hovering;
    
    // Smooth Parallax Image Shift
    animate(cardElement.value?.querySelector('.product-img'), {
        scale: hovering ? 1.05 : 1,
        translateX: hovering ? 10 : 0,
        easing: 'easeOutExpo',
        duration: 1000
    });

    // Content Slide
    animate(cardElement.value?.querySelector('.overlay-content'), {
        translateY: hovering ? [20, 0] : [0, 20],
        opacity: hovering ? [0, 1] : [1, 0],
        easing: 'easeOutExpo',
        duration: 800
    });
};

const whatsappUrl = `https://wa.me/${props.phoneNumber}?text=${encodeURIComponent(
    `Hello! I'm interested in: ${props.product.name} (KSh ${Number(props.product.price).toLocaleString()}). Can you help me order?`
)}`;
const isSoldOut = computed(() => props.product.batch_limit && props.product.batch_sold >= props.product.batch_limit);
const isLimitedDrop = computed(() => props.product.batch_limit !== null && props.product.batch_limit > 0 && props.product.batch_limit <= 30);
</script>

<template>
    <div 
        ref="cardElement"
        :class="[
            'group relative transition-all duration-700',
            layout === 'editorial' ? 'h-[600px] lg:h-[800px]' : ''
        ]"
        @mouseenter="handleHover(true)"
        @mouseleave="handleHover(false)"
    >
        <!-- Image Container -->
        <div :class="[
            'overflow-hidden bg-transparent relative rounded-sm border dark:border-white/10 border-black/10 shadow-2xl transition-all duration-700',
            layout === 'editorial' ? 'h-full mb-0' : 'aspect-[3/4] mb-6'
        ]">
            <img
                class="product-img w-full h-full object-cover transition-transform duration-1000"
                :src="product.image_url || '/images/hero_editorial.png'"
                :alt="product.name"
                loading="lazy"
            />
            
            <!-- Technical X-Ray Overlay -->
            <XRayOverlay :specs="product.specifications" :active="isHovered" />

            <!-- Editorial/Grid Hover Overlay -->
            <div :class="[
                'overlay-content absolute inset-0 flex flex-col justify-end p-8 gap-4 z-30 pointer-events-none',
                layout === 'editorial' 
                    ? 'bg-gradient-to-t from-black via-black/80 to-transparent' 
                    : 'dark:bg-surface/80 bg-surface/80 backdrop-blur-sm'
            ]" :style="layout === 'grid' ? 'opacity: 0' : ''">
                
                <div v-if="layout === 'editorial'" class="space-y-3 mb-6">
                    <p class="text-primary font-headline text-[11px] font-medium tracking-[0.3em]">{{ product.category?.name || 'Collection' }}</p>
                    <h3 class="text-5xl lg:text-7xl font-headline font-medium dark:text-white text-on-surface dark:text-white text-on-surface leading-none drop-shadow-2xl">{{ product.name }}</h3>
                    <p class="text-3xl font-headline font-medium dark:text-white text-on-surface dark:text-white text-on-surface">KSh {{ Number(product.price).toLocaleString() }}</p>
                </div>

                <div class="flex flex-col gap-4 pointer-events-auto">
                    <button 
                        @click="cart.addItem(product)"
                        class="w-full bg-gradient-to-br from-primary to-primary-container text-black py-5 text-[12px] font-headline font-medium tracking-[0.2em] shadow-[0_0_25px_rgba(57, 255, 20,0.4)] flex items-center justify-center gap-3 rounded-sm hover:scale-105 active:scale-95 transition-all"
                    >
                        <span class="material-symbols-outlined text-[18px]">shopping_bag</span>
                        Add to bag
                    </button>
                    <Link 
                        :href="route('shop.show', product.slug)"
                        class="w-full glass-card border dark:border-white/10 border-black/10 dark:text-white text-on-surface dark:text-white text-on-surface py-5 text-[12px] font-headline font-medium tracking-[0.2em] flex items-center justify-center gap-2 rounded-sm hover:bg-primary/10 transition-all"
                    >
                        <span class="material-symbols-outlined text-[18px]">open_in_full</span>
                        View spec sheet
                    </Link>
                </div>
            </div>

            <!-- Limited Drop Badge -->
            <div v-if="isLimitedDrop && !isSoldOut" class="absolute top-4 right-4 z-10">
                <span class="bg-amber-500/90 backdrop-blur-sm text-black px-2.5 py-1 text-[10px] font-medium tracking-widest rounded-md shadow-lg">Limited Drop</span>
            </div>

            <!-- Sold Out Overlay -->
            <div v-if="isSoldOut" class="absolute inset-0 z-20 dark:bg-black/60 bg-white/60 backdrop-blur-[2px] flex items-center justify-center">
                <span class="dark:text-white/60 text-black/60 text-xs font-medium tracking-[0.3em] uppercase border dark:border-white/20 border-black/20 px-4 py-2 rounded-sm">Sold Out</span>
            </div>

            <!-- Custom Badge -->
            <div v-if="product.is_customizable && !isSoldOut" class="absolute top-4 left-4 z-10">
                <span class="dark:bg-black/60 bg-white/60 backdrop-blur-2xl border border-primary/40 text-primary px-5 py-2.5 text-[10.5px] font-headline font-medium tracking-[0.25em] rounded-sm shadow-2xl">Custom</span>
            </div>
        </div>

        <!-- Product Info (Grid only) -->
        <div v-if="layout === 'grid'" class="mt-8 px-1">
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-3">
                    <p class="text-[9px] font-bold tracking-[0.3em] text-primary uppercase">{{ product.category?.name || 'Archive' }}</p>
                    <div class="w-1 h-1 rounded-full bg-white/10"></div>
                    <p class="text-[9px] font-bold tracking-[0.3em] dark:text-white/40 text-black/40 uppercase">Edition #{{ product.id }}</p>
                </div>
                <div class="flex justify-between items-end">
                    <h3 class="serif-text text-2xl font-light dark:text-white text-on-surface">{{ product.name }}</h3>
                    <p class="text-sm font-bold tracking-widest text-[#B9C3FF]">KSh {{ Number(product.price).toLocaleString() }}</p>
                </div>
            </div>

            <!-- Mobile persistent CTA -->
            <button
                v-if="!isSoldOut"
                @click="cart.addItem(product)"
                class="md:hidden w-full mt-6 bg-primary/5 border border-primary/20 text-primary py-4 text-[10px] font-black tracking-[0.2em] uppercase rounded-sm active:bg-primary active:text-black transition-all"
            >
                Add to Bag
            </button>
        </div>
    </div>
</template>

<style scoped>
.text-glow-after {
    transition: text-shadow 0.3s ease;
}
.group:hover .text-glow-after {
    text-shadow: 0 0 10px rgba(57, 255, 20,0.4);
}
</style>
