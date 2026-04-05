<script setup>
import { ref, watch } from 'vue';
import { animate, stagger } from 'animejs';

const props = defineProps({
    specs: { type: Object, default: () => ({}) },
    active: { type: Boolean, default: false }
});

const specsList = ref(null);
const techPoints = ref(null);

const runAnimation = () => {
    if (props.active) {
        // 1. Staggered Specs Reveal — scoped to the specsList ref
        const specItems = specsList.value?.querySelectorAll('.spec-item');
        if (specItems?.length) {
            animate(specItems, {
                translateX: [-10, 0],
                opacity: [0, 1],
                delay: stagger(80),
                easing: 'easeOutExpo',
                duration: 800
            });
        }

        // 2. Point Callouts — scoped to the techPoints ref
        const points = techPoints.value?.querySelectorAll('.tech-point');
        if (points?.length) {
            animate(points, {
                scale: [0, 1],
                opacity: [0, 1],
                delay: stagger(200, { start: 300 }),
                easing: 'easeOutBack',
                duration: 800
            });
        }
    } else {
        // Reset
        const specItems = specsList.value?.querySelectorAll('.spec-item');
        const points = techPoints.value?.querySelectorAll('.tech-point');
        if (specItems?.length) animate(specItems, { opacity: [null, 0], duration: 300 });
        if (points?.length) animate(points, { opacity: [null, 0], duration: 300 });
    }
};

watch(() => props.active, runAnimation);
</script>

<template>
    <div :class="[
        'absolute inset-0 bg-background/70 backdrop-blur-md transition-all duration-700 pointer-events-none z-20 flex items-center justify-center overflow-hidden',
        active ? 'opacity-100' : 'opacity-0'
    ]">
        <!-- Technical Grid Background -->
        <div class="absolute inset-0 opacity-[0.15]" 
             style="background-image: radial-gradient(circle at 2px 2px, #99ff33 1px, transparent 0); background-size: 32px 32px;">
        </div>

        <!-- Animated Scanning Line -->
        <div class="absolute top-0 left-0 w-full h-1/3 bg-gradient-to-b from-primary/30 to-transparent animate-scan-fast"></div>

        <!-- Technical Callouts -->
        <div ref="techPoints" class="relative w-full h-full p-8 font-headline">
            <!-- Point 1: Stitching -->
            <div class="tech-point absolute top-1/4 left-1/4 flex items-center gap-3 opacity-0">
                <div class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-primary"></span>
                </div>
                <div class="px-2.5 py-1.5 dark:bg-black/60 bg-white/60 border border-primary/30 rounded-lg text-[7px] font-black tracking-[0.3em] uppercase dark:text-white text-on-surface backdrop-blur-xl shadow-2xl">
                    12 CPI Precision
                </div>
            </div>

            <!-- Point 2: Material -->
            <div class="tech-point absolute bottom-1/3 right-1/4 flex items-center flex-row-reverse gap-3 opacity-0">
                <div class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-primary"></span>
                </div>
                <div class="px-2.5 py-1.5 dark:bg-black/60 bg-white/60 border border-primary/30 rounded-lg text-[7px] font-black tracking-[0.3em] uppercase dark:text-white text-on-surface backdrop-blur-xl shadow-2xl">
                    Mulberry Silk 100%
                </div>
            </div>

            <!-- Specs Overlay -->
            <div ref="specsList" class="absolute bottom-10 left-10 space-y-2">
                <div v-for="(val, key) in specs" :key="key" class="spec-item flex gap-3 items-center opacity-0 translate-x-[-10px]">
                    <div class="h-[1px] w-6 bg-primary/40"></div>
                    <span class="text-[7px] uppercase tracking-[0.2em] text-primary/60 font-bold">{{ key.replace('_', ' ') }}:</span>
                    <span class="text-[8px] uppercase tracking-widest dark:text-white text-on-surface font-black">{{ val }}</span>
                </div>
            </div>

            <!-- Serial Number -->
            <div class="absolute top-10 right-10 flex flex-col items-end gap-1">
                <span class="text-primary/40 text-[6px] font-mono tracking-[0.4em] uppercase">Auth Password:</span>
                <span class="dark:text-white/60 text-black/60 text-[8px] font-mono tracking-[0.2em]">SRN-{{ Math.random().toString(36).substr(2, 8).toUpperCase() }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes scan-fast {
    0% { transform: translateY(-100%); opacity: 0; }
    15% { opacity: 1; }
    100% { transform: translateY(300%); opacity: 0; }
}
.animate-scan-fast {
    animation: scan-fast 3s infinite cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
