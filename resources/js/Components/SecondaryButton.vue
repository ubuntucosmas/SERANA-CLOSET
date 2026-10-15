<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="action-secondary text-on-surface disabled:opacity-50"
    >
        <slot />
    </button>
</template>
