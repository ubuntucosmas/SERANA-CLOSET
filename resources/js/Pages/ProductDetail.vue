<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCartStore } from '@/Stores/useCartStore';
import { useCurrency } from '@/Composables/useCurrency';

import { useWhatsApp } from '@/Composables/useWhatsApp';
import { usePulseStore } from '@/Stores/usePulseStore';

const page = usePage();
const { formatAmount } = useCurrency();
const { getWhatsAppUrl } = useWhatsApp();
const pulse = usePulseStore();

const props = defineProps({
    product: Object
});

const whatsappUrl = computed(() => {
    return getWhatsAppUrl(props.product, {
        topSize: selectedTopSize.value,
        bottomSize: selectedBottomSize.value
    }, page.props);
});

const cart = useCartStore();
const isSoldOut = computed(() => props.product.in_stock === false || (props.product.batch_limit != null && props.product.batch_sold >= props.product.batch_limit));

// ── Data-Driven Configuration Elements ───────────────────────────────────────
const uiElements = computed(() => props.product.specifications?.ui || {
    steps: ['1. Start', '2. Select', '3. Fit'],
    configuring_set_label: 'CHOOSE YOUR PIECES',
    icon_check: 'check_circle',
    piece_1_label: 'ITEM 01',
    piece_1_name: 'Hoodie',
    piece_2_label: 'ITEM 02',
    piece_2_name: 'Joggers',
    size_prefix: 'SIZE:',
    size_guide: 'Size Guide',
    sizes: ['S', 'M', 'L', 'XL', 'XXL'],
    icon_bag: 'shopping_bag',
    cta_primary: 'Add to Bag',
    icon_chat: 'chat',
    cta_secondary: 'CHAT WITH US'
});

const defaultSizes = ['S', 'M', 'L', 'XL', 'XXL'];
const sizes = computed(() => props.product.specifications?.ui?.sizes || props.product.specifications?.sizes || defaultSizes);

// ── Jogger Set Configuration Engine ──────────────────────────────────────────
const selectedTopSize = ref(null);
const selectedBottomSize = ref(null);
const activeComponent = ref(props.product.garment_type === 'jogger' ? 'joggers' : 'hoodie');

const addToBag = () => {
    if (isSoldOut.value) return;
    const isSet = props.product.garment_type === 'set';
    const isHoodie = props.product.garment_type === 'hoodie';
    const isJogger = props.product.garment_type === 'jogger';
    const isGeneric = !isSet && !isHoodie && !isJogger;

    if (isSet) {
        if (!selectedTopSize.value || !selectedBottomSize.value) {
            const missing = !selectedTopSize.value && !selectedBottomSize.value 
                ? 'both hoodie & jogger sizes' 
                : (!selectedTopSize.value ? 'hoodie size' : 'jogger size');
                
            pulse.emit(`Please select ${missing} to add the set to your bag.`, 'error');
            return;
        }
    } else if ((isHoodie || isGeneric) && !selectedTopSize.value && activeComponent.value === 'hoodie') {
        pulse.emit(`Please select a size for your ${uiElements.value?.piece_1_name || 'piece'}.`, 'error');
        return;
    } else if ((isJogger || isGeneric) && !selectedBottomSize.value && activeComponent.value === 'joggers') {
        pulse.emit(`Please select a size for your ${uiElements.value?.piece_2_name || 'piece'}.`, 'error');
        return;
    }
    
    if (props.product.batch_limit) {
        const available = props.product.batch_limit - (props.product.batch_sold || 0);
        const currentQty = cart.items.find(item => item.id === props.product.id)?.quantity ?? 0;
        if (currentQty >= available) {
            pulse.emit(`You have added the available quantity of this item.`, 'error');
            return;
        }
    }

    const itemToBag = {
        ...props.product,
        name: isSet ? `${props.product.name} (Set)` : props.product.name,
        options: {
            top_size: isJogger ? null : selectedTopSize.value,
            bottom_size: isHoodie ? null : selectedBottomSize.value
        }
    };
    
    try {
        if (!cart.addItem(itemToBag)) {
            pulse.emit('No more stock is available for this item. Review your bag.', 'error');
            return;
        }
        pulse.emit('Added to your bag. Your next look is ready.', 'success');
    } catch (e) {
        console.error("Cart addition failed:", e);
        pulse.emit(`Could not add this piece. Please try again.`, 'error');
    }
};

const trustHighlights = computed(() => [
    { label: 'Free delivery', icon: 'local_shipping' },
    { label: 'Bespoke support', icon: 'support_agent' },
    { label: 'Secure checkout', icon: 'verified_user' },
]);


const allImages = computed(() => {
    const images = [];
    if (props.product.optimized_image_url) images.push(props.product.optimized_image_url);
    if (props.product.optimized_secondary_urls) images.push(...props.product.optimized_secondary_urls);
    return images.length > 0 ? images : ['/images/hero_editorial.png'];
});

const galleryRef = ref(null);
// Which slide is in view — drives the active gallery dot.
const currentIndex = ref(0);
const onGalleryScroll = () => {
    if (!galleryRef.value) return;
    currentIndex.value = Math.round(galleryRef.value.scrollLeft / galleryRef.value.clientWidth);
};
let autoScrollInterval = null;

const scrollGallery = (direction) => {
    if (!galleryRef.value) return;
    
    const container = galleryRef.value;
    const scrollAmount = container.clientWidth;
    const maxScroll = container.scrollWidth - container.clientWidth;
    
    if (direction === 'next') {
        if (container.scrollLeft >= maxScroll - 10) {
            container.scrollTo({ left: 0, behavior: 'smooth' });
        } else {
            container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
        }
    } else {
        if (container.scrollLeft <= 10) {
            container.scrollTo({ left: maxScroll, behavior: 'smooth' });
        } else {
            container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
        }
    }
    
    // Reset timer on manual interaction
    resetAutoScroll();
};

const startAutoScroll = () => {
    if (allImages.value.length <= 1) return;
    autoScrollInterval = setInterval(() => {
        scrollGallery('next');
    }, 5000);
};

const resetAutoScroll = () => {
    if (autoScrollInterval) {
        clearInterval(autoScrollInterval);
        startAutoScroll();
    }
};

onMounted(() => {
    startAutoScroll();
});

onUnmounted(() => {
    if (autoScrollInterval) clearInterval(autoScrollInterval);
});
</script>

<template>
    <StorefrontLayout :hideMobileCard="true">
        <Head :title="`${product.name} | Serana Closet`" />

        <main class="pb-24 px-0 md:px-8 font-body text-on-surface bg-background min-h-screen flex items-center justify-center">
            <div class="w-full max-w-[1300px] mx-auto bg-surface border-y md:border dark:border-white/5 border-black/5 rounded-none md:rounded-2xl shadow-[0_30px_60px_-15px_rgba(0,0,0,0.12)] dark:shadow-[0_30px_60px_-15px_rgba(0,0,0,0.8)] overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-0">
                    
                    <!-- Left: The Visual Axis (Atelier Gallery) — deliberately
                         always dark: the controls overlay the photograph. -->
                    <div class="relative group/slider min-h-[40vh] lg:min-h-full bg-black always-dark">
                        <div ref="galleryRef" @scroll.passive="onGalleryScroll" class="absolute inset-0 flex overflow-x-auto snap-x snap-mandatory no-scrollbar scroll-smooth">
                            <div v-for="(img, idx) in allImages" :key="idx" class="flex-none w-full snap-center h-full">
                                <img :src="img" class="w-full h-full object-cover" />
                            </div>
                        </div>

                        <!-- Gallery Controls (Minimal) -->
                        <div class="absolute inset-0 pointer-events-none z-20 flex flex-col justify-end p-6">
                            <div v-if="allImages.length > 1" class="flex justify-between items-center gap-4">
                                <button aria-label="Previous product image" @click="scrollGallery('prev')" class="pointer-events-auto w-12 h-12 rounded-full bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white/50 hover:text-white hover:border-white/30 transition-all">
                                    <span class="material-symbols-outlined text-xl">chevron_left</span>
                                </button>
                                
                                <div class="flex gap-2">
                                    <div v-for="(_, i) in allImages" :key="i" class="w-1.5 h-1.5 rounded-full transition-all duration-300" :class="currentIndex === i ? 'bg-primary w-4' : 'bg-white/20'"></div>
                                </div>

                                <button aria-label="Next product image" @click="scrollGallery('next')" class="pointer-events-auto w-12 h-12 rounded-full bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white/50 hover:text-white hover:border-white/30 transition-all">
                                    <span class="material-symbols-outlined text-xl">chevron_right</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: The Configuration Canvas -->
                    <div class="p-4 md:p-6 lg:p-10 flex flex-col justify-center space-y-4 md:space-y-6 relative border-t lg:border-t-0 lg:border-l dark:border-white/5 border-black/5 bg-surface-container/20">
                        
                        <!-- Header Card -->
                        <header class="p-3 md:p-5 rounded-sm glass-panel">
                            <div class="flex justify-between items-center gap-4">
                                <div class="space-y-0.5">
                                    <p class="text-primary font-bold text-[9px] tracking-wider opacity-60">{{ product.category?.name || 'PRIVATE COLLECTION' }}</p>
                                    <h1 class="text-xl font-bold tracking-tight text-on-surface flex items-center gap-3">
                                        {{ product.name }}<span class="w-1.5 h-1.5 bg-primary rounded-full animate-pulse"></span>
                                    </h1>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xl font-bold text-primary">{{ formatAmount(product.price, page.props) }}</p>
                                    <p class="text-[8px] text-on-surface-variant/70 tracking-widest font-medium mt-0.5">{{ isSoldOut ? 'Out of Stock' : 'Available to Order' }}</p>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t dark:border-white/5 border-black/5">
                                <p class="text-on-surface-variant text-xs font-medium leading-relaxed">
                                    {{ product.description }}
                                </p>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-2">
                                <div v-for="item in trustHighlights" :key="item.label" class="rounded-xl border border-black/5 bg-white/5 px-2 py-3 text-center">
                                    <span class="material-symbols-outlined text-base text-primary block">{{ item.icon }}</span>
                                    <span class="mt-1 block text-[8px] font-bold uppercase tracking-wider text-on-surface-variant">{{ item.label }}</span>
                                </div>
                            </div>
                        </header>

                        <!-- Configuration Card: Garment Selection -->
                        <div class="p-3 md:p-5 rounded-sm glass-panel space-y-5 md:space-y-6">
                            <p class="text-[9px] font-black uppercase tracking-[0.35em] text-primary">Build your fit</p>

                            <div class="space-y-2">
                                <div class="flex justify-between text-[9px] tracking-wider text-on-surface-variant/70 font-bold">
                                    <span>{{ uiElements.steps[0] }}</span>
                                    <span class="text-primary">{{ uiElements.steps[1] }}</span>
                                    <span>{{ uiElements.steps[2] }}</span>
                                </div>
                                <div class="h-[1px] w-full dark:bg-white/10 bg-black/10 relative">
                                    <div class="absolute left-0 top-0 h-full w-1/2 bg-blue-600 shadow-[0_0_10px_rgba(37,99,235,0.4)]"></div>
                                    <div class="absolute left-1/2 top-1/2 -translate-y-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-blue-500"></div>
                                </div>
                            </div>

                            <!-- Piece Selectors -->
                            <div v-if="product.garment_type === 'set'" class="space-y-3">
                                <label class="text-[8px] font-bold tracking-wider text-on-surface-variant/70">{{ uiElements.configuring_set_label }}</label>
                                <div class="flex gap-2 md:gap-3">
                                    <button
                                        @click="activeComponent = 'hoodie'"
                                        class="flex-1 flex flex-col p-3 md:p-4 dark:bg-slate-950/20 bg-black/[0.03] border dark:border-white/5 border-black/10 transition-all group relative overflow-hidden"
                                        :class="activeComponent === 'hoodie' ? '!border-primary/40 bg-primary/5' : 'dark:hover:border-white/10 hover:border-black/20'"
                                    >
                                        <div v-if="activeComponent === 'hoodie'" class="absolute top-0 right-0 p-2"><span class="material-symbols-outlined text-primary text-[14px]">{{ uiElements.icon_check }}</span></div>
                                        <span class="text-[8px] font-bold tracking-widest mb-1" :class="activeComponent === 'hoodie' ? 'text-primary' : 'text-on-surface-variant/70'">{{ uiElements.piece_1_label }}</span>
                                        <div class="flex justify-between items-end">
                                            <span class="text-sm font-bold tracking-wide text-on-surface">{{ uiElements.piece_1_name }}</span>
                                            <span class="text-[11px] font-bold text-primary" v-if="selectedTopSize">{{ selectedTopSize }}</span>
                                        </div>
                                    </button>
                                    <button
                                        @click="activeComponent = 'joggers'"
                                        class="flex-1 flex flex-col p-3 md:p-4 dark:bg-slate-950/20 bg-black/[0.03] border dark:border-white/5 border-black/10 transition-all group relative overflow-hidden"
                                        :class="activeComponent === 'joggers' ? '!border-primary/40 bg-primary/5' : 'dark:hover:border-white/10 hover:border-black/20'"
                                    >
                                        <div v-if="activeComponent === 'joggers'" class="absolute top-0 right-0 p-2"><span class="material-symbols-outlined text-primary text-[14px]">{{ uiElements.icon_check }}</span></div>
                                        <span class="text-[8px] font-bold tracking-widest mb-1" :class="activeComponent === 'joggers' ? 'text-primary' : 'text-on-surface-variant/70'">{{ uiElements.piece_2_label }}</span>
                                        <div class="flex justify-between items-end">
                                            <span class="text-sm font-bold tracking-wide text-on-surface">{{ uiElements.piece_2_name }}</span>
                                            <span class="text-[11px] font-bold text-primary" v-if="selectedBottomSize">{{ selectedBottomSize }}</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                            <div v-else class="space-y-1">
                                <p class="text-[8px] font-bold tracking-wider text-primary">SINGLE PIECE</p>
                                <h3 class="text-sm font-bold tracking-wide text-on-surface opacity-60">
                                    {{ product.garment_type === 'hoodie' ? `ITEM 01: ${uiElements.piece_1_name}` : `ITEM 02: ${uiElements.piece_2_name}` }}
                                </h3>
                            </div>
                        </div>

                        <!-- Configuration Card: Sizing -->
                        <div class="p-3 md:p-5 rounded-sm glass-panel space-y-3 md:space-y-4">
                            <div class="flex justify-between items-center">
                                <label class="text-[8px] font-bold tracking-wider text-on-surface-variant/70">
                                    {{ uiElements.size_prefix || 'SIZE:' }} {{ activeComponent === 'hoodie' ? (uiElements.piece_1_name || 'Piece').toLowerCase() : (uiElements.piece_2_name || 'Piece').toLowerCase() }}
                                </label>
                                <!-- No sizing chart yet — route fit questions to the atelier on WhatsApp, with the chosen sizes prefilled. -->
                                <a :href="whatsappUrl" target="_blank" rel="noopener" class="text-[8px] font-bold tracking-widest text-primary border-b border-primary/20 hover:border-primary transition-all">{{ uiElements.size_guide || 'Size Guide' }}</a>
                            </div>
                            <div class="grid grid-cols-5 gap-2">
                                <button 
                                    v-for="size in sizes" :key="size"
                                    @click="activeComponent === 'hoodie' ? selectedTopSize = size : selectedBottomSize = size"
                                    class="py-3 border dark:border-white/5 border-black/10 rounded-sm text-xs font-bold transition-all"
                                    :class="[
                                        (activeComponent === 'hoodie' ? selectedTopSize : selectedBottomSize) === size
                                            ? '!border-primary text-primary bg-primary/10'
                                            : 'text-on-surface-variant/70 dark:hover:border-white/10 hover:border-black/20 hover:text-on-surface'
                                    ]"
                                >
                                    {{ size }}
                                </button>
                            </div>

                            <div v-if="product.specifications?.tech_specs?.length" class="pt-4 border-t dark:border-white/5 border-black/5 space-y-3">
                                <div class="flex items-center gap-2 opacity-30 text-on-surface">
                                    <span class="material-symbols-outlined text-sm">architecture</span>
                                    <span class="text-[7px] font-bold tracking-wider">Product Details</span>
                                </div>
                                <div class="grid grid-cols-2 gap-4 text-[9px] text-on-surface-variant">
                                    <div v-for="(spec, idx) in product.specifications.tech_specs" :key="idx" class="flex gap-2 items-start">
                                        <span class="text-primary/40 font-mono">0{{ idx + 1 }}</span>
                                        <p class="leading-relaxed">{{ spec }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Metrics Card -->
                        <div v-if="product.specifications?.fabric_weight" class="p-4 md:p-5 rounded-sm glass-panel space-y-3 animate-in fade-in duration-1000">
                            <div class="flex justify-between items-center">
                                <span class="text-[8px] font-bold tracking-wider text-on-surface-variant/70">Fabric Weight</span>
                                <span class="text-[11px] font-bold text-primary">{{ product.specifications.fabric_weight }}</span>
                            </div>
                            <div class="h-1 w-full dark:bg-white/5 bg-black/10 rounded-full overflow-hidden">
                                <div class="h-full bg-blue-600 transition-all duration-1000" :style="{ width: (product.specifications?.weight_index || 50) + '%' }"></div>
                            </div>
                            <p class="text-[8px] text-on-surface-variant/70 tracking-widest">{{ product.specifications?.weight_label || 'Fabric details' }}</p>
                        </div>

                        <!-- CTA Card -->
                        <div class="mt-auto pt-5 border-t dark:border-white/5 border-black/5 flex flex-col gap-3">
                            <button 
                                :disabled="isSoldOut"
                                @click="addToBag"
                                class="w-full py-4 bg-blue-600 text-white disabled:opacity-50 font-bold text-xs tracking-wider rounded-sm hover:bg-blue-500 active:scale-[0.98] transition-all flex items-center justify-center gap-3"
                            >
                                <span class="material-symbols-outlined font-bold text-lg">{{ uiElements.icon_bag }}</span>
                                {{ isSoldOut ? 'Out of Stock' : 'Add to Bag' }}
                            </button>
                            <Link v-if="isSoldOut" :href="route('shop')" class="text-primary underline text-center">Browse the Collection</Link>
                            <p class="text-sm text-on-surface-variant">Select your size, then add to your bag. Delivery is included. Pay with M-Pesa at checkout.</p>
                            <a :href="whatsappUrl" target="_blank" class="w-full py-3.5 border dark:border-white/5 border-black/10 dark:bg-white/5 bg-black/[0.03] text-on-surface-variant hover:text-on-surface hover:!border-primary/40 font-bold text-[9px] tracking-wider rounded-sm transition-all text-center flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-[16px]">{{ uiElements.icon_chat }}</span>
                                {{ uiElements.cta_secondary }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>

    </StorefrontLayout>
</template>

<style scoped>
.text-glow-after { transition: text-shadow 0.3s ease; }
.text-glow-after:hover { text-shadow: 0 0 15px rgba(57, 255, 20, 0.6); }

/* Animation for reveal */
.reveal {
    animation: revealUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes revealUp {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
