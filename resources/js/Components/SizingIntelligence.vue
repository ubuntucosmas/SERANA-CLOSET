<script setup>
defineProps({
    activeField: { type: String, default: null },
    measurements: { type: Object, default: () => ({}) }
});

const measurementTips = {
    height_cm: "Artisan Standard: Stand natural against a vertical plane. Full verticality from vertex to floor.",
    bust_cm: "Elite Fit: Circumference around the apex of the bust. Maintain horizontal tape tension.",
    waist_cm: "Architectural Line: Your natural pivot point. The narrowest circumference between rib and hip.",
    hips_cm: "Silhouette Foundation: Maximum circumference across the posterior. Typically 20-22cm below waist.",
    length_cm: "Drape Specification: From the high shoulder point (HPS) to the intended hemline terminal.",
};
</script>

<template>
    <div class="bg-surface-container/30 backdrop-blur-2xl border dark:border-white/10 border-black/10 rounded-2xl p-8 sticky top-32 shadow-2xl overflow-hidden group/sizing">
        <!-- Background Luminous Aura -->
        <div class="absolute -top-32 -right-32 w-64 h-64 bg-primary/20 blur-[80px] rounded-full pointer-events-none transition-all duration-1000"
             :class="{ 'opacity-100 scale-150 translate-x-10': activeField, 'opacity-40 scale-100': !activeField }"></div>

        <div class="relative z-10 space-y-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary luminous-glow text-xl">architecture</span>
                    <h3 class="font-headline text-[10px] tracking-[0.3em] font-black uppercase text-on-surface">Precision_Mapping</h3>
                </div>
                <div class="px-2 py-1 rounded-full bg-primary/10 border border-primary/20">
                    <span class="text-[8px] font-headline font-bold text-primary uppercase tracking-widest">v2.4 active</span>
                </div>
            </div>

            <!-- Cinematic Editorial Diagram -->
            <div class="relative w-full aspect-[3/4] flex justify-center py-4 bg-black/20 rounded-xl border border-white/5 overflow-hidden">
                <div class="absolute inset-0 scan-lines opacity-[0.03]"></div>
                
                <svg viewBox="0 0 100 160" class="h-full w-auto text-on-surface-variant/20 fill-none stroke-current stroke-[0.4] transition-all duration-700" :class="{'scale-105 opacity-100': activeField, 'opacity-40': !activeField}">
                    <!-- Editorial Body Form -->
                    <g class="transition-all duration-700">
                        <path d="M50 12 C54 12 58 16 58 22 C58 28 54 32 50 32 C46 32 42 28 42 22 C42 16 46 12 50 12 Z" /> <!-- Head -->
                        <path d="M35 38 Q50 35 65 38 Q67 50 72 75 Q40 80 28 75 Q33 50 35 38 Z" /> <!-- Shoulders/Bust Area -->
                        <path d="M33 75 Q50 72 67 75 L62 95 Q50 92 38 95 Z" /> <!-- Waist -->
                        <path d="M38 95 Q50 90 62 95 Q68 115 72 135 L28 135 Q32 115 38 95 Z" /> <!-- Hips/Legs -->
                    </g>

                    <!-- Mapping Highlights -->
                    <transition-group name="mapping">
                        <!-- Height Mapping -->
                        <g v-if="activeField === 'height_cm'" key="height" class="text-primary stroke-[1] drop-shadow-[0_0_8px_rgba(57,255,20,0.6)]">
                            <line x1="15" y1="8" x2="15" y2="152" stroke-dasharray="2 2" />
                            <path d="M12 8 L18 8 M12 152 L18 152" />
                            <circle cx="15" cy="8" r="1.5" fill="currentColor" />
                            <circle cx="15" cy="152" r="1.5" fill="currentColor" />
                        </g>

                        <!-- Bust Mapping -->
                        <g v-if="activeField === 'bust_cm'" key="bust" class="text-primary stroke-[1.2] drop-shadow-[0_0_8px_rgba(57,255,20,0.6)]">
                            <path d="M30 55 Q50 50 70 55" stroke-dasharray="3 2" />
                            <circle cx="30" cy="55" r="2" fill="currentColor" />
                            <circle cx="70" cy="55" r="2" fill="currentColor" />
                        </g>

                        <!-- Waist Mapping -->
                        <g v-if="activeField === 'waist_cm'" key="waist" class="text-primary stroke-[1.2] drop-shadow-[0_0_8px_rgba(57,255,20,0.6)]">
                            <path d="M35 85 Q50 80 65 85" stroke-dasharray="3 2" />
                            <circle cx="35" cy="85" r="2" fill="currentColor" />
                            <circle cx="65" cy="85" r="2" fill="currentColor" />
                        </g>

                        <!-- Hips Mapping -->
                        <g v-if="activeField === 'hips_cm'" key="hips" class="text-primary stroke-[1.2] drop-shadow-[0_0_8px_rgba(57,255,20,0.6)]">
                            <path d="M31 110 Q50 105 69 110" stroke-dasharray="3 2" />
                            <circle cx="31" cy="110" r="2" fill="currentColor" />
                            <circle cx="69" cy="110" r="2" fill="currentColor" />
                        </g>

                        <!-- Length Mapping -->
                        <g v-if="activeField === 'length_cm'" key="length" class="text-primary stroke-[1] drop-shadow-[0_0_8px_rgba(57,255,20,0.6)]">
                            <line x1="50" y1="40" x2="50" y2="145" stroke-dasharray="2 2" />
                            <circle cx="50" cy="40" r="1.5" fill="currentColor" />
                            <path d="M47 145 L53 145" />
                        </g>
                    </transition-group>
                </svg>

                <!-- Data Feed Overlay -->
                <div v-if="!activeField" class="absolute inset-x-0 bottom-8 flex flex-col items-center gap-2 animate-pulse">
                    <span class="text-[8px] font-headline tracking-[0.4em] text-primary uppercase">Waiting for input_</span>
                    <div class="w-12 h-[1px] bg-primary/30"></div>
                </div>
            </div>

            <!-- Artisan Logic Module -->
            <transition name="slide-up">
                <div v-if="activeField" class="relative">
                    <div class="p-6 rounded-xl border border-primary/30 bg-primary/10 backdrop-blur-md overflow-hidden animate-reveal">
                        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent"></div>
                        <div class="relative z-10 flex flex-col gap-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-primary animate-ping"></div>
                                    <span class="text-[9px] font-headline font-black text-primary uppercase tracking-[0.2em]">Live_Guidance</span>
                                </div>
                                <span class="text-[8px] font-mono text-primary/40">ID: {{ activeField.toUpperCase() }}</span>
                            </div>
                            <p class="text-sm font-medium leading-relaxed dark:text-white text-on-surface uppercase tracking-wider">
                                {{ measurementTips[activeField] }}
                            </p>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Status Indicator -->
            <div class="pt-6 border-t dark:border-white/10 border-black/10 flex items-center justify-between">
                <div class="flex flex-col gap-1">
                    <span class="text-[9px] font-headline text-on-surface-variant/40 uppercase tracking-widest">Encryption STATUS</span>
                    <span class="text-[10px] font-headline text-primary uppercase font-bold tracking-widest">End-to-End Secure</span>
                </div>
                <span class="material-symbols-outlined text-primary/40 text-sm">lock_open</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mapping-enter-active, .mapping-leave-active {
    transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.mapping-enter-from, .mapping-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(10px);
}

.slide-up-enter-active, .slide-up-leave-active {
    transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
.slide-up-enter-from, .slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

.scan-lines {
    background-image: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(255, 255, 255, 0.05) 2px,
        rgba(255, 255, 255, 0.05) 3px
    );
}

.animate-reveal {
    animation: reveal 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes reveal {
    from { clip-path: inset(0 100% 0 0); }
    to { clip-path: inset(0 0 0 0); }
}

.luminous-glow {
    text-shadow: 0 0 15px rgba(57, 255, 20, 0.5);
}
</style>

