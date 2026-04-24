<script setup>
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const getCategoryBanner = (category) => {
    if (category.banner_url) return category.banner_url;

    const slug = category.slug;
    const categoryBannerMap = {
        'mens-collection': 'cat_men_bg',
        'ladies-wear': 'cat_women_bg',
        'accessories': 'cat_acc_bg',
        'kids-collection': 'cat_kids_bg',
        'casual-collection': 'cat_casual_bg',
        'hoodies': 'cat_hoodies_bg',
        'dresses': 'cat_dresses_bg',
        'corporate-wear': 'cat_corporate_bg'
    };

    const bannerKey = categoryBannerMap[slug];
    const legacyBanner = bannerKey ? page.props.theme_settings[bannerKey] : null;

    if (legacyBanner) return legacyBanner;

    // Fallback based on common keywords if no specific banner exists
    if (slug.includes('men')) return page.props.theme_settings.cat_men_bg || '/images/navy_contrast_set.png';
    if (slug.includes('women') || slug.includes('ladies') || slug.includes('dresses')) return page.props.theme_settings.cat_women_bg || '/images/hero_editorial.png';
    if (slug.includes('kids')) return page.props.theme_settings.cat_kids_bg || '/images/ready_to_wear_rack.png';

    return '/images/detailed_texture.png';
};
</script>

<template>
    <section id="services" class="py-40 px-8 max-w-screen-2xl mx-auto">
        <header class="mb-24 max-w-2xl anime-reveal">
            <div class="flex items-center gap-4 mb-6">
                <span class="w-12 h-px bg-primary/30"></span>
                <span class="text-primary font-headline font-medium text-[10px] tracking-[0.4em]">The seasonal archive</span>
            </div>
            <h2 class="font-headline text-5xl md:text-7xl font-medium dark:text-white text-on-surface tracking-tighter leading-tight mb-8">
                Our <span class="dark:text-white/40 text-black/40 font-light">Collections.</span>
            </h2>
            <p class="dark:text-white/40 text-black/40 font-body text-base max-w-lg font-light leading-relaxed opacity-80">
                A curated selection of tactical wardrobe solutions. Every piece is a commitment to precision, engineered for the absolute silhouette.
            </p>
        </header>

        <!-- Catalog Simplified Grid -->
        <!-- Strategic 6-Chapter Archive Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <!-- Dynamic Category Cards -->
            <article v-for="category in ($page.props.categories || [])" 
                :key="category.id"
                class="h-[320px] md:h-[450px] bg-transparent border dark:border-white/5 border-black/5 rounded-sm overflow-hidden group relative anime-reveal"
            >
                <img 
                    :alt="category.name" 
                    class="absolute inset-0 w-full h-full object-cover grayscale opacity-40 group-hover:opacity-100 group-hover:grayscale-0 transition-all duration-1000 scale-100 group-hover:scale-105" 
                    :src="getCategoryBanner(category)"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-background via-background/20 to-transparent"></div>
                <div class="absolute inset-0 p-10 flex flex-col justify-end">
                    <h3 class="text-3xl font-headline font-medium dark:text-white text-on-surface mb-3 uppercase tracking-tighter">{{ category.name }}_</h3>
                    <Link :href="route('shop', {category: category.slug})" class="text-primary font-headline font-medium text-[9px] tracking-[0.3em] flex items-center gap-4 hover:translate-x-2 transition-transform uppercase">
                        View collection <span class="material-symbols-outlined text-sm">arrow_right_alt</span>
                    </Link>
                </div>
            </article>

            <!-- 06: Custom Story (CTA) -->
            <article class="h-[450px] bg-background border border-primary/20 rounded-sm overflow-hidden group relative anime-reveal">
                <div class="absolute inset-0 bg-primary/5 group-hover:bg-primary/10 transition-colors"></div>
                <div class="absolute inset-0 p-10 flex flex-col justify-center items-start">
                    <h3 class="text-4xl font-headline font-medium dark:text-white text-on-surface mb-4 leading-tight uppercase tracking-tighter">Order <br/> <span class="text-primary tracking-widest">Custom_</span></h3>
                    <p class="text-[11px] dark:text-white/40 text-black/40 font-light mb-8 max-w-xs leading-relaxed opacity-80 uppercase tracking-widest">
                        Hand-carved identities. Built from scratch for your architecture.
                    </p>
                    <Link :href="route('custom-order')" class="bg-primary text-background px-8 py-4 rounded-sm font-headline font-medium tracking-[0.4em] text-[10px] hover:scale-105 hover:bg-white transition-all uppercase">
                        Get started_
                    </Link>
                </div>
            </article>
            
        </div>
    </section>
</template>
