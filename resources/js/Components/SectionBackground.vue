<script setup>
/**
 * SectionBackground.vue
 * High-depth "Obsidian Silk" and "White" precision layer for major platform sections.
 */
defineProps({
    texture: {
        type: String,
        default: '/images/black_cloth_texture.png'
    },
    opacity: {
        type: String,
        default: 'opacity-10'
    },
    showScan: {
        type: Boolean,
        default: true
    },
    blur: {
        type: String,
        default: 'blur-[2px]'
    }
});
</script>

<template>
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none select-none">
        <!-- Silk Texture Layer -->
        <img 
            :src="texture" 
            class="absolute inset-0 w-full h-full object-cover grayscale brightness-50" 
            :class="[opacity, blur]"
            alt="Obsidian Silk Background"
        />

        <!-- Refined Gradient Shading -->
        <div class="absolute inset-0 bg-gradient-to-b from-background via-transparent to-background z-10 opacity-80"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-background/40 via-transparent to-background/40 z-10"></div>

        <!-- Tactical White Scan Line -->
        <div v-if="showScan" class="absolute inset-0 z-20">
            <div class="w-full h-px bg-white/5 absolute top-0 animate-section-scan"></div>
        </div>
    </div>
</template>

<style scoped>
@keyframes section-scan {
    0% { transform: translateY(-100%); opacity: 0; }
    10% { opacity: 0.15; }
    50% { opacity: 0.05; }
    100% { transform: translateY(100vh); opacity: 0; }
}

.animate-section-scan {
    animation: section-scan 15s linear infinite;
}
</style>
