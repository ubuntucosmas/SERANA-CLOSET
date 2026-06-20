<!--
  SizingFigure — a simple human figure that highlights the body part you're
  measuring (bust / waist / hips / height) and shows plain how-to guidance.
  Compact and reusable: pass the currently focused field as `activeField`.
-->
<script setup>
import { computed } from 'vue';

const props = defineProps({
    activeField: { type: String, default: null },     // bust_cm | waist_cm | hips_cm | height_cm
    measurements: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['pick']);

const tips = {
    bust_cm: { label: 'Bust', text: 'Wrap the tape around the fullest part of your chest, level all the way round.' },
    waist_cm: { label: 'Waist', text: 'Measure the narrowest part of your waist, usually just above the navel.' },
    hips_cm: { label: 'Hips', text: 'Measure around the widest part of your hips, about 20 cm below the waist.' },
    height_cm: { label: 'Height', text: 'Stand barefoot against a wall and measure from the top of your head to the floor.' },
};

const active = computed(() => props.activeField);
const tip = computed(() => tips[props.activeField] || null);

// Tappable hotspots, so people can explore the figure too.
const fields = ['bust_cm', 'waist_cm', 'hips_cm', 'height_cm'];
</script>

<template>
    <div class="rounded-2xl border dark:border-white/10 border-black/10 bg-black/[0.03] dark:bg-white/[0.03] p-4 flex gap-4 items-center">
        <!-- Figure -->
        <div class="relative shrink-0 w-24 h-40">
            <svg viewBox="0 0 100 150" class="w-full h-full">
                <!-- Body silhouette -->
                <g class="dark:text-white/25 text-black/25" fill="currentColor">
                    <circle cx="50" cy="15" r="9" />
                    <path d="M50 25
                        C44 25 41 28 40 33
                        L31 38 C27 40 26 45 28 49 C30 52 33 51 35 49 L41 45
                        C40 53 39 60 41 67
                        C39 76 39 86 42 95
                        L40 132 C40 136 46 136 46 132 L49 100
                        L51 100 L54 132 C54 136 60 136 60 132 L58 95
                        C61 86 61 76 59 67
                        C61 60 60 53 59 45 L65 49 C67 51 70 52 72 49 C74 45 73 40 69 38 L60 33
                        C59 28 56 25 50 25 Z" />
                </g>

                <!-- Tap hotspots (invisible) -->
                <g @click="emit('pick','bust_cm')" class="cursor-pointer"><rect x="30" y="36" width="40" height="14" fill="transparent" /></g>
                <g @click="emit('pick','waist_cm')" class="cursor-pointer"><rect x="34" y="58" width="32" height="12" fill="transparent" /></g>
                <g @click="emit('pick','hips_cm')" class="cursor-pointer"><rect x="34" y="74" width="32" height="14" fill="transparent" /></g>

                <!-- Measuring highlights -->
                <g class="text-primary" stroke="currentColor" fill="none" stroke-linecap="round">
                    <!-- Bust -->
                    <g v-if="active === 'bust_cm'" class="band">
                        <line x1="33" y1="43" x2="67" y2="43" stroke-width="2.2" />
                        <line x1="33" y1="40" x2="33" y2="46" stroke-width="2" />
                        <line x1="67" y1="40" x2="67" y2="46" stroke-width="2" />
                    </g>
                    <!-- Waist -->
                    <g v-else-if="active === 'waist_cm'" class="band">
                        <line x1="36" y1="64" x2="64" y2="64" stroke-width="2.2" />
                        <line x1="36" y1="61" x2="36" y2="67" stroke-width="2" />
                        <line x1="64" y1="61" x2="64" y2="67" stroke-width="2" />
                    </g>
                    <!-- Hips -->
                    <g v-else-if="active === 'hips_cm'" class="band">
                        <line x1="35" y1="82" x2="65" y2="82" stroke-width="2.2" />
                        <line x1="35" y1="79" x2="35" y2="85" stroke-width="2" />
                        <line x1="65" y1="79" x2="65" y2="85" stroke-width="2" />
                    </g>
                    <!-- Height -->
                    <g v-else-if="active === 'height_cm'" class="band">
                        <line x1="80" y1="8" x2="80" y2="135" stroke-width="1.6" stroke-dasharray="3 3" />
                        <line x1="77" y1="8" x2="83" y2="8" stroke-width="2" />
                        <line x1="77" y1="135" x2="83" y2="135" stroke-width="2" />
                    </g>
                </g>
            </svg>
        </div>

        <!-- Guidance -->
        <div class="min-w-0">
            <template v-if="tip">
                <p class="text-xs font-bold uppercase tracking-widest text-primary mb-1">Measuring your {{ tip.label.toLowerCase() }}</p>
                <p class="text-[13px] leading-snug dark:text-white/80 text-on-surface">{{ tip.text }}</p>
            </template>
            <template v-else>
                <p class="text-[13px] leading-snug text-on-surface-variant">Tap a measurement below — the figure shows you exactly where to measure. All optional, but the more you add the better the fit.</p>
            </template>
        </div>
    </div>
</template>

<style scoped>
.band { animation: bandIn 0.35s ease; filter: drop-shadow(0 0 5px currentColor); }
@keyframes bandIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
</style>
