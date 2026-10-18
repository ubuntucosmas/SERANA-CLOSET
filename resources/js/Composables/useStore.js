import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Who the store is and how to reach it: page.props.store, filled from
 * Studio → Settings → Store details & contact (App\Support\StoreProfile).
 * Use this instead of reading theme_settings or hard-coding contacts.
 */
export function useStore() {
    const page = usePage();
    const store = computed(() => page.props.store || { name: 'Serana Closet', whatsapp: '', whatsapp_url: '', socials: [] });

    /** wa.me link to the store with an optional pre-filled message. */
    const whatsappLink = (message = '') => {
        const number = store.value.whatsapp;
        if (!number) return null;
        return message ? `https://wa.me/${number}?text=${encodeURIComponent(message)}` : `https://wa.me/${number}`;
    };

    return { store, whatsappLink };
}
