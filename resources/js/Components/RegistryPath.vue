<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const pathSegments = computed(() => {
    const url = page.url.split('?')[0]; // Strip query params
    const segments = url.split('/').filter(Boolean);
    
    if (segments.length === 0) return ['ROOT', 'HOME'];
    
    return ['ROOT', ...segments.map(s => s.toUpperCase().replace(/[-_]/g, '_'))];
});
</script>

<template>
    <div class="fixed bottom-10 left-10 z-[100] hidden lg:flex items-center gap-3 font-mono text-[8px] uppercase tracking-[0.4em] opacity-20 hover:opacity-60 transition-all cursor-default select-none group">
        <div class="flex items-center gap-1">
            <span class="text-primary animate-pulse">●</span>
            <span class="text-primary">SERANA://</span>
        </div>
        <div class="flex items-center gap-2">
            <template v-for="(segment, index) in pathSegments" :key="index">
                <span :class="index === pathSegments.length - 1 ? 'text-white' : 'text-white/40'">{{ segment }}</span>
                <span v-if="index < pathSegments.length - 1" class="text-white/10">❯</span>
            </template>
        </div>
        <!-- Coordinate Metadata (Faux) -->
        <div class="ml-10 flex gap-4 text-white/5 group-hover:text-white/10 transition-colors">
            <span>LOC:{{ Math.floor(Math.random() * 1000) }}.{{ Math.floor(Math.random() * 90) }}</span>
            <span>OS:SRN_V2</span>
        </div>
    </div>
</template>
