<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
const image = computed(() => page.props.theme_settings?.focal_1_bg || '/images/hero_editorial.png');
</script>

<template>
    <section class="relative overflow-hidden bg-background pt-36 pb-16 lg:pt-44 lg:pb-24">
        <div aria-hidden="true" class="absolute -right-24 top-16 w-[600px] h-[600px] rounded-full bg-blue-600/10 blur-[100px] pointer-events-none"></div>
        <div class="relative max-w-7xl mx-auto px-6 lg:px-10 grid lg:grid-cols-2 items-center gap-12 lg:gap-20">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-4 py-2 text-xs font-semibold text-primary mb-6">SERANA CLOSET · NAIROBI</p>
                <h1 class="font-headline text-5xl sm:text-6xl xl:text-7xl font-semibold leading-[1.08] tracking-tight mb-6">Made for<br/><span class="text-primary">your style.</span></h1>
                <p class="text-base sm:text-lg text-on-surface-variant leading-relaxed max-w-lg mb-8">Discover ready-to-wear pieces, reserve a limited Drop, or create something personal with our bespoke studio.</p>
                <div class="flex flex-wrap gap-4">
                    <Link :href="route('shop')" class="action-primary">Shop Collection <span aria-hidden="true">→</span></Link>
                    <Link :href="route('custom-order')" class="action-secondary">Start Bespoke Request</Link>
                </div>
                <p class="mt-7 text-sm text-on-surface-variant">Pay with M-Pesa · Track your order online</p>
            </div>
            <figure class="relative">
                <div class="absolute -inset-4 rounded-[3rem] bg-gradient-to-br from-blue-600/20 to-primary/10 blur-2xl" aria-hidden="true"></div>
                <img :src="image" alt="Serana fashion collection" class="relative w-full max-h-[560px] aspect-[4/5] object-cover rounded-3xl border border-primary/20" fetchpriority="high" />
                <figcaption class="relative mt-4 text-sm text-on-surface-variant">{{ page.props.theme_settings?.focal_1_label || 'Designed in Nairobi. Worn your way.' }}</figcaption>
            </figure>
        </div>
    </section>
</template>
