import { defineStore } from 'pinia';
import { ref } from 'vue';

const MAX_VISIBLE = 3;

export const usePulseStore = defineStore('pulse', () => {
    const notifications = ref([]);
    const timers = new Map();

    /**
     * Trigger a system-level notification
     * @param {string} message - The message to display
     * @param {string} type - 'success', 'error', 'info', or 'warning'
     * @param {number} duration - MS before auto-hiding
     */
    const emit = (message, type = 'success', duration = 5000) => {
        // The same message again (a double tap, a repeated error) refreshes the
        // existing toast instead of stacking a copy.
        const existing = notifications.value.find(n => n.message === message && n.type === type);
        if (existing) {
            schedule(existing.id, duration);
            return;
        }

        const id = Date.now() + Math.random();
        notifications.value.push({ id, message, type });
        while (notifications.value.length > MAX_VISIBLE) remove(notifications.value[0].id);
        schedule(id, duration);
    };

    const schedule = (id, duration) => {
        clearTimeout(timers.get(id));
        timers.set(id, setTimeout(() => remove(id), duration));
    };

    const remove = (id) => {
        clearTimeout(timers.get(id));
        timers.delete(id);
        const index = notifications.value.findIndex(n => n.id === id);
        if (index !== -1) {
            notifications.value.splice(index, 1);
        }
    };

    return {
        notifications,
        emit,
        remove
    };
});
