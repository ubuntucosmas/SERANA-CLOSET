<template>
    <div class="section-bg absolute inset-0 z-0 overflow-hidden pointer-events-none select-none">
        <!-- Subtle texture -->
        <img
            :src="texture"
            class="absolute inset-0 w-full h-full object-cover grayscale brightness-50"
            :class="[opacity, blur]"
            alt="Background texture"
        />
        <!-- Simple gradient -->
        <div class="section-bg-scrim absolute inset-0 bg-gradient-to-b from-[#0a0a0b]/80 via-transparent to-[#0a0a0b]/80 z-10"></div>
    </div>
</template>

<script setup>
defineProps({
    texture: { type: String, default: '/images/black_cloth_texture.png' },
    opacity: { type: String, default: 'opacity-10' },
    blur: { type: String, default: 'blur-[2px]' }
});
</script>

<style scoped>
/* Removed scan line animation for clean elegance */
</style>
