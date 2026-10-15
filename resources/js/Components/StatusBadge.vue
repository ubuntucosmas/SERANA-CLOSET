<script setup>
defineProps({ label: { type: String, required: true }, tone: { type: String, default: 'pending' } });
const tones = {
    success: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    error: 'bg-red-500/10 text-red-700 dark:text-red-300 border-red-500/30',
    warning: 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30',
    pending: 'bg-primary/10 text-primary border-primary/30',
};
</script>
<template><span :class="tones[tone] || tones.pending" class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold">{{ label }}</span></template>
