<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
const props = defineProps({ targetSelector: { type: String, default: '[data-shop-action]' }, entrySide: { type: String, default: 'left' } });
const root = ref(null);
const path = ref('');
const button = ref(null);
const maskId = `shopping-thread-${Math.random().toString(36).slice(2)}`;
let observer;
let frame;
function measure() {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(() => {
        const container = root.value?.parentElement;
        const heading = container?.querySelector('#home-heading');
        const target = container?.querySelector(props.targetSelector);
        if (!heading || !target) return;
        const bounds = container.getBoundingClientRect();
        const title = heading.getBoundingClientRect();
        const lastLine = heading.querySelector('.stitch-second')?.getBoundingClientRect() || title;
        const action = target.getBoundingClientRect();
        const startX = lastLine.right - bounds.left;
        const startY = title.bottom - bounds.top + 7;
        const fromRight = props.entrySide === 'right';
        const endX = fromRight ? action.right - bounds.left + 3 : action.left - bounds.left - 3;
        const endY = action.top - bounds.top + action.height / 2;
        // Route the thread through the outer gutter, away from paragraph text.
        const gutter = fromRight ? bounds.width + 7 : -7;
        const turn = fromRight ? bounds.width - 6 : 6;
        path.value = `M ${startX} ${startY} H ${turn} Q ${gutter} ${startY} ${gutter} ${startY + 13} V ${endY - 13} Q ${gutter} ${endY} ${endX} ${endY}`;
        button.value = { x: action.left - bounds.left - 3, y: action.top - bounds.top - 3, width: action.width + 6, height: action.height + 6, radius: (action.height + 6) / 2 };
    });
}
onMounted(() => {
    const container = root.value.parentElement;
    if ('ResizeObserver' in window) {
        observer = new ResizeObserver(measure);
        observer.observe(container);
        const heading = container.querySelector('#home-heading');
        const target = container.querySelector(props.targetSelector);
        if (heading) observer.observe(heading);
        if (target) observer.observe(target);
    }
    window.addEventListener('resize', measure, { passive: true });
    measure();
});
onUnmounted(() => {
    observer?.disconnect();
    cancelAnimationFrame(frame);
    window.removeEventListener('resize', measure);
});
</script>

<template>
    <svg ref="root" :data-stitch-target="entrySide === 'right' ? 'custom' : 'shop'" class="shopping-thread" aria-hidden="true" focusable="false">
        <defs><mask :id="maskId" maskUnits="userSpaceOnUse" x="-20" y="0" width="200%" height="200%"><path :d="path" pathLength="100" class="thread-draw" fill="none" stroke="white" stroke-width="5" /></mask></defs>
        <path :d="path" :mask="`url(#${maskId})`" class="thread-route" fill="none" />
        <rect v-if="button" :x="button.x" :y="button.y" :width="button.width" :height="button.height" :rx="button.radius" pathLength="100" class="thread-button" fill="none" />
    </svg>
</template>

<style scoped>
.shopping-thread { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; pointer-events: none; color: var(--primary); }
.thread-route { stroke: currentColor; stroke-width: 1.5px; stroke-dasharray: 4 5; opacity: .75; }
.thread-draw { stroke-dasharray: 100; stroke-dashoffset: 100; animation: guide-to-shop 6s linear infinite; }
.thread-button { stroke: currentColor; stroke-width: 1.5px; stroke-dasharray: 1 1; opacity: 0; animation: shop-arrival 6s ease-out infinite; }
@keyframes guide-to-shop {
    0%, 38% { stroke-dashoffset: 100; opacity: 1; }
    52%, 67% { stroke-dashoffset: 0; opacity: 1; }
    78%, 100% { stroke-dashoffset: 0; opacity: 0; }
}
@keyframes shop-arrival {
    0%, 51% { opacity: 0; stroke-dashoffset: 35; }
    62%, 69% { opacity: .9; stroke-dashoffset: 0; }
    80%, 100% { opacity: 0; stroke-dashoffset: 0; }
}
:global(.stitch-guidance-paused .thread-draw),
:global(.stitch-guidance-paused .thread-button) { animation-play-state: paused; }
:global(.hero-copy:focus-within .shopping-thread),
:global(.hero-copy:hover .shopping-thread) { opacity: 0; }
@media (prefers-reduced-motion: reduce) { .shopping-thread { display: none; } }
</style>
