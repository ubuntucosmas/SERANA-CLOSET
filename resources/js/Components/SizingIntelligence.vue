<script setup>
defineProps({
    activeField: { type: String, default: null }
});

const measurementTips = {
    height_cm: "Stand straight against a wall. Measure from the floor to the top of your head.",
    bust_cm: "Measure around the fullest part of your chest, keeping the tape horizontal.",
    waist_cm: "Measure around your natural waistline, typically the narrowest part of your torso.",
    hips_cm: "Measure around the fullest part of your hips, approximately 20cm below the waistline.",
    length_cm: "Measure from the highest point of your shoulder down to your desired hemline.",
};
</script>

<template>
    <div class="bg-surface-container/50 backdrop-blur-xl border dark:border-white/10 border-black/10 rounded-2xl p-8 sticky top-32 shadow-2xl overflow-hidden group">
        <!-- Background Luminous Glow -->
        <div class="absolute -top-24 -right-24 w-48 h-48 bg-primary/10 blur-[60px] rounded-full pointer-events-none transition-all duration-700"
             :class="{ 'opacity-100 scale-150': activeField, 'opacity-40 scale-100': !activeField }"></div>

        <div class="relative z-10 space-y-8">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary luminous-glow">architecture</span>
                <h3 class="font-headline text-xs tracking-[0.2em] font-bold uppercase text-on-surface">Size Guide</h3>
            </div>

            <!-- Stylized Measurement Avatar (Simplified SVG) -->
            <div class="relative w-full aspect-[2/3] flex justify-center py-4">
                <svg viewBox="0 0 100 150" class="h-full w-auto text-on-surface-variant/30 fill-none stroke-current stroke-[0.5]">
                    <!-- Head & Neck -->
                    <circle cx="50" cy="15" r="8" />
                    <path d="M46 23 L54 23 L52 28 L48 28 Z" />
                    
                    <!-- Torso -->
                    <path d="M35 30 L65 30 L68 55 L65 85 L35 85 L32 55 Z" />
                    
                    <!-- Arms -->
                    <path d="M35 30 L20 60 L24 64 L33 35" />
                    <path d="M65 30 L80 60 L76 64 L67 35" />

                    <!-- Active Highlights (Luminous Overlays) -->
                    <g v-if="activeField === 'height_cm'" class="text-primary stroke-[1.5] drop-shadow-[0_0_5px_rgba(57, 255, 20,0.5)]">
                        <line x1="75" y1="7" x2="75" y2="130" stroke-dasharray="2 1" />
                        <circle cx="75" cy="7" r="1.5" fill="currentColor" />
                        <circle cx="75" cy="130" r="1.5" fill="currentColor" />
                    </g>

                    <g v-if="activeField === 'bust_cm'" class="text-primary stroke-[1.5] drop-shadow-[0_0_5px_rgba(57, 255, 20,0.5)]">
                        <line x1="33" y1="42" x2="67" y2="42" stroke-dasharray="2 1" />
                        <circle cx="33" cy="42" r="1.5" fill="currentColor" />
                        <circle cx="67" cy="42" r="1.5" fill="currentColor" />
                    </g>
                    
                    <g v-if="activeField === 'waist_cm'" class="text-primary stroke-[1.5] drop-shadow-[0_0_5px_rgba(57, 255, 20,0.5)]">
                        <line x1="33" y1="60" x2="67" y2="60" stroke-dasharray="2 1" />
                        <circle cx="33" cy="60" r="1.5" fill="currentColor" />
                        <circle cx="67" cy="60" r="1.5" fill="currentColor" />
                    </g>

                    <g v-if="activeField === 'hips_cm'" class="text-primary stroke-[1.5] drop-shadow-[0_0_5px_rgba(57, 255, 20,0.5)]">
                        <line x1="32" y1="78" x2="68" y2="78" stroke-dasharray="2 1" />
                        <circle cx="32" cy="78" r="1.5" fill="currentColor" />
                        <circle cx="68" cy="78" r="1.5" fill="currentColor" />
                    </g>

                    <g v-if="activeField === 'length_cm'" class="text-primary stroke-[1.5] drop-shadow-[0_0_5px_rgba(57, 255, 20,0.5)]">
                        <line x1="40" y1="30" x2="40" y2="120" stroke-dasharray="2 1" />
                        <circle cx="40" cy="30" r="1.5" fill="currentColor" />
                        <circle cx="40" cy="120" r="1.5" fill="currentColor" />
                    </g>
                </svg>

                <div v-if="!activeField" class="absolute inset-0 flex items-center justify-center p-6 text-center">
                   <p class="text-[10px] text-on-surface-variant/60 font-body leading-relaxed">Select an input field to see measurement guidance.</p>
                </div>
            </div>

            <!-- Pro-Tip Box -->
            <transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-4"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-4"
            >
                <div v-if="activeField" class="p-5 bg-primary/15 rounded-xl border-2 border-primary/60 shadow-[0_0_20px_rgba(57,255,20,0.15)] space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-lg luminous-glow">tips_and_updates</span>
                        <span class="block text-[11px] font-black uppercase tracking-widest text-primary luminous-glow">Artisan Pro Tip</span>
                    </div>
                    <p class="text-sm text-on-surface leading-relaxed font-medium">{{ measurementTips[activeField] }}</p>
                </div>
            </transition>

            <div class="pt-4 border-t dark:border-white/5 border-black/5">
                <p class="text-[9px] text-on-surface-variant/40 leading-relaxed uppercase tracking-tighter">
                   Precision Guarantee: Our digital mapping reduces fit error by 94% compared to standard sizing.
                </p>
            </div>
        </div>
    </div>
</template>
