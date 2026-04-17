<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    show: { type: Boolean, required: true },
    orderType: { type: String, default: 'Custom' }
});

const progress = ref(0);
const statusIndex = ref(0);
const statuses = [
    "Preparing your order...",
    "Finalizing your design details...",
    "Connecting to our team...",
    "Verifying fabric availability...",
    "Confirming your measurements...",
    "Connection ready. Opening WhatsApp."
];

onMounted(() => {
    const interval = setInterval(() => {
        if (props.show) {
            if (progress.value < 100) {
                progress.value += 1.5;
                if (progress.value > (statusIndex.value + 1) * (100 / statuses.length)) {
                    statusIndex.value++;
                }
            } else {
                clearInterval(interval);
            }
        }
    }, 50);
});
</script>

<template>
    <transition name="handoff">
        <div v-if="show" class="fixed inset-0 z-[100] bg-background/95 backdrop-blur-2xl flex flex-col items-center justify-center p-6 text-center space-y-12">
            <!-- ── Cinematic Background ── -->
            <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <div class="absolute inset-0 scan-lines opacity-10"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-background via-transparent to-background"></div>
                <!-- Pulsing Aura -->
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-primary/5 blur-[120px] rounded-full animate-pulse"></div>
            </div>

            <!-- ── Core Handoff Content ── -->
            <div class="relative z-10 w-full max-w-lg space-y-16">
                <!-- Artisan ID Branding -->
                <div class="flex flex-col items-center gap-4">
                    <div class="flex items-center gap-4 border-b border-white/5 pb-4 px-8">
                        <span class="font-headline text-[10px] tracking-[0.4em] text-primary uppercase font-black">Serana Boutique Checkout</span>
                        <div class="w-1.5 h-1.5 rounded-full bg-primary animate-ping"></div>
                    </div>
                    <h2 class="font-headline text-4xl font-black dark:text-white text-on-surface tracking-tighter uppercase">
                        SECURE CHECKOUT_
                    </h2>
                </div>

                <!-- Cinematic Progress Hexagon -->
                <div class="relative flex justify-center">
                    <div class="w-48 h-48 relative">
                        <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                            <!-- Track -->
                            <circle cx="50" cy="50" r="45" class="stroke-white/5 fill-none stroke-[2]" />
                            <!-- Progress -->
                            <circle 
                                cx="50" cy="50" r="45" 
                                class="stroke-primary fill-none stroke-[3] transition-all duration-100 ease-linear"
                                :style="{ strokeDasharray: 283, strokeDashoffset: 283 - (283 * progress / 100) }"
                                stroke-linecap="round"
                            />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-4xl font-headline font-black text-primary luminous-glow">{{ Math.floor(progress) }}%</span>
                            <span class="text-[8px] font-headline tracking-widest text-primary/40 uppercase">Processing_</span>
                        </div>
                    </div>
                </div>

                <!-- Status Ticker -->
                <div class="space-y-4">
                    <transition name="status" mode="out-in">
                        <p :key="statusIndex" class="text-xs font-headline font-bold tracking-[0.2em] text-on-surface uppercase min-h-[1.5em]">
                            {{ statuses[statusIndex] }}
                        </p>
                    </transition>
                    <div class="flex justify-center gap-1.5">
                        <div 
                            v-for="i in statuses.length" 
                            :key="i"
                            class="w-8 h-1 transition-all duration-500 rounded-full"
                            :class="i - 1 <= statusIndex ? 'bg-primary' : 'bg-white/5'"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- ── Footer Intelligence ── -->
            <div class="absolute bottom-12 inset-x-0 flex flex-col items-center gap-4 opacity-40">
                <div class="flex items-center gap-6">
                    <span class="text-[8px] font-headline tracking-[0.2em] text-white uppercase">Secure Checkout</span>
                    <span class="text-[8px] font-headline tracking-[0.2em] text-white uppercase">Serana Design Studio</span>
                </div>
                <p class="text-[8px] font-headline tracking-[0.3em] text-primary uppercase">Our team is ready to help.</p>
            </div>
        </div>
    </transition>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@900&display=swap');

.font-headline {
    font-family: 'Poppins', sans-serif !important;
}

.handoff-enter-active, .handoff-leave-active {
    transition: all 0.8s cubic-bezier(0.22, 1, 0.36, 1);
}
.handoff-enter-from, .handoff-leave-to {
    opacity: 0;
    backdrop-filter: blur(0px);
}

.status-enter-active, .status-leave-active {
    transition: all 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}
.status-enter-from {
    opacity: 0;
    transform: translateY(10px);
}
.status-leave-to {
    opacity: 0;
    transform: translateY(-10px);
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

.luminous-glow {
    text-shadow: 0 0 20px rgba(185, 195, 255, 0.4);
}
</style>
