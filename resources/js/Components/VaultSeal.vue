<script setup>
import { ref, onMounted } from 'vue';

const isBroken = ref(false);
const emit = defineEmits(['unlocked']);

function breakSeal() {
    isBroken.value = true;
    setTimeout(() => {
        emit('unlocked');
    }, 1500);
}
</script>

<template>
    <div v-if="!isBroken || true" 
         class="fixed inset-0 z-[100] flex items-center justify-center bg-background transition-all duration-1000"
         :class="isBroken ? 'opacity-0 pointer-events-none scale-110' : 'opacity-100'">
        
        <!-- Geometric Grid Background -->
        <div class="absolute inset-0 opacity-10" 
             style="background-image: linear-gradient(#99ff33 1px, transparent 1px), linear-gradient(90deg, #99ff33 1px, transparent 1px); background-size: 50px 50px;">
        </div>

        <div class="relative flex flex-col items-center gap-12 group cursor-pointer" @click="breakSeal">
            <!-- The Seal -->
            <div class="relative w-48 h-48 flex items-center justify-center">
                <!-- Inner Spinning Rings -->
                <div class="absolute inset-0 border-2 border-primary/20 rounded-full animate-[spin_10s_linear_infinite]"></div>
                <div class="absolute inset-4 border border-primary/40 rounded-full animate-[spin_6s_linear_infinite_reverse]"></div>
                
                <!-- Main Icon -->
                <div class="relative z-10 p-8 bg-surface-container rounded-full border border-primary/50 shadow-[0_0_50px_rgba(57, 255, 20,0.2)] group-hover:scale-110 group-hover:shadow-[0_0_80px_rgba(57, 255, 20,0.4)] transition-all duration-500">
                    <span class="material-symbols-outlined text- primary text-6xl luminous-glow">lock_open</span>
                </div>

                <!-- Labels -->
                <div class="absolute -top-12 left-1/2 -translate-x-1/2 whitespace-nowrap">
                    <span class="text-[10px] font-headline font-bold uppercase tracking-[0.5em] text-primary animate-pulse">Identity Verified</span>
                </div>
            </div>

            <div class="text-center space-y-2">
                <h2 class="text-2xl font-headline font-black dark:text-white text-on-surface tracking-widest uppercase">The Serana Vault</h2>
                <p class="text-[9px] font-headline font-bold uppercase tracking-[0.3em] text-on-surface-variant opacity-60">Click to enter your private archive</p>
            </div>

            <!-- Scanning Line -->
            <div class="absolute -inset-24 border border-primary/5 opacity-20 rounded-full animate-ping"></div>
        </div>

        <!-- Glitch Overlay when breaking -->
        <div v-if="isBroken" class="absolute inset-0 bg-primary/20 mix-blend-overlay animate-pulse"></div>
    </div>
</template>

<style scoped>
@keyframes ping {
    75%, 100% {
        transform: scale(1.5);
        opacity: 0;
    }
}
</style>
