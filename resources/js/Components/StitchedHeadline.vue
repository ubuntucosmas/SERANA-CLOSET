<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

const emit = defineEmits(['motion-state', 'cycle']);

const phrases = [
    ['Good style.', 'Entirely you.'],
    ['Your style.', 'Your story.'],
    ['Every stitch.', 'Made for you.'],
    ['Fresh looks.', 'Your way.'],
    ['Your vision.', 'Our craft.'],
];
const activePhrase = ref(0);
const currentPhrase = computed(() => phrases[activePhrase.value]);
let remaining = [];
function advancePhrase(event) {
    // Four animations bubble from each line; only the first word owns rotation.
    if (!event.animationName.startsWith('sew-first') || !event.target.classList.contains('stitch-word') || stopped.value) return;
    if (!remaining.length) {
        remaining = phrases.map((_, index) => index).filter(index => index !== activePhrase.value);
        for (let i = remaining.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [remaining[i], remaining[j]] = [remaining[j], remaining[i]];
        }
    }
    activePhrase.value = remaining.shift();
    emit('cycle');
}
const root = ref(null);
const paused = ref(false);
const visible = ref(true);
const hidden = ref(false);
const stopped = computed(() => paused.value || !visible.value || hidden.value);
watch(stopped, value => emit('motion-state', value), { immediate: true });
let observer;
const syncVisibility = () => { hidden.value = document.hidden; };
onMounted(() => {
    syncVisibility();
    document.addEventListener('visibilitychange', syncVisibility);
    if ('IntersectionObserver' in window) {
        observer = new IntersectionObserver(([entry]) => { visible.value = entry.isIntersecting; });
        observer.observe(root.value);
    }
});
onUnmounted(() => {
    observer?.disconnect();
    document.removeEventListener('visibilitychange', syncVisibility);
});
</script>

<template>
    <div ref="root" class="stitched-headline relative w-fit" :class="{ 'stitch-paused': stopped, 'stitch-user-paused': paused }">
        <h1 id="home-heading" :aria-label="currentPhrase.join(' ')" @animationiteration="advancePhrase" class="font-headline text-[26px] sm:text-[clamp(3rem,6.4vw,6.4rem)] font-semibold leading-[1.02] tracking-[-.065em] text-on-surface">
            <span v-for="(line, index) in currentPhrase" :key="line" aria-hidden="true" class="stitch-line" :class="index === 1 ? 'stitch-second text-primary' : 'stitch-first'">
                <span class="stitch-word">{{ line }}</span>
                <span class="stitch-seam"></span>
                <span class="stitch-needle-track"><svg class="stitch-needle" viewBox="0 0 24 12" fill="none"><path d="M2 10 21 2 18 6 2 10Z" fill="currentColor"/><path d="m16 4 3-1" stroke="var(--surface)" stroke-width="1"/><path d="M2 10C-2 10 0 2 5 4" stroke="currentColor" stroke-width="1"/></svg></span>
            </span>
        </h1>
        <button type="button" @click="paused = !paused" :aria-label="paused ? 'Play headline stitching animation' : 'Pause headline stitching animation'" :aria-pressed="paused" class="stitch-control absolute left-full top-0 ml-1 inline-flex h-11 w-11 items-center justify-center rounded-full text-on-surface-variant hover:bg-primary/10 hover:text-primary">
            <span class="material-symbols-outlined text-base" aria-hidden="true">{{ paused ? 'play_arrow' : 'pause' }}</span>
        </button>
    </div>
</template>

<style scoped>
.stitch-line { display: table; position: relative; }
.stitch-word { display: inline-block; }
.stitch-seam { position: absolute; left: 0; right: 0; bottom: -.06em; height: 1px; background: repeating-linear-gradient(90deg, currentColor 0 4px, transparent 4px 8px); opacity: .6; }
.stitch-needle-track { position: absolute; left: 0; bottom: -.12em; width: 100%; height: 12px; pointer-events: none; opacity: 0; }
.stitch-needle { position: absolute; right: -7px; width: 24px; height: 12px; }
@supports (clip-path: inset(0 0 0 0)) {
    .stitch-first .stitch-word, .stitch-first .stitch-seam { animation: sew-first 6s steps(26, end) infinite; }
    .stitch-second .stitch-word, .stitch-second .stitch-seam { animation: sew-second 6s steps(28, end) infinite; }
    .stitch-first .stitch-needle-track { animation: needle-first 6s linear infinite; }
    .stitch-second .stitch-needle-track { animation: needle-second 6s linear infinite; }
}
@keyframes sew-first {
    0% { clip-path: inset(-.1em 100% -.15em 0); opacity: 1; }
    22%, 88% { clip-path: inset(-.1em 0 -.15em 0); opacity: 1; }
    96%, 100% { clip-path: inset(-.1em 0 -.15em 0); opacity: 0; }
}
@keyframes sew-second {
    0%, 16% { clip-path: inset(-.1em 100% -.15em 0); opacity: 1; }
    38%, 88% { clip-path: inset(-.1em 0 -.15em 0); opacity: 1; }
    96%, 100% { clip-path: inset(-.1em 0 -.15em 0); opacity: 0; }
}
@keyframes needle-first {
    0% { transform: translateX(-100%); opacity: 1; }
    22% { transform: translateX(0); opacity: 1; }
    24%, 100% { transform: translateX(0); opacity: 0; }
}
@keyframes needle-second {
    0%, 15.9% { transform: translateX(-100%); opacity: 0; }
    16% { transform: translateX(-100%); opacity: 1; }
    38% { transform: translateX(0); opacity: 1; }
    40%, 100% { transform: translateX(0); opacity: 0; }
}
.stitch-paused .stitch-word, .stitch-paused .stitch-seam, .stitch-paused .stitch-needle-track { animation-play-state: paused; }
.stitch-user-paused .stitch-word, .stitch-user-paused .stitch-seam, .stitch-user-paused .stitch-needle-track { animation: none; clip-path: none; }
.stitch-user-paused .stitch-word { opacity: 1; }
.stitch-user-paused .stitch-needle-track { opacity: 0; }
@media (prefers-reduced-motion: reduce) {
    .stitch-word, .stitch-seam, .stitch-needle-track { animation: none !important; clip-path: none; }
    .stitch-word { opacity: 1; }
    .stitch-needle-track, .stitch-control { display: none; }
}
</style>
