<script setup>
import { ref, onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const show = ref(false);
const message = ref('');
const type = ref('success');

const hideTimeout = ref(null);

function trigger(msg, t = 'success') {
    message.value = msg;
    type.value = t;
    show.value = true;
    
    if (hideTimeout.value) clearTimeout(hideTimeout.value);
    hideTimeout.value = setTimeout(() => {
        show.value = false;
    }, 5000);
}

// Watch for Inertia Flash messages
watch(() => page.props.flash, (flash) => {
    if (flash?.success) trigger(flash.success, 'success');
    if (flash?.error) trigger(flash.error, 'error');
}, { deep: true, immediate: true });

onMounted(() => {
    if (page.props.flash?.success) trigger(page.props.flash.success, 'success');
    if (page.props.flash?.error) trigger(page.props.flash.error, 'error');

    window.addEventListener('serana-toast', (e) => {
        trigger(e.detail.message, e.detail.type || 'success');
    });
});
</script>

<template>
    <div class="fixed top-8 right-8 z-[200] pointer-events-none space-y-4">
        <transition 
            enter-active-class="transform transition ease-out duration-500" 
            enter-from-class="translate-y-[-100%] opacity-0 scale-95" 
            enter-to-class="translate-y-0 opacity-100 scale-100" 
            leave-active-class="transition ease-in duration-300" 
            leave-from-class="opacity-100 scale-100" 
            leave-to-class="opacity-0 scale-95"
        >
            <div v-if="show" 
                 class="pointer-events-auto min-w-[320px] bg-surface-container/80 backdrop-blur-2xl border dark:border-white/10 border-black/10 p-5 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] flex items-center gap-4 group">
                
                <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                     :class="type === 'success' ? 'bg-primary/20 text-primary' : 'bg-red-500/20 text-red-400'">
                    <span class="material-symbols-outlined text-xl">
                        {{ type === 'success' ? 'verified' : 'error' }}
                    </span>
                </div>
                
                <div class="flex-1">
                    <p class="text-[10px] font-headline uppercase tracking-widest font-black"
                       :class="type === 'success' ? 'text-primary' : 'text-red-400'">
                        {{ type === 'success' ? 'Authority Confirmed' : 'Alert' }}
                    </p>
                    <p class="text-xs dark:text-white text-on-surface font-medium leading-relaxed mt-0.5">
                        {{ message }}
                    </p>
                </div>

                <button @click="show = false" class="text-on-surface-variant hover:dark:text-white text-on-surface transition-colors">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>

                <!-- Luminous Progress Bar -->
                <div class="absolute bottom-0 left-0 h-[1px] bg-primary transition-all duration-[5000ms] ease-linear"
                     :style="{ width: show ? '100%' : '0%' }"></div>
            </div>
        </transition>
    </div>
</template>
