<script setup>
import { ref, onMounted } from 'vue';

const isDark = ref(true);

const toggleTheme = () => {
    isDark.value = !isDark.value;
    updateTheme();
};

const updateTheme = () => {
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('serana-theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('serana-theme', 'light');
    }
};

onMounted(() => {
    const savedTheme = localStorage.getItem('serana-theme');
    if (savedTheme) {
        isDark.value = savedTheme === 'dark';
    } else {
        // Default to dark for that Serana brand identity
        isDark.value = true;
    }
    updateTheme();
});
</script>

<template>
    <button 
        @click="toggleTheme" 
        class="w-10 h-10 flex items-center justify-center rounded-xl border dark:border-white/10 border-black/10 hover:border-primary/50 transition-all duration-300 group relative overflow-hidden bg-white/5 active:scale-90"
        :title="isDark ? 'Switch to Studio White' : 'Switch to Tech-Noir'"
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
