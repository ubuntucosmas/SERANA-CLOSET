<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SectionHeader from '@/Components/SectionHeader.vue';

const props = defineProps({
    limit: { type: Number, default: 6 },
    title: { type: String, default: 'Shop By Category' },
    subtitle: { type: String, default: 'Find the right piece faster, one collection at a time.' },
});

const page = usePage();
const categories = computed(() => (page.props.categories || []).slice(0, props.limit));

// Admin banner first, then the legacy per-category theme images, then a small
// local fallback (all under 100KB; the large hero PNGs are deliberately avoided).
const legacyKeys = {
    'mens-collection': 'cat_men_bg', 'ladies-wear': 'cat_women_bg', 'accessories': 'cat_acc_bg',
    'kids-collection': 'cat_kids_bg', 'casual-collection': 'cat_casual_bg', 'hoodies': 'cat_hoodies_bg',
    'dresses': 'cat_dresses_bg', 'corporate-wear': 'cat_corporate_bg',
};
const fallbacks = ['/images/hero_editorial.png', '/images/navy_contrast_set.png', '/images/ready_to_wear_rack.png', '/images/beige_contrast_set.png', '/images/category_ladies.png', '/images/detailed_texture.png'];

function banner(category, i) {
    if (category.banner_url) return category.banner_url;
    const legacy = page.props.theme_settings?.[legacyKeys[category.slug]];
    return legacy || fallbacks[i % fallbacks.length];
}
</script>

<template>
    <section v-if="categories.length" class="page-container page-section">
        <SectionHeader :title="title" :subtitle="subtitle" :href="route('shop')" />
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <Link
                v-for="(category, i) in categories"
                :key="category.id"
                :href="route('shop', { category: category.slug })"
                class="card card-hover group flex flex-col p-3 text-center sm:p-4"
            >
                <span class="card-media mb-3 block aspect-square">
                    <img :src="banner(category, i)" alt="" loading="lazy" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" />
                </span>
                <span class="text-sm font-semibold text-on-surface transition-colors group-hover:text-primary">{{ category.name }}</span>
                <span class="mt-0.5 text-xs text-on-surface-variant">Shop now</span>
            </Link>
        </div>
    </section>
</template>
