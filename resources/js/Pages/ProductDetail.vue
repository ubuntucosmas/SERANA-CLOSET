<script setup>
import { computed, ref, reactive, onMounted, onUnmounted } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCartStore } from '@/Stores/useCartStore';
import { useCurrency } from '@/Composables/useCurrency';
import { useWhatsApp } from '@/Composables/useWhatsApp';
import { usePulseStore } from '@/Stores/usePulseStore';
import { useBackToShop } from '@/Composables/useBackToShop';
import { isSoldOut as soldOut, lowStockNote } from '@/Support/stock';

const props = defineProps({
    product: Object
});

const page = usePage();
const cart = useCartStore();
const pulse = usePulseStore();
const { formatAmount } = useCurrency();
const { getWhatsAppUrl } = useWhatsApp();

const isSoldOut = computed(() => soldOut(props.product));
const stockNote = computed(() => lowStockNote(props.product));

// Admin-editable piece names (Studio → product specs), with plain defaults.
const ui = computed(() => props.product.specifications?.ui || {});
const sizes = computed(() => ui.value.sizes || props.product.specifications?.sizes || ['S', 'M', 'L', 'XL', 'XXL']);

// Which sizes this piece needs: a set needs both, a jogger the bottom only,
// everything else the top only. Each gets its own visible row, so nothing
// is hidden behind a toggle.
const sizeRows = computed(() => {
    const type = props.product.garment_type;
    const top = { key: 'top', label: type === 'set' ? `${ui.value.piece_1_name || 'Hoodie'} size` : 'Size' };
    const bottom = { key: 'bottom', label: type === 'set' ? `${ui.value.piece_2_name || 'Jogger'} size` : 'Size' };
    if (type === 'set') return [top, bottom];
    if (type === 'jogger') return [bottom];
    return [top];
});
const selected = reactive({ top: null, bottom: null });

// The button itself teaches the next step: no error toast needed.
const missing = computed(() => sizeRows.value.filter(row => !selected[row.key]));
const ctaLabel = computed(() => {
    if (isSoldOut.value) return 'Out of Stock';
    if (missing.value.length === sizeRows.value.length) return sizeRows.value.length > 1 ? 'Choose your sizes' : 'Choose a size';
    if (missing.value.length) return `Choose ${missing.value[0].label.toLowerCase()}`;
    return `Add to Bag · ${formatAmount(props.product.price, page.props)}`;
});

const whatsappUrl = computed(() => getWhatsAppUrl(props.product, { topSize: selected.top, bottomSize: selected.bottom }, page.props));

const addToBag = () => {
    if (isSoldOut.value || missing.value.length) return;
    const added = cart.addItem({
        ...props.product,
        options: { top_size: selected.top, bottom_size: selected.bottom },
    });
    // On success the bag drawer opens: that is the confirmation.
    if (!added) pulse.emit("That's all we have of this piece right now. Check your bag.", 'error');
};

// Closing returns to the list they came from; otherwise to this piece's category.
const { back } = useBackToShop();
const closeProduct = () => back(route('shop', props.product.category?.slug ? { category: props.product.category.slug } : {}));
// Esc closes, unless the bag is open or they're typing somewhere.
const onKey = (e) => {
    if (e.key !== 'Escape' || cart.isOpen || e.defaultPrevented) return;
    if (/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName)) return;
    closeProduct();
};
onMounted(() => window.addEventListener('keydown', onKey));
onUnmounted(() => window.removeEventListener('keydown', onKey));

// Phones: while the real button is still below the screen, a slim bar keeps
// the next step one tap away. Tapping it with a size missing scrolls there.
const ctaRef = ref(null);
const ctaBelowFold = ref(false);
let observer = null;
onMounted(() => {
    if (!ctaRef.value || !('IntersectionObserver' in window)) return;
    observer = new IntersectionObserver(([entry]) => {
        ctaBelowFold.value = !entry.isIntersecting && entry.boundingClientRect.top > 0;
    });
    observer.observe(ctaRef.value);
});
onUnmounted(() => observer?.disconnect());
const stickyAction = () => {
    if (missing.value.length) {
        document.getElementById(`size-${missing.value[0].key}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    addToBag();
};

// Only specs that actually say something; empty rows read as broken content.
const techSpecs = computed(() => (props.product.specifications?.tech_specs || []).filter(spec => typeof spec === 'string' && spec.trim() !== ''));

const trustHighlights = computed(() => [
    { label: 'Delivery included', icon: 'local_shipping' },
    page.props.payments?.mpesa ? { label: 'Pay with M-Pesa', icon: 'verified_user' } : { label: 'Order on WhatsApp', icon: 'chat' },
    { label: 'Fit help on WhatsApp', icon: 'support_agent' },
]);

const allImages = computed(() => {
    const images = [];
    if (props.product.optimized_image_url) images.push(props.product.optimized_image_url);
    if (props.product.optimized_secondary_urls) images.push(...props.product.optimized_secondary_urls);
    return images.length > 0 ? images : ['/images/hero_editorial.png'];
});

// Swipe or tap the arrows. No auto-advance: the photo stays put while it is being looked at.
const galleryRef = ref(null);
const currentIndex = ref(0);
const onGalleryScroll = () => {
    if (!galleryRef.value) return;
    currentIndex.value = Math.round(galleryRef.value.scrollLeft / galleryRef.value.clientWidth);
};
const goTo = (index) => {
    const el = galleryRef.value;
    if (!el) return;
    const count = allImages.value.length;
    el.scrollTo({ left: ((index + count) % count) * el.clientWidth, behavior: 'smooth' });
};
</script>

<template>
    <StorefrontLayout>
        <Head :title="`${product.name} | Serana Closet`" />

        <main class="page-container pb-24 font-body text-on-surface">
            <div class="card relative w-full overflow-hidden shadow-xl shadow-black/10 dark:shadow-black/40">
                <!-- Close (desktop): on the card, like closing a preview. -->
                <button type="button" @click="closeProduct" aria-label="Close and go back to products" title="Back to products (Esc)"
                    class="absolute right-3 top-3 z-30 hidden h-11 w-11 items-center justify-center rounded-full bg-black/55 text-white ring-1 ring-white/15 backdrop-blur-md transition hover:bg-black/75 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary lg:flex">
                    <span class="material-symbols-outlined text-[22px]" aria-hidden="true">close</span>
                </button>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-0">

                    <!-- Gallery — deliberately always dark: the controls overlay the photograph. -->
                    <div class="relative aspect-[4/5] max-h-[55vh] w-full bg-black always-dark lg:aspect-auto lg:max-h-none lg:min-h-full">
                        <div ref="galleryRef" @scroll.passive="onGalleryScroll" @keydown.left.prevent="goTo(currentIndex - 1)" @keydown.right.prevent="goTo(currentIndex + 1)" tabindex="0" role="region" :aria-label="`${product.name} photos`" class="absolute inset-0 flex overflow-x-auto snap-x snap-mandatory no-scrollbar focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary">
                            <div v-for="(img, idx) in allImages" :key="idx" class="flex-none w-full snap-center h-full">
                                <img :alt="`${product.name} — photo ${idx + 1} of ${allImages.length}`" :src="img" :loading="idx === 0 ? 'eager' : 'lazy'" :fetchpriority="idx === 0 ? 'high' : 'auto'" decoding="async" class="w-full h-full object-cover" />
                            </div>
                        </div>

                        <div v-if="allImages.length > 1" class="absolute inset-x-0 bottom-0 z-20 flex items-center justify-between gap-4 p-4 pointer-events-none">
                            <button aria-label="Previous photo" @click="goTo(currentIndex - 1)" class="pointer-events-auto w-11 h-11 rounded-full bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white/70 hover:text-white transition-colors">
                                <span class="material-symbols-outlined text-xl" aria-hidden="true">chevron_left</span>
                            </button>
                            <div class="flex gap-2" aria-hidden="true">
                                <span v-for="(_, i) in allImages" :key="i" class="h-1.5 rounded-full transition-all duration-300" :class="currentIndex === i ? 'bg-primary w-4' : 'bg-white/40 w-1.5'"></span>
                            </div>
                            <button aria-label="Next photo" @click="goTo(currentIndex + 1)" class="pointer-events-auto w-11 h-11 rounded-full bg-black/40 backdrop-blur-md border border-white/10 flex items-center justify-center text-white/70 hover:text-white transition-colors">
                                <span class="material-symbols-outlined text-xl" aria-hidden="true">chevron_right</span>
                            </button>
                        </div>
                    </div>

                    <!-- Buy column: name → price → size → Add to Bag first; details after. -->
                    <div class="flex flex-col gap-6 p-5 md:p-8 lg:p-10 border-t lg:border-t-0 lg:border-l dark:border-white/5 border-black/5">
                        <header>
                            <p class="text-sm text-on-surface-variant">{{ product.category?.name || 'Collection' }}</p>
                            <div class="mt-1 flex items-start justify-between gap-4">
                                <h1 class="font-headline text-2xl font-bold tracking-tight text-on-surface md:text-3xl">{{ product.name }}</h1>
                                <p class="shrink-0 font-headline text-2xl font-bold text-on-surface md:text-3xl">{{ formatAmount(product.price, page.props) }}</p>
                            </div>
                            <p class="mt-2 text-sm" :class="isSoldOut ? 'text-on-surface-variant' : 'text-primary'">{{ isSoldOut ? 'Out of stock' : (stockNote || 'In stock') + ' · delivery included' }}</p>
                        </header>

                        <!-- Sizes -->
                        <div v-if="!isSoldOut" class="space-y-5">
                            <fieldset v-for="row in sizeRows" :key="row.key" :id="`size-${row.key}`" class="scroll-mt-24">
                                <div class="mb-2.5 flex items-center justify-between">
                                    <legend class="text-sm font-semibold text-on-surface">
                                        {{ row.label }}<span v-if="selected[row.key]" class="font-normal text-on-surface-variant"> · {{ selected[row.key] }}</span>
                                    </legend>
                                    <a v-if="row === sizeRows[0]" :href="whatsappUrl" target="_blank" rel="noopener" class="inline-flex min-h-[32px] items-center gap-1 text-sm font-medium text-primary hover:underline">
                                        <span class="material-symbols-outlined text-base" aria-hidden="true">straighten</span>Ask about fit
                                    </a>
                                </div>
                                <div class="grid grid-cols-5 gap-2">
                                    <button
                                        v-for="size in sizes" :key="size"
                                        type="button"
                                        @click="selected[row.key] = size"
                                        :aria-pressed="selected[row.key] === size"
                                        class="min-h-[48px] rounded-xl border text-sm font-semibold transition-colors"
                                        :class="selected[row.key] === size
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-black/10 text-on-surface hover:border-black/30 dark:border-white/10 dark:hover:border-white/30'"
                                    >{{ size }}</button>
                                </div>
                            </fieldset>
                        </div>

                        <!-- Primary action -->
                        <div class="flex flex-col gap-3">
                            <button ref="ctaRef" :disabled="isSoldOut || missing.length > 0" @click="addToBag" class="action-primary w-full py-4 text-base">
                                <span v-if="!missing.length && !isSoldOut" class="material-symbols-outlined text-lg" aria-hidden="true">shopping_bag</span>
                                {{ ctaLabel }}
                            </button>
                            <Link v-if="isSoldOut" :href="route('shop')" class="action-secondary w-full">Browse pieces in stock</Link>
                            <Link v-if="product.is_customizable" :href="route('custom-order', { product: product.slug })" class="action-secondary w-full">
                                <span class="material-symbols-outlined text-base" aria-hidden="true">content_cut</span>
                                Want it made to your measurements?
                            </Link>
                        </div>

                        <ul class="grid grid-cols-3 gap-2">
                            <li v-for="item in trustHighlights" :key="item.label" class="flex flex-col items-center gap-1 rounded-xl border border-black/5 px-2 py-3 text-center dark:border-white/5">
                                <span class="material-symbols-outlined text-lg text-primary" aria-hidden="true">{{ item.icon }}</span>
                                <span class="text-xs leading-tight text-on-surface-variant">{{ item.label }}</span>
                            </li>
                        </ul>

                        <!-- Details: below the buying decision, never in its way. -->
                        <section v-if="product.description || techSpecs.length || product.specifications?.fabric_weight" class="space-y-4 border-t border-black/5 pt-6 dark:border-white/5">
                            <h2 class="text-sm font-semibold text-on-surface">About this piece</h2>
                            <p v-if="product.description" class="text-sm leading-relaxed text-on-surface-variant">{{ product.description }}</p>
                            <ul v-if="techSpecs.length" class="list-disc space-y-1.5 pl-5 text-sm text-on-surface-variant">
                                <li v-for="(spec, idx) in techSpecs" :key="idx">{{ spec }}</li>
                            </ul>
                            <div v-if="product.specifications?.fabric_weight" class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-on-surface-variant">Fabric</span>
                                    <span class="font-medium text-on-surface">{{ product.specifications.fabric_weight }}</span>
                                </div>
                                <div class="h-1 w-full overflow-hidden rounded-full bg-black/10 dark:bg-white/10">
                                    <div class="h-full bg-primary" :style="{ width: (product.specifications?.weight_index || 50) + '%' }"></div>
                                </div>
                            </div>
                        </section>

                        <p class="text-sm text-on-surface-variant">
                            Questions about this piece?
                            <a :href="whatsappUrl" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">Chat with the studio on WhatsApp</a>
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Close (phones): pinned under the header so it stays reachable while
             scrolling. Teleported: the card's backdrop-filter would otherwise
             make "fixed" scroll with the card. -->
        <teleport to="body">
            <button type="button" @click="closeProduct" aria-label="Close and go back to products"
                class="fixed right-4 top-[116px] z-30 flex h-11 w-11 items-center justify-center rounded-full bg-black/60 text-white shadow-lg ring-1 ring-white/15 backdrop-blur-md transition active:scale-95 lg:hidden">
                <span class="material-symbols-outlined text-[22px]" aria-hidden="true">close</span>
            </button>
        </teleport>
        <teleport to="body">
        <transition name="buy-bar">
            <div v-if="ctaBelowFold && !isSoldOut" class="fixed inset-x-4 bottom-[6.25rem] z-[90] lg:hidden">
                <div class="card flex items-center gap-3 rounded-full p-1.5 pl-5 shadow-2xl shadow-black/30">
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold text-on-surface">{{ formatAmount(product.price, page.props) }}</span>
                    <button type="button" @click="stickyAction" class="action-primary shrink-0 px-5 py-3">{{ missing.length ? ctaLabel : 'Add to Bag' }}</button>
                </div>
            </div>
        </transition>
        </teleport>
    </StorefrontLayout>
</template>

<style scoped>
.buy-bar-enter-active, .buy-bar-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.buy-bar-enter-from, .buy-bar-leave-to { opacity: 0; transform: translateY(12px); }
</style>
