<script setup>
import { ref, onMounted } from 'vue';

const isDark = ref(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

const updateTheme = () => {
    document.documentElement.classList.toggle('dark', isDark.value);
    try {
        localStorage.setItem('serana-theme-v2', isDark.value ? 'dark' : 'light');
    } catch {
        // The toggle still works for this visit when browser storage is unavailable.
    }
};

const toggleTheme = () => {
    isDark.value = !isDark.value;
    updateTheme();
};

onMounted(() => {
    try {
        isDark.value = localStorage.getItem('serana-theme-v2') === 'dark';
    } catch {
        isDark.value = false;
    }
    updateTheme();
});
</script>

<template>
    <button
        type="button"
        @click="toggleTheme" 
        class="w-10 h-10 flex items-center justify-center rounded-xl border dark:border-white/10 border-black/10 hover:border-primary/50 transition-all duration-300 group relative overflow-hidden bg-white/5 active:scale-90"
        :title="isDark ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
        :aria-label="isDark ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
        :aria-pressed="isDark"
    >
        <div class="relative w-6 h-6 flex flex-col transition-transform duration-500" :style="{ transform: isDark ? 'translateY(0)' : 'translateY(-100%)' }">
            <span class="material-symbols-outlined text-[20px] shrink-0 h-6 flex items-center justify-center text-primary">dark_mode</span>
            <span class="material-symbols-outlined text-[20px] shrink-0 h-6 flex items-center justify-center text-on-surface">light_mode</span>
        </div>
        
        <!-- Subtle background glow on hover -->
        <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
    </button>
</template>

<style scoped>
.material-symbols-outlined {
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
</style>
