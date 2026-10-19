<script setup>
import { Link } from '@inertiajs/vue3';

// The one section heading used across the storefront: title + optional
// subtitle on the left, an optional "View all" link aligned to the baseline.
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    href: { type: String, default: '' },
    linkLabel: { type: String, default: 'View All' },
    as: { type: String, default: 'h2' },
});
</script>

<template>
    <div class="section-head">
        <div>
            <component :is="as" class="section-title">{{ title }}</component>
            <p v-if="subtitle" class="section-sub">{{ subtitle }}</p>
        </div>
        <Link v-if="href" :href="href" class="link-more">
            <span>{{ linkLabel }}</span>
            <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
        </Link>
    </div>
</template>
