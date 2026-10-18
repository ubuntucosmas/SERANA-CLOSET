<script setup>
import { ref, computed, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';
import { isSoldOut as soldOut, lowStockNote } from '@/Support/stock';

const props = defineProps({
    product: { type: Object, required: true },
});

const page = usePage();
const { formatAmount } = useCurrency();

const currentIndex = ref(0);

const images = computed(() => {
    const list = [props.product.optimized_image_url || props.product.image_url];
    list.push(...(props.product.optimized_secondary_urls || props.product.secondary_image_urls || []));
    return list.filter(Boolean);
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

const isSoldOut = computed(() => soldOut(props.product));

// One badge, from real data only: availability first, then service, then recency.
const badge = computed(() => {
    if (isSoldOut.value) return { label: 'Sold Out', class: 'badge-muted' };
    const note = lowStockNote(props.product);
    if (note) return { label: note, class: 'badge-accent' };
    if (props.product.is_customizable) return { label: 'Custom option', class: 'badge-accent' };
    const added = props.product.created_at ? new Date(props.product.created_at) : null;
    if (added && Date.now() - added.getTime() < 30 * 24 * 60 * 60 * 1000) return { label: 'New', class: 'badge-new' };
    return null;
});

// Desktop hover previews the other photos; touch devices never trigger it.
let cycle = null;
const startCycle = () => {
    if (images.value.length < 2 || cycle) return;
    cycle = setInterval(() => { currentIndex.value = (currentIndex.value + 1) % images.value.length; }, 1500);
};
const stopCycle = () => {
    clearInterval(cycle);
    cycle = null;
    currentIndex.value = 0;
};
onUnmounted(stopCycle);
</script>

<template>
    <article
        class="card card-hover group relative flex h-full flex-col p-3 sm:p-4"
        @mouseenter="startCycle"
        @mouseleave="stopCycle"
    >
        <!-- Status badge (top left) -->
        <div v-if="badge" class="absolute left-5 top-5 z-20 sm:left-6 sm:top-6">
            <span :class="badge.class">{{ badge.label }}</span>
        </div>

        <!-- Product visual -->
        <Link :href="route('shop.show', product.slug)" prefetch class="card-media always-dark block aspect-[4/5]" :aria-label="product.name">
            <img
                v-for="(imgSrc, i) in images.length ? images : [FALLBACK_IMAGE]"
                :key="i"
                class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105"
                :class="[i === currentIndex ? 'opacity-100' : 'opacity-0', isSoldOut ? 'grayscale-[60%]' : '']"
                :src="imgSrc"
                :alt="i === 0 ? product.name : ''"
                loading="lazy"
                decoding="async"
                @error="onImgError"
            />
            <div v-if="images.length > 1" class="absolute bottom-3 left-1/2 z-10 flex -translate-x-1/2 gap-1.5" aria-hidden="true">
                <span v-for="(_, i) in images" :key="i" class="h-1.5 rounded-full transition-all duration-300" :class="i === currentIndex ? 'w-4 bg-primary' : 'w-1.5 bg-white/50'"></span>
            </div>
        </Link>

        <!-- Product metadata -->
        <div class="mt-4 flex flex-1 flex-col border-t border-black/5 pt-3 dark:border-white/5">
            <p class="truncate text-[13px] text-on-surface-variant">{{ product.category?.name || 'Collection' }}</p>
            <h3 class="mt-0.5 line-clamp-2 text-base font-semibold text-on-surface transition-colors group-hover:text-primary">
                <Link :href="route('shop.show', product.slug)">{{ product.name }}</Link>
            </h3>
            <div class="mt-auto flex items-end justify-between gap-2 pt-4">
                <div>
                    <span class="block text-xs text-on-surface-variant">{{ isSoldOut ? 'Sold out' : 'Price' }}</span>
                    <span class="whitespace-nowrap font-headline text-base font-bold text-on-surface sm:text-lg">{{ formatAmount(product.price, page.props) }}</span>
                </div>
                <!-- Sizes are chosen on the product page, so this goes there. -->
                <Link
                    v-if="!isSoldOut"
                    :href="route('shop.show', product.slug)"
                    class="action-icon"
                    :aria-label="`Choose a size for ${product.name}`"
                >
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">shopping_bag</span>
                </Link>
            </div>
        </div>
    </article>
</template>
