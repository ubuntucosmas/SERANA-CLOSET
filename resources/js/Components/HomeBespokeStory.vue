<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
const image = computed(() => page.props.theme_settings?.hero_detail_bg || '/images/ready_to_wear_rack.png');
const steps = [
    { title: 'Share your idea', text: 'A style, a reference, an occasion. Start with what you know.' },
    { title: 'Make a plan together', text: 'Discuss the fit, fabric, quote and timing with the studio on WhatsApp.' },
    { title: 'Follow your piece', text: 'Use your request reference to check the updates from the studio.' },
];
</script>

<template>
    <section data-cinema-scene class="page-container page-section" aria-labelledby="home-bespoke-heading">
        <div class="grid overflow-hidden rounded-3xl border border-outline-variant/50 bg-surface lg:grid-cols-2">
            <div data-cinema-reveal="image" class="relative min-h-64 overflow-hidden lg:min-h-full">
                <img data-cinema-depth="-34" :src="image" alt="Clothes and materials in the Serana studio" loading="lazy" width="800" height="800" class="cinema-image-pan h-full max-h-[540px] w-full object-cover lg:absolute lg:inset-0 lg:max-h-none" />
                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 to-transparent p-6 pt-16 text-white sm:p-9">
                    <p class="text-xs font-medium uppercase tracking-[.14em] text-white/75">A more personal process</p>
                    <p class="mt-2 font-headline text-3xl font-medium">Your idea. Our studio.</p>
                </div>
            </div>
            <div class="p-6 sm:p-9 lg:p-12">
                <p data-cinema-reveal class="text-xs font-semibold uppercase tracking-[.14em] text-primary">Custom orders, made simple</p>
                <h2 data-cinema-reveal data-cinema-delay="70" id="home-bespoke-heading" class="mt-4 font-headline text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">Something in mind?<br />Let’s bring it to life.</h2>
                <ol class="mt-8 space-y-6">
                    <li v-for="(step, index) in steps" :key="step.title" data-cinema-reveal :data-cinema-delay="index * 90" class="flex gap-4">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-outline-variant text-xs font-semibold text-primary" aria-hidden="true">{{ index + 1 }}</span>
                        <div>
                            <h3 class="text-sm font-semibold text-on-surface">{{ step.title }}</h3>
                            <p class="mt-1 max-w-md text-sm leading-relaxed text-on-surface-variant">{{ step.text }}</p>
                        </div>
                    </li>
                </ol>
                <Link :href="route('custom-order')" class="action-primary mt-8">Start a Custom Order <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span></Link>
                <p class="mt-3 text-xs text-on-surface-variant">No payment to submit. We confirm the quote with you.</p>
            </div>
        </div>
    </section>
</template>
