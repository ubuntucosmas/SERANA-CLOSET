<script setup>
import { useStore } from '@/Composables/useStore';

// The store's social profiles, as set in Studio → Settings. Only the ones filled in show.
defineProps({ itemClass: { type: String, default: 'footer-social' } });
const { store } = useStore();
const icons = {
    instagram: '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.5"/><circle cx="17.5" cy="6.5" r=".8"/>',
    tiktok: '<path d="M16.6 5.82A4.28 4.28 0 0 1 15.54 3h-3.09v12.4a2.59 2.59 0 1 1-2.59-2.59c.27 0 .52.04.77.11V9.66a6 6 0 0 0-.77-.05A5.95 5.95 0 1 0 15.9 15.5V9.01a7.5 7.5 0 0 0 4.38 1.4V7.3a4.28 4.28 0 0 1-3.68-1.48z"/>',
    facebook: '<path d="M14 21v-8h3l.5-4H14V7c0-1.2.4-2 2-2h2V1.5A26 26 0 0 0 15 1c-3 0-5 1.8-5 5v3H7v4h3v8h4Z"/>',
    x: '<path d="M17.75 3h3.07l-6.71 7.67L22 21h-6.18l-4.84-6.33L5.44 21H2.36l7.18-8.2L2 3h6.33l4.38 5.79L17.75 3Zm-1.08 16.2h1.7L7.4 4.7H5.57l11.1 14.5Z"/>',
};
</script>

<template>
    <div v-if="store.socials?.length" class="flex flex-wrap gap-2" role="group" :aria-label="`Follow ${store.name}`">
        <a v-for="s in store.socials" :key="s.key" :href="s.url" target="_blank" rel="noopener" :aria-label="`${store.name} on ${s.label}`" :class="itemClass">
            <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="currentColor" aria-hidden="true" v-html="icons[s.key]"></svg>
        </a>
    </div>
</template>
