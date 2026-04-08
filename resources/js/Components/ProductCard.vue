<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';
import { Link, usePage } from '@inertiajs/vue3';
import XRayOverlay from '@/Components/XRayOverlay.vue';
import { animate } from 'animejs';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    layout: {
        type: String,
        default: 'grid' // 'grid' or 'editorial'
    }
});

const page = usePage();
const cart = useCartStore();
const isHovered = ref(false);
const cardElement = ref(null);
const currentIndex = ref(0);
let hoverInterval = null;

const allImages = computed(() => {
    const images = [props.product.image_url];
    if (props.product.secondary_image_urls) {
        images.push(...props.product.secondary_image_urls.filter(Boolean));
    }
    return images;
});

const handleHover = (hovering) => {
    isHovered.value = hovering;
    
    if (hovering && allImages.value.length > 1) {
        hoverInterval = setInterval(() => {
            currentIndex.value = (currentIndex.value + 1) % allImages.value.length;
        }, 1500);
    } else {
        if (hoverInterval) clearInterval(hoverInterval);
        currentIndex.value = 0;
    }

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

const whatsappUrl = computed(() => {
    const baseUrl = window.location.origin;
    const imageUrl = props.product.image_url.startsWith('http') ? props.product.image_url : `${baseUrl}/${props.product.image_url.replace(/^\//, '')}`;
    
    const message = `🏁 *NEW ARTISAN BRIEF* 🏁\n\n` +
                    `*[ 01: PIECE ]*\n` +
                    `• Name: ${props.product.name}\n` +
                    `• Collection: ${props.product.category?.name || 'Serana Archive'}\n` +
                    `• Price: KSh ${Number(props.product.price).toLocaleString()}\n\n` +
                    `*[ 02: REFERENCE ]*\n` +
                    `• URL: ${route('shop.show', props.product.slug)}\n` +
                    `• Image: ${imageUrl}\n\n` +
                    `--------------------------\n` +
                    `Can you help me secure this piece?\n` +
                    `Sent via Serana Digital Atelier.`;

    return `https://wa.me/${page.props.whatsapp_number}?text=${rawurlencode(message)}`;
});

const rawurlencode = (str) => {
    return encodeURIComponent(str).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
};
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
                class="product-img w-full h-full object-cover transition-all duration-700"
                :src="allImages[currentIndex] || '/images/hero_editorial.png'"
                :alt="product.name"
                loading="lazy"
                :class="{ 'opacity-80': isHovered }"
            />
            
            <!-- Technical X-Ray Overlay -->
            <XRayOverlay :specs="product.specifications" :active="isHovered" />

            <!-- Editorial/Grid Hover Overlay -->
            <div :class="[
                'overlay-content absolute inset-0 flex flex-col justify-end p-8 gap-4 z-30 pointer-events-none opacity-0',
                layout === 'editorial' 
                    ? 'bg-gradient-to-t from-black via-black/80 to-transparent' 
                    : 'bg-black/60 backdrop-blur-md'
            ]">
                
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

            <!-- Multi-Image Indicator -->
            <div v-if="allImages.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity duration-700">
                <div v-for="(_, i) in allImages" :key="i" 
                     class="w-1 h-1 rounded-full transition-all duration-300"
                     :class="currentIndex === i ? 'bg-primary shadow-[0_0_5px_#39FF14] scale-125' : 'bg-white/40'">
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

        <!-- Cinematic Grand Gallery Tooltip (Universal Pop) -->
        <div v-if="isHovered && allImages.length >= 1" 
             class="absolute -top-80 left-1/2 -translate-x-1/2 z-[100] pointer-events-none px-2 animate-tooltip-pop min-w-[300px] flex justify-center">
            <div class="bg-black/90 backdrop-blur-3xl border border-primary/30 p-6 shadow-[0_60px_120px_rgba(0,0,0,1)] flex gap-6 rounded-2xl items-center justify-center pointer-events-auto scale-110">
                <div v-for="(img, i) in allImages" :key="i" 
                     class="w-48 h-64 bg-surface overflow-hidden rounded-xl border border-white/20 reveal-stagger group/thumb cursor-crosshair shadow-2xl"
                     :style="{ transitionDelay: (i * 100) + 'ms' }">
                    <img :src="img" class="w-full h-full object-cover grayscale group-hover/thumb:grayscale-0 transition-all duration-1000 opacity-30 group-hover/thumb:opacity-100 group-hover/thumb:scale-110" />
                </div>
            </div>
        </div>

        <!-- Product Info (Grid only) -->
        <div v-if="layout === 'grid'" class="mt-4 px-1">
            <div class="flex justify-between items-start mb-3">
                <div class="flex-1 min-w-0">
                    <h3 class="font-headline text-xl font-medium dark:text-white text-on-surface truncate pr-4 group-hover:text-primary transition-colors text-glow-after">{{ product.name }}</h3>
                    <p class="dark:text-white/40 text-black/40 text-[11px] tracking-[0.2em] font-headline font-medium">{{ product.category?.name || 'Collection' }}</p>
                </div>
                <span class="text-primary font-headline font-medium text-lg luminous-glow shrink-0">KSh {{ Number(product.price).toLocaleString() }}</span>
            </div>

            <!-- Mobile persistent CTA (touch devices can't hover) -->
            <button
                v-if="!isSoldOut"
                @click="cart.addItem(product)"
                class="md:hidden w-full mt-3 bg-primary text-black py-4 text-[10.5px] font-headline font-black uppercase tracking-[0.25em] rounded-full shadow-[0_15px_30px_rgba(57,255,20,0.4)] border-2 border-primary active:scale-95 transition-all flex items-center justify-center gap-2"
            >
                <span class="material-symbols-outlined text-[18px] font-black">shopping_bag</span>
                Add to Bag
            </button>
            <a
                v-if="!isSoldOut"
                :href="whatsappUrl"
                target="_blank"
                class="md:hidden w-full mt-2 bg-white/5 border dark:border-white/10 border-black/10 dark:text-white text-on-surface py-3.5 text-[10px] font-headline font-bold uppercase tracking-[0.2em] rounded-full active:scale-95 transition-all flex items-center justify-center gap-2"
            >
                <span class="material-symbols-outlined text-[18px]">chat</span>
                Quick Order
            </a>
            <div v-else class="md:hidden w-full mt-2 border dark:border-white/10 border-black/10 dark:text-white text-on-surface/30 py-2.5 text-xs font-medium tracking-widest rounded-sm flex items-center justify-center">
                Sold Out
            </div>
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
.group:hover {
    transform: scale(1.02);
    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    z-index: 40;
}

@keyframes tooltipPop {
    from { opacity: 0; transform: translateY(10px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.animate-tooltip-pop {
    animation: tooltipPop 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.reveal-stagger {
    opacity: 0;
    transform: translateY(5px);
    animation: revealItem 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes revealItem {
    to { opacity: 1; transform: translateY(0); }
}
</style>
