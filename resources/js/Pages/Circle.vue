<script setup>
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
            <meta name="description" content="Witness the Serana Standard in the real world. A curated gallery of our most striking commissions and global client features.">
        </Head>

        <main class="pt-32 pb-24 bg-surface/5">
            <div class="max-w-[1600px] mx-auto px-8 lg:px-16">
                
                <!-- Editorial Header -->
                <div class="max-w-3xl mb-24 reveal">
                    <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary/10 rounded-full border border-primary/30 mb-6 w-fit">
                        <span class="text-[9px] font-medium tracking-[0.2em] text-primary">Social authority</span>
                    </div>
                    <h1 class="font-headline text-5xl lg:text-7xl font-medium dark:text-white text-on-surface leading-[1.1] mb-8">
                        The Serana <span class="text-primary luminous-glow">Circle.</span>
                    </h1>
                    <p class="dark:text-white/40 text-black/40 font-body text-lg lg:text-xl leading-relaxed">
                        Excellence is not claimed; it is observed. Explore our curated archive of clients who have chosen the Serana standard. This is the visual proof of the perfect fit.
                    </p>
                </div>

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
                            :src="item.image_url" 
                            :alt="item.client_name" 
                            class="w-full h-full object-cover transition-transform duration-1000 group-hover:scale-110 opacity-70 group-hover:opacity-100"
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

                <!-- Invitation CTA Section (Phase 9 Core) -->
                <section class="mt-32 py-24 bg-transparent rounded-[40px] border dark:border-white/5 border-black/5 relative overflow-hidden reveal">
                    <div class="absolute -right-24 -top-24 text-primary/5 w-96 h-96 material-symbols-outlined pointer-events-none" style="font-size: 400px;">group_add</div>
                    
                    <div class="max-w-3xl mx-auto text-center px-8 relative z-10">
                        <h2 class="font-headline text-4xl lg:text-5xl font-medium dark:text-white text-on-surface mb-8">
                            Pass the <span class="text-primary">Standard.</span>
                        </h2>
                        <p class="dark:text-white/40 text-black/40 font-body text-lg mb-12">
                            Invitations to join the Serana Circle are exclusive. Every piece we craft is a testament to trust. Want to recommend a friend to the studio?
                        </p>
                        
                        <div v-if="!$page.props.auth?.user">
                            <Link :href="route('login')" class="inline-flex items-center gap-4 bg-white/10 border dark:border-white/20 border-black/20 dark:text-white text-on-surface px-10 py-5 rounded-sm font-headline text-xs tracking-[0.2em] font-medium hover:bg-white hover:text-black transition-all shadow-none">
                                Join the vault to invite <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            </Link>
                        </div>
                        <div v-else>
                            <Link :href="route('profile.designs')" class="inline-flex items-center gap-4 bg-transparent border border-primary/50 text-primary px-10 py-5 rounded-sm font-headline text-xs tracking-[0.2em] font-medium hover:bg-primary hover:text-black transition-all">
                                Generate invitation code <span class="material-symbols-outlined text-sm">qr_code_2</span>
                            </Link>
                        </div>
                    </div>
                </section>

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
