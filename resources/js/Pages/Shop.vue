<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

import { useCartStore } from '@/Stores/useCartStore';
import ProductCard from '@/Components/ProductCard.vue';
import XRayOverlay from '@/Components/XRayOverlay.vue';

const props = defineProps({
    products: Array,
    categories: Array,
    filters: Object
});

const cart = useCartStore();
const currentLayout = ref('grid'); // 'grid' or 'editorial'

// ── Cinematic Background System ──────────────────────────────────────────────
const bgImages = [
    '/images/white_hoodie_silver_zipper.png',
    '/images/black_white_hoodies_showcase.png',
    '/images/beige_contrast_set.png',
    '/images/navy_contrast_set.png',
    '/images/black_zip_up_hoodie_gray_bg.png',
    '/images/ready_to_wear_rack.png',
    '/images/category_ladies.png',
    '/images/hero_editorial.png',
    '/images/detailed_texture.png',
    '/images/black_cloth_texture.png',
];

// Dual-slot cross-fade: A and B layers swap opacity
const slotA = ref(bgImages[0]);
const slotB = ref(bgImages[1]);
const showA = ref(true); // when true, A is visible; B is fading out
let bgInterval = null;
let bgIndex = ref(1);

onMounted(() => {
    bgInterval = setInterval(() => {
        bgIndex.value = (bgIndex.value + 1) % bgImages.length;
        if (showA.value) {
            // B takes the new image and fades in
            slotB.value = bgImages[bgIndex.value];
            showA.value = false;
        } else {
            slotA.value = bgImages[bgIndex.value];
            showA.value = true;
        }
    }, 7000);
});
onUnmounted(() => clearInterval(bgInterval));
// ─────────────────────────────────────────────────────────────────────────────

const filterByCategory = (slug) => {
    router.get(route('shop'), { category: slug, sort: currentSort.value }, { preserveState: true, replace: true });
};

const sortOptions = [
    { label: 'Newest Arrivals', value: 'newest' },
    { label: 'Price: Low to High', value: 'price_asc' },
    { label: 'Price: High to Low', value: 'price_desc' },
];
const currentSort = ref(props.filters?.sort || 'newest');
const showSortMenu = ref(false);
const currentSortLabel = computed(() => sortOptions.find(o => o.value === currentSort.value)?.label ?? 'Newest Arrivals');

const filterBySort = (value) => {
    currentSort.value = value;
    showSortMenu.value = false;
    router.get(route('shop'), { category: props.filters?.category, sort: value }, { preserveState: true, replace: true });
};

const activeCategory = computed(() => {
    if (!props.filters?.category || props.filters.category === 'all') return null;
    return props.categories?.find(c => c.slug === props.filters.category) ?? null;
});
</script>

<template>
    <StorefrontLayout>
        <Head>
            <title>The Collection | Serana Closet</title>
            <meta name="description" content="Browse our curated seasonal drops and limited-batch ready-to-wear pieces. Every garment is engineered for timeless confidence.">
        </Head>

        <div class="relative">
            <!-- ── Cinematic Shifting Background ─────────────────────────── -->
            <!-- Tall wrapper so absolute children cover the full page height -->
            <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <!-- SLOT A -->
                <img
                    :src="slotA"
                    class="absolute inset-0 w-full h-full object-cover scale-110 grayscale brightness-50 blur-[2px] transition-opacity duration-[3000ms]"
                    :class="showA ? 'opacity-30' : 'opacity-0'"
                    alt=""
                />
                <!-- SLOT B -->
                <img
                    :src="slotB"
                    class="absolute inset-0 w-full h-full object-cover scale-110 grayscale brightness-50 blur-[2px] transition-opacity duration-[3000ms]"
                    :class="showA ? 'opacity-0' : 'opacity-30'"
                    alt=""
                />

                <!-- Creative: Edge-only vignette — center stays clear so image pops -->
                <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 80% at 50% 40%, transparent 30%, rgba(0,0,0,0.6) 70%, rgba(0,0,0,0.9) 100%);"></div>

                <!-- Creative: Horizontal split-tone — warm dark top bar + neon wash at the very top -->
                <div class="absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-black/70 via-black/20 to-transparent"></div>

                <!-- Creative: Bottom fade to solid background for the product grid to sit on -->
                <div class="absolute inset-x-0 bottom-0 h-64 bg-gradient-to-t from-background via-background/80 to-transparent"></div>

                <!-- Neon accent top line -->
                <div class="absolute inset-x-0 top-0 h-[1px] bg-gradient-to-r from-transparent via-primary/40 to-transparent"></div>

                <!-- Scan lines — slightly more visible for texture -->
                <div class="absolute inset-0 scan-lines opacity-[0.05]"></div>
            </div>
            <!-- ─────────────────────────────────────────────────────────── -->

            <main class="relative z-10 pt-32 pb-24 max-w-screen-2xl mx-auto px-4 sm:px-8 font-body">
            <!-- Page Header -->
            <header class="mb-16 reveal">
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                    <div>
                        <span class="text-[11px] font-headline font-medium tracking-[0.2em] text-primary mb-4 block drop-shadow-md">The digital studio</span>
                        <h1 class="serif-text text-4xl md:text-8xl dark:text-white text-on-surface font-light tracking-tighter">
                            <span class="text-primary luminous-glow">Our</span> Collection
                        </h1>
                    </div>
                    <div class="flex items-center justify-between md:justify-end gap-4 w-full md:w-auto">
                        <!-- Layout Toggle -->
                        <div class="flex items-center bg-white/5 border dark:border-white/10 border-black/10 p-1 rounded-sm">
                            <button @click="currentLayout = 'grid'" 
                                    class="p-2 flex items-center justify-center transition-all rounded-md"
                                    :class="currentLayout === 'grid' ? 'bg-primary text-black' : 'dark:text-white/40 text-black/40 hover:dark:text-white text-on-surface'">
                                <span class="material-symbols-outlined text-[18px]">grid_view</span>
                            </button>
                            <button @click="currentLayout = 'editorial'" 
                                    class="p-2 flex items-center justify-center transition-all rounded-md"
                                    :class="currentLayout === 'editorial' ? 'bg-primary text-black' : 'dark:text-white/40 text-black/40 hover:dark:text-white text-on-surface'">
                                <span class="material-symbols-outlined text-[18px]">view_agenda</span>
                            </button>
                        </div>

                        <!-- Sort Dropdown -->
                        <div class="relative">
                            <button @click="showSortMenu = !showSortMenu" class="flex items-center gap-2 text-[10px] font-semibold tracking-widest dark:text-white/40 text-black/40 hover:text-primary transition-colors group px-4 py-3 bg-white/5 border dark:border-white/10 border-black/10 rounded-sm">
                                <span class="dark:text-white text-on-surface/50 hidden sm:inline">Sort:</span>
                                <span class="dark:text-white text-on-surface font-medium uppercase">{{ currentSortLabel }}</span>
                                <span class="material-symbols-outlined text-[16px] transition-transform duration-300" :class="showSortMenu ? 'rotate-180' : ''">keyboard_arrow_down</span>
                            </button>
                            <!-- Dropdown -->
                            <transition name="fade">
                                <div v-if="showSortMenu" class="glass-panel absolute right-0 top-12 z-50 w-48 rounded-sm shadow-2xl overflow-hidden animate-tooltip-pop">
                                    <button
                                        v-for="opt in sortOptions" :key="opt.value"
                                        @click="filterBySort(opt.value)"
                                        class="w-full text-left px-4 py-3 text-[10px] uppercase font-semibold tracking-widest transition-colors border-b last:border-0 dark:border-white/5 border-black/5"
                                        :class="currentSort === opt.value ? 'text-primary bg-primary/5' : 'text-white/60 hover:text-white hover:bg-white/5'"
                                    >{{ opt.label }}</button>
                                </div>
                            </transition>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Mobile Horizontal Category Pills -->
            <div class="md:hidden sticky top-20 z-40 -mx-4 px-4 py-4 bg-background/80 backdrop-blur-md mb-8 border-b dark:border-white/5 border-black/5">
                <div class="flex gap-3 overflow-x-auto no-scrollbar pb-2">
                    <button 
                        @click="filterByCategory('all')"
                        class="shrink-0 px-6 py-2.5 rounded-full text-[10px] font-headline font-bold uppercase tracking-[0.2em] transition-all"
                        :class="!filters.category || filters.category === 'all' ? 'bg-primary text-black shadow-[0_0_20px_rgba(57,255,20,0.4)]' : 'bg-white/5 border dark:border-white/10 border-black/10 dark:text-white/60 text-black/60'"
                    >
                        All
                    </button>
                    <button 
                        v-for="category in categories" :key="category.id"
                        @click="filterByCategory(category.slug)"
                        class="shrink-0 px-6 py-2.5 rounded-full text-[10px] font-headline font-bold uppercase tracking-[0.2em] transition-all"
                        :class="filters.category === category.slug ? 'bg-primary text-black shadow-[0_0_20px_rgba(57,255,20,0.4)]' : 'bg-white/5 border dark:border-white/10 border-black/10 dark:text-white/60 text-black/60'"
                    >
                        {{ category.name }}
                    </button>
                </div>
            </div>

            <!-- Active Filter Chip + Result Count -->
            <div class="flex items-center justify-between gap-4 mb-12 flex-wrap">
                <!-- Result count -->
                <span class="text-[10px] dark:text-white/30 text-black/30 font-headline font-medium uppercase tracking-[0.3em]">
                    Showing {{ products.length }} {{ products.length === 1 ? 'piece' : 'pieces' }}
                </span>
                <!-- Active category chip (Hidden on mobile because of pills) -->
                <transition name="fade">
                    <button
                        v-if="activeCategory"
                        @click="filterByCategory('all')"
                        class="hidden md:flex items-center gap-2 px-4 py-2 bg-primary/10 border border-primary/30 rounded-full text-[10px] font-headline font-bold text-primary hover:bg-primary/20 transition-all uppercase tracking-widest"
                    >
                        <span>{{ activeCategory.name }}</span>
                        <span class="material-symbols-outlined text-[14px]">close</span>
                    </button>
                </transition>
            </div>

            <div class="flex flex-col md:flex-row gap-12">
                <!-- Sidebar Filters -->
                <aside class="hidden md:block w-full md:w-64 flex-shrink-0 relative reveal reveal-delay-200">
                    <!-- Subtle glass background for the sidebar in Luminous Dark theme -->
                    <div class="sticky top-40 space-y-12 glass-panel p-6 rounded-sm shadow-2xl">
                        
                        <!-- Category -->
                        <section>
                            <h3 class="text-xs font-headline tracking-[0.15em] mb-6 dark:text-white text-on-surface font-medium border-b dark:border-white/10 border-black/10 pb-2">Category</h3>
                            <ul class="space-y-4 text-sm font-body tracking-wide dark:text-white/40 text-black/40">
                                <!-- All Categories option -->
                                <li class="flex items-center justify-between group cursor-pointer transition-colors"
                                    :class="!filters.category || filters.category === 'all' ? 'text-primary luminous-glow' : 'hover:text-primary'"
                                    @click="filterByCategory('all')">
                                    <span :class="!filters.category || filters.category === 'all' ? 'font-medium' : ''">All Pieces</span>
                                </li>
                                
                                <li v-for="category in categories" :key="category.id"
                                    @click="filterByCategory(category.slug)"
                                    class="flex items-center justify-between group cursor-pointer transition-colors"
                                    :class="filters.category === category.slug ? 'text-primary' : 'hover:text-primary'">
                                    
                                    <span :class="filters.category === category.slug ? 'font-medium underline underline-offset-8 decoration-primary-container' : ''">
                                        {{ category.name }}
                                    </span>
                                    <span class="text-[10px] opacity-50 group-hover:opacity-100 transition-opacity">
                                        <span class="material-symbols-outlined text-xs">chevron_right</span>
                                    </span>
                                </li>
                            </ul>
                        </section>

                        <!-- Price Range (Visual Polish only for mapping Carve Arts) -->
                        <section>
                            <h3 class="text-xs font-headline tracking-[0.15em] mb-6 dark:text-white text-on-surface font-medium border-b dark:border-white/10 border-black/10 pb-2">Price range</h3>
                            <div class="space-y-6">
                                <div class="relative h-px bg-outline-variant/30 w-full mb-3">
                                    <div class="absolute h-px bg-primary w-1/2 left-1/4 shadow-[0_0_10px_rgba(57, 255, 20,0.5)]"></div>
                                    <div class="absolute -top-1.5 left-1/4 w-3 h-3 rounded-full bg-surface border-2 border-primary cursor-pointer hover:scale-125 transition-transform"></div>
                                    <div class="absolute -top-1.5 left-3/4 w-3 h-3 rounded-full bg-surface border-2 border-primary cursor-pointer hover:scale-125 transition-transform"></div>
                                </div>
                                <div class="flex justify-between text-[11.5px] font-headline font-medium tracking-[0.1em] dark:text-white text-on-surface">
                                    <span>$100</span>
                                    <span class="text-primary">$2,500+</span>
                                </div>
                            </div>
                        </section>

                        <!-- Style (Visual Polish) -->
                        <section>
                            <h3 class="text-xs font-headline tracking-[0.15em] mb-6 dark:text-white text-on-surface font-medium border-b dark:border-white/10 border-black/10 pb-2">Style</h3>
                            <div class="flex flex-wrap gap-2">
                                <button class="px-3 py-1.5 border border-outline-variant/40 rounded text-[11px] font-headline font-medium tracking-widest hover:border-primary hover:text-primary transition-colors bg-transparent-high/50">Minimalist</button>
                                <button class="px-3 py-1.5 border border-primary rounded text-[11px] font-headline font-medium tracking-widest text-primary shadow-[0_0_15px_rgba(57, 255, 20,0.1)]">Avant-Garde</button>
                                <button class="px-3 py-1.5 border border-outline-variant/40 rounded text-[11px] font-headline font-medium tracking-widest hover:border-primary hover:text-primary transition-colors bg-transparent-high/50">Heritage</button>
                                <button class="px-3 py-1.5 border border-outline-variant/40 rounded text-[11px] font-headline font-medium tracking-widest hover:border-primary hover:text-primary transition-colors bg-transparent-high/50">Custom</button>
                            </div>
                        </section>
                    </div>
                </aside>

                <!-- Product Grid -->
                <div class="flex-1">
                    
                    <div v-if="products.length === 0" class="flex flex-col items-center justify-center py-48 text-center reveal">
                        <div class="relative mb-12">
                            <span class="material-symbols-outlined text-[120px] dark:text-white text-on-surface/5 text-black/5 select-none animate-pulse">precision_manufacturing</span>
                            <span class="absolute inset-0 flex items-center justify-center material-symbols-outlined text-4xl text-primary/20">search_off</span>
                        </div>
                        <h3 class="text-3xl font-headline dark:text-white text-on-surface dark:text-white text-on-surface font-medium mb-4">No Pieces Detected.</h3>
                        <p class="dark:text-white/40 text-black/40 font-body text-sm max-w-sm mx-auto leading-relaxed tracking-widest opacity-60">
                            The current filter configuration returned zero matches from the studio collection.
                        </p>
                        <button 
                            @click="filterByCategory('all')" 
                            class="mt-12 group flex flex-col items-center gap-4 transition-all"
                        >
                            <span class="text-[11.5px] font-headline font-medium tracking-[0.4em] text-primary dark:group-hover:dark:text-white text-on-surface group-hover:dark:text-white text-on-surface">Reset filters</span>
                            <div class="w-px h-12 bg-primary group-hover:bg-primary transition-all group-hover:h-20"></div>
                        </button>
                    </div>

                    <div :class="[
                        'grid gap-y-16 transition-all duration-700',
                        currentLayout === 'editorial' ? 'grid-cols-1 lg:grid-cols-2 gap-x-12' : 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8'
                    ]">
                        
                        <!-- Product Card Loop -->
                        <ProductCard 
                            v-for="(product, idx) in products" 
                            :key="product.id" 
                            :product="product"
                            :layout="currentLayout"
                            :class="'reveal-delay-' + ((idx % 3) * 100)"
                        />

                    </div>

                    <!-- Load More / Pagination placeholder -->
                    <div v-if="products.length > 0" class="mt-32 flex justify-center reveal">
                        <button class="group flex flex-col items-center gap-4">
                            <span class="text-xs font-headline font-medium tracking-[0.3em] dark:text-white/40 text-black/40 group-hover:text-primary transition-colors drop-shadow-md">Show more pieces</span>
                            <div class="w-px h-16 bg-outline-variant/30 group-hover:bg-primary transition-colors origin-top group-hover:scale-y-125 duration-500 shadow-[0_0_10px_rgba(57, 255, 20,0.3)]"></div>
                        </button>
                    </div>
                </div>
            </div>
        </main>
        </div>
    </StorefrontLayout>
</template>

<style scoped>
/* Repeating scan-line grid overlay */
.scan-lines {
    background-image: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(255, 255, 255, 0.04) 2px,
        rgba(255, 255, 255, 0.04) 3px
    );
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
