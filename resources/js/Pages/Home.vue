<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import HeroSection from '@/Components/HeroSection.vue';
import TrustBar from '@/Components/TrustBar.vue';
import CategoryGrid from '@/Components/CategoryGrid.vue';
import SectionHeader from '@/Components/SectionHeader.vue';
import ProductCard from '@/Components/ProductCard.vue';
import HomeBespokeStory from '@/Components/HomeBespokeStory.vue';
import { useCinematicScroll } from '@/Composables/useCinematicScroll';

const cinematicRoot = ref(null);
useCinematicScroll(cinematicRoot);

const props = defineProps({
    showcaseProducts: { type: Array, default: () => [] },
    featuredProducts: { type: Array, default: () => [] },
    featuredDrop: { type: Object, default: null },
});
const page = usePage();
const { formatAmount } = useCurrency();
const selectedCategory = ref('all');
const collectionFilters = computed(() => {
    const categories = new Map();
    props.featuredProducts.forEach(product => {
        if (product.category?.id) categories.set(String(product.category.id), product.category.name);
    });
    return [{ id: 'all', name: 'All Arrivals' }, ...[...categories].map(([id, name]) => ({ id, name }))];
});
const visibleProducts = computed(() => selectedCategory.value === 'all'
    ? props.featuredProducts
    : props.featuredProducts.filter(product => String(product.category?.id) === selectedCategory.value));
const helpHref = computed(() => page.props.store?.whatsapp_url || route('contact'));
</script>

<template>
    <StorefrontLayout flushTop>
        <Head title="Serana Closet | Ready-to-Wear & Custom Orders" />
        <div ref="cinematicRoot" class="cinematic-home">
        <HeroSection :products="showcaseProducts" />

        <section data-cinema-reveal class="home-trust page-container mb-4" aria-label="Shopping with Serana">
            <TrustBar />
        </section>

        <section id="new-arrivals" class="page-container page-section scroll-mt-24" aria-labelledby="arrivals-heading">
            <div data-cinema-reveal class="mb-7 flex flex-wrap items-end justify-between gap-5">
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-[.14em] text-primary">A fresh point of view</p>
                    <h2 id="arrivals-heading" class="section-title">New Arrivals</h2>
                    <p class="section-sub">Meet your next wardrobe regular.</p>
                </div>
                <Link :href="route('shop')" class="link-more min-h-11">Shop the Full Collection <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span></Link>
            </div>

            <div v-if="featuredProducts.length" class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2" role="group" aria-label="Filter new arrivals by collection">
                    <button v-for="category in collectionFilters" :key="category.id" type="button" :aria-pressed="selectedCategory === category.id" aria-controls="home-arrival-grid" @click="selectedCategory = category.id" class="arrival-filter min-h-11 rounded-full border px-4 py-2 text-xs font-semibold transition-colors sm:text-sm" :class="selectedCategory === category.id ? 'border-on-surface bg-on-surface text-background' : 'border-outline-variant/60 bg-transparent text-on-surface-variant hover:border-primary hover:text-on-surface'">
                        {{ category.name }}
                    </button>
                </div>
                <p class="text-xs text-on-surface-variant" role="status">{{ visibleProducts.length }} {{ visibleProducts.length === 1 ? 'piece' : 'pieces' }} in this edit</p>
            </div>
            <div v-if="featuredProducts.length" id="home-arrival-grid" class="grid grid-cols-2 items-stretch gap-3 sm:gap-6 lg:grid-cols-4">
                <div v-for="(product, index) in visibleProducts" :key="product.id" data-cinema-reveal :data-cinema-delay="(index % 4) * 85" class="min-w-0">
                    <ProductCard :product="product" />
                </div>
            </div>
            <div v-else class="card p-8 text-center">
                <h3 class="text-lg font-semibold text-on-surface">The next edit is on its way.</h3>
                <p class="mt-2 text-sm text-on-surface-variant">Have something particular in mind? Share it with the studio.</p>
                <Link :href="route('custom-order')" class="action-secondary mt-5">Start a Custom Order</Link>
            </div>
        </section>

        <div data-cinema-stagger=".section-head, .card" class="cinema-collection-scene border-y border-outline-variant/40 bg-surface/40">
            <CategoryGrid title="Find Your Direction" subtitle="A whole wardrobe of possibilities. Start with what feels like you." />
        </div>

        <section v-if="featuredDrop" class="page-container page-section" aria-labelledby="featured-drop-heading">
            <Link data-cinema-reveal="image" :href="route('drops.show', featuredDrop.slug)" class="card card-hover group grid overflow-hidden md:grid-cols-2">
                <div class="card-media aspect-[16/10] rounded-none md:aspect-auto">
                    <img v-if="featuredDrop.image_url" :src="featuredDrop.image_url" :alt="featuredDrop.title" loading="lazy" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" />
                </div>
                <div class="flex flex-col justify-center p-7 md:p-12">
                    <span class="kicker self-start"><span class="kicker-dot"></span> Live Drop</span>
                    <h2 id="featured-drop-heading" class="mt-5 font-headline text-3xl font-semibold text-on-surface md:text-4xl">{{ featuredDrop.title }}</h2>
                    <p class="mt-2 text-xl font-semibold text-primary">{{ formatAmount(featuredDrop.price, page.props) }}</p>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-on-surface-variant">Reserve before we make it. Production starts when the campaign reaches its goal.</p>
                    <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-black/10 dark:bg-white/10" aria-hidden="true">
                        <div class="h-full bg-primary" :style="{ width: featuredDrop.funded_percent + '%' }"></div>
                    </div>
                    <p class="mt-2 text-xs text-on-surface-variant">{{ featuredDrop.reserved_count }} of {{ featuredDrop.target_quantity }} reserved</p>
                    <span class="action-primary mt-6 self-start">View Drop & Reserve <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span></span>
                </div>
            </Link>
        </section>

        <HomeBespokeStory />

        <section data-cinema-reveal class="page-container pb-14 pt-3 sm:pb-20" aria-labelledby="home-help-heading">
            <div class="flex flex-col justify-between gap-6 border-t border-outline-variant/50 pt-8 sm:flex-row sm:items-center">
                <div>
                    <h2 id="home-help-heading" class="font-headline text-xl font-semibold text-on-surface">A little guidance goes a long way.</h2>
                    <p class="mt-2 text-sm text-on-surface-variant">Need a second opinion on sizing, or an update on your order?</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a :href="helpHref" :target="page.props.store?.whatsapp_url ? '_blank' : undefined" rel="noopener" class="action-secondary">Ask the Studio <span class="material-symbols-outlined text-base" aria-hidden="true">north_east</span></a>
                    <Link :href="route('track')" class="inline-flex min-h-11 items-center px-3 text-sm font-semibold text-primary hover:underline">Track Order</Link>
                </div>
            </div>
        </section>
        </div>
    </StorefrontLayout>
</template>

<style scoped>
/* Only enhance elements registered by the homepage motion controller. */
.cinematic-home :deep([data-cinema-waiting]) { opacity: 0; }
.cinematic-home :deep([data-cinema-waiting]:focus-within) { opacity: 1; }
.cinematic-home :deep([data-cinema-depth]) { translate: 0 var(--cinema-y, 0px); }
.cinematic-home :deep(.cinema-image-pan) { transform: scale(1.12); }
@media (prefers-reduced-motion: reduce) {
    .cinematic-home :deep([data-cinema-waiting]) { opacity: 1; }
    .cinematic-home :deep([data-cinema-depth]) { translate: none; }
    .cinematic-home :deep(.cinema-image-pan) { transform: none; }
}

@media (max-width: 639px) {
    .home-trust :deep(.card) { padding: 1rem; }
    .home-trust :deep(ul) { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem .75rem; }
    .home-trust :deep(li) { align-items: center; gap: .5rem; }
    .home-trust :deep(.icon-tile) { width: 2rem; height: 2rem; border-radius: .625rem; }
    .home-trust :deep(p:first-child) { font-size: .6875rem; line-height: 1.5; }
    .home-trust :deep(p + p) { display: none; }
}
</style>
