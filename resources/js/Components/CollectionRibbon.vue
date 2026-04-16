<script setup>
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { animate, stagger } from 'animejs';

defineProps({
    products: Array
});

onMounted(() => {
    animate('.ribbon-item', {
        opacity: [0, 1],
        translateX: [50, 0],
        delay: stagger(100, { start: 500 }),
        duration: 1000,
        easing: 'easeOutExpo'
    });
});
</script>

<template>
    <section class="py-12 bg-background border-y dark:border-white/5 border-black/5 overflow-hidden">
        <div class="px-8 mb-8 flex justify-between items-end">
            <div class="space-y-1">
                <span class="text-primary font-headline font-bold text-[8px] tracking-[0.4em] uppercase hidden lg:block">Live from the Archive</span>
                <h2 class="text-xl lg:text-2xl font-headline font-medium dark:text-white text-on-surface uppercase tracking-tight">Current Drop_</h2>
            </div>
            <Link :href="route('shop')" class="text-[9px] font-headline font-bold text-primary border-b border-primary/20 pb-1 hover:border-primary transition-all uppercase tracking-widest px-2">
                All
            </Link>
        </div>

        <div class="flex gap-6 overflow-x-auto no-scrollbar px-8 pb-4 mask-horizontal">
            <div 
                v-for="product in products" 
                :key="product.id" 
                class="ribbon-item flex-shrink-0 w-64 opacity-0"
            >
                <Link :href="route('product.show', product.slug)" class="group block">
                    <div class="aspect-[4/5] bg-surface-container overflow-hidden rounded-sm relative border dark:border-white/5 border-black/5 shadow-2xl">
                        <img 
                            :src="product.image" 
                            class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700 scale-100 group-hover:scale-105" 
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <!-- Hidden on mobile for Zen -->
                        <div class="absolute bottom-4 left-4 right-4 translate-y-4 group-hover:translate-y-0 transition-transform opacity-0 group-hover:opacity-100 hidden lg:block">
                             <span class="bg-primary text-black px-4 py-2 text-[8px] font-headline font-black uppercase tracking-widest rounded-sm">Quick_View</span>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-between items-start">
                        <h3 class="text-[11px] font-headline font-medium dark:text-white text-on-surface uppercase tracking-widest">{{ product.name }}</h3>
                        <span class="text-[9px] font-headline font-black text-primary">KSh {{ Number(product.price).toLocaleString() }}</span>
                    </div>
                </Link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.mask-horizontal {
    mask-image: linear-gradient(to right, transparent, black 5%, black 95%, transparent);
    -webkit-mask-image: linear-gradient(to right, transparent, black 15%, black 85%, transparent);
}
</style>
