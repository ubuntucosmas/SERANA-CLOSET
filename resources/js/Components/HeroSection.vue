<script setup>
import { onMounted, ref, computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { animate, stagger } from 'animejs';

const page = usePage();

const dynamicBackgrounds = computed(() => {
    const list = [
        '/images/detailed_texture.png',
        '/images/black_cloth_texture.png',
        '/images/Black zip-up hoodie on gray background.png'
    ];
    if (page.props.theme_settings.home_hero_bg) {
        list.unshift(page.props.theme_settings.home_hero_bg);
    }
    return list;
});

const focalImages = computed(() => {
    const defaults = [
        { src: '/images/hero_editorial.png', label: 'Signature Collection • Midnight Silk' },
        { src: '/images/Black and white hoodies showcase.png', label: 'Luminous Series • High-Contrast Hoodies' },
        { src: '/images/ready_to_wear_rack.png', label: 'Ready-To-Wear • Nairobi HQ' }
    ];

    const dynamic = [];
    for (let i = 1; i <= 3; i++) {
        const src = page.props.theme_settings[`hero_focal_${i}`];
        const label = page.props.theme_settings[`hero_focal_label_${i}`];
        if (src) {
            dynamic.push({ src, label: label || 'Featured Collection' });
        }
    }

    return dynamic.length > 0 ? dynamic : defaults;
});

const currentBgIndex = ref(0);
const currentFocalIndex = ref(0);

onMounted(() => {
    // Initial Reveal Animations
    animate('.reveal-hero-item', {
        opacity: [0, 1],
        translateY: [30, 0],
        duration: 1200,
        delay: stagger(150),
        easing: 'easeOutExpo'
    });

    // Slow cinematic alternation
    setInterval(() => {
        currentBgIndex.value = (currentBgIndex.value + 1) % dynamicBackgrounds.value.length;
        currentFocalIndex.value = (currentFocalIndex.value + 1) % focalImages.value.length;
    }, 12000);
});
</script>

<template>
    <section id="home" class="relative w-full min-h-[100svh] min-h-[750px] overflow-hidden bg-background px-4 sm:px-8 flex items-end pb-16 sm:pb-24 md:pb-32 pt-24 sm:pt-32">
        
        <!-- Refined Alternating Background Layer -->
        <div class="absolute inset-0 z-0 overflow-hidden">
            <transition name="fade-extreme">
                <img 
                    :key="dynamicBackgrounds[currentBgIndex]"
                    :src="dynamicBackgrounds[currentBgIndex]" 
                    class="absolute inset-0 w-full h-full object-cover scale-110 opacity-30 blur-[2px] grayscale brightness-50" 
                    alt="Serana Studio Tectonic" 
                />
            </transition>

            <!-- Soft gradient overlay -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent z-10"></div>

            <!-- Subtle luxury tint -->
            <div class="absolute inset-0 bg-[#0c2d48]/20 z-20"></div>

            <!-- Elegant scan effect -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden z-30">
                <div class="w-full h-[1px] bg-white/5 absolute top-0 animate-scan"></div>
            </div>
        </div>

        <!-- Hero Content (Grid Layout) -->
        <div class="relative z-40 w-full max-w-screen-2xl mx-auto px-8 h-full">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-end h-full pb-20">
                
                <!-- Text Context Content (Left) -->
                <div class="lg:col-span-6 relative z-20 space-y-10 pb-12">
                    <!-- Badge -->
                    <div class="reveal-hero-item opacity-0 flex items-center gap-3 text-[#B9C3FF] text-[9px] font-headline font-bold tracking-[0.5em] uppercase">
                        <span class="w-8 h-px bg-[#B9C3FF]/30"></span>
                        Est. Nairobi · {{ new Date().getFullYear() }} Collection_
                    </div>

                    <!-- Main Heading -->
                    <h1 class="reveal-hero-item opacity-0 font-headline text-5xl sm:text-6xl md:text-[8rem] font-medium leading-[0.85] dark:text-white text-on-surface tracking-tighter">
                        Crafting your<br/>
                        <span class=" font-light dark:text-white/40 text-black/40">perfect design_</span>
                    </h1>

                    <!-- Subtitle -->
                    <p class="reveal-hero-item opacity-0 text-lg md:text-xl dark:text-white/40 text-black/40 max-w-lg leading-relaxed font-light border-l border-primary/20 pl-10 opacity-80">
                        Careful tailoring for the modern style. From signature custom orders to limited drops, we make clothes with excellent craft.
                    </p>

                    <!-- Actions -->
                    <div class="reveal-hero-item opacity-0 flex flex-wrap gap-4 sm:gap-8 pt-6">
                        <Link :href="route('custom-order')" class="bg-primary text-background px-8 sm:px-14 py-5 sm:py-6 rounded-sm font-headline font-medium text-[11px] tracking-[0.4em] hover:bg-white transition-all duration-700 active:scale-95 shadow-[0_0_40px_rgba(57,255,20,0.15)]">
                            Start order_
                        </Link>
                        <Link :href="route('shop')" class="bg-white/5 backdrop-blur-3xl dark:text-white text-on-surface border dark:border-white/10 border-black/10 px-8 sm:px-14 py-5 sm:py-6 rounded-sm font-headline font-medium text-[11px] tracking-[0.4em] hover:bg-white/10 transition-all duration-700 active:scale-95">
                            Open Shop_
                        </Link>
                    </div>
                </div>

                <!-- Asymmetrical Cinematic Imagery (Right) -->
                <div class="lg:col-span-6 relative h-full min-h-[500px] flex items-center justify-end z-10 w-full opacity-0 md:opacity-100 group/hero reveal-hero-item">
                    <!-- Alternating Primary Main Image -->
                    <div class="relative w-full max-w-md aspect-[3/4] rounded-sm overflow-hidden shadow-[0_60px_150px_rgba(0,0,0,1)] border dark:border-white/5 border-black/5 z-10 group-hover/hero:scale-[1.02] transition-transform duration-1000 translate-y-12 bg-transparent">
                        <transition name="fade-mid" mode="out-in">
                            <img :key="focalImages[currentFocalIndex].src" :src="focalImages[currentFocalIndex].src" class="w-full h-full object-cover" />
                        </transition>
                        
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>
                        
                        <div class="absolute bottom-10 left-10 right-10">
                            <transition name="fade-fast" mode="out-in">
                                <span :key="focalImages[currentFocalIndex].label" class="inline-block bg-background/80 backdrop-blur-2xl text-primary text-[9px] font-headline font-medium tracking-[0.4em] px-8 py-4 border border-primary/20 rounded-sm shadow-2xl">
                                    {{ focalImages[currentFocalIndex].label }}
                                </span>
                            </transition>
                        </div>
                    </div>

                    <!-- Secondary Floating Details Image (Fixed or could rotate too) -->
                    <div class="absolute -bottom-10 left-0 w-64 aspect-square rounded-sm overflow-hidden shadow-[0_80px_150px_rgba(0,0,0,1)] border border-primary/40 z-20 grayscale hover:grayscale-0 transition-all duration-1000 -rotate-3 hover:rotate-0 hover:scale-110">
                        <img :src="$page.props.theme_settings.hero_detail_bg || '/images/detailed_texture.png'" class="w-full h-full object-cover" />
                    </div>
                </div>
            </div>
        </div>

    </section>
</template>


<style scoped>
.fade-extreme-enter-active, .fade-extreme-leave-active {
    transition: opacity 4000ms ease;
}
.fade-extreme-enter-from, .fade-extreme-leave-to {
    opacity: 0;
}

.fade-mid-enter-active, .fade-mid-leave-active {
    transition: opacity 2000ms cubic-bezier(0.4, 0, 0.2, 1);
}
.fade-mid-enter-from, .fade-mid-leave-to {
    opacity: 0;
}

.fade-fast-enter-active, .fade-fast-leave-active {
    transition: all 800ms ease;
}
.fade-fast-enter-from {
    opacity: 0;
    transform: translateY(10px);
}
.fade-fast-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

@keyframes scan {
    0% { transform: translateY(-100%); opacity: 0; }
    10% { opacity: 0.2; }
    50% { opacity: 0.1; }
    100% { transform: translateY(100vh); opacity: 0; }
}

.animate-scan {
    animation: scan 12s linear infinite;
}
</style>
