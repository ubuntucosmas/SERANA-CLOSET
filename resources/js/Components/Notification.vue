<script setup>
import { watch, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePulseStore } from '@/Stores/usePulseStore';

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

const icons = { success: 'check_circle', error: 'warning', info: 'chat', warning: 'warning' };
</script>

<template>
    <!-- Top-centre on phones (clear of the bottom bar), top-right on desktop. -->
    <div aria-live="polite" class="fixed inset-x-4 top-4 z-[200] pointer-events-none flex flex-col items-center gap-2 sm:inset-x-auto sm:right-8 sm:top-8 sm:items-end">
        <transition-group name="toast">
            <div
                v-for="note in pulse.notifications"
                :key="note.id"
                :role="note.type === 'error' ? 'alert' : 'status'"
                class="toast pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border p-4 shadow-xl backdrop-blur-xl"
                :class="note.type === 'error' ? 'toast-error' : ''"
            >
                <span class="material-symbols-outlined mt-px text-xl" :class="note.type === 'error' ? 'text-red-500' : 'text-primary'" aria-hidden="true">{{ icons[note.type] || 'check_circle' }}</span>
                <p class="flex-1 text-sm leading-snug text-on-surface">{{ note.message }}</p>
                <button aria-label="Dismiss notification" @click="pulse.remove(note.id)" class="-m-2 flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-on-surface-variant transition-colors hover:text-on-surface">
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">close</span>
                </button>
            </div>
        </transition-group>
    </div>
</template>

<style scoped>
.toast {
    background: rgb(var(--surface-rgb) / 0.94);
    border-color: var(--card-border, rgba(127, 127, 127, 0.2));
}
.toast-error {
    border-color: rgba(239, 68, 68, 0.45);
}
.toast-enter-active, .toast-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.toast-enter-from { opacity: 0; transform: translateY(-8px); }
.toast-leave-to { opacity: 0; transform: translateY(-4px); }
.toast-move { transition: transform 0.2s ease; }
</style>
