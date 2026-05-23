<script setup>
defineProps({
    activeField: { type: String, default: null },
    measurements: { type: Object, default: () => ({}) },
});

const measurementTips = {
    height_cm: "Stand straight, barefoot against a wall. Measure from the crown of your head straight down to the floor.",
    bust_cm: "Wrap the tape around the fullest part of your chest. Keep it parallel to the floor and breathe normally.",
    waist_cm: "Locate your natural waist (the narrowest point, usually above the navel) and measure around it.",
    hips_cm: "Measure around the widest part of your hips, approximately 20cm below the waistline.",
    length_cm: "Decide your preferred garment length and measure from the shoulder seam straight down to that point."
  };
</script>

<template>
    <div
        class="bg-surface-container/30 backdrop-blur-2xl border dark:border-white/10 border-black/10 rounded-2xl p-8 sticky top-32 shadow-2xl overflow-hidden group/sizing"
    >
        <!-- Background Luminous Aura -->
        <div
            class="absolute -top-32 -right-32 w-64 h-64 bg-primary/20 blur-[80px] rounded-full pointer-events-none transition-all duration-1000"
            :class="{
                'opacity-100 scale-150 translate-x-10': activeField,
                'opacity-40 scale-100': !activeField,
            }"
        ></div>

        <div class="relative z-10 space-y-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-xl"
                        >architecture</span
                    >
                    <h3
                        class="font-headline text-[10px] tracking-[0.3em] font-black uppercase text-on-surface"
                    >
                        Precision_Mapping
                    </h3>
                </div>
                <div
                    class="px-3 py-1 rounded-full bg-primary/10 border border-primary/20"
                >
                    <span
                        class="text-[9px] font-headline font-bold text-primary uppercase tracking-widest"
                        >Atelier Precision</span
                    >
                </div>
            </div>

            <!-- Simple Human Figure -->
            <div
                class="relative w-full h-40 flex justify-center items-center bg-black/20 rounded-xl border border-white/5 overflow-hidden"
            >
                <svg
                    viewBox="0 0 100 140"
                    class="h-36 w-auto text-on-surface-variant/30 fill-none stroke-current stroke-[0.8] transition-all duration-700"
                    :class="{
                        'scale-105 opacity-100': activeField,
                        'opacity-40': !activeField,
                    }"
                >
                    <!-- Simple Human Silhouette -->
                    <g class="transition-all duration-700">
                        <!-- Head -->
                        <circle cx="50" cy="18" r="8" />
                        <!-- Neck -->
                        <line x1="50" y1="26" x2="50" y2="32" />
                        <!-- Shoulders & Torso -->
                        <path d="M35 34 Q50 32 65 34 L62 58 Q50 56 38 58 Z" />
                        <!-- Waist -->
                        <path d="M38 58 Q50 56 62 58 L60 78 Q50 76 40 78 Z" />
                        <!-- Hips & Legs -->
                        <path d="M40 78 Q50 76 60 78 L58 110 L42 110 Z" />
                        <line x1="46" y1="110" x2="46" y2="132" />
                        <line x1="54" y1="110" x2="54" y2="132" />
                    </g>

                    <!-- Mapping Highlights -->
                    <transition-group name="mapping">
                        <!-- Height Mapping -->
                        <g
                            v-if="activeField === 'height_cm'"
                            key="height"
                            class="text-primary stroke-[1] drop-shadow-[0_0_8px_rgba(57,255,20,0.6)]"
                        >
                            <line
                                x1="50"
                                y1="6"
                                x2="50"
                                y2="135"
                                stroke-dasharray="2 2"
                            />
                            <path d="M47 6 L53 6 M47 135 L53 135" />
                            <circle
                                cx="50"
                                cy="6"
                                r="1.5"
                                fill="currentColor"
                            />
                            <circle
                                cx="50"
                                cy="135"
                                r="1.5"
                                fill="currentColor"
                            />
                        </g>

                        <!-- Bust Mapping -->
                        <g
                            v-if="activeField === 'bust_cm'"
                            key="bust"
                            class="text-primary stroke-[1.2] drop-shadow-[0_0_8px_rgba(57,255,20,0.6)]"
                        >
                            <path
                                d="M38 42 Q50 40 62 42"
                                stroke-dasharray="3 2"
                            />
                            <circle
                                cx="38"
                                cy="42"
                                r="1.5"
                                fill="currentColor"
                            />
                            <circle
                                cx="62"
                                cy="42"
                                r="1.5"
                                fill="currentColor"
                            />
                        </g>

                        <!-- Waist Mapping -->
                        <g
                            v-if="activeField === 'waist_cm'"
                            key="waist"
                            class="text-primary stroke-[1.2] drop-shadow-[0_0_8px_rgba(57,255,20,0.6)]"
                        >
                            <path
                                d="M40 65 Q50 64 60 65"
                                stroke-dasharray="3 2"
                            />
                            <circle
                                cx="40"
                                cy="65"
                                r="1.5"
                                fill="currentColor"
                            />
                            <circle
                                cx="60"
                                cy="65"
                                r="1.5"
                                fill="currentColor"
                            />
                        </g>

                        <!-- Hips Mapping -->
                        <g
                            v-if="activeField === 'hips_cm'"
                            key="hips"
                            class="text-primary stroke-[1.2] drop-shadow-[0_0_8px_rgba(57,255,20,0.6)]"
                        >
                            <path
                                d="M40 82 Q50 81 60 82"
                                stroke-dasharray="3 2"
                            />
                            <circle
                                cx="40"
                                cy="82"
                                r="1.5"
                                fill="currentColor"
                            />
                            <circle
                                cx="60"
                                cy="82"
                                r="1.5"
                                fill="currentColor"
                            />
                        </g>

                        <!-- Length Mapping -->
                        <g
                            v-if="activeField === 'length_cm'"
                            key="length"
                            class="text-primary stroke-[1] drop-shadow-[0_0_8px_rgba(57,255,20,0.6)]"
                        >
                            <line
                                x1="50"
                                y1="32"
                                x2="50"
                                y2="132"
                                stroke-dasharray="2 2"
                            />
                            <circle
                                cx="50"
                                cy="32"
                                r="1.5"
                                fill="currentColor"
                            />
                            <path d="M47 132 L53 132" />
                        </g>
                    </transition-group>
                </svg>

                <!-- Data Feed Overlay -->
                <div
                    v-if="!activeField"
                    class="absolute inset-x-0 bottom-8 flex flex-col items-center gap-2 animate-pulse"
                >
                    <span
                        class="text-[8px] font-headline tracking-[0.4em] text-primary uppercase"
                        >Waiting for input_</span
                    >
                    <div class="w-12 h-[1px] bg-primary/30"></div>
                </div>
            </div>

            <!-- Artisan Logic Module -->
            <transition name="slide-up">
                <div v-if="activeField" class="relative">
                    <div
                        class="p-6 rounded-xl border border-primary/30 bg-primary/10 backdrop-blur-md overflow-hidden animate-reveal"
                    >
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-primary/5 to-transparent"
                        ></div>
                        <div class="relative z-10 flex flex-col gap-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-2 h-2 rounded-full bg-primary animate-ping"
                                    ></div>
                                    <span
                                        class="text-[9px] font-headline font-black text-primary uppercase tracking-[0.2em]"
                                        >Live_Guidance</span
                                    >
                                </div>
                                <span
                                    class="text-[8px] font-mono text-primary/40"
                                    >ID: {{ activeField.toUpperCase() }}</span
                                >
                            </div>
                            <p
                                class="text-sm font-medium leading-relaxed dark:text-white text-on-surface uppercase tracking-wider"
                            >
                                {{ measurementTips[activeField] }}
                            </p>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- Status Indicator -->
            <div
                class="pt-6 border-t dark:border-white/10 border-black/10 flex items-center justify-between"
            >
                <div>
                    <span
                        class="text-[9px] font-headline text-on-surface-variant/40 uppercase tracking-widest"
                        >Handcrafted in Nairobi</span
                    >
                    <div
                        class="text-primary text-[10px] tracking-widest font-medium"
                    >
                        Serana Atelier
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mapping-enter-active,
.mapping-leave-active {
    transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}
.mapping-enter-from,
.mapping-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(10px);
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

.animate-reveal {
    animation: reveal 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes reveal {
    from {
        clip-path: inset(0 100% 0 0);
    }
    to {
        clip-path: inset(0 0 0 0);
    }
}
</style>
