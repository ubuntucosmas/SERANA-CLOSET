<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
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
const currentLayout = ref('grid'); 

const showLogisticsOverlay = ref(false);

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
    showLogisticsOverlay.value = false;
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
    showLogisticsOverlay.value = false;
    router.get(route('shop'), { category: props.filters?.category, sort: value }, { preserveState: true, replace: true });
};

const activeCategory = computed(() => {
    if (!props.filters?.category || props.filters.category === 'all') return null;
    return props.categories?.find(c => c.slug === props.filters.category) ?? null;
});

// Prevent body scroll when overlay is open
watch(showLogisticsOverlay, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
});
</script>

<template>
    <StorefrontLayout>
        <Head>
            <title>{{ activeCategory ? `${activeCategory.name} | Archive` : 'The Collection' }} | Serana Closet</title>
        </Head>

        <div class="relative min-h-screen bg-background">
            <!-- Cinematic Shifting Background -->
            <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none">
                <img :src="slotA" class="absolute inset-0 w-full h-full object-cover scale-110 grayscale brightness-[0.3] blur-[2px] transition-opacity duration-[3000ms]" :class="showA ? 'opacity-30' : 'opacity-0'" alt="" />
                <img :src="slotB" class="absolute inset-0 w-full h-full object-cover scale-110 grayscale brightness-[0.3] blur-[2px] transition-opacity duration-[3000ms]" :class="showA ? 'opacity-0' : 'opacity-30'" alt="" />
                <div class="absolute inset-0 bg-gradient-to-t from-background via-transparent to-transparent"></div>
            </div>

            <main class="relative z-10 pt-24 lg:pt-32 pb-48 max-w-screen-2xl mx-auto px-4 sm:px-8">
                <header class="mb-12 lg:mb-20 reveal">
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-10">
                        <div>
                            <h1 class="text-5xl lg:text-9xl dark:text-white text-on-surface font-light tracking-tighter leading-none">
                                <span class="text-primary font-medium">Studio</span><br/>Collection_
                            </h1>
                            <p class="mt-6 text-[11px] uppercase tracking-[0.4em] dark:text-white/30 text-black/30 hidden md:block">Engineered for timeless confidence.</p>
                        </div>
                        
                        <!-- Desktop Logistics Row -->
                        <div class="hidden md:flex items-center gap-6">
                            <div class="relative group">
                                <button @click="showSortMenu = !showSortMenu" class="px-8 py-4 bg-white/5 border border-white/10 rounded-sm flex items-center gap-4 hover:border-primary transition-all group">
                                    <span class="text-[10px] uppercase font-black tracking-widest opacity-40">Sort_by:</span>
                                    <span class="text-[10px] uppercase font-black tracking-[0.2em] text-primary">{{ currentSortLabel }}</span>
                                    <span class="material-symbols-outlined text-[16px] text-primary transition-transform" :class="showSortMenu ? 'rotate-180' : ''">expand_more</span>
                                </button>
                                <transition name="fade">
                                    <div v-if="showSortMenu" class="absolute right-0 top-full mt-2 w-64 bg-surface border border-white/10 shadow-2xl z-[100] animate-tooltip-pop">
                                        <button v-for="opt in sortOptions" :key="opt.value" @click="filterBySort(opt.value)" class="w-full text-left px-6 py-4 text-[10px] uppercase font-black tracking-widest hover:bg-primary/5 hover:text-primary transition-all border-b border-white/5 last:border-0" :class="currentSort === opt.value ? 'text-primary' : 'text-white/40'">
                                            {{ opt.label }}
                                        </button>
                                    </div>
                                </transition>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="flex flex-col md:flex-row gap-16">
                    <!-- Desktop Sidebar -->
                    <aside class="hidden md:block w-72 flex-shrink-0 relative">
                        <div class="sticky top-40 space-y-16">
                            <section>
                                <h3 class="text-[10px] font-black tracking-[0.3em] mb-8 uppercase text-primary border-b border-white/10 pb-4">Categories_</h3>
                                <div class="flex flex-col gap-6">
                                    <button @click="filterByCategory('all')" class="text-left text-[14px] uppercase tracking-widest transition-all" :class="!filters.category || filters.category === 'all' ? 'text-white font-black pl-4 border-l-2 border-primary' : 'text-white/30 hover:text-white'">All Collections</button>
                                    <button v-for="category in categories" :key="category.id" @click="filterByCategory(category.slug)" class="text-left text-[14px] uppercase tracking-widest transition-all" :class="filters.category === category.slug ? 'text-white font-black pl-4 border-l-2 border-primary' : 'text-white/30 hover:text-white'">{{ category.name }}</button>
                                </div>
                            </section>
                        </div>
                    </aside>

                    <!-- Main Grid -->
                    <div class="flex-1">
                        <div v-if="products.length === 0" class="flex flex-col items-center justify-center py-48 text-center">
                            <h3 class="text-4xl font-light dark:text-white text-on-surface uppercase tracking-tighter mb-8">No pieces found_</h3>
                            <button @click="filterByCategory('all')" class="text-[10px] font-black uppercase tracking-[0.5em] text-primary border-b border-primary/40 pb-2">Reset Studio_</button>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 gap-x-4 md:gap-x-8 gap-y-12 md:gap-y-20">
                            <ProductCard v-for="product in products" :key="product.id" :product="product" layout="grid" />
                        </div>
                    </div>
                </div>
            </main>

            <!-- Zen Logistics Hub (Mobile Only) -->
            <div class="md:hidden fixed bottom-8 left-1/2 -translate-x-1/2 z-[100] w-[90%] max-w-[400px]">
                <button @click="showLogisticsOverlay = true" class="w-full bg-white text-black py-5 px-8 rounded-sm shadow-[0_30px_60px_rgba(0,0,0,0.5)] flex items-center justify-between group active:scale-95 transition-all">
                    <div class="flex items-center gap-4">
                        <span class="text-[9px] font-black uppercase tracking-widest opacity-40">Showing</span>
                        <span class="text-[10px] font-black uppercase tracking-widest">{{ activeCategory?.name || 'All Pieces' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-1 h-1 bg-black/20 rounded-full"></span>
                        <span class="text-[10px] font-black">{{ products.length }}</span>
                        <span class="material-symbols-outlined text-[18px] ml-2">tune</span>
                    </div>
                </button>
            </div>

            <!-- Studio Control Overlay (Mobile Only) -->
            <transition name="studio-pop">
                <div v-if="showLogisticsOverlay" class="md:hidden fixed inset-0 z-[200] bg-black/95 backdrop-blur-3xl p-10 flex flex-col justify-between">
                    <div class="flex justify-between items-center">
                        <span class="text-[10px] font-black uppercase tracking-[0.5em] text-primary">Studio_Control</span>
                        <button @click="showLogisticsOverlay = false" class="text-white hover:text-primary transition-colors">
                            <span class="material-symbols-outlined text-[32px]">close</span>
                        </button>
                    </div>

                    <div class="space-y-16">
                        <section>
                            <h3 class="text-[9px] font-black uppercase tracking-[0.3em] text-white/30 mb-8">Category_selection</h3>
                            <div class="flex flex-col gap-8">
                                <button @click="filterByCategory('all')" class="text-left text-4xl font-light transition-all uppercase tracking-tighter" :class="!filters.category || filters.category === 'all' ? 'text-primary' : 'text-white/40'">All Collections</button>
                                <button v-for="category in categories" :key="category.id" @click="filterByCategory(category.slug)" class="text-left text-4xl font-light transition-all uppercase tracking-tighter" :class="filters.category === category.slug ? 'text-primary' : 'text-white/40'">{{ category.name }}</button>
                            </div>
                        </section>

                        <section>
                            <h3 class="text-[9px] font-black uppercase tracking-[0.3em] text-white/30 mb-8">Re-order_archive</h3>
                            <div class="grid grid-cols-1 gap-4">
                                <button v-for="opt in sortOptions" :key="opt.value" @click="filterBySort(opt.value)" class="text-left py-4 px-6 border rounded-sm text-[11px] uppercase font-black tracking-widest transition-all" :class="currentSort === opt.value ? 'bg-primary text-black border-primary' : 'bg-white/5 text-white/40 border-white/10'">
                                    {{ opt.label }}
                                </button>
                            </div>
                        </section>
                    </div>

                    <p class="text-[9px] text-center dark:text-white/20 text-black/20 uppercase tracking-[0.4em]">Serana Closet Studio Matrix</p>
                </div>
            </transition>
        </div>
    </StorefrontLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.5s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.studio-pop-enter-active, .studio-pop-leave-active { transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.studio-pop-enter-from { opacity: 0; transform: translateY(100%); }
.studio-pop-leave-to { opacity: 0; transform: translateY(100%); }

@keyframes tooltipPop {
    from { opacity: 0; transform: translateY(10px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-tooltip-pop { animation: tooltipPop 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
</style>
