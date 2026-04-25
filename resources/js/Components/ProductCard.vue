<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';
import { Link, usePage, router } from '@inertiajs/vue3';
import XRayOverlay from '@/Components/XRayOverlay.vue';
import { animate } from 'animejs';
import HandoffOverlay from '@/Components/HandoffOverlay.vue';
import { useCurrency } from '@/Composables/useCurrency';

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
const { formatAmount } = useCurrency();
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

const loupeStyle = ref({ opacity: 0, scale: 0.8 });

const handleMouseMove = (e) => {
    if (!isHovered.value || window.innerWidth < 768) return;
    
    const container = e.currentTarget.getBoundingClientRect();
    const x = e.clientX - container.left;
    const y = e.clientY - container.top;
    
    const zoom = 2.2; 
    const size = 180;
    
    loupeStyle.value = {
        opacity: 1,
        transform: `scale(1)`,
        left: `${x - size/2}px`,
        top: `${y - size/2}px`,
        backgroundImage: `url(${optimizedAllImages.value[currentIndex.value] || product.image_url})`,
        backgroundSize: `${container.width * zoom}px ${container.height * zoom}px`,
        backgroundPosition: `-${x * zoom - size/2}px -${y * zoom - size/2}px`,
    };
};

const handleHover = (hovering) => {
    isHovered.value = hovering;
    if (!hovering) {
        loupeStyle.value = { opacity: 0, transform: 'scale(0.8)' };
    }
    
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
                    `• Price: ${formatAmount(props.product.price, page.props)}\n\n` +
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
        ]"
        @mousemove="handleMouseMove"
        >
            <img
                class="product-img w-full h-full object-cover transition-all duration-700"
                :src="optimizedAllImages[currentIndex] || product.optimized_image_url || '/images/placeholder.png'"
                :alt="product.name"
                loading="lazy"
            />

            <!-- The Digital Loupe (Sensory Inspection) -->
            <div 
                v-if="isHovered"
                class="hidden md:block pointer-events-none absolute w-[180px] h-[180px] rounded-full border border-primary/40 shadow-[0_0_30px_rgba(57,255,20,0.3)] z-[60] transition-opacity duration-300 overflow-hidden"
                :style="loupeStyle"
            >
                <!-- Organic Crosshair -->
                <div class="absolute inset-0 flex items-center justify-center opacity-20 pointer-events-none">
                    <div class="w-full h-[0.5px] bg-primary"></div>
                    <div class="h-full w-[0.5px] bg-primary absolute"></div>
                    <div class="w-4 h-4 rounded-full border border-primary"></div>
                </div>
            </div>
            
            <!-- Technical X-Ray Overlay - Hidden on Mobile -->
            <XRayOverlay :specs="product.specifications" :productId="product.id" :active="isHovered" class="hidden md:flex" />

            <!-- Desktop Editorial/Grid Hover Overlay -->
            <div :class="[
                'overlay-content absolute inset-0 flex flex-col justify-end p-8 gap-4 z-30 pointer-events-none opacity-0 hidden md:flex',
                layout === 'editorial' 
                    ? 'bg-gradient-to-t from-black via-black/80 to-transparent' 
                    : 'bg-transparent'
            ]">
                
                <div v-if="layout === 'editorial'" class="space-y-3 mb-6">
                    <p class="text-primary font-headline text-[11px] font-medium tracking-[0.3em] uppercase">{{ product.category?.name || 'Collection' }}</p>
                    <h3 class="text-5xl lg:text-7xl font-headline font-medium dark:text-white text-on-surface leading-none drop-shadow-2xl">{{ product.name }}</h3>
                    <p class="text-3xl font-headline font-medium dark:text-white text-on-surface">{{ formatAmount(product.price, page.props) }}</p>
                </div>
            </div>

            <!-- Zen "Product Tag" (Fancy Mobile Info Overlay) -->
            <div class="md:hidden absolute inset-x-0 bottom-0 p-4 z-40 bg-gradient-to-t from-black/90 via-black/40 to-transparent pointer-events-none pb-5">
                <div class="flex flex-col gap-4 pointer-events-auto mt-8">
                    <!-- Action Icons (Floating Left & Right) -->
                    <div class="flex justify-between items-start -mt-6 pr-1 pl-1">
                        <button 
                            @click.stop="cart.addItem(product)"
                            class="w-8 h-8 flex items-center justify-center active:scale-90 transition-all text-white drop-shadow-lg"
                        >
                            <span class="material-symbols-outlined text-[18px]">shopping_bag</span>
                        </button>
                        <a 
                            :href="whatsappUrl" 
                            target="_blank" 
                            @click.stop
                            class="w-8 h-8 flex items-center justify-center active:scale-90 transition-all text-[#25D366] drop-shadow-lg"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.57-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                    </div>
                    
                    <!-- Product Details & Price (Bottom Row) -->
                    <div class="flex items-end justify-between z-10 px-1">
                        <div class="flex flex-col flex-1 min-w-0 pr-2">
                            <h3 class="text-[12px] font-headline font-medium text-white uppercase tracking-widest leading-tight drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] truncate">{{ product.name }}</h3>
                            <p class="text-[8px] mt-1 font-medium dark:text-white/60 text-on-surface-variant uppercase tracking-[0.2em]">{{ product.category?.name || 'Archive' }}</p>
                        </div>
                        <span class="text-[14px] font-headline font-black text-primary drop-shadow-lg shrink-0">{{ formatAmount(product.price, page.props) }}</span>
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

        <!-- Desktop Action Panel (Outside Image, prevents Loupe obstruction) -->
        <div class="hidden md:block mt-6 px-1">
            <div :class="[
                'flex flex-col gap-4 transform transition-all duration-500',
                isHovered ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-80'
            ]">
                <div class="flex justify-between items-start">
                    <div class="flex flex-col">
                        <h3 :class="[
                            'font-headline text-xl font-medium dark:text-white text-on-surface transition-colors',
                            isHovered ? 'text-primary drop-shadow-[0_0_10px_rgba(57,255,20,0.3)]' : ''
                        ]">{{ product.name }}</h3>
                        <div class="flex items-center gap-3 mt-1">
                            <p class="dark:text-white/40 text-on-surface-variant text-[10px] tracking-[0.3em] font-headline font-medium uppercase">{{ product.category?.name || 'Collection' }}</p>
                            <span v-if="product.garment_type === 'set'" class="text-primary text-[8px] font-black tracking-widest border border-primary/20 px-2 py-0.5 rounded-full">// FULL SET</span>
                            <span v-else class="text-white/20 text-[8px] font-black tracking-widest border border-white/5 px-2 py-0.5 rounded-full">// SINGLE</span>
                        </div>
                    </div>
                    <span class="text-primary font-headline font-black text-2xl luminous-glow shrink-0">{{ formatAmount(product.price, page.props) }}</span>
                </div>

                <!-- Primary Purchase Action -->
                <button 
                    @click.stop="cart.addItem(product)"
                    class="w-full py-5 bg-white/5 border border-white/10 text-white font-headline font-black text-[10px] tracking-[0.3em] uppercase rounded-sm hover:bg-primary hover:text-black hover:border-primary hover:shadow-[0_15px_30px_rgba(57,255,20,0.2)] transition-all flex items-center justify-center gap-3 group/btn"
                >
                    <span class="material-symbols-outlined text-[18px] group-hover/btn:scale-110 transition-transform">shopping_bag</span>
                    Add to Bag
                </button>
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
                    <img :src="img" class="w-full h-full object-cover transition-all duration-1000 opacity-80 group-hover/thumb:opacity-100 group-hover/thumb:scale-110" />
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
