<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import { ref, computed, watch } from "vue";
import { Head, router, useRemember } from "@inertiajs/vue3";
import StorefrontLayout from "@/Layouts/StorefrontLayout.vue";

import ProductCard from "@/Components/ProductCard.vue";
import { isSoldOut } from "@/Support/stock";

const props = defineProps({
    products: Array,
    categories: Array,
    filters: Object,
});

// Remembered in history, so closing a product (Back) returns to the same search.
const searchQuery = useRemember('', 'shop-search');

const clearFilters = () => {
    searchQuery.value = '';
    router.get(
        route("shop"),
        { sort: currentSort.value },
        { preserveState: true, replace: true },
    );
};

const filterByCategory = (slug) => {
    router.get(
        route("shop"),
        { category: slug, sort: currentSort.value },
        { preserveState: true, replace: true },
    );
};

const sortOptions = [
    { label: "Newest Arrivals", value: "newest" },
    { label: "Price: Low to High", value: "price_asc" },
    { label: "Price: High to Low", value: "price_desc" },
];
const currentSort = ref(props.filters?.sort || "newest");
const showSortMenu = ref(false);
const currentSortLabel = computed(
    () =>
        sortOptions.find((o) => o.value === currentSort.value)?.label ??
        "Newest Arrivals",
);

// Sort is applied client-side for instant feedback (the catalogue is already
// loaded; no round-trip needed).
const filterBySort = (value) => {
    currentSort.value = value;
    showSortMenu.value = false;
    // Keep the choice in the URL (no reload) so refresh, Back and shared links keep it.
    const url = new URL(window.location.href);
    if (value === 'newest') url.searchParams.delete('sort'); else url.searchParams.set('sort', value);
    window.history.replaceState(window.history.state, '', url);
};

const activeCategory = computed(() => {
    if (!props.filters?.category || props.filters.category === "all")
        return null;
    return (
        props.categories?.find((c) => c.slug === props.filters.category) ?? null
    );
});

const isCategorySelected = slug => (activeCategory.value?.slug || 'all') === slug;

// ── Sorted + paginated grid (replaces the old infinite carousel) ─────────────
const comparators = {
    price_asc: (a, b) => Number(a.price) - Number(b.price),
    price_desc: (a, b) => Number(b.price) - Number(a.price),
    newest: (a, b) => new Date(b.created_at) - new Date(a.created_at),
};
const sortedProducts = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();
    const list = (props.products || []).filter(product => !query || `${product.name} ${product.description || ''}`.toLowerCase().includes(query));
    const compare = comparators[currentSort.value] || comparators.newest;
    // Sold-out pieces sink to the end so they never interrupt what can be bought.
    return list.sort((a, b) => (isSoldOut(a) - isSoldOut(b)) || compare(a, b));
});

const PAGE_SIZE = 12;
// Remembered too: "Load More" pages survive Back, so scroll lands on the same piece.
const visibleCount = useRemember(PAGE_SIZE, 'shop-visible');
const visibleProducts = computed(() =>
    sortedProducts.value.slice(0, visibleCount.value),
);
const canLoadMore = computed(
    () => visibleCount.value < sortedProducts.value.length,
);
const loadMore = () => {
    visibleCount.value += PAGE_SIZE;
};

// Reset paging whenever the result set or ordering changes.
watch(
    // By ids, not array identity: Back re-delivers the same list and must keep paging.
    [currentSort, searchQuery, () => (props.products || []).map(p => p.id).join(',')],
    () => {
        visibleCount.value = PAGE_SIZE;
    },
);

// ── Utility ──────────────────────────────────────────────────────────────────
const toSentenceCase = (str) =>
    str ? str.charAt(0).toUpperCase() + str.slice(1).toLowerCase() : "";
</script>

<template>
    <StorefrontLayout>
        <Head>
            <title>
                {{
                    activeCategory
                        ? `${activeCategory.name} | Archive`
                        : "The Collection"
                }}
                | Serana Closet
            </title>
        </Head>

        <div class="relative">
            <main class="page-container pb-20">
                <!-- Page header -->
                <PageHeader kicker="The Collection" :title="activeCategory ? toSentenceCase(activeCategory.name) : 'Shop All Pieces'" subtitle="Ready-to-wear pieces, cut in limited numbers.">
                    <template #actions>
                    <!-- Desktop sort -->
                    <div class="relative hidden md:block">
                        <button @click="showSortMenu = !showSortMenu" :aria-expanded="showSortMenu" aria-haspopup="listbox" class="action-secondary">
                            <span class="text-on-surface-variant">Sort</span>
                            <span class="font-semibold text-primary">{{ currentSortLabel }}</span>
                            <span class="material-symbols-outlined text-base text-primary transition-transform" :class="showSortMenu ? 'rotate-180' : ''" aria-hidden="true">expand_more</span>
                        </button>
                        <transition name="fade">
                            <div v-if="showSortMenu" role="listbox" class="card absolute right-0 top-full z-[100] mt-2 w-60 overflow-hidden p-1.5 shadow-2xl">
                                <button
                                    v-for="opt in sortOptions"
                                    :key="opt.value"
                                    role="option"
                                    :aria-selected="currentSort === opt.value"
                                    @click="filterBySort(opt.value)"
                                    class="w-full rounded-xl px-4 py-2.5 text-left text-sm transition hover:bg-black/5 dark:hover:bg-white/5"
                                    :class="currentSort === opt.value ? 'font-semibold text-primary' : 'text-on-surface-variant'"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                        </transition>
                    </div>
                    </template>
                </PageHeader>

                <!-- Search -->
                <div class="mb-8">
                    <label for="shop-search" class="sr-only">Search the collection</label>
                    <div class="relative max-w-lg">
                        <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-lg text-on-surface-variant" aria-hidden="true">search</span>
                        <input id="shop-search" v-model="searchQuery" type="search" placeholder="Search by name or description" class="card w-full rounded-full py-3 pl-11 pr-5 text-sm text-on-surface placeholder:text-on-surface-variant focus:border-primary focus:ring-1 focus:ring-primary" />
                    </div>
                    <!-- Phones: categories as one swipeable row, sort as a native picker. -->
                    <div class="md:hidden mt-4 -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 no-scrollbar" role="group" aria-label="Categories">
                        <button type="button" @click="filterByCategory('all')" class="chip" :class="{ 'chip-active': isCategorySelected('all') }" :aria-pressed="isCategorySelected('all')">All <svg v-if="isCategorySelected('all')" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg></button>
                        <button v-for="category in categories" :key="category.id" type="button" @click="filterByCategory(category.slug)" class="chip" :class="{ 'chip-active': isCategorySelected(category.slug) }" :aria-pressed="isCategorySelected(category.slug)">{{ toSentenceCase(category.name) }} <svg v-if="isCategorySelected(category.slug)" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg></button>
                    </div>
                    <label class="md:hidden mt-3 flex items-center gap-2 text-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-base" aria-hidden="true">tune</span>
                        Sort
                        <select :value="currentSort" @change="filterBySort($event.target.value)" class="rounded-full border-0 bg-transparent py-1 pl-1 pr-8 text-sm font-semibold text-on-surface focus:ring-1 focus:ring-primary">
                            <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </label>
                    <p v-if="activeCategory || searchQuery" class="mt-3 text-sm text-on-surface-variant" aria-live="polite">{{ sortedProducts.length }} results<span v-if="activeCategory"> in {{ activeCategory.name }}</span><span v-if="searchQuery"> for “{{ searchQuery }}”</span>. <button @click="clearFilters" class="font-semibold text-primary underline-offset-2 hover:underline">Clear Filters</button></p>
                </div>

                <div class="flex flex-col gap-8 md:flex-row">
                    <!-- Desktop category sidebar -->
                    <aside class="hidden w-60 flex-shrink-0 md:block">
                        <nav aria-label="Categories" class="card sticky top-28 p-4">
                            <h2 class="mb-3 px-3 text-sm font-bold uppercase tracking-wider text-on-surface">Categories</h2>
                            <ul class="space-y-1 text-sm">
                                <li>
                                    <button type="button" @click="filterByCategory('all')" class="shop-cat" :class="{ 'shop-cat-active': isCategorySelected('all') }" :aria-pressed="isCategorySelected('all')">
                                        <span>All pieces</span>
                                        <svg v-if="isCategorySelected('all')" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                                    </button>
                                </li>
                                <li v-for="category in categories" :key="category.id">
                                    <button type="button" @click="filterByCategory(category.slug)" class="shop-cat" :class="{ 'shop-cat-active': isCategorySelected(category.slug) }" :aria-pressed="isCategorySelected(category.slug)">
                                        <span>{{ toSentenceCase(category.name) }}</span>
                                        <svg v-if="isCategorySelected(category.slug)" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
                                    </button>
                                </li>
                            </ul>
                        </nav>
                    </aside>

                    <!-- Product grid -->
                    <div class="min-w-0 flex-1">
                        <p class="mb-4 text-sm text-on-surface-variant">
                            {{ sortedProducts.length }} {{ sortedProducts.length === 1 ? 'piece' : 'pieces' }}
                        </p>

                        <div v-if="sortedProducts.length === 0" class="card flex flex-col items-center px-6 py-16 text-center">
                            <span class="icon-tile mb-5"><span class="material-symbols-outlined text-xl" aria-hidden="true">search_off</span></span>
                            <h2 class="font-headline text-xl font-bold text-on-surface">No pieces match this selection</h2>
                            <p class="mt-2 max-w-md text-sm text-on-surface-variant">Try another category or clear the filters to see the full collection.</p>
                            <button @click="clearFilters()" class="action-secondary mt-6">View All Pieces</button>
                        </div>

                        <div v-else>
                            <div class="grid grid-cols-2 gap-4 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                                <ProductCard
                                    v-for="(product, i) in visibleProducts"
                                    :key="product.id"
                                    :product="product"
                                    class="reveal-stagger"
                                    :style="{ animationDelay: (i % 4) * 80 + 'ms' }"
                                />
                            </div>

                            <div v-if="canLoadMore" class="mt-12 flex flex-col items-center gap-3">
                                <button @click="loadMore" class="action-secondary">Load More</button>
                                <p class="text-xs text-on-surface-variant">Showing {{ visibleProducts.length }} of {{ sortedProducts.length }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

        </div>
    </StorefrontLayout>

</template>

<style scoped lang="postcss">
.chip, .shop-cat {
    @apply inline-flex min-h-12 items-center gap-2 rounded-xl border px-4 py-3 text-base font-medium leading-snug text-on-surface transition-colors;
    border-color: var(--outline-variant);
}
.chip {
    @apply shrink-0 whitespace-nowrap rounded-full;
}
.shop-cat {
    @apply w-full justify-between text-left;
    border-color: transparent;
}
.chip:hover, .shop-cat:hover {
    border-color: var(--primary);
    background: var(--tint-bg);
}
.chip-active, .shop-cat-active,
.chip-active:hover, .shop-cat-active:hover {
    border-color: var(--primary);
    background: var(--primary);
    color: var(--background);
    font-weight: 700;
}
.chip:focus-visible, .shop-cat:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 3px;
}
@media (prefers-reduced-motion: reduce) {
    .chip, .shop-cat { transition: none; }
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.5s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

</style>
