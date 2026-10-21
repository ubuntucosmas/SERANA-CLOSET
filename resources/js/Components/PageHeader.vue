<script setup>
// The one page heading for every storefront page: left aligned, kicker +
// title (+ optional accent word) + subtitle, with optional right-side actions.
defineProps({
    kicker: { type: String, default: '' },
    title: { type: String, required: true },
    accent: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <header class="mb-5 sm:mb-8 flex flex-col justify-between gap-3 sm:gap-6 md:flex-row md:items-end">
        <div class="max-w-2xl">
            <slot name="before" />
            <span v-if="kicker" class="kicker"><span class="kicker-dot"></span> {{ kicker }}</span>
            <h1 class="font-headline text-2xl font-bold tracking-tight text-on-surface sm:text-4xl" :class="kicker ? 'mt-3 sm:mt-4' : ''">
                {{ title }}<span v-if="accent" class="text-primary">{{ " " + accent }}</span>
            </h1>
            <p v-if="subtitle" class="section-sub">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="shrink-0"><slot name="actions" /></div>
    </header>
</template>
