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
const currentLayout = ref('grid'); // Keep grid as standard

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

// Dual-slot cross-fade
const slotA = ref(bgImages[0]);
const slotB = ref(bgImages[1]);
const showA = ref(true);
let bgInterval = null;
let bgIndex = ref(1);

onMounted(() => {
    bgInterval = setInterval(() => {
        bgIndex.value = (bgIndex.value + 1) % bgImages.length;
        if (showA.value) {
            slotB.value = bgImages[bgIndex.value];
            showA.value = false;
        } else {
            slotA.value = bgImages[bgIndex.value];
            showA.value = true;
        }
    }, 7000);
});
onUnmounted(() => clearInterval(bgInterval));

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
            <title>{{ activeCategory ? `${activeCategory.name} | Archive` : 'The Collection' }} | Serana Closet</title>
        </Head>

        <div class="relative">
            <!-- Cinematic Shifting Background -->
            <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <img :src="slotA" class="absolute inset-0 w-full h-full object-cover scale-110 grayscale brightness-50 blur-[2px] transition-opacity duration-[3000ms]" :class="showA ? 'opacity-30' : 'opacity-0'" alt="" />
                <img :src="slotB" class="absolute inset-0 w-full h-full object-cover scale-110 grayscale brightness-50 blur-[2px] transition-opacity duration-[3000ms]" :class="showA ? 'opacity-0' : 'opacity-30'" alt="" />
                <div class="absolute inset-0" style="background: radial-gradient(ellipse 70% 80% at 50% 40%, transparent 30%, rgba(0,0,0,0.6) 70%, rgba(0,0,0,0.9) 100%);"></div>
                <div class="absolute inset-x-0 bottom-0 h-64 bg-gradient-to-t from-background via-background/80 to-transparent"></div>
            </div>

            <main class="relative z-10 pt-24 lg:pt-32 pb-24 max-w-screen-2xl mx-auto px-4 sm:px-8 font-body">
                <header class="mb-10 lg:mb-16 reveal">
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-8">
                        <div>
                            <h1 class="serif-text text-4xl lg:text-8xl dark:text-white text-on-surface font-light tracking-tighter">
                                <span class="text-primary luminous-glow">Our</span> Collection
                            </h1>
                        </div>
                        
                        <div class="flex items-center justify-between md:justify-end gap-4 w-full md:w-auto">
                            <!-- Mobile Sort (Professional Selection Matrix) -->
                            <div class="md:hidden flex items-center bg-white/5 border dark:border-white/10 border-black/10 rounded-sm overflow-hidden">
                                <button @click="showSortMenu = !showSortMenu" class="px-6 py-3.5 flex items-center gap-3 active:bg-primary/5 transition-colors">
                                    <span class="text-[9px] font-headline font-black uppercase tracking-[0.2em] dark:text-white text-on-surface opacity-40">Filter_by:</span>
                                    <span class="text-[10px] font-headline font-black uppercase tracking-[0.2em] text-primary">{{ currentSortLabel }}</span>
                                    <span class="material-symbols-outlined text-[16px] text-primary transition-transform duration-300" :class="showSortMenu ? 'rotate-180' : ''">expand_more</span>
                                </button>
                            </div>

                            <!-- Desktop Sort -->
                            <div class="relative hidden md:block">
                                <button @click="showSortMenu = !showSortMenu" class="flex items-center gap-2 text-[10px] font-semibold tracking-widest dark:text-white/40 text-black/40 hover:text-primary transition-colors group px-6 py-3 bg-white/5 border dark:border-white/10 border-black/10 rounded-sm">
                                    <span class="dark:text-white text-on-surface/50">Sort:</span>
                                    <span class="dark:text-white text-on-surface font-medium uppercase">{{ currentSortLabel }}</span>
                                    <span class="material-symbols-outlined text-[16px] transition-transform duration-300" :class="showSortMenu ? 'rotate-180' : ''">keyboard_arrow_down</span>
                                </button>
                            </div>

                            <!-- Selection Matrix Menu -->
                            <transition name="fade">
                                <div v-if="showSortMenu" class="glass-panel absolute right-0 md:right-0 top-32 md:top-12 z-[100] w-full md:w-56 rounded-sm shadow-2xl overflow-hidden animate-tooltip-pop border-b border-primary/20">
                                    <button
                                        v-for="opt in sortOptions" :key="opt.value"
                                        @click="filterBySort(opt.value)"
                                        class="w-full text-left px-8 py-5 text-[10px] uppercase font-black tracking-[0.3em] transition-all border-b last:border-0 dark:border-white/5 border-black/5"
                                        :class="currentSort === opt.value ? 'text-primary bg-primary/5 pl-10' : 'text-white/40 hover:text-white hover:bg-white/5'"
                                    >
                                        <span v-if="currentSort === opt.value" class="absolute left-4 w-1 h-1 bg-primary rounded-full animate-pulse shadow-[0_0_10px_#39FF14]"></span>
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </transition>
                        </div>
                    </div>
                </header>

                <!-- Professional Segmenter (Categories - Mobile) -->
                <nav class="md:hidden sticky top-20 z-40 bg-background/60 backdrop-blur-2xl border-b dark:border-white/10 border-black/10 -mx-4 px-4 py-8 mb-4">
                    <div class="flex gap-4 overflow-x-auto no-scrollbar scroll-smooth w-full px-2">
                        <button 
                            @click="filterByCategory('all')"
                            class="shrink-0 relative px-8 py-4 text-[10px] font-headline font-black uppercase tracking-[0.2em] transition-all rounded-full border border-transparent"
                            :class="!filters.category || filters.category === 'all' 
                                ? 'bg-primary text-black shadow-[0_15px_30px_rgba(57,255,20,0.4)]' 
                                : 'dark:text-white/40 text-black/40 bg-white/5 border-white/5'"
                        >
                            All Collections
                        </button>
                        
                        <button 
                            v-for="category in categories" :key="category.id"
                            @click="filterByCategory(category.slug)"
                            class="shrink-0 relative px-8 py-4 text-[10px] font-headline font-black uppercase tracking-[0.2em] transition-all rounded-full border border-transparent"
                            :class="filters.category === category.slug 
                                ? 'bg-primary text-black shadow-[0_15px_30px_rgba(57,255,20,0.4)]' 
                                : 'dark:text-white/40 text-black/40 bg-white/5 border-white/5'"
                        >
                            {{ category.name }}
                        </button>
                    </div>
                </nav>

                <!-- Status & Count Bar -->
                <div class="flex items-center justify-between gap-4 mb-8 lg:mb-12 flex-wrap">
                    <span class="text-[10px] dark:text-white/30 text-black/30 font-headline font-medium uppercase tracking-[0.4em]">
                        Found {{ products.length }} {{ products.length === 1 ? 'archive piece' : 'archive pieces' }}
                    </span>
                    <transition name="fade">
                        <button
                            v-if="activeCategory"
                            @click="filterByCategory('all')"
                            class="hidden md:flex items-center gap-2 px-5 py-2.5 bg-primary/10 border border-primary/30 rounded-full text-[10px] font-headline font-bold text-primary hover:bg-primary/20 transition-all uppercase tracking-widest"
                        >
                            <span>{{ activeCategory.name }}</span>
                            <span class="material-symbols-outlined text-[14px]">close</span>
                        </button>
                    </transition>
                </div>

                <div class="flex flex-col md:flex-row gap-12">
                    <!-- Desktop Sidebar -->
                    <aside class="hidden md:block w-72 flex-shrink-0 relative reveal reveal-delay-200">
                        <div class="sticky top-40 space-y-12 glass-panel p-8 rounded-sm shadow-2xl">
                            <section>
                                <h3 class="text-xs font-headline tracking-[0.2em] mb-8 dark:text-white text-on-surface font-medium border-b dark:border-white/10 border-black/10 pb-3 uppercase">Collections</h3>
                                <ul class="space-y-5 text-[13px] font-body tracking-widest dark:text-white/40 text-black/40">
                                    <li class="flex items-center justify-between group cursor-pointer transition-colors"
                                        :class="!filters.category || filters.category === 'all' ? 'text-primary' : 'hover:text-primary'"
                                        @click="filterByCategory('all')">
                                        <span :class="!filters.category || filters.category === 'all' ? 'font-black' : ''">All Pieces</span>
                                    </li>
                                    <li v-for="category in categories" :key="category.id"
                                        @click="filterByCategory(category.slug)"
                                        class="flex items-center justify-between group cursor-pointer transition-colors"
                                        :class="filters.category === category.slug ? 'text-primary' : 'hover:text-primary'">
                                        <span :class="filters.category === category.slug ? 'font-black' : ''">{{ category.name }}</span>
                                    </li>
                                </ul>
                            </section>

                            <section>
                                <h3 class="text-xs font-headline tracking-[0.2em] mb-8 dark:text-white text-on-surface font-medium border-b dark:border-white/10 border-black/10 pb-3 uppercase">Refinement</h3>
                                <div class="space-y-6">
                                    <div class="relative h-px bg-outline-variant/30 w-full mb-3">
                                        <div class="absolute h-px bg-primary w-1/2 left-1/4 shadow-[0_0_10px_#39FF14]"></div>
                                        <div class="absolute -top-1.5 left-1/4 w-3 h-3 rounded-full bg-surface border-2 border-primary"></div>
                                        <div class="absolute -top-1.5 left-3/4 w-3 h-3 rounded-full bg-surface border-2 border-primary"></div>
                                    </div>
                                    <div class="flex justify-between text-[11px] font-headline font-black tracking-widest text-primary">
                                        <span>$100</span>
                                        <span>$2,500+</span>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </aside>

                    <!-- Main Grid -->
                    <div class="flex-1">
                        <div v-if="products.length === 0" class="flex flex-col items-center justify-center py-48 text-center reveal">
                            <span class="material-symbols-outlined text-[120px] dark:text-white/5 text-black/5 animate-pulse">precision_manufacturing</span>
                            <h3 class="text-3xl font-headline dark:text-white text-on-surface font-medium mt-8 mb-4 uppercase tracking-tighter">Selection_Empty</h3>
                            <button @click="filterByCategory('all')" class="mt-8 group flex flex-col items-center gap-4">
                                <span class="text-[10px] font-headline font-black tracking-[0.5em] text-primary uppercase">Reset Filters_</span>
                                <div class="w-px h-12 bg-primary group-hover:h-20 transition-all"></div>
                            </button>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-16">
                            <ProductCard 
                                v-for="(product, idx) in products" 
                                :key="product.id" 
                                :product="product"
                                layout="grid"
                                :class="'reveal-delay-' + ((idx % 3) * 100)"
                            />
                        </div>

                        <div v-if="products.length > 0" class="mt-32 flex justify-center reveal">
                            <button class="group flex flex-col items-center gap-6">
                                <span class="text-[10px] font-headline font-black tracking-[0.4em] dark:text-white/40 text-black/40 group-hover:text-primary transition-colors">Show more archive pieces</span>
                                <div class="w-px h-20 bg-outline-variant/30 group-hover:bg-primary transition-all origin-top group-hover:scale-y-125 shadow-[0_0_10px_#39FF14]"></div>
                            </button>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </StorefrontLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(10px); }

@keyframes tooltipPop {
    from { opacity: 0; transform: translateY(10px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-tooltip-pop { animation: tooltipPop 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
</style>
