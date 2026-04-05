<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const year = new Date().getFullYear();

// Social Links
const instagramFallback = "https://instagram.com/seranacloset";
const whatsapp = "https://wa.me/254700000000";

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
                    <a :href="whatsapp" target="_blank" rel="noopener" class="text-on-surface-variant hover:text-primary transition-all duration-300 transform hover:scale-110" title="WhatsApp">
                        <span class="material-symbols-outlined text-xl">chat</span>
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
                    <p class="text-[11px] dark:text-white text-on-surface font-headline tracking-[0.2em] leading-relaxed font-black">Join the archive registry for priority notifications on new limited batch drops.</p>
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
                {{ $page.props.theme_settings.footer_copyright || `© ${year} Serana Closet. The digital atelier.` }}
                <span class="mx-6 text-primary not-italic">Nairobi • Worldwide</span>
            </span>
            <div class="flex gap-10 items-center text-on-surface-variant opacity-30">
                <span class="material-symbols-outlined text-xl" title="Available Worldwide">public</span>
                <span class="material-symbols-outlined text-xl" title="Secure & Encrypted">verified_user</span>
                <span class="material-symbols-outlined text-xl" title="Bespoke Standard">straighten</span>
            </div>
        </div>
    </footer>
</template>
