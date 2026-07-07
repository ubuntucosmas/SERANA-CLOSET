<!--
  BuyingModes — the connective tissue across the three ways to own Serana:
  Shop (ready-to-wear), Custom (bespoke), Drops (made-to-demand). Shown at the
  foot of each buying page so the paths feel like one product. The current mode
  is de-emphasised; Drops shows a "live now" cue when a drop is open.
-->
<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    current: { type: String, default: '' },            // shop | custom | drops
    liveDropSlug: { type: String, default: null },
});

const modes = computed(() => [
    { id: 'shop', label: 'Shop', tagline: 'Ready to wear, ready today.', icon: 'shopping_bag', href: route('shop') },
    { id: 'custom', label: 'Custom', tagline: 'Bespoke, cut to your measurements.', icon: 'content_cut', href: route('custom-order') },
    {
        id: 'drops', label: 'Drops',
        tagline: props.liveDropSlug ? 'Reserve before we make it — live now.' : 'Made to demand, in limited runs.',
        icon: 'bolt',
        href: props.liveDropSlug ? route('drops.show', props.liveDropSlug) : route('drops'),
        live: !!props.liveDropSlug,
    },
]);
</script>

<template>
    <section class="max-w-screen-xl mx-auto px-6 mt-24 mb-4">
        <p class="text-[10px] font-headline font-black tracking-[0.4em] uppercase text-primary mb-6 text-center">Three ways to own Serana</p>
        <div class="grid sm:grid-cols-3 gap-4">
            <Link v-for="m in modes" :key="m.id" :href="m.href"
                :class="['group relative rounded-2xl border p-6 transition-all duration-300',
                         m.id === current
                            ? 'border-primary/40 bg-primary/[0.06] pointer-events-none'
                            : 'dark:border-white/10 border-black/10 hover:border-primary/50']">
                <span v-if="m.live" class="absolute top-4 right-4 inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-primary">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span> Live
                </span>
                <span class="material-symbols-outlined text-primary text-2xl">{{ m.icon }}</span>
                <h3 class="mt-3 font-headline text-xl dark:text-white text-on-surface">{{ m.label }}</h3>
                <p class="mt-1 text-sm text-on-surface-variant leading-snug">{{ m.tagline }}</p>
                <span v-if="m.id !== current" class="mt-4 inline-flex items-center gap-1 text-xs font-bold uppercase tracking-widest text-primary group-hover:translate-x-1 transition-transform">
                    {{ m.id === 'drops' && m.live ? 'Reserve' : 'Explore' }} <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </span>
                <span v-else class="mt-4 inline-block text-xs font-bold uppercase tracking-widest text-on-surface-variant/50">You're here</span>
            </Link>
        </div>
    </section>
</template>

<style scoped>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300; vertical-align: middle; }
</style>
