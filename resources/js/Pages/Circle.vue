<script setup>
import BespokeBanner from '@/Components/BespokeBanner.vue';
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineProps({
    images: Array
});

function getGridClass(index) {
    // Strategic asymmetry for luxury feel
    const classes = [
        'lg:col-span-8 lg:row-span-2', // Large featured
        'lg:col-span-4 lg:row-span-1', // Side small
        'lg:col-span-4 lg:row-span-1', // Side small
        'lg:col-span-6 lg:row-span-2', // Medium wide
        'lg:col-span-6 lg:row-span-1', // Medium narrow
    ];
    return classes[index % classes.length];
}
</script>

<template>
    <StorefrontLayout>
        <Head>
            <title>The Circle | Serana Social Proof</title>
        </Head>

        <main class="pb-24 bg-surface/5">
            <div class="page-container">
                
                <!-- Editorial Header -->
                <PageHeader kicker="Client Gallery" title="The Serana" accent="Circle" subtitle="Pieces we have made for real clients. See something you like? Start a custom order and we'll make it for you." />

                <!-- Asymmetric Gallery Grid -->
                <div v-if="images.length > 0" class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 auto-rows-min">
                    <div 
                        v-for="(item, index) in images" 
                        :key="item.id"
                        class="relative group overflow-hidden rounded-sm border dark:border-white/5 border-black/5 bg-transparent  transition-all duration-700 reveal"
                        :class="getGridClass(index)"
                    >
                        <!-- Image -->
                        <img
                            :src="item.image_url || '/images/hero_editorial.png'"
                            :alt="item.client_name"
                            class="w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110 opacity-70 group-hover:opacity-100"
                            @error="(e) => { if (!e.target.src.endsWith('/images/hero_editorial.png')) e.target.src = '/images/hero_editorial.png'; }"
                        />

                        <!-- Overlay info -->
                        <div class="absolute inset-0 bg-gradient-to-t from-background via-background/20 to-transparent p-8 flex flex-col justify-end transform translate-y-4 group-hover:translate-y-0 transition-transform duration-500">
                            <div class="space-y-2">
                                <span class="text-[9px] font-headline font-medium tracking-widest text-primary">{{ item.garment_name }}</span>
                                <h3 class="text-2xl font-headline font-medium dark:text-white text-on-surface">{{ item.client_name }}</h3>
                                <p class="text-sm dark:text-white/40 text-black/40 font-body line-clamp-2 opacity-0 group-hover:opacity-100 transition-opacity duration-700 delay-100">
                                    "{{ item.testimonial }}"
                                </p>
                            </div>
                        </div>

                        <!-- Technical Badge (Hover) -->
                        <div class="absolute top-6 right-6 opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                            <div class="px-4 py-2 bg-white/5 backdrop-blur-md rounded-full border dark:border-white/10 border-black/10 text-[9px] font-medium tracking-widest dark:text-white text-on-surface shadow-xl">
                                Precision fit verified
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State (The Silence of Excellence) -->
                <div v-else class="py-32 flex flex-col items-center text-center reveal">
                    <div class="w-24 h-24 rounded-full border dark:border-white/5 border-black/5 bg-white/5 flex items-center justify-center mb-10 ">
                        <span class="material-symbols-outlined text-4xl text-primary/30">photo_library</span>
                    </div>
                    <h2 class="font-headline text-3xl font-medium dark:text-white text-on-surface mb-4">The archive is building.</h2>
                    <p class="dark:text-white/40 text-black/40 max-w-lg mx-auto leading-relaxed font-body">
                        The Serana Circle is a curated space for verified custom completions. We are currently preparing the next batch of editorial captures for the latest Studio releases.
                    </p>
                    <div class="mt-12 w-px h-24 bg-gradient-to-b from-primary to-transparent opacity-30 shadow-[0_0_15px_rgba(57, 255, 20,0.5)]"></div>
                </div>

                <div class="mt-16 -mx-4 sm:-mx-6">
                    <BespokeBanner title="Seen Something You Love?" text="Share the piece that inspired you. The studio will discuss the design, fit and price with you on WhatsApp before anything is made." />
                </div>

            </div>
        </main>
    </StorefrontLayout>
</template>

<style scoped>
.reveal {
    animation: reveal-up 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    opacity: 0;
}

@keyframes reveal-up {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
