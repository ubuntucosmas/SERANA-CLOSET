<script setup>
import StitchShoppingGuide from '@/Components/StitchShoppingGuide.vue';
import StitchedHeadline from '@/Components/StitchedHeadline.vue';
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({ products: { type: Array, default: () => [] } });
const page = usePage();
const { formatAmount } = useCurrency();
const stitchPaused = ref(false);
const stitchCycle = ref(0);
const showcase = ref(null);
const order = ref([]);
const activeLook = ref(0);
const paused = ref(false);
const hovered = ref(false);
const focused = ref(false);
const visible = ref(false);
const hidden = ref(false);
const reducedMotion = ref(true);
let timer;
let observer;
let motionQuery;
const fallback = computed(() => ({
    id: 'editorial', image_url: page.props.theme_settings?.focal_1_bg || '/images/hero_editorial.png',
    name: 'The Serana Edit', category: 'Considered pieces. Effortless combinations.',
}));
const looks = computed(() => order.value.length ? order.value : [fallback.value]);
const currentLook = computed(() => looks.value[activeLook.value] || looks.value[0]);
const nextLook = computed(() => looks.value[(activeLook.value + 1) % looks.value.length]);
const playing = computed(() => looks.value.length > 1 && !paused.value && !hovered.value && !focused.value && visible.value && !hidden.value && !reducedMotion.value);
function shuffle(products, previousId = null) {
    const result = [...products];
    for (let i = result.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [result[i], result[j]] = [result[j], result[i]];
    }
    if (result.length > 1 && result[0].id === previousId) [result[0], result[1]] = [result[1], result[0]];
    return result;
}
watch(() => props.products, products => { order.value = shuffle(products); activeLook.value = 0; }, { immediate: true });
function advance() {
    if (activeLook.value === looks.value.length - 1) {
        order.value = shuffle(order.value, currentLook.value.id);
        activeLook.value = 0;
    } else activeLook.value++;
}
function select(direction) {
    paused.value = true;
    activeLook.value = (activeLook.value + direction + looks.value.length) % looks.value.length;
}
watch([playing, currentLook], () => {
    clearTimeout(timer);
    if (playing.value) timer = setTimeout(advance, 6500);
}, { flush: 'post' });
function syncMotion() { reducedMotion.value = motionQuery.matches; }
function syncVisibility() { hidden.value = document.hidden; }
onMounted(() => {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    syncMotion(); syncVisibility();
    motionQuery.addEventListener('change', syncMotion);
    document.addEventListener('visibilitychange', syncVisibility);
    observer = new IntersectionObserver(([entry]) => { visible.value = entry.isIntersecting; }, { threshold: 0.2 });
    observer.observe(showcase.value);
});
onBeforeUnmount(() => {
    clearTimeout(timer);
    observer?.disconnect();
    motionQuery?.removeEventListener('change', syncMotion);
    document.removeEventListener('visibilitychange', syncVisibility);
});
const onImageError = event => {
    if (!event.target.src.endsWith('/images/hero_editorial.png')) event.target.src = '/images/hero_editorial.png';
};
</script>

<template>
    <section data-cinema-scene class="home-hero relative isolate overflow-hidden pb-6 pt-5 sm:pb-16 sm:pt-12 lg:pb-20 lg:pt-14" aria-labelledby="home-heading">
        <div v-if="page.props.theme_settings?.hero_bg" class="hero-background" aria-hidden="true">
            <img :src="page.props.theme_settings.hero_bg" alt="" width="1920" height="1080" class="h-full w-full object-cover" @error="onImageError" />
        </div>
        <div class="hero-halo" aria-hidden="true"></div>
        <div class="page-container relative">
            <div class="grid items-center gap-5 sm:gap-10 lg:grid-cols-[.95fr_1.05fr] lg:gap-12 xl:gap-20">
                <div class="hero-copy relative z-10 max-w-xl lg:py-8" :class="{ 'stitch-guidance-paused': stitchPaused }">
                    <StitchedHeadline @motion-state="stitchPaused = $event" @cycle="stitchCycle++" />
                    <StitchShoppingGuide :key="stitchCycle" :target-selector="stitchCycle % 2 ? '[data-custom-action]' : '[data-shop-action]'" :entry-side="stitchCycle % 2 ? 'right' : 'left'" />
                    <p data-cinema-reveal data-cinema-delay="160" class="mt-4 sm:mt-6 max-w-[27rem] text-sm leading-relaxed text-on-surface-variant sm:text-lg">
                        Shop ready-to-wear pieces, or share your idea for a custom order.
                    </p>
                    <div class="mt-5 sm:mt-8 flex flex-wrap items-center gap-3 sm:gap-4">
                        <Link :href="route('shop')" data-shop-action class="action-primary group relative z-10">
                            Shop Collection
                            <span class="material-symbols-outlined text-lg transition-transform group-hover:translate-x-1" aria-hidden="true">arrow_forward</span>
                        </Link>
                        <Link :href="route('custom-order')" data-custom-action class="hero-bespoke-link relative z-10 inline-flex min-h-[44px] items-center gap-2 px-1 text-sm font-semibold text-on-surface">
                            Start a Custom Order
                            <span class="material-symbols-outlined text-base" aria-hidden="true">north_east</span>
                        </Link>
                    </div>
                    <div class="mt-4 sm:mt-9 flex items-center gap-3 border-t border-outline-variant/50 pt-3 sm:pt-5 text-sm text-on-surface-variant lg:mt-12">
                        <span class="material-symbols-outlined text-xl text-primary" aria-hidden="true">gesture</span>
                        <p>Ready to wear. Room to make it your own.</p>
                    </div>
                </div>

                <div ref="showcase" data-cinema-reveal="image" data-cinema-delay="80" class="hero-editorial relative min-w-0 pb-4 pl-5 sm:pl-10 lg:pl-8" role="region" aria-roledescription="carousel" aria-label="Available collection showcase" @mouseenter="hovered = true" @mouseleave="hovered = false" @focusin="focused = true" @focusout="focused = $event.currentTarget.contains($event.relatedTarget)">
                    <div data-cinema-depth="28" class="hero-image-frame relative ml-auto aspect-[4/5] max-h-[590px] w-[91%] overflow-hidden rounded-[1.75rem] bg-surface-container">
                        <Transition name="look-change" @before-leave="el => { el.inert = true; }">
                            <div :key="currentLook.id" class="showcase-slide always-dark absolute inset-0" role="group" aria-roledescription="slide" :aria-label="`${activeLook + 1} of ${looks.length}`">
                                <img :src="currentLook.image_url" :alt="currentLook.name" fetchpriority="high" width="640" height="800" class="h-full w-full object-cover" @error="onImageError" />
                                <div class="showcase-overlay absolute inset-x-0 bottom-0 px-5 pb-6 pt-24 text-white sm:px-7">
                                    <p class="mb-2 text-[11px] font-semibold uppercase tracking-[.16em] text-white/80">{{ currentLook.category || 'The Serana Collection' }}</p>
                                    <h2 class="line-clamp-2 font-headline text-2xl font-medium leading-tight sm:text-3xl">{{ currentLook.name }}</h2>
                                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                                        <p v-if="currentLook.price != null" class="text-lg font-semibold tracking-tight">{{ formatAmount(currentLook.price, page.props) }}</p>
                                        <Link :href="currentLook.slug ? route('shop.show', currentLook.slug) : route('shop')" class="showcase-link inline-flex min-h-11 items-center gap-2 rounded-full bg-white px-5 text-xs font-semibold text-black">
                                            {{ currentLook.slug ? 'View Piece' : 'Shop Collection' }}
                                            <span class="material-symbols-outlined text-base" aria-hidden="true">north_east</span>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                    <div v-if="looks.length > 1" data-cinema-depth="-54" class="hero-inset pointer-events-none absolute left-0 top-[12%] w-[30%] max-w-[170px] rotate-[-7deg] overflow-hidden rounded-2xl border-[5px] border-background bg-surface-container shadow-2xl" aria-hidden="true">
                        <img :src="nextLook.image_url" alt="" loading="lazy" width="200" height="260" class="aspect-[3/4] w-full object-cover" @error="onImageError" />
                    </div>
                    <div class="relative ml-auto mt-5 flex w-[91%] items-center justify-between gap-2">
                        <p class="text-xs font-medium tracking-wide text-on-surface-variant">{{ products.length ? 'From the collection' : 'The Serana Edit' }} <span v-if="looks.length > 1" class="ml-2 tabular-nums">{{ String(activeLook + 1).padStart(2, '0') }} / {{ String(looks.length).padStart(2, '0') }}</span></p>
                        <div v-if="looks.length > 1" class="flex gap-1" role="group" aria-label="Showcase controls">
                            <button type="button" class="showcase-control" aria-label="Previous product" @click="select(-1)"><span class="material-symbols-outlined" aria-hidden="true">chevron_left</span></button>
                            <button v-if="!reducedMotion" type="button" class="showcase-control" :aria-label="paused ? 'Play product rotation' : 'Pause product rotation'" @click="paused = !paused"><span class="material-symbols-outlined text-lg" aria-hidden="true">{{ paused ? 'play_arrow' : 'pause' }}</span></button>
                            <button type="button" class="showcase-control" aria-label="Next product" @click="select(1)"><span class="material-symbols-outlined" aria-hidden="true">chevron_right</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.hero-background { position: absolute; inset: 0; z-index: -2; pointer-events: none; }
.hero-background img { opacity: .38; }
.hero-background::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, var(--background) 0%, transparent 85%), linear-gradient(0deg, var(--background), transparent 65%); }
.hero-halo { position: absolute; inset: 0; z-index: -1; background: radial-gradient(ellipse at 76% 38%, rgba(0, 98, 255, .12), transparent 55%); pointer-events: none; }
.showcase-overlay { background: linear-gradient(transparent, rgba(0,0,0,.7) 40%, rgba(0,0,0,.92)); }
.showcase-control { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border: 1px solid var(--outline-variant); border-radius: 50%; color: var(--on-surface); }
.showcase-control:hover { border-color: var(--primary); color: var(--primary); }
.showcase-control:focus-visible, .showcase-link:focus-visible { outline: 2px solid var(--primary); outline-offset: 4px; }
.hero-heading-accent { color: var(--primary); }
.hero-image-frame { box-shadow: 0 30px 90px -45px rgba(0, 98, 255, .3); }
.hero-bespoke-link { text-decoration: underline; text-decoration-color: var(--outline-variant); text-underline-offset: 6px; }
.hero-bespoke-link:hover { text-decoration-color: var(--primary); }
.look-change-enter-active, .look-change-leave-active { transition: opacity .8s cubic-bezier(.22, 1, .36, 1); }
.look-change-leave-active { pointer-events: none; }
.look-change-enter-from, .look-change-leave-to { opacity: 0; }
@media (prefers-reduced-motion: reduce) { .look-change-enter-active, .look-change-leave-active { transition: none; } }
</style>
