<script setup>
import { watch, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePulseStore } from '@/Stores/usePulseStore';
import { animate } from 'animejs';

const page = usePage();
const pulse = usePulseStore();

// Watch for Inertia Flash messages and pipe them to Pulse
watch(() => page.props.flash, (flash) => {
    if (flash?.success) pulse.emit(flash.success, 'success');
    if (flash?.error) pulse.emit(flash.error, 'error');
}, { deep: true, immediate: true });

const handleToast = (event) => pulse.emit(event.detail.message, event.detail.type || 'success');
onMounted(() => window.addEventListener('serana-toast', handleToast));
onUnmounted(() => window.removeEventListener('serana-toast', handleToast));

const enterAnimation = (el, done) => {
    animate(el, {
        translateX: [400, 0],
        opacity: [0, 1],
        easing: 'spring(1, 80, 10, 0)',
        duration: 800,
        onComplete: done
    });
    
    // Glitch effect on entry
    animate(el.querySelector('.glitch-overlay'), {
        opacity: [0, 0.5, 0],
        translateX: [-5, 5, 0],
        duration: 200,
        easing: 'easeInOutQuad'
    });
};

const leaveAnimation = (el, done) => {
    animate(el, {
        translateX: [0, 400],
        opacity: [1, 0],
        easing: 'easeInOutExpo',
        duration: 500,
        onComplete: done
    });
};
</script>

<template>
    <div aria-live="polite" class="fixed top-4 right-4 sm:top-8 sm:right-8 z-[200] pointer-events-none flex flex-col gap-3 items-end">
        <transition-group 
            @enter="enterAnimation" 
            @leave="leaveAnimation"
            :css="false"
        >
            <div 
                v-for="note in pulse.notifications" 
                :key="note.id"
                class="pointer-events-auto w-[min(20rem,calc(100vw-2rem))] md:w-96 bg-surface/90 backdrop-blur-xl border border-white/5 p-4 rounded-sm shadow-[0_15px_40px_rgba(0,0,0,0.6)] relative overflow-hidden group"
            >
                <!-- Tech-Noir Scanner Element -->
                <div class="absolute inset-0 pointer-events-none overflow-hidden">
                    <div class="scanner-line h-full w-[1px] bg-primary/20 absolute left-0 animate-scan"></div>
                    <div class="glitch-overlay absolute inset-0 bg-primary/5 opacity-0"></div>
                </div>

                <div class="flex items-start gap-4 relative z-10">
                    <div 
                        class="w-10 h-10 shrink-0 border border-white/10 flex items-center justify-center bg-black/40"
                        :class="note.type === 'success' ? 'text-primary' : 'text-red-500'"
                    >
                        <span class="material-symbols-outlined text-lg">
                            {{ note.type === 'success' ? 'dynamic_feed' : 'warning' }}
                        </span>
                    </div>

                    <div class="flex-1 space-y-1">
                        <div class="flex justify-between items-center">
                            <span class="text-[11px] font-bold tracking-[0.2em] uppercase" :class="note.type === 'success' ? 'text-primary' : 'text-red-500'">
                                {{ note.type === 'success' ? 'Done' : 'Something went wrong' }}
                            </span>
                            <button aria-label="Dismiss notification" @click="pulse.remove(note.id)" class="text-white/20 hover:text-white transition-colors">
                                <span class="material-symbols-outlined text-[14px]">close</span>
                            </button>
                        </div>
                        <p class="text-[11px] text-white/80 font-medium leading-relaxed font-mono">
                            {{ note.message }}
                        </p>
                    </div>
                </div>

                <!-- Luminous Progress Bar -->
                <div class="absolute bottom-0 left-0 h-[1px] bg-primary/40 w-full origin-left animate-timer"></div>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
@keyframes scan {
    0% { transform: translateX(0); }
    100% { transform: translateX(384px); }
}
.animate-scan {
    animation: scan 3s linear infinite;
}

@keyframes timer {
    0% { transform: scaleX(1); }
    100% { transform: scaleX(0); }
}
.animate-timer {
    animation: timer 5s linear forwards;
}

.font-mono {
    font-family: 'Manrope', sans-serif;
}
</style>
