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
        class="h-11 w-11 min-h-[44px] min-w-[44px] shrink-0 flex items-center justify-center rounded-full text-on-surface-variant hover:bg-black/5 dark:hover:bg-white/5 transition-colors duration-200 group relative overflow-hidden active:scale-95"
        :title="isDark ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
        :aria-label="isDark ? 'Switch to Light Theme' : 'Switch to Dark Theme'"
        :aria-pressed="isDark"
    >
        <span class="material-symbols-outlined text-[22px]" aria-hidden="true">{{ isDark ? 'light_mode' : 'dark_mode' }}</span>

        <!-- Subtle background glow on hover -->
        <div class="absolute inset-0 bg-primary/5 opacity-0 group-hover:opacity-100 transition-opacity"></div>
    </button>
</template>

<style scoped>
.material-symbols-outlined {
    font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
</style>
