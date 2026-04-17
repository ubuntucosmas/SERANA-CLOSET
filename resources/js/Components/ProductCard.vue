<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';
import { Link, usePage, router } from '@inertiajs/vue3';
import XRayOverlay from '@/Components/XRayOverlay.vue';
import { animate } from 'animejs';
import HandoffOverlay from '@/Components/HandoffOverlay.vue';

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
const showHandoff = ref(false);
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

const optimizedAllImages = computed(() => {
    const images = [props.product.optimized_image_url || props.product.image_url];
    if (props.product.optimized_secondary_urls) {
        images.push(...props.product.optimized_secondary_urls.filter(Boolean));
    } else if (props.product.secondary_image_urls) {
        images.push(...props.product.secondary_image_urls.filter(Boolean));
    }
    return images.filter(Boolean);
});

const navigateToSpecs = () => {
    router.visit(route('shop.show', props.product.slug));
};

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
    const imageUrl = (props.product.image_url && props.product.image_url.startsWith('http')) 
        ? props.product.image_url 
        : `${baseUrl}/${(props.product.image_url || '').replace(/^\//, '')}`;
    
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
            'group relative transition-all duration-700 cursor-pointer',
            layout === 'editorial' ? 'h-[600px] lg:h-[800px]' : ''
        ]"
        @mouseenter="handleHover(true)"
        @mouseleave="handleHover(false)"
        @click="navigateToSpecs"
    >
        <!-- Image Container -->
        <div :class="[
            'overflow-hidden bg-transparent relative rounded-sm border dark:border-white/10 border-black/10 shadow-2xl transition-all duration-700',
            layout === 'editorial' ? 'h-full mb-0' : 'aspect-[3/4] mb-3 md:mb-6'
        ]">
            <img
                class="product-img w-full h-full object-cover transition-all duration-700"
                :src="optimizedAllImages[currentIndex] || product.optimized_image_url || '/images/placeholder.png'"
                :alt="product.name"
                loading="lazy"
                :class="{ 'opacity-80': isHovered }"
            />
            
            <!-- Technical X-Ray Overlay - Hidden on Mobile -->
            <XRayOverlay :specs="product.specifications" :active="isHovered" class="hidden md:flex" />

            <!-- Desktop Editorial/Grid Hover Overlay -->
            <div :class="[
                'overlay-content absolute inset-0 flex flex-col justify-end p-8 gap-4 z-30 pointer-events-none opacity-0 hidden md:flex',
                layout === 'editorial' 
                    ? 'bg-gradient-to-t from-black via-black/80 to-transparent' 
                    : 'bg-black/60 backdrop-blur-md'
            ]">
                
                <div v-if="layout === 'editorial'" class="space-y-3 mb-6">
                    <p class="text-primary font-headline text-[11px] font-medium tracking-[0.3em] uppercase">{{ product.category?.name || 'Collection' }}</p>
                    <h3 class="text-5xl lg:text-7xl font-headline font-medium dark:text-white text-on-surface leading-none drop-shadow-2xl">{{ product.name }}</h3>
                    <p class="text-3xl font-headline font-medium dark:text-white text-on-surface">KSh {{ Number(product.price).toLocaleString() }}</p>
                </div>

                <div class="flex flex-col gap-4 pointer-events-auto">
                    <button 
                        @click.stop="cart.addItem(product)"
                        class="w-full bg-primary text-black py-5 text-[12px] font-headline font-medium tracking-[0.2em] shadow-[0_0_25px_rgba(57, 255, 20,0.4)] flex items-center justify-center gap-3 rounded-sm hover:scale-105 active:scale-95 transition-all"
                    >
                        <span class="material-symbols-outlined text-[18px]">shopping_bag</span>
                        Add to bag
                    </button>
                    <!-- Link removed as card is now clickable -->
                </div>
            </div>

            <!-- Zen "Atelier Tag" (Fancy Mobile Info Overlay) -->
            <div class="md:hidden absolute inset-x-0 bottom-0 p-3 z-40 bg-gradient-to-t from-black/80 via-black/20 to-transparent pointer-events-none">
                <div class="flex flex-col gap-2 p-3 bg-black/40 backdrop-blur-xl border border-white/10 rounded-sm pointer-events-auto shadow-2xl">
                    <div class="flex justify-between items-start">
                        <div class="flex-1 min-w-0 pr-2">
                            <h3 class="text-[11px] font-medium text-white truncate uppercase tracking-widest leading-tight mb-0.5">{{ product.name }}</h3>
                            <p class="text-[8px] font-medium text-primary uppercase tracking-[0.3em] opacity-80">{{ product.category?.name || 'Archive' }}</p>
                        </div>
                        <span class="text-[10px] font-black text-primary luminous-glow shrink-0">KSh {{ Number(product.price).toLocaleString() }}</span>
                    </div>
                    
                    <div class="flex gap-2 mt-1">
                        <button 
                            @click.stop="cart.addItem(product)"
                            class="flex-1 bg-white text-black py-2.5 rounded-sm flex items-center justify-center active:scale-95 transition-all shadow-lg"
                        >
                            <span class="material-symbols-outlined text-[16px] font-black">shopping_bag</span>
                        </button>
                        <a 
                            :href="whatsappUrl" 
                            target="_blank" 
                            @click.stop
                            class="flex-1 bg-primary/10 border border-primary/30 text-primary py-2.5 rounded-sm flex items-center justify-center active:scale-95 transition-all"
                        >
                            <span class="material-symbols-outlined text-[16px]">chat</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Indicator Dots -->
            <div v-if="allImages.length > 1" class="absolute top-4 left-1/2 -translate-x-1/2 z-10 flex gap-1.5 opacity-60">
                <div v-for="(_, i) in allImages" :key="i" 
                     class="w-1 h-1 rounded-full transition-all duration-300"
                     :class="currentIndex === i ? 'bg-primary shadow-[0_0_5px_#39FF14] scale-125' : 'bg-white/40'">
                </div>
            </div>

            <!-- Sold Out Overlay -->
            <div v-if="isSoldOut" class="absolute inset-0 z-[50] bg-black/60 backdrop-blur-[2px] flex items-center justify-center">
                <span class="text-white/60 text-[9px] font-black tracking-[0.4em] uppercase border border-white/20 px-4 py-2 rounded-sm bg-black/40">Sold_Out</span>
            </div>
        </div>

        <!-- Desktop Grid Info (Hidden on Mobile) -->
        <div v-if="layout === 'grid'" class="hidden md:block mt-4 px-1">
            <div class="flex flex-col mb-3">
                <h3 class="font-headline text-xl font-medium dark:text-white text-on-surface truncate group-hover:text-primary transition-colors text-glow-after">{{ product.name }}</h3>
                <div class="flex justify-between items-center mt-1">
                    <p class="dark:text-white/40 text-black/40 text-[11px] tracking-[0.2em] font-headline font-medium uppercase">{{ product.category?.name || 'Collection' }}</p>
                    <span class="text-primary font-headline font-black text-lg luminous-glow shrink-0">KSh {{ Number(product.price).toLocaleString() }}</span>
                </div>
            </div>
        </div>
        <HandoffOverlay v-if="showHandoff && product" :show="showHandoff" :order-type="product.name" />

        <!-- Hover Image Gallery Tooltip Pop-up (Hidden on Mobile) -->
        <div v-if="isHovered && optimizedAllImages.length > 1" 
             class="hidden md:flex absolute -top-80 left-1/2 -translate-x-1/2 z-[100] pointer-events-none px-2 animate-tooltip-pop min-w-[300px] justify-center">
            <div class="bg-black/90 backdrop-blur-3xl border border-primary/30 p-6 shadow-[0_60px_120px_rgba(0,0,0,1)] flex gap-6 rounded-2xl items-center justify-center pointer-events-auto scale-110">
                <div v-for="(img, i) in optimizedAllImages" :key="i" 
                     class="w-48 h-64 bg-surface overflow-hidden rounded-xl border border-white/20 reveal-stagger group/thumb cursor-crosshair shadow-2xl"
                     :style="{ transitionDelay: (i * 100) + 'ms' }">
                    <img :src="img" class="w-full h-full object-cover grayscale group-hover/thumb:grayscale-0 transition-all duration-1000 opacity-30 group-hover/thumb:opacity-100 group-hover/thumb:scale-110" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.text-glow-after { transition: text-shadow 0.3s ease; }
.group:hover .text-glow-after { text-shadow: 0 0 15px rgba(57, 255, 20, 0.6); }

/* Animation for the Tag appearance */
.atelier-tag-pulse {
    animation: tagPulse 3s ease-in-out infinite;
}

@keyframes tagPulse {
    0% { border-color: rgba(57, 255, 20, 0.1); }
    50% { border-color: rgba(57, 255, 20, 0.4); }
    100% { border-color: rgba(57, 255, 20, 0.1); }
}

@keyframes tooltipPop {
    from { opacity: 0; transform: translateY(10px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.animate-tooltip-pop { animation: tooltipPop 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }

.reveal-stagger {
    opacity: 0;
    transform: translateY(5px);
    animation: revealItem 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes revealItem {
    to { opacity: 1; transform: translateY(0); }
}
</style>
