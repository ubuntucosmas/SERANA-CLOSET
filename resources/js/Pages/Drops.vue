<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    drops: { type: Array, default: () => [] },
});
const page = usePage();
const { formatAmount } = useCurrency();

const hasDrops = computed(() => props.drops.length > 0);
</script>

<template>
    <Head title="Drops — reserve before we make it" />
    <StorefrontLayout>
        <section class="max-w-screen-xl mx-auto px-6 pt-28 pb-24">
            <!-- Header -->
            <div class="max-w-2xl mb-14">
                <p class="text-[10px] font-headline font-black tracking-[0.4em] uppercase text-primary mb-4">Serana Drops</p>
                <h1 class="text-4xl md:text-6xl font-headline font-light tracking-tight dark:text-white text-on-surface leading-[0.95]">
                    Reserve it <span class="text-primary">before</span> we make it.
                </h1>
                <p class="mt-5 text-on-surface-variant leading-relaxed">
                    Limited design drops, made to demand. Reserve your piece — once enough are claimed, we cut, sew and ship. No mass production, no dead stock, no missing out.
                </p>
            </div>

            <!-- Empty -->
            <div v-if="!hasDrops" class="text-center py-24 border border-dashed dark:border-white/10 border-black/10 rounded-3xl">
                <span class="material-symbols-outlined text-5xl text-primary/60">bolt</span>
                <p class="mt-4 dark:text-white text-on-surface font-headline text-xl">No live drops right now.</p>
                <p class="text-on-surface-variant text-sm mt-2">Follow us — the next one lands soon.</p>
            </div>

            <!-- Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <Link v-for="drop in drops" :key="drop.id" :href="route('drops.show', drop.slug)"
                    class="group block rounded-3xl overflow-hidden border dark:border-white/10 border-black/5 bg-background hover:border-primary/40 transition-all duration-500">
                    <div class="aspect-[4/5] bg-black/5 dark:bg-white/5 relative overflow-hidden">
                        <img v-if="drop.image_url" :src="drop.image_url" :alt="drop.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" />
                        <div v-else class="w-full h-full grid place-items-center text-on-surface-variant/30">
                            <span class="material-symbols-outlined text-5xl">checkroom</span>
                        </div>
                        <span v-if="drop.is_funded" class="absolute top-4 left-4 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full bg-primary text-background">Greenlit ✓</span>
                        <span v-else-if="!drop.is_open" class="absolute top-4 left-4 text-[10px] font-black uppercase tracking-widest px-3 py-1 rounded-full bg-black/70 text-white">Closed</span>
                    </div>
                    <div class="p-6">
                        <div class="flex items-baseline justify-between gap-3">
                            <h2 class="font-headline text-xl dark:text-white text-on-surface truncate">{{ drop.title }}</h2>
                            <span class="text-primary font-headline shrink-0">{{ formatAmount(drop.price, page.props) }}</span>
                        </div>
                        <!-- Progress -->
                        <div class="mt-5">
                            <div class="h-1.5 rounded-full bg-black/10 dark:bg-white/10 overflow-hidden">
                                <div class="h-full bg-primary transition-all duration-1000" :style="{ width: drop.funded_percent + '%' }"></div>
                            </div>
                            <div class="mt-2 flex justify-between text-[11px] text-on-surface-variant font-medium">
                                <span>{{ drop.reserved_count }} of {{ drop.target_quantity }} reserved</span>
                                <span class="text-primary group-hover:translate-x-1 transition-transform inline-flex items-center gap-1">Reserve <span class="material-symbols-outlined text-sm">arrow_forward</span></span>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>
        </section>
    </StorefrontLayout>
</template>
