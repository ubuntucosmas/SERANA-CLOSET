<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const year = new Date().getFullYear();

// Social Links
const instagramFallback = computed(() => usePage().props.theme_settings?.instagram_url || "https://instagram.com/seranacloset");
const whatsapp = computed(() => `https://wa.me/${usePage().props.whatsapp_number}`);

// Navigation Links
const exploreLinks = [
    { label: 'The Archive', href: route('shop') },
    { label: 'Custom Studio', href: route('custom-order') },
    { label: 'The Measurement Lab', href: route('custom-order') },
    { label: 'Our Story', href: route('about') },
];

const legalLinks = [
    { label: 'Shipping & Logistics', href: '#' },
    { label: 'Privacy Policy', href: '#' },
    { label: 'Terms of Service', href: '#' },
    { label: 'Studio Authenticity', href: '#' },
];

const form = useForm({
    email: '',
});

function subscribe() {
    form.post(route('newsletter.subscribe'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: (errors) => {
            if (errors.email) {
                window.dispatchEvent(new CustomEvent('serana-toast', { 
                    detail: { message: errors.email, type: 'error' } 
                }));
            }
        }
    });
}
</script>

<template>
    <footer class="bg-background border-t dark:border-white/5 border-black/5 w-full pt-32 pb-16">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-16 px-8 max-w-screen-2xl mx-auto mb-24">

            <!-- Brand Column -->
            <div class="md:col-span-1 space-y-8">
                <div class="font-headline text-2xl font-black dark:text-white text-on-surface tracking-tighter">
                    SERANA <span class="text-primary font-normal luminous-glow">CLOSET</span>
                </div>
                <p class="text-on-surface-variant font-body text-sm leading-relaxed max-w-xs">
                    Celebrating the art of custom tailoring and modern luxury. Every piece is a testament to trust and precision.
                </p>
                <div class="flex gap-6">
                    <a :href="$page.props.theme_settings.instagram_url || instagramFallback" target="_blank" rel="noopener" class="text-on-surface-variant hover:text-primary transition-all duration-300 transform hover:scale-110" title="Instagram">
                        <span class="material-symbols-outlined text-xl">photo_camera</span>
                    </a>
                    <a :href="whatsapp" target="_blank" rel="noopener" class="text-on-surface-variant hover:scale-110 transition-all duration-300 transform" title="WhatsApp">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" style="fill:#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.57-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    </a>
                </div>
            </div>

            <!-- Explore Column -->
            <div>
                <h4 class="text-primary font-headline font-black text-[11px] tracking-[0.25em] mb-10">Archive core</h4>
                <ul class="space-y-5">
                    <li v-for="link in exploreLinks" :key="link.label">
                        <Link :href="link.href" class="text-on-surface-variant font-body text-sm hover:text-primary transition-all duration-300 block">
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- Legal Column -->
            <div>
                <h4 class="text-on-surface-variant font-headline font-black text-[11px] tracking-[0.25em] mb-10 opacity-70">Standards</h4>
                <ul class="space-y-5">
                    <li v-for="link in legalLinks" :key="link.label">
                        <Link :href="link.href" class="text-on-surface-variant font-body text-sm hover:text-primary transition-all duration-300 block">
                            {{ link.label }}
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- Newsletter Column -->
            <div class="md:col-span-1 space-y-8">
                <h4 class="text-primary font-headline font-black text-[11px] tracking-[0.25em] mb-8">Inner circle</h4>
                <div class="flex flex-col gap-6">
                    <p class="text-[11px] dark:text-white text-on-surface font-headline tracking-[0.2em] leading-relaxed font-black">Join our newsletter for priority notifications on new limited batch drops.</p>
                    <form @submit.prevent="subscribe" class="flex gap-2">
                        <input v-model="form.email" type="email" placeholder="Your email" class="flex-grow dark:bg-white/5 dark:bg-black bg-white/5 dark:border-white/10 border-black/10 text-[11px] font-headline tracking-[0.2em] px-6 py-5 rounded-xl focus:border-primary/50 focus:ring-0 transition-all dark:text-white text-on-surface placeholder:text-on-surface-variant/30 font-black outline-none" required />
                        <button type="submit" :disabled="form.processing" class="bg-primary text-background px-4 py-4 rounded-xl hover:shadow-[0_0_20px_rgba(57, 255, 20,0.3)] transition-all flex items-center justify-center">
                            <span v-if="form.processing" class="animate-spin material-symbols-outlined text-sm">sync</span>
                            <span v-else class="material-symbols-outlined text-sm">arrow_forward</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="px-8 max-w-screen-2xl mx-auto flex flex-col md:flex-row justify-between items-center border-t dark:border-white/5 border-black/5 pt-12 gap-8">
            <span class="dark:text-white text-on-surface text-[11px] font-headline tracking-[0.2em] font-black">
                {{ $page.props.theme_settings.footer_copyright || `© ${year} Serana Closet.` }}
                <span class="mx-6 text-primary not-italic uppercase tracking-widest">IMENTI HOUSE 29 • Nairobi • Worldwide</span>
            </span>
            <div class="flex gap-10 items-center text-on-surface-variant opacity-30">
                <span class="material-symbols-outlined text-xl" title="Available Worldwide">public</span>
                <span class="material-symbols-outlined text-xl" title="Secure & Encrypted">verified_user</span>
                <span class="material-symbols-outlined text-xl" title="Bespoke Standard">straighten</span>
            </div>
        </div>
    </footer>
</template>
