<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

// The bespoke invitation shared by Home and The Circle.
defineProps({
    title: { type: String, default: 'Made to Your Measurements' },
    text: { type: String, default: 'Tell us what you want to wear and when you need it. We confirm the design, fabric, price and timeline with you on WhatsApp before anything is made.' },
});

const page = usePage();
const bespokeImage = computed(() => page.props.theme_settings?.focal_2_bg || '/images/ready_to_wear_rack.png');
</script>

<template>
    <section class="page-container page-section">
        <div class="relative overflow-hidden rounded-3xl border border-blue-500/30 bg-gradient-to-r from-[#e8f1ff] via-white to-[#eef9ff] p-8 shadow-xl dark:from-[#0d1526] dark:via-[#091122] dark:to-[#040812] sm:p-12">
            <div aria-hidden="true" class="pointer-events-none absolute -top-32 right-1/4 h-96 w-96 rounded-full bg-cyan-500/20 blur-[120px]"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-20 left-10 h-80 w-80 rounded-full bg-blue-600/20 blur-[100px]"></div>
            <div class="relative z-10 grid items-center gap-10 lg:grid-cols-12">
                <div class="space-y-5 lg:col-span-6">
                    <span class="kicker">Custom Orders</span>
                    <h2 class="font-headline text-3xl font-extrabold leading-tight tracking-tight text-on-surface sm:text-4xl lg:text-5xl">{{ title }}</h2>
                    <p class="max-w-lg text-sm leading-relaxed text-on-surface-variant sm:text-base">{{ text }}</p>
                    <div class="flex flex-wrap items-center gap-4 pt-2">
                        <Link :href="route('custom-order')" class="action-primary">Start a Custom Order <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span></Link>
                        <span class="text-xs text-on-surface-variant">No payment to submit a request.</span>
                    </div>
                </div>
                <div class="relative flex justify-center lg:col-span-6">
                    <div class="relative w-full max-w-md">
                        <img :src="bespokeImage" alt="Custom pieces made by the Serana studio" loading="lazy" class="aspect-[4/3] w-full rounded-2xl border border-white/10 object-cover shadow-2xl" />
                        <div class="card absolute -bottom-4 -left-2 flex items-center gap-2.5 rounded-2xl px-3.5 py-2 text-xs shadow-lg sm:-left-4">
                            <span class="material-symbols-outlined text-base text-primary" aria-hidden="true">straighten</span>
                            <span class="font-medium text-on-surface">Cut to your size</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
