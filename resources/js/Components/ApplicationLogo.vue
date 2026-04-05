<template>
    <div class="flex items-center gap-4 group cursor-pointer" @click="$inertia.visit('/')">
        <!-- Site Logo -->
        <template v-if="$page.props.theme_settings.site_logo">
            <img :src="$page.props.theme_settings.site_logo" class="h-12 w-auto object-contain" alt="Serana Logo">
        </template>
        
        <!-- Serana Monolith Icon (Fallback) -->
        <svg v-else viewBox="0 0 100 100" class="w-12 h-12 drop-shadow-[0_0_15px_rgba(8,203,0,0.3)]">
            <!-- The Monolith (Main Slab) -->
            <rect x="35" y="10" width="30" height="80" class="fill-white" />
            
            <!-- The Luminous Pulse (Offset Accent) -->
            <rect x="42" y="45" width="16" height="10" class="fill-primary animate-pulse" />
            
            <!-- Framing Guides (Subtle) -->
            <g class="stroke-white/10" stroke-width="0.5">
                <line x1="10" y1="50" x2="30" y2="50" />
                <line x1="70" y1="50" x2="90" y2="50" />
            </g>
        </svg>

        <!-- Serana Wordmark -->
        <div class="flex flex-col">
            <h1 class="font-headline text-2xl font-black tracking-[0.4em] dark:text-white text-on-surface leading-none uppercase">
                {{ $page.props.theme_settings.site_headline || 'SERANA' }}
            </h1>
            <span class="text-[8px] font-headline font-bold uppercase tracking-[0.8em] text-primary mt-1">
                {{ $page.props.theme_settings.site_headline ? 'STUDIO' : 'CLOSET' }}
            </span>
        </div>
    </div>
</template>

<script setup>
// Simplified Static Logo for Monolith aesthetic
</script>

<style scoped>
.logo-pulse {
    transition: color 0.3s ease;
}
.group:hover .logo-pulse {
    color: white;
    text-shadow: 0 0 30px rgba(255,255,255,0.8);
}
</style>
