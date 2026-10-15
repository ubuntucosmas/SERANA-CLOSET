<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useCartStore } from '@/Stores/useCartStore';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    product: { type: Object, required: true },
    layout: { type: String, default: 'grid' }
});

const page = usePage();
const cart = useCartStore();
const { formatAmount } = useCurrency();

// Interaction states
const isHovered = ref(false);
const isZoomed = ref(false);
const currentIndex = ref(0);
const addToCartAnimating = ref(false);
const showQuickView = ref(false);
const quickViewIndex = ref(0);
const zoomStyle = ref({ transformOrigin: 'center center' });
const isZooming = ref(false);

const openQuickView = () => {
    quickViewIndex.value = 0;
    showQuickView.value = true;
};

const handleZoomMove = (e) => {
    if (!isZooming.value) return;
    const rect = e.currentTarget.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    zoomStyle.value = { transformOrigin: `${x}% ${y}%` };
};

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

// Gracefully degrade to a known placeholder if an image fails to load (e.g. a
// dead storage host) instead of showing a broken-image icon.
const FALLBACK_IMAGE = '/images/hero_editorial.png';
const onImgError = (e) => {
    const el = e?.target;
    if (el && !el.src.endsWith(FALLBACK_IMAGE)) {
        el.src = FALLBACK_IMAGE;
    }
};

const navigateToSpecs = () => {
    router.visit(route('shop.show', props.product.slug));
};

const isSoldOut = computed(() => props.product.in_stock === false || (props.product.batch_limit != null && props.product.batch_sold >= props.product.batch_limit));



const quickViewWhatsapp = computed(() => {
    const msg = `Hi! I'm interested in the ${props.product.name}. Can you tell me more?`;
    return `https://wa.me/${page.props.whatsapp_number}?text=${encodeURIComponent(msg)}`;
});

const cycleInterval = ref(null);

const startAutoCycle = () => {
    isHovered.value = true;
    const totalImages = optimizedAllImages.value.length;
    if (totalImages > 1 && !cycleInterval.value) {
        // Start auto-cycling through images every 1.5 seconds
        cycleInterval.value = setInterval(() => {
            currentIndex.value = (currentIndex.value + 1) % totalImages;
        }, 1500);
    }
};

const stopAutoCycle = () => {
    isHovered.value = false;
    if (cycleInterval.value) {
        clearInterval(cycleInterval.value);
        cycleInterval.value = null;
    }
    currentIndex.value = 0;
};

const addToCart = () => {
    if (isSoldOut.value) return;
    router.visit(route('shop.show', props.product.slug));
};

const cycleImage = () => {
    if (allImages.value.length > 1) {
        currentIndex.value = (currentIndex.value + 1) % allImages.value.length;
    }
};

const handleDoubleClick = (e) => {
    e.stopPropagation();
    cycleImage();
};

onUnmounted(() => {
    if (cycleInterval.value) clearInterval(cycleInterval.value);
    currentIndex.value = 0;
});
</script>

<template>
    <article
        class="group relative transition-all duration-500 cursor-pointer bg-surface dark:bg-[#111114] p-3 lg:p-4 hover:bg-surface-container dark:hover:bg-[#15151a] rounded-2xl border border-white/5 hover:border-primary/30 hover:shadow-[0_0_40px_rgba(87,213,252,0.07)] overflow-hidden flex flex-col h-full"
        :class="[layout === 'editorial' ? 'min-h-[450px]' : 'min-h-[350px]']"
        @mouseenter="startAutoCycle"
        @mouseleave="stopAutoCycle"
        @dblclick="handleDoubleClick"
    >
        <!-- Ambient hover glow (subtle background element) -->
        <div
            class="absolute bottom-0 left-0 right-0 h-1/2 pointer-events-none z-0 opacity-0 group-hover:opacity-100 transition-opacity duration-1000"
            style="background: radial-gradient(ellipse at 50% 100%, rgba(87,213,252,0.05) 0%, transparent 60%);"
        ></div>

        <!-- Product Image Container -->
        <div class="always-dark relative overflow-hidden mb-4 aspect-[4/5] bg-black rounded-xl shrink-0 z-10 border border-white/5">
            <!-- Dynamic Image Scrubber -->
            <img
                v-for="(imgSrc, i) in optimizedAllImages.length ? optimizedAllImages : ['/images/hero_editorial.png']"
                :key="i"
                class="absolute inset-0 w-full h-full object-cover transition-opacity duration-1000 transform group-hover:scale-105 transition-transform"
                :class="i === currentIndex ? 'opacity-100 z-10' : 'opacity-0 z-0'"
                :src="imgSrc"
                :alt="product.name + (i > 0 ? ' view ' + (i + 1) : '')"
                loading="lazy"
                @error="onImgError"
            />

            <!-- Atelier-style Badge (Top Left) -->
            <div class="absolute top-4 left-4 z-20 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                <span v-if="!isSoldOut" class="bg-primary px-3 py-1.5 text-black text-[9px] font-bold tracking-wider flex items-center gap-1.5 shadow-lg">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-black opacity-60"></span>
                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-black"></span>
                    </span>
                    In Stock
                </span>
                <span v-else class="bg-black/80 backdrop-blur-md border border-red-500/30 px-3 py-1.5 text-red-400 text-[9px] font-bold tracking-wider shadow-lg">
                    Sold Out
                </span>
            </div>

            <!-- Image Indicators (Bottom Center) -->
            <div v-if="allImages.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-1.5 z-20">
                <div v-for="(_, i) in allImages" :key="i" class="h-1.5 rounded-full transition-all duration-300" :class="i === currentIndex ? 'w-4 bg-primary' : 'w-1.5 bg-white/40'"></div>
            </div>

            <!-- Dark Overlay on Hover for Buttons -->
            <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity duration-500 z-10 pointer-events-none"></div>

            <!-- Quick Action Hover Overlay -->
            <div class="absolute inset-0 flex items-center justify-center gap-4 opacity-0 group-hover:opacity-100 transition-all duration-300 z-20">
                <button
                    @click.stop="openQuickView"
                    class="w-12 h-12 bg-black/50 backdrop-blur-md rounded-full flex items-center justify-center text-white/70 hover:text-white hover:scale-110 hover:bg-primary hover:text-black transition-all shadow-xl"
                    title="Quick View"
                >                  <span class="material-symbols-outlined">visibility</span>
                </button>

                <button
                    v-if="!isSoldOut"
                    @click.stop="addToCart"
                    class="w-12 h-12 rounded-full bg-primary text-black flex items-center justify-center font-bold hover:bg-white hover:scale-110 transition-all shadow-[0_0_20px_rgba(87,213,252,0.3)]"
                    :class="{'animate-bounce': addToCartAnimating}"
                    title="Add to Cart"
                >
                    <span class="material-symbols-outlined">{{ addToCartAnimating ? 'check' : 'shopping_bag' }}</span>
                </button>
            </div>
        </div>

        <!-- Product Details -->
        <div class="flex flex-col flex-grow relative z-10 mt-1">
            <!-- Category Eyebrow -->
            <p class="text-white/40 font-mono tracking-wider text-[8px] mb-1 truncate group-hover:text-primary/70 transition-colors">{{ product.category?.name || 'Collection' }}</p>
            
            <!-- Title -->
            <h2 class="text-base md:text-lg font-display text-white leading-tight line-clamp-2 tracking-tight">{{ product.name }}</h2>
            
            <!-- Price Row -->
            <div class="mt-2 pt-2 border-t border-white/5 flex justify-between items-center group-hover:border-primary/20 transition-colors duration-300">
                <span class="text-sm font-bold text-primary tracking-wide">{{ formatAmount(product.price, page.props) }}</span>
                <span class="text-[8px] font-mono tracking-widest text-white/0 group-hover:text-primary transition-all duration-300 flex items-center gap-1 transform translate-x-2 group-hover:translate-x-0">
                    Discover <span class="material-symbols-outlined text-[10px]">arrow_forward</span>
                </span>
            </div>
        </div>

        <!-- Quick View Modal -->
        <teleport to="body">
            <transition name="modal">
                <div v-if="showQuickView" class="fixed inset-0 z-[200] flex items-center justify-center p-4 md:p-6" @click.self="showQuickView = false">
                    <div class="absolute inset-0 bg-black/90 backdrop-blur-md" @click="showQuickView = false"></div>
                    
                    <div class="always-dark relative bg-[#111114] border border-white/10 rounded-2xl max-w-5xl w-full flex flex-col md:flex-row overflow-hidden shadow-2xl h-[90vh] md:h-[650px]">
                        <button @click="showQuickView = false" class="absolute top-4 right-4 z-50 text-white/40 hover:text-white bg-black/50 rounded-full p-2 backdrop-blur-md transition-colors hover:bg-white/10">
                            <span class="material-symbols-outlined text-lg">close</span>
                        </button>
                        
                        <!-- Image Section with Zoom -->
                        <div class="w-full md:w-[55%] bg-[#0a0a0c] relative overflow-hidden group cursor-crosshair shrink-0"
                             @mouseenter="isZooming = true"
                             @mouseleave="isZooming = false; zoomStyle = { transformOrigin: 'center center' }"
                             @mousemove="handleZoomMove">
                             
                            <img :src="optimizedAllImages[quickViewIndex]" :alt="product.name"
                                 class="w-full h-full object-cover transition-transform duration-200 ease-out"
                                 :class="isZooming ? 'scale-[2]' : 'scale-100'"
                                 :style="zoomStyle" @error="onImgError" />
                                 
                            <!-- Image Navigation Arrows -->
                            <button v-if="optimizedAllImages.length > 1" @click.stop="quickViewIndex = (quickViewIndex - 1 + optimizedAllImages.length) % optimizedAllImages.length" class="absolute left-4 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center bg-black/40 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-md hover:bg-black/80">
                                <span class="material-symbols-outlined">chevron_left</span>
                            </button>
                            <button v-if="optimizedAllImages.length > 1" @click.stop="quickViewIndex = (quickViewIndex + 1) % optimizedAllImages.length" class="absolute right-4 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center bg-black/40 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity backdrop-blur-md hover:bg-black/80">
                                <span class="material-symbols-outlined">chevron_right</span>
                            </button>
                        </div>
                        
                        <!-- Details Section -->
                        <div class="w-full md:w-[45%] p-6 md:p-10 flex flex-col h-full overflow-y-auto">
                            <p class="text-white/40 font-mono tracking-wider text-[10px] mb-2">{{ product.category?.name || 'Collection' }}</p>
                            <h3 class="text-2xl md:text-3xl font-display text-white mb-4 leading-tight">{{ product.name }}</h3>
                            <p class="text-primary font-bold text-xl mb-6 tracking-wide">{{ formatAmount(product.price, page.props) }}</p>
                            
                            <div class="mb-8 text-white/50 text-sm leading-relaxed font-light">
                                <p v-if="product.description">{{ product.description }}</p>
                                <p v-else class="italic">A definitive piece from the Serana Closet archive.</p>
                            </div>
                            
                            <!-- Thumbnails -->
                            <div v-if="optimizedAllImages.length > 1" class="mb-8">
                                <p class="text-[9px] font-mono tracking-widest text-white/30 mb-3">Gallery</p>
                                <div class="flex gap-3 overflow-x-auto pb-2 scrollbar-hide">
                                    <button v-for="(img, i) in optimizedAllImages" :key="i" 
                                            @click="quickViewIndex = i"
                                            class="w-16 h-20 shrink-0 rounded-md overflow-hidden border-2 transition-all"
                                            :class="quickViewIndex === i ? 'border-primary shadow-[0_0_15px_rgba(87,213,252,0.3)]' : 'border-transparent opacity-50 hover:opacity-100'">
                                        <img :src="img" class="w-full h-full object-cover" />
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-auto flex flex-col gap-3 pt-6 border-t border-white/5">
                                <button
                                    v-if="!isSoldOut"
                                    @click="addToCart"
                                    class="w-full bg-primary text-black py-4 text-[11px] font-bold font-mono tracking-widest flex items-center justify-center gap-2 hover:bg-white transition-all active:scale-95 rounded-sm shadow-lg shadow-primary/20"
                                >
                                    Choose Size
                                    <span class="material-symbols-outlined text-[16px]">{{ addToCartAnimating ? 'check' : 'shopping_bag' }}</span>
                                </button>
                                <button
                                    v-else disabled
                                    class="w-full bg-white/5 text-white/30 py-4 text-[11px] font-bold font-mono tracking-widest flex items-center justify-center rounded-sm cursor-not-allowed border border-white/10"
                                >
                                    Out of Stock
                                </button>
                                
                                <a :href="quickViewWhatsapp" target="_blank" class="w-full py-3 bg-transparent border border-white/10 text-white/60 font-bold tracking-widest text-[10px] rounded-sm flex items-center justify-center gap-2 hover:bg-white/5 hover:text-white transition-all">
                                    <span class="material-symbols-outlined text-sm">whatsapp</span>
                                    Inquire via WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>
        </teleport>
    </article>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.3s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>