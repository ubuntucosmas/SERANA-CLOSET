<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

import { useCartStore } from '@/Stores/useCartStore';
import ProductCard from '@/Components/ProductCard.vue';
import XRayOverlay from '@/Components/XRayOverlay.vue';
import SectionBackground from '@/Components/SectionBackground.vue';

const props = defineProps({
    products: Array,
    categories: Array,
    filters: Object
});

const cart = useCartStore();
const page = usePage();
const whatsappUrl = computed(() => `https://wa.me/${page.props.whatsapp_number}`);
const currentLayout = ref('grid'); 

const showLogisticsOverlay = ref(false);

// ── Cinematic Background System ──────────────────────────────────────────────
// Unified via SectionBackground.vue

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

        <div class="relative min-h-screen bg-surface-container/30">
            <!-- Cinematic Video Background -->
            <SectionBackground video="/videos/heritage_loop.mp4" opacity="opacity-60" blur="blur-sm" />

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
            <div class="md:hidden fixed bottom-28 left-1/2 -translate-x-1/2 z-[100] w-[90%] max-w-[400px]">
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
            <transition name="drawer">
                <div v-if="showLogisticsOverlay" class="md:hidden fixed inset-0 z-[200]">
                    <!-- Backdrop -->
                    <div @click="showLogisticsOverlay = false" class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity"></div>
                    
                    <!-- Drawer Content -->
                    <div class="absolute right-0 top-0 bottom-0 w-[85%] max-w-[360px] bg-background border-l border-white/5 p-8 flex flex-col shadow-[-20px_0_60px_rgba(0,0,0,0.5)]">
                        <div class="flex justify-between items-center mb-12">
                            <span class="text-[9px] font-black uppercase tracking-[0.4em] text-primary">Studio_Curation</span>
                            <button @click="showLogisticsOverlay = false" class="text-white/40 hover:text-white transition-colors">
                                <span class="material-symbols-outlined text-2xl">close</span>
                            </button>
                        </div>

                        <div class="flex-grow space-y-12 overflow-y-auto no-scrollbar">
                            <section>
                                <h3 class="text-[8px] font-black uppercase tracking-[0.3em] text-white/20 mb-6">Discovery_category</h3>
                                <div class="grid grid-cols-1 gap-3">
                                    <button 
                                        @click="filterByCategory('all')" 
                                        class="text-left px-5 py-3 border rounded-sm text-[11px] uppercase tracking-widest transition-all"
                                        :class="!filters.category || filters.category === 'all' ? 'bg-primary text-black border-primary font-black' : 'bg-white/5 text-white/40 border-white/5'"
                                    >
                                        All Collections
                                    </button>
                                    <button 
                                        v-for="category in categories" :key="category.id" 
                                        @click="filterByCategory(category.slug)" 
                                        class="text-left px-5 py-3 border rounded-sm text-[11px] uppercase tracking-widest transition-all"
                                        :class="filters.category === category.slug ? 'bg-primary text-black border-primary font-black' : 'bg-white/5 text-white/40 border-white/5'"
                                    >
                                        {{ category.name }}
                                    </button>
                                </div>
                            </section>

                            <section>
                                <h3 class="text-[8px] font-black uppercase tracking-[0.3em] text-white/20 mb-6">Archive_sort</h3>
                                <div class="grid grid-cols-1 gap-3">
                                    <button 
                                        v-for="opt in sortOptions" :key="opt.value" 
                                        @click="filterBySort(opt.value)" 
                                        class="text-left px-5 py-3 border rounded-sm text-[10px] uppercase font-black tracking-widest transition-all" 
                                        :class="currentSort === opt.value ? 'bg-white text-black border-white' : 'bg-white/5 text-white/20 border-white/5'"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </section>
                        </div>

                        <div class="mt-auto pt-8 border-t border-white/5">
                            <p class="text-[8px] text-center dark:text-white/20 text-black/20 uppercase tracking-[0.3em]">Serana Closet Studio Control v2.0</p>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </StorefrontLayout>

    <!-- Mobile Floating WhatsApp Button -->
    <a :href="whatsappUrl" target="_blank"
        class="lg:hidden fixed bottom-6 right-6 z-50 w-14 h-14 rounded-full shadow-2xl flex items-center justify-center transition-transform hover:scale-110 active:scale-95"
        style="background-color:#25D366"
        title="Chat on WhatsApp"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 fill-white" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.57-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    </a>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.5s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.studio-pop-enter-active, .studio-pop-leave-active { transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.studio-pop-enter-from { opacity: 0; transform: translateY(100%); }
.studio-pop-leave-to { opacity: 0; transform: translateY(100%); }

.drawer-enter-active, .drawer-leave-active { transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.drawer-enter-active .bg-background { transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.drawer-enter-from { opacity: 0; }
.drawer-enter-from .bg-background { transform: translateX(100%); }
.drawer-leave-to { opacity: 0; }
.drawer-leave-to .bg-background { transform: translateX(100%); }

@keyframes tooltipPop {
    from { opacity: 0; transform: translateY(10px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.animate-tooltip-pop { animation: tooltipPop 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
</style>
