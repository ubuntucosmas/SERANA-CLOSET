<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { animate, stagger } from 'animejs';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SectionBackground from '@/Components/SectionBackground.vue';

// --- Page Data ---
const stats = [
    { value: '500+', label: 'Happy Clients' },
    { value: '100%', label: 'Made to Order' },
    { value: '3yrs', label: 'In Business' },
    { value: 'KE/EA', label: 'Delivering To' },
];

const values = [
    {
        icon: 'straighten',
        title: 'Fit First',
        body: 'Every piece is built around your measurements. We don\'t believe in "close enough" — your fit should be exact, comfortable, and yours.'
    },
    {
        icon: 'local_florist',
        title: 'Real Fabrics',
        body: 'We source fabrics we\'d wear ourselves. Breathable, long-lasting, and honest. No cheap substitutes, no hidden blends.'
    },
    {
        icon: 'handshake',
        title: 'Clear Process',
        body: 'You\'ll always know where your order is. We send updates, photos, and check in before finalizing anything. No surprises.'
    },
];

const processSteps = [
    { label: 'You Share', desc: 'Send us your style idea, outfit type, and what occasion it\'s for.' },
    { label: 'We Plan', desc: 'We suggest fabrics, styles, and confirm your measurements.' },
    { label: 'We Build', desc: 'Our tailors get to work. You receive a progress photo before finishing.' },
    { label: 'You Receive', desc: 'Your finished piece is delivered. We follow up to make sure it fits.' },
];

// --- Animations ---
const aboutRoot = ref(null);

onMounted(() => {
    const observerOptions = {
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const section = entry.target;
                
                // Hero Reveal
                if (section.classList.contains('reveal-hero')) {
                    const targets = section.querySelectorAll('.reveal-item');
                    if (targets.length) {
                        animate(targets, {
                            opacity: [0, 1],
                            translateY: [30, 0],
                            duration: 1200,
                            delay: stagger(150),
                            easing: 'easeOutExpo'
                        });
                    }
                    observer.unobserve(section);
                }

                // Stats Reveal
                if (section.classList.contains('reveal-stats')) {
                    const targets = section.querySelectorAll('.stat-item');
                    if (targets.length) {
                        animate(targets, {
                            opacity: [0, 1],
                            translateY: [20, 0],
                            scale: [0.9, 1],
                            duration: 1000,
                            delay: stagger(100),
                            easing: 'easeOutBack'
                        });
                    }
                    observer.unobserve(section);
                }

                // Story / General Sections Reveal
                if (section.classList.contains('reveal-section')) {
                    const targets = section.querySelectorAll('.reveal-content');
                    if (targets.length) {
                        animate(targets, {
                            opacity: [0, 1],
                            translateX: (el) => el.classList.contains('from-left') ? [-40, 0] : [0, 0],
                            translateY: (el) => !el.classList.contains('from-left') ? [40, 0] : [0, 0],
                            duration: 1200,
                            delay: stagger(100),
                            easing: 'easeOutExpo'
                        });
                    }
                    observer.unobserve(section);
                }

                // Grid / Process Reveal
                if (section.classList.contains('reveal-grid')) {
                    const targets = section.querySelectorAll('.grid-item');
                    if (targets.length) {
                        animate(targets, {
                            opacity: [0, 1],
                            translateY: [40, 0],
                            duration: 1000,
                            delay: stagger(150),
                            easing: 'easeOutExpo'
                        });
                    }
                    observer.unobserve(section);
                }
            }
        });
    }, observerOptions);

    // Attach observer to marked sections
    if (aboutRoot.value) {
        aboutRoot.value.querySelectorAll('.reveal-on-scroll').forEach(section => {
            observer.observe(section);
        });
    }
});
</script>

<template>
    <StorefrontLayout>
        <Head>
            <title>Our Story | Serana Closet</title>
            <meta name="description" content="Discover the people, passion, and craft behind Serana Closet. Real tailoring. Real care. Made in Nairobi.">
        </Head>

        <main ref="aboutRoot" class="overflow-hidden font-body dark:text-white text-on-surface">

            <!-- ═══ HERO ═══ -->
            <section class="relative min-h-[85vh] flex items-end justify-start px-8 pb-20 pt-32 border-b dark:border-white/5 border-black/5 reveal-on-scroll reveal-hero overflow-hidden">
                <SectionBackground texture="/images/Black and white hoodies showcase.png" opacity="opacity-20" />

                <!-- Hero text -->
                <div class="relative z-10 max-w-screen-xl mx-auto w-full">
                    <div class="flex items-center gap-4 text-primary font-headline tracking-[0.3em] text-[10.5px] font-medium mb-8 reveal-item opacity-0">
                        <span class="w-12 h-px bg-primary/40"></span>
                        Est. Nairobi · {{ new Date().getFullYear() - 2 }} archive
                    </div>
                    <h1 class="serif-text text-6xl md:text-[9rem] font-light leading-[0.9] mb-8 max-w-4xl reveal-item opacity-0">
                        Made<br/>
                        <span class="text-white/40">with care.</span>
                    </h1>
                    <p class="text-lg md:text-xl dark:text-white/40 text-black/40 max-w-xl leading-relaxed font-light reveal-item opacity-0">
                        We started Serana Closet to solve one problem — finding clothes that actually fit, look sharp, and last. Everything we do comes back to that.
                    </p>

                    <!-- Live indicator -->
                    <div class="mt-12 inline-flex items-center gap-4 px-6 py-4 dark:bg-black/40 bg-white/40 backdrop-blur border dark:border-white/10 border-black/10 rounded-sm reveal-item opacity-0">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary"></span>
                        </span>
                        <span class="text-[10.5px] font-headline font-medium tracking-[0.15em] text-primary">Studio is live</span>
                        <span class="text-[10.5px] dark:text-white text-on-surface font-medium tracking-widest">· Accepting Commissions</span>
                    </div>
                </div>

                <!-- Coordinates badge -->
                <div class="absolute top-32 right-8 hidden lg:flex flex-col items-end gap-2 opacity-50 lg:opacity-20 reveal-item">
                    <span class="text-[9px] font-mono tracking-widest text-primary font-medium">Collection location</span>
                    <span class="text-[11px] font-mono dark:text-white text-on-surface dark:text-white text-on-surface font-medium tracking-tighter">01.29°S / 36.82°E</span>
                    <span class="text-[10px] font-mono dark:text-white/60 text-black/60 text-black/60">Nairobi, Kenya</span>
                </div>
            </section>

            <!-- ═══ BRAND STATS BAR ═══ -->
            <section class="border-y dark:border-white/10 border-black/10 bg-transparent reveal-on-scroll reveal-stats">
                <div class="max-w-screen-xl mx-auto px-8 py-10 grid grid-cols-2 md:grid-cols-4 gap-px">
                    <div v-for="stat in stats" :key="stat.label" class="flex flex-col items-center py-6 px-4 text-center group stat-item opacity-0">
                        <span class="font-headline text-5xl md:text-7xl font-medium text-primary luminous-glow mb-4 group-hover:scale-105 transition-transform">{{ stat.value }}</span>
                        <span class="text-[10.5px] font-headline tracking-[0.25em] dark:text-white text-on-surface dark:text-white text-on-surface font-medium">{{ stat.label }}</span>
                    </div>
                </div>
            </section>
 
            <!-- ═══ OUR STORY ═══ -->
            <section class="relative max-w-screen-xl mx-auto px-8 py-40 grid grid-cols-1 lg:grid-cols-2 gap-16 items-center border-b dark:border-white/5 border-black/5 reveal-on-scroll reveal-section">
                <SectionBackground opacity="opacity-10" :showScan="false" />
                <!-- Image side -->
                <div class="relative order-2 lg:order-1 reveal-content from-left opacity-0">
                    <div class="aspect-[3/4] rounded-sm overflow-hidden">
                        <img src="/images/Black zip-up hoodie on gray background.png" class="w-full h-full object-cover" alt="Serana Studio Story" />
                        <div class="absolute inset-0 bg-gradient-to-t from-background/60 via-transparent to-transparent rounded-sm"></div>
                    </div>
                    <!-- Floating tag -->
                    <div class="absolute -bottom-6 -right-4 lg:-right-8 bg-transparent border-2 dark:border-primary/20 border-primary/10 rounded-sm px-8 py-5 backdrop-blur shadow-2xl">
                        <h1 class="text-4xl md:text-8xl font-headline font-light tracking-tighter dark:text-white text-on-surface leading-[0.9] uppercase ">
                            Building <span class="text-primary  font-medium">The Future</span> of Nairobi Fashion
                        </h1>
                        <p class="max-w-xl text-[10px] md:text-xs dark:text-white/40 text-black/40 uppercase tracking-[0.4em] font-medium leading-loose font-sans">
                            Merging high-tech design with careful making. We are a studio dedicated to the new generation of makers.
                        </p>
                    </div>
                    <!-- Accent line -->
                    <div class="absolute -top-4 -left-4 w-16 h-16 border-t-2 border-l-2 border-primary/40 rounded-tl-xl pointer-events-none"></div>
                </div>

                <!-- Text side -->
                <div class="order-1 lg:order-2 space-y-8 reveal-content opacity-0">
                    <div class="flex items-center gap-4 text-primary text-[10.5px] font-headline font-medium tracking-[0.25em]">
                        <span class="w-12 h-px bg-primary/40"></span>
                        Our custom story
                    </div>
                    <h2 class="font-headline text-4xl md:text-5xl font-medium leading-tight">
                        Born out of <span class=" font-light text-primary">TikTok orders</span> that became a movement.
                    </h2>
                    <div class="space-y-5 dark:text-white/40 text-black/40 leading-relaxed font-light text-base">
                        <p>What started as custom orders on TikTok quickly grew into something much bigger. Clients weren't just buying clothes — they were trusting us with how they showed up to the world.</p>
                        <p>We learned early that fit is personal. A shirt that's 2cm off can ruin your whole look. So we built a system around getting it <em>exactly</em> right — every time.</p>
                        <p>Today, every piece we make carries that same commitment: honest fabrics, careful construction, and a process that treats you like an individual, not a size label.</p>
                    </div>
                    <Link :href="route('custom-order')" class="inline-flex items-center gap-4 bg-transparent border border-primary/50 text-primary hover:bg-primary hover:text-black transition-all">
                        <span class="material-symbols-outlined text-sm font-medium">draw</span>
                        Begin custom journey
                    </Link>
                </div>
            </section>

            <!-- ═══ VALUES GRID ═══ -->
            <section class="relative max-w-screen-xl mx-auto px-8 py-40 border-b dark:border-white/5 border-black/5 reveal-on-scroll reveal-grid overflow-hidden">
                <SectionBackground texture="/images/black_cloth_texture.png" opacity="opacity-10" :showScan="false" />
                <div class="text-center mb-20">
                    <div class="flex items-center justify-center gap-4 text-primary text-[10.5px] font-headline font-medium tracking-[0.25em] mb-6">
                        <span class="w-12 h-px bg-primary/40"></span>
                        What drives us
                        <span class="w-12 h-px bg-primary/40"></span>
                    </div>
                    <h2 class="font-headline text-3xl md:text-5xl font-medium">Three things we never skip.</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div v-for="value in values" :key="value.title"
                        class="glass-panel group relative rounded-sm p-8 transition-all duration-500 hover:bg-black/60 overflow-hidden grid-item opacity-0">
                        <!-- Background accent -->
                        <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -translate-y-1/2 translate-x-1/2 group-hover:bg-primary/10 transition-all duration-700 blur-2xl"></div>

                        <span class="material-symbols-outlined text-4xl text-primary mb-8 block transition-colors">{{ value.icon }}</span>
                        <h3 class="font-headline text-2xl font-medium mb-4 group-hover:text-primary transition-colors">{{ value.title }}</h3>
                        <p class="dark:text-white text-on-surface font-body text-base leading-relaxed font-medium">{{ value.body }}</p>
                        <div class="mt-8 w-12 h-1 bg-primary/30 group-hover:w-full transition-all duration-700 shadow-[0_0_10px_rgba(57, 255, 20,0.2)]"></div>
                    </div>
                </div>
            </section>

            <!-- ═══ HOW WE WORK ═══ -->
            <section class="bg-transparent border-b dark:border-white/10 border-black/10 reveal-on-scroll reveal-grid">
                <div class="max-w-screen-xl mx-auto px-8 py-24">
                    <div class="text-center mb-16">
                        <div class="flex items-center justify-center gap-4 text-primary text-[10.5px] font-headline font-medium tracking-[0.25em] mb-8">
                            <span class="w-12 h-px bg-primary/40"></span>
                            The artisan process
                            <span class="w-12 h-px bg-primary/40"></span>
                        </div>
                        <h2 class="font-headline text-3xl md:text-4xl font-medium">Simple. Transparent. Yours.</h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-8 relative">
                        <!-- Connector line (desktop only) -->
                        <div class="hidden md:block absolute top-8 left-[calc(12.5%)] right-[calc(12.5%)] h-px bg-gradient-to-r from-transparent via-primary/30 to-transparent"></div>

                        <div v-for="(step, i) in processSteps" :key="step.label" class="flex flex-col items-center text-center gap-4 grid-item opacity-0">
                            <div class="relative w-14 h-14 rounded-full border border-primary/30 bg-transparent flex items-center justify-center z-10">
                                <span class="font-headline font-medium text-primary text-sm">0{{ i+1 }}</span>
                            </div>
                            <h4 class="font-headline font-medium text-sm tracking-widest">{{ step.label }}</h4>
                            <p class="dark:text-white/40 text-black/40 text-xs leading-relaxed font-light max-w-[180px]">{{ step.desc }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══ BOTTOM CTA ═══ -->
            <section class="relative py-48 overflow-hidden reveal-on-scroll reveal-hero border-t dark:border-white/5 border-black/5">
                <SectionBackground texture="/images/ready_to_wear_rack.png" opacity="opacity-20" />

                <div class="relative z-10 max-w-3xl mx-auto px-8 text-center space-y-8">
                    <div class="flex items-center justify-center gap-4 text-primary text-[10.5px] font-headline font-medium tracking-[0.3em] reveal-item opacity-0">
                        <span class="w-12 h-px bg-primary/40"></span>
                        Ready for archive?
                        <span class="w-12 h-px bg-primary/40"></span>
                    </div>
                    <h2 class="text-4xl md:text-6xl font-headline font-medium dark:text-white text-on-surface leading-[0.95] tracking-tighter uppercase ">
                        Our <span class="dark:text-white text-on-surface/20">Story</span>
                    </h2>
                    <p class="text-sm dark:text-white/40 text-black/40 leading-loose  font-sans uppercase tracking-widest text-[10px] font-medium">
                        Serana Closet started as a small workshop in Nairobi. Today, it is a high-tech studio where every piece is carefully made.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center pt-4 reveal-item opacity-0">
                        <Link :href="route('custom-order')" class="inline-flex items-center justify-center gap-3 bg-transparent border border-primary/50 text-primary hover:bg-primary hover:text-black transition-all outline-none font-medium">
                            <span class="material-symbols-outlined text-sm">draw</span>
                            Custom order
                        </Link>
                        <Link :href="route('shop')" class="inline-flex items-center justify-center gap-3 border dark:border-white/20 border-black/20 hover:border-primary/50 dark:text-white text-on-surface dark:text-white/40 text-black/40 font-headline font-medium text-[11px] tracking-widest px-10 py-5 rounded-sm hover:text-primary hover:text-black transition-all">
                            <span class="material-symbols-outlined text-sm">storefront</span>
                            Browse ready-to-wear
                        </Link>
                    </div>
                </div>
            </section>

        </main>
    </StorefrontLayout>
</template>

<style scoped>
@keyframes scan {
    0% {
        transform: translateY(-100%);
        opacity: 0;
    }
    10% {
        opacity: 0.3;
    }
    50% {
        opacity: 0.15;
    }
    100% {
        transform: translateY(100vh);
        opacity: 0;
    }
}

.animate-scan {
    animation: scan 12s linear infinite;
}
</style>
