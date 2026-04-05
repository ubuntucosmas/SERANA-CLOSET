<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
    description: { type: String, required: true },
    image: { type: String, required: true },
    alignRight: { type: Boolean, default: false }
});
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-24 items-center py-20 reveal">
        
        <!-- Image Block with Cinematic Glass Overlay -->
        <div class="relative aspect-square md:aspect-[4/3] lg:aspect-square bg-transparent border dark:border-white/5 border-black/5 rounded-sm overflow-hidden shadow-2xl border dark:border-white/5 border-black/5"
             :class="{ 'lg:order-last': alignRight }">
            <img :src="image" :alt="title" class="w-full h-full object-cover transition-transform duration-1000 hover:scale-105" />
            
            <div class="absolute inset-0 bg-gradient-to-t from-background/80 via-transparent to-transparent pointer-events-none"></div>
            
            <!-- Luminous Edge Accent -->
            <div class="absolute inset-0 border border-primary/20 rounded-sm mix-blend-overlay pointer-events-none"></div>
        </div>

        <!-- Typography Storytelling -->
        <div class="space-y-6 flex flex-col justify-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-primary/10 rounded-full border border-primary/30 w-fit">
                <span class="text-[9px] font-medium uppercase tracking-[0.2em] text-primary">{{ subtitle }}</span>
            </div>
            
            <h2 class="text-4xl lg:text-5xl font-medium font-headline dark:text-white text-on-surface leading-[1.1] drop-shadow-lg" v-html="title"></h2>
            
            <div class="h-px w-16 bg-primary shadow-[0_0_10px_rgba(57, 255, 20,0.5)] my-6"></div>

            <p class="dark:text-white/40 text-black/40 font-body leading-relaxed text-base lg:text-lg">
                {{ description }}
            </p>
            
            <slot name="action"></slot>
        </div>

    </div>
</template>
