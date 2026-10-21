<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePulseStore } from '@/Stores/usePulseStore';

const page = usePage();
const pulse = usePulseStore();
const modalHost = ref(null);
const destination = computed(() => modalHost.value || 'body');
let observer;
function updateHost() {
    const hosts = [...document.querySelectorAll('[data-notification-host]')].filter(host => {
        const modal = host.closest('[data-notification-modal]');
        return modal && modal.getAttribute('data-notification-active') !== 'false' && modal.getClientRects().length > 0;
    });
    // Native dialogs occupy the browser top layer, above ordinary overlays.
    const nativeHosts = hosts.filter(host => host.closest('dialog[open]'));
    modalHost.value = (nativeHosts.length ? nativeHosts : hosts).at(-1) || null;
}


// Watch for Inertia Flash messages and pipe them to Pulse
watch(() => page.props.flash, (flash) => {
    if (flash?.success) pulse.emit(flash.success, 'success');
    if (flash?.error) pulse.emit(flash.error, 'error');
}, { deep: true, immediate: true });

const handleToast = (event) => pulse.emit(event.detail.message, event.detail.type || 'success');
onMounted(() => {
    window.addEventListener('serana-toast', handleToast);
    observer = new MutationObserver(updateHost);
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['open', 'hidden', 'data-notification-active'] });
    updateHost();
});
onUnmounted(() => {
    window.removeEventListener('serana-toast', handleToast);
    observer?.disconnect();
});

const icons = { success: 'check_circle', error: 'warning', info: 'chat', warning: 'warning' };
</script>

<template>
    <Teleport :to="destination">
    <div v-if="pulse.notifications.length" aria-live="polite" :class="modalHost ? 'modal-notifications' : 'page-notifications'" class="pointer-events-none flex flex-col gap-2">
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
    </Teleport>
</template>

<style scoped>
.page-notifications { position: fixed; inset: 16px 16px auto; z-index: 500; align-items: center; }
.modal-notifications { position: relative; width: 100%; align-items: stretch; padding: 12px; }
.modal-notifications .toast { max-width: none; }
@media (min-width: 640px) { .page-notifications { left: auto; right: 32px; top: 32px; align-items: flex-end; } }

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
