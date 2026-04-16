<script setup>
import { ref, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import HeroSection    from '@/Components/HeroSection.vue';
import CategoryGrid   from '@/Components/CategoryGrid.vue';
import ProductCard    from '@/Components/ProductCard.vue';
import CollectionRibbon from '@/Components/CollectionRibbon.vue';
import CraftsmanshipBlock from '@/Components/CraftsmanshipBlock.vue';
import SectionBackground from '@/Components/SectionBackground.vue';
import { animate, stagger } from 'animejs';

const props = defineProps({
    featuredProducts: Array
});

const studioLoad = ref(12);
const studioCapacity = ref(85);

// Newsletter form
const form = useForm({
    email: '',
});

function subscribeNewsletter() {
    form.post(route('newsletter.subscribe'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        }
    });
}

onMounted(() => {
    // Intersection Observer for scroll reveals
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = entry.target;
                
                // Staggered reveal for children if requested
                if (target.dataset.stagger) {
                    const elements = target.querySelectorAll(target.dataset.stagger);
                    if (elements.length > 0) {
                        animate(elements, {
                            translateY: [30, 0],
                            opacity: [0, 1],
                            delay: stagger(100),
                            easing: 'easeOutExpo',
                            duration: 1200
                        });
                    }
                } else {
                    animate(target, {
                        translateY: [30, 0],
                        opacity: [0, 1],
                        easing: 'easeOutExpo',
                        duration: 1200
                    });
                }
                observer.unobserve(target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.anime-reveal').forEach(el => observer.observe(el));

    // Dynamic Studio Pulse Simulation
    studioLoad.value = Math.floor(Math.random() * (16 - 8 + 1)) + 8;
    studioCapacity.value = Math.floor(Math.random() * (96 - 78 + 1)) + 78;
});
</script>

<template>
    <StorefrontLayout>
        <Head>
            <title>Serana Closet | The Digital Studio</title>
            <meta name="description" content="Nairobi's elite bespoke fashion house. Merging traditional tailoring with futuristic digital precision. Experience the Serana Standard of custom wear.">
        </Head>

        <!-- 1. Hero -->
        <HeroSection />

        <!-- 2. Collection Ribbon (New Mobile Hook) -->
        <CollectionRibbon :products="featuredProducts" />

        <!-- 3. Categories -->
        <div>
            <CategoryGrid />
        </div>

        <!-- 3. The Serana Standard -->
        <section class="py-16 md:py-24 bg-background px-4 sm:px-8 anime-reveal">
            <div class="max-w-screen-xl mx-auto">
                <CraftsmanshipBlock 
                    subtitle="Our Uncompromising Standard"
                    title="Built by Hand. <br/> <span class='text-primary font-medium luminous-glow'>Perfected by Code.</span>"
                    description="We reject the mediocrity of fast fashion. Every piece at Serana Closet is a fusion of traditional Kenyan tailoring excellence and futuristic digital precision. We source only the heaviest silks and most resilient canvases to ensure your garment isn't just an outfit—it's an armor of style."
                    image="/images/white_hoodie_silver_zipper.png"
                >
                    <template #action>
                        <Link :href="route('about')" class="inline-flex items-center gap-2 text-xs font-headline font-medium text-primary hover:underline underline-offset-4 mt-6">
                            Read the Manifesto <span class="material-symbols-outlined text-sm">arrow_right_alt</span>
                        </Link>
                    </template>
                </CraftsmanshipBlock>
            </div>
        </section>

        <!-- 4. Studio Pulse (Minimalist Refinement) -->
        <section class="py-16 md:py-24 lg:py-40 bg-background border-b dark:border-white/5 border-black/5 reveal relative">
            <SectionBackground opacity="opacity-20" blur="blur-none" />
            <div class="max-w-screen-xl mx-auto px-8 flex flex-col md:flex-row justify-between items-center gap-12 relative z-10">
                <div class="flex items-center gap-10">
                    <div class="space-y-2">
                        <p class="text-[9px] font-headline font-medium uppercase tracking-[0.5em] text-primary">Live Studio Pulse</p>
                        <h1 class="text-3xl md:text-8xl font-headline font-light tracking-tighter dark:text-white text-on-surface leading-[0.9] uppercase ">
                            The <span class="bg-gradient-to-r from-primary to-[#08CB00] bg-clip-text text-transparent  font-medium">Studio</span> Archive_
                        </h1>
                        <p class="max-w-xl text-[10px] md:text-xs dark:text-white/40 text-black/40 uppercase tracking-[0.4em] font-medium leading-loose font-sans">
                            Nairobi's high-tech maker space. Engineering high-fashion silhouettes with careful precision and digital craft.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-8 text-left">
                    <div class="space-y-2">
                        <p class="text-[10.5px] font-headline uppercase tracking-[0.2em] text-primary mb-1 font-medium">Est.</p>
                        <p class="text-4xl font-headline font-medium dark:text-white text-on-surface dark:text-white text-on-surface">2022</p>
                    </div>
                    <div class="space-y-2 pl-8 border-l dark:border-white/5 border-black/5">
                        <span class="block text-[8px] font-headline font-medium uppercase tracking-[0.4em] text-primary/60">Studio Load</span>
                        <span class="text-4xl font-headline font-medium dark:text-white text-on-surface tracking-tighter">{{ studioLoad }} <span class="dark:text-white/40 text-black/40 font-light text-xl">pcs</span></span>
                    </div>
                    <div class="space-y-2 pl-8 border-l dark:border-white/5 border-black/5">
                        <span class="block text-[8px] font-headline font-medium uppercase tracking-[0.4em] text-primary/60">Capacity</span>
                        <span class="text-4xl font-headline font-medium text-primary tracking-tighter">{{ studioCapacity }}%</span>
                    </div>
                </div>

                <Link :href="route('custom-order')" class="hidden lg:flex items-center gap-6 px-10 py-5 border dark:border-white/10 border-black/10 rounded-sm text-[10px] font-headline font-medium uppercase tracking-[0.3em] hover:bg-white hover:text-background transition-all">
                    Secure Slot <span class="material-symbols-outlined text-sm">arrow_right_alt</span>
                </Link>
            </div>
        </section>

        <!-- 5. Featured Products -->
        <section class="py-16 md:py-32 lg:py-48 px-8 max-w-screen-2xl mx-auto anime-reveal relative" data-stagger=".product-card-container">
            <SectionBackground opacity="opacity-10" :showScan="false" />
            <div class="flex flex-col md:flex-row justify-between items-end mb-24 gap-12 relative z-10">
                <div class="space-y-6">
                    <div class="inline-flex items-center gap-3 text-primary font-sans tracking-[0.4em] text-[9px] font-medium uppercase">
                        <span class="w-8 h-px bg-primary/40"></span>
                        Artisan History
                    </div>
                    <h2 class="text-4xl md:text-6xl font-headline font-medium dark:text-white text-on-surface leading-[0.95] tracking-tighter uppercase ">
                        Every stitch_ <span class="dark:text-white text-on-surface/20">tells a story</span>
                    </h2>
                </div>
                <Link :href="route('shop')" class="text-primary font-headline text-[10px] uppercase tracking-[0.4em] font-medium border-b border-primary/20 pb-2 hover:border-primary hover:dark:text-white text-on-surface transition-all">
                    Shop All
                </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-16 relative z-10">
                <div v-for="product in featuredProducts" :key="product.id" class="product-card-container opacity-0">
                    <ProductCard :product="product" />
                </div>
            </div>
        </section>

        <!-- 6. Newsletter / Studio Circle CTA -->
        <section class="bg-background dark:text-white text-on-surface py-16 md:py-32 lg:py-48 px-8 overflow-hidden relative border-t dark:border-white/5 border-black/5 reveal">
            <SectionBackground texture="/images/black_zip_up_hoodie_gray_bg.png" opacity="opacity-30" />
            
            <div class="max-w-screen-xl mx-auto grid grid-cols-1 md:grid-cols-2 items-center gap-24 relative z-10">
                <div class="space-y-10">
                    <div class="flex items-center justify-center gap-4 text-primary text-[10.5px] font-headline font-medium uppercase tracking-[0.3em] reveal-item opacity-0">
                        <span class="w-12 h-px bg-primary/40"></span>
                        Ready to shop?
                        <span class="w-12 h-px bg-primary/40"></span>
                    </div>
                    <h2 class="font-headline text-4xl lg:text-[7rem] font-medium dark:text-white text-on-surface leading-[0.9] tracking-tighter">The Inner <br/><span class="dark:text-white/40 text-black/40 font-light">Circle.</span></h2>
                    <p class="text-sm dark:text-white/40 text-black/40 leading-loose  max-w-md font-sans uppercase tracking-widest text-[10px] font-medium">
                        Our pieces are not just made; they are carefully built. We merge high-tech design with the soul of Kenyan makers to create something truly unique.
                    </p>
                    <form @submit.prevent="subscribeNewsletter" class="flex flex-col gap-6 max-w-md">
                        <div class="flex flex-col sm:flex-row gap-4">
                             <input
                                v-model="form.email"
                                type="email"
                                placeholder="ENTER YOUR EMAIL..."
                                class="flex-grow bg-white/5 dark:border-white/10 border-black/10 text-[11px] font-headline font-medium tracking-[0.3em] px-8 py-5 rounded-sm focus:border-primary/50 focus:ring-0 transition-all dark:text-white text-on-surface placeholder:dark:text-white text-on-surface/20 uppercase"
                                required
                            />
                            <button type="submit" :disabled="form.processing" class="bg-primary text-background px-10 py-5 rounded-sm font-headline text-[11px] tracking-[0.3em] font-medium uppercase hover:bg-white transition-all shrink-0">
                                Join
                            </button>
                        </div>
                        <div class="text-[10px] font-headline font-medium uppercase tracking-[0.3em] dark:text-white text-on-surface/30">
                            Est. Nairobi · {{ new Date().getFullYear() - 2 }}
                        </div>
                        <p v-if="form.wasSuccessful" class="text-primary text-[11px] font-medium uppercase tracking-[0.3em] animate-pulse">◆ WELCOME. You are now part of the Inner Circle.</p>
                    </form>
                </div>
                <!-- Decorative image -->
                <div class="hidden md:block relative aspect-square group">
                    <div class="absolute inset-0 bg-primary/20 rounded-full blur-[120px] group-hover:bg-primary/30 transition-all duration-1000"></div>
                    <div class="relative z-10 w-full h-full aspect-square rounded-full border dark:border-white/10 border-black/10 overflow-hidden">
                        <img
                            class="w-full h-full object-cover grayscale opacity-40 hover:opacity-100 hover:grayscale-0 transition-all duration-1000"
                            src="/images/black_zip_up_hoodie_gray_bg.png"
                        />
                    </div>
                </div>
            </div>
        </section>
    </StorefrontLayout>
</template>

<style scoped>
.reveal {
    animation: reveal-up 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    opacity: 0;
}
@keyframes reveal-up {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
