import { defineStore } from 'pinia';
import { ref } from 'vue';

export const usePulseStore = defineStore('pulse', () => {
    const notifications = ref([]);

    /**
     * Trigger a system-level notification
     * @param {string} message - The message to display
     * @param {string} type - 'success', 'error', 'info', or 'warning'
     * @param {number} duration - MS before auto-hiding
     */
    const emit = (message, type = 'success', duration = 5000) => {
        const id = Date.now() + Math.random();
        
        const notification = {
            id,
            message,
            type,
            active: true
        };

        notifications.value.push(notification);

        setTimeout(() => {
            remove(id);
        }, duration);
    };

    const remove = (id) => {
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
