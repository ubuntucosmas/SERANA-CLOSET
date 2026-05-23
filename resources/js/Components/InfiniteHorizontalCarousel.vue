<script setup>
import { ref, computed, onUnmounted } from"vue";
import { Link } from"@inertiajs/vue3";
import ProductCard from"./ProductCard.vue";

const props = defineProps({
 products: { type: Array, default: () => [] },
 categoryName: { type: String, default:"" },
 categorySlug: { type: String, default:"" },
 itemWidth: { type: Number, default: 280 },
 // seconds for one full loop of all items
 duration: { type: Number, default: 40 },
});

// Two tracks side-by-side for a seamless loop
const track = computed(() => {
 if (!props.products.length) return [];
 return [...props.products];
});

// ----- Hover & scroll state -----
const isHovered = ref(false);
const isPaused = ref(false);
let resumeTimer = null;

// The scrollable wrapper that wraps BOTH tracks.
// We target it to manually scroll during arrow press.
const wrapperRef = ref(null);

function pause() {
 isPaused.value = true;
 clearTimeout(resumeTimer);
}

function resume() {
 isPaused.value = false;
}

function scrollBy(amount) {
 if (!wrapperRef.value) return;
 pause();
 wrapperRef.value.scrollBy({ left: amount, behavior:"smooth" });
 clearTimeout(resumeTimer);
 resumeTimer = setTimeout(resume, 800);
}

function scrollLeft() {
 scrollBy(-(props.itemWidth + 20) * 2);
}

function scrollRight() {
 scrollBy((props.itemWidth + 20) * 2);
}

onUnmounted(() => clearTimeout(resumeTimer));
</script>

<template>
 <div v-if="products.length" class="mb-14">

 <!-- Header -->
 <div class="flex items-end justify-between mb-5 px-1">
 <div>
 <h3 class="text-xl font-light tracking-tighter text-white">
 {{ categoryName.charAt(0).toUpperCase() + categoryName.slice(1).toLowerCase() }}
 </h3>
 <p class="text-[10px] text-white/40 tracking-[2px]">
 {{ products.length }} pieces
 </p>
 </div>
 <Link
 v-if="categorySlug"
 :href="route('shop', { category: categorySlug })"
 class="text-[10px] text-primary/70 hover:text-primary tracking-[2px] font-medium transition-colors"
 >
 Explore full →
 </Link>
 </div>

 <!-- Outer wrapper: relative for button positioning, NO overflow clipping -->
 <div
 class="relative w-full"
 @mouseenter="isHovered = true"
 @mouseleave="isHovered = false"
 >
 <!-- Track: overflow-hidden clips the animated content only -->
 <div class="w-full overflow-hidden rounded-2xl bg-[#0a0a0a] border border-white/5">
 <div ref="wrapperRef" class="w-full overflow-x-hidden no-scrollbar carousel-mask">
 <!-- Two-track marquee -->
 <div
 class="flex w-max"
 :style="{ '--dur': `${duration}s` }"
 >
 <!-- Track 1 -->
 <div
 class="flex gap-5 py-6 pl-5 marquee-track"
 :class="isPaused ? '[animation-play-state:paused]' : ''"
 :style="{ '--dur': `${duration}s` }"
 >
 <div
 v-for="(product, index) in track"
 :key="`t1-${product.id}-${index}`"
 class="flex-shrink-0"
 :style="{ width: `${itemWidth}px` }"
 >
 <ProductCard :product="product" layout="grid" class="transition-all duration-500" />
 </div>
 </div>

 <!-- Track 2 — seamless loop clone -->
 <div
 class="flex gap-5 py-6 pl-5 pr-5 marquee-track"
 :class="isPaused ? '[animation-play-state:paused]' : ''"
 :style="{ '--dur': `${duration}s` }"
 aria-hidden="true"
 >
 <div
 v-for="(product, index) in track"
 :key="`t2-${product.id}-${index}`"
 class="flex-shrink-0"
 :style="{ width: `${itemWidth}px` }"
 >
 <ProductCard :product="product" layout="grid" class="transition-all duration-500" />
 </div>
 </div>
 </div>
 </div>
 </div>

 <!-- Left Arrow — outside overflow-hidden, always in viewport bounds -->
 <button
 @click="scrollLeft"
 class="absolute left-3 top-1/2 -translate-y-1/2 z-20
 h-11 w-11 flex items-center justify-center
 rounded-full bg-black/60 border border-white/15 text-white
 backdrop-blur-md shadow-xl
 transition-all duration-300
 hover:bg-primary/20 hover:border-primary hover:text-primary
 hover:scale-110 active:scale-95"
 :class="isHovered ? 'opacity-100' : 'md:opacity-0 opacity-100'"
 aria-label="Scroll left"
 >
 <span class="material-symbols-outlined text-xl leading-none">chevron_left</span>
 </button>

 <!-- Right Arrow — outside overflow-hidden, always in viewport bounds -->
 <button
 @click="scrollRight"
 class="absolute right-3 top-1/2 -translate-y-1/2 z-20
 h-11 w-11 flex items-center justify-center
 rounded-full bg-black/60 border border-white/15 text-white
 backdrop-blur-md shadow-xl
 transition-all duration-300
 hover:bg-primary/20 hover:border-primary hover:text-primary
 hover:scale-110 active:scale-95"
 :class="isHovered ? 'opacity-100' : 'md:opacity-0 opacity-100'"
 aria-label="Scroll right"
 >
 <span class="material-symbols-outlined text-xl leading-none">chevron_right</span>
 </button>
 </div>
 </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

/* Edge fade via CSS mask — preserves background behind cards */
.carousel-mask {
 -webkit-mask-image: linear-gradient(
 to right,
 transparent 0%,
 black 6%,
 black 94%,
 transparent 100%
 );
 mask-image: linear-gradient(
 to right,
 transparent 0%,
 black 6%,
 black 94%,
 transparent 100%
 );
}

/*
 PULSE SCROLL — Glides quickly to the next position, then holds.

 The track is split into 4 equal segments (0% → 25% → 50% → 75% → 100%).
 Each segment:
 - 0%–8% of the segment = fast smooth glide (cubic-bezier)
 - 8%–25% of the segment = hold still (pause feel)

 Two tracks sit side-by-side. Each animates -100% of its own width.
 Because track 2 is an identical clone starting exactly where track 1 ends,
 the loop is mathematically seamless — no jump, no stutter.
*/
.marquee-track {
 animation: pulse-scroll var(--dur, 32s) ease-in-out infinite;
 will-change: transform;
}

@keyframes pulse-scroll {
 /* ── Segment 1: glide to 25%, then hold ── */
 0% { transform: translateX(0%); }
 8% { transform: translateX(-25%); }
 25% { transform: translateX(-25%); }

 /* ── Segment 2: glide to 50%, then hold ── */
 33% { transform: translateX(-50%); }
 50% { transform: translateX(-50%); }

 /* ── Segment 3: glide to 75%, then hold ── */
 58% { transform: translateX(-75%); }
 75% { transform: translateX(-75%); }

 /* ── Segment 4: glide to 100% (loop point) ── */
 83% { transform: translateX(-100%); }
 100% { transform: translateX(-100%); }
}
</style>
