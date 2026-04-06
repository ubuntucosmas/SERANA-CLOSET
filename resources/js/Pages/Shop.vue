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
const showFilters = ref(false);
// ── Cinematic Background System ──────────────────────────────────────────────
const bgImages = [
    '/images/White hoodie with silver zipper details.png',
    '/images/Black and white hoodies showcase.png',
    '/images/beige_contrast_set.png',
    '/images/navy_contrast_set.png',
    '/images/Black zip-up hoodie on gray background.png',
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
            <!-- Filter Drawer (Slide-over) -->
            <transition name="slide-right">
                <aside v-if="showFilters" class="fixed inset-y-0 left-0 w-80 md:w-96 glass-panel z-[100] shadow-[30px_0_60px_rgba(0,0,0,0.5)] p-10 overflow-y-auto no-scrollbar">
                    <div class="flex justify-between items-start mb-12">
                        <div>
                            <span class="text-[10px] text-primary tracking-[0.4em] uppercase font-bold mb-2 block">Studio Refinement</span>
                            <h2 class="serif-text text-3xl font-light dark:text-white text-on-surface">Filters</h2>
                        </div>
                        <button @click="showFilters = false" class="material-symbols-outlined dark:text-white/40 text-black/40 hover:text-primary transition-colors">close</button>
                    </div>

                    <div class="space-y-16">
                        <!-- Category -->
                        <section>
                            <h3 class="text-[10px] font-headline tracking-[0.3em] mb-8 dark:text-white text-on-surface font-bold uppercase opacity-40">Classification</h3>
                            <ul class="space-y-6 text-[13px] font-body tracking-widest dark:text-white/60 text-black/60">
                                <li class="flex items-center justify-between group cursor-pointer transition-colors"
                                    :class="!filters.category || filters.category === 'all' ? 'text-primary' : 'hover:dark:text-white text-on-surface'"
                                    @click="filterByCategory('all')">
                                    <span :class="!filters.category || filters.category === 'all' ? 'font-black' : ''">All Collections</span>
                                    <span v-if="!filters.category || filters.category === 'all'" class="w-1 h-1 rounded-full bg-primary luminous-glow"></span>
                                </li>
                                
                                <li v-for="category in categories" :key="category.id"
                                    @click="filterByCategory(category.slug)"
                                    class="flex items-center justify-between group cursor-pointer transition-colors"
                                    :class="filters.category === category.slug ? 'text-primary' : 'hover:dark:text-white text-on-surface'">
                                    <span :class="filters.category === category.slug ? 'font-black' : ''">{{ category.name }}</span>
                                    <span v-if="filters.category === category.slug" class="w-1 h-1 rounded-full bg-primary luminous-glow"></span>
                                </li>
                            </ul>
                        </section>

                        <!-- Price Range -->
                        <section>
                            <h3 class="text-[10px] font-headline tracking-[0.3em] mb-8 dark:text-white text-on-surface font-bold uppercase opacity-40">Value Range</h3>
                            <div class="space-y-8">
                                <div class="relative h-px bg-white/10 w-full">
                                    <div class="absolute h-px bg-primary w-1/2 left-1/4 shadow-[0_0_15px_#B9C3FF]"></div>
                                    <div class="absolute -top-1.5 left-1/4 w-3 h-3 bg-black border border-primary cursor-pointer hover:scale-125 transition-transform"></div>
                                    <div class="absolute -top-1.5 left-3/4 w-3 h-3 bg-black border border-primary cursor-pointer hover:scale-125 transition-transform"></div>
                                </div>
                                <div class="flex justify-between text-[10px] font-headline font-bold tracking-widest dark:text-white text-on-surface opacity-60 uppercase">
                                    <span>KSh 500</span>
                                    <span>KSh 20,000+</span>
                                </div>
                            </div>
                        </section>

                        <!-- Style Tags -->
                        <section>
                            <h3 class="text-[10px] font-headline tracking-[0.3em] mb-8 dark:text-white text-on-surface font-bold uppercase opacity-40">Atmosphere</h3>
                            <div class="flex flex-wrap gap-3">
                                <button v-for="tag in ['Minimalist', 'Avant-Garde', 'Heritage', 'Custom']" :key="tag"
                                    class="px-4 py-2 border dark:border-white/10 border-black/10 text-[9px] font-bold tracking-[0.2em] uppercase hover:border-primary hover:text-primary transition-all bg-white/5 active:scale-95">
                                    {{ tag }}
                                </button>
                            </div>
                        </section>
                    </div>
                </aside>
            </transition>

            <!-- Backdrop -->
            <transition name="fade">
                <div v-if="showFilters" @click="showFilters = false" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[90]"></div>
            </transition>

            <main class="relative z-10 pt-32 pb-24 max-w-screen-2xl mx-auto px-4 sm:px-8 font-body">
                <!-- Page Header -->
                <header class="mb-12 reveal">
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-10">
                        <div>
                            <div class="flex items-center gap-4 text-primary font-headline tracking-[0.3em] text-[10px] font-bold mb-6 uppercase">
                                <span class="w-12 h-px bg-primary/40"></span>
                                Serana Archive
                            </div>
                            <h1 class="serif-text text-7xl md:text-[10rem] dark:text-white text-on-surface font-light tracking-tighter leading-[0.8] mb-6">
                                The<br/>Collection
                            </h1>
                            
                            <!-- Active Filter Overview & Action -->
                            <div class="flex items-center gap-8 mt-12">
                                <button @click="showFilters = true" class="flex items-center gap-4 group p-1">
                                    <div class="w-12 h-12 bg-primary/10 border border-primary/20 flex items-center justify-center group-hover:bg-primary transition-all duration-500">
                                        <span class="material-symbols-outlined text-primary group-hover:text-black">tune</span>
                                    </div>
                                    <div class="text-left">
                                        <p class="text-[10px] text-primary tracking-[0.3em] font-bold uppercase">Classification</p>
                                        <p class="text-[13px] dark:text-white text-on-surface font-medium tracking-widest">{{ activeCategory?.name || 'All Collections' }}</p>
                                    </div>
                                </button>

                                <div class="w-px h-12 bg-white/5"></div>

                                <div class="hidden lg:block text-left">
                                    <p class="text-[10px] dark:text-white text-on-surface/40 tracking-[0.3em] font-bold uppercase mb-1">Archive Size</p>
                                    <p class="text-[13px] dark:text-white text-on-surface font-medium tracking-widest">{{ products.length }} Pieces</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-8">
                            <!-- Layout Toggle -->
                            <div class="flex items-center glass-panel p-1 rounded-sm">
                                <button @click="currentLayout = 'grid'" 
                                        class="p-2.5 flex items-center justify-center transition-all"
                                        :class="currentLayout === 'grid' ? 'bg-primary text-black shadow-[0_0_15px_#B9C3FF]' : 'dark:text-white/40 text-black/40 hover:dark:text-white text-on-surface'">
                                    <span class="material-symbols-outlined text-[20px]">grid_view</span>
                                </button>
                                <button @click="currentLayout = 'editorial'" 
                                        class="p-2.5 flex items-center justify-center transition-all"
                                        :class="currentLayout === 'editorial' ? 'bg-primary text-black shadow-[0_0_15px_#B9C3FF]' : 'dark:text-white/40 text-black/40 hover:dark:text-white text-on-surface'">
                                    <span class="material-symbols-outlined text-[20px]">view_agenda</span>
                                </button>
                            </div>

                            <!-- Sort Dropdown -->
                            <div class="relative">
                                <button @click="showSortMenu = !showSortMenu" class="flex items-center gap-3 text-[10px] font-bold tracking-[0.3em] dark:text-white/40 text-black/40 hover:text-primary transition-colors group uppercase">
                                    <span class="dark:text-white text-on-surface/20">Sort</span>
                                    <span class="dark:text-white text-on-surface">{{ currentSortLabel }}</span>
                                    <span class="material-symbols-outlined text-[18px] transition-transform duration-500" :class="showSortMenu ? 'rotate-180' : ''">expand_more</span>
                                </button>
                                <transition name="fade">
                                    <div v-if="showSortMenu" class="glass-panel absolute right-0 top-10 z-50 w-56 rounded-sm shadow-2xl overflow-hidden py-2 animate-in slide-in-from-top-2 duration-500">
                                        <button
                                            v-for="opt in sortOptions" :key="opt.value"
                                            @click="filterBySort(opt.value)"
                                            class="w-full text-left px-6 py-4 text-[10px] font-bold tracking-[0.2em] uppercase transition-colors"
                                            :class="currentSort === opt.value ? 'text-primary bg-primary/5' : 'text-white/60 hover:text-white hover:bg-white/5'"
                                        >{{ opt.label }}</button>
                                    </div>
                                </transition>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="w-full">
                    
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

.slide-right-enter-active,
.slide-right-leave-active {
    transition: transform 0.8s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.8s ease;
}

.slide-right-enter-from,
.slide-right-leave-to {
    transform: translateX(-100%);
    opacity: 0;
}
</style>
