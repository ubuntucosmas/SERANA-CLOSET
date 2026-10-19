<script setup>
import { computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import SocialLinks from '@/Components/SocialLinks.vue';
import { useStore } from '@/Composables/useStore';

defineProps({ compact: { type: Boolean, default: false } });
const page = usePage();
const year = new Date().getFullYear();
// Contacts and socials come from the store profile (Studio → Settings).
const { store } = useStore();
const address = computed(() => store.value.address);
const studioHref = computed(() => store.value.whatsapp_url || route('contact'));
const columns = computed(() => [
    { title: 'Explore', links: [
        { label: 'All Pieces', href: route('shop') },
        ...(page.props.categories || []).slice(0, 4).map(category => ({ label: category.name, href: route('shop', { category: category.slug }) })),
    ] },
    { title: 'The Studio', links: [
        { label: 'Custom Order', href: route('custom-order') },
        { label: 'The Serana Circle', href: route('circle') },
        { label: 'Our Story', href: route('about') },
    ] },
    { title: 'Here to Help', links: [
        { label: 'Track Your Order', href: route('track') },
        { label: 'Contact & Delivery', href: route('contact') },
        { label: 'Your Bag', href: route('cart') },
    ] },
]);
const form = useForm({ email: '' });
function subscribe() {
    form.post(route('newsletter.subscribe'), { preserveScroll: true, onSuccess: () => form.reset() });
}
function backToTop() {
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
}
</script>

<template>
    <footer class="serana-footer border-t border-outline-variant/50 bg-surface text-on-surface" :class="compact ? 'py-6' : 'pb-24 pt-10 sm:pt-12 lg:pb-5'">
        <div class="page-container">
            <template v-if="!compact">
                <section class="grid items-center gap-6 border-b border-outline-variant/50 pb-9 sm:pb-11 lg:grid-cols-2 lg:gap-12" aria-labelledby="footer-newsletter-title">
                    <div>
                        <p class="mb-3 text-[11px] font-semibold uppercase tracking-[.16em] text-primary">Stay in the loop</p>
                        <h2 id="footer-newsletter-title" class="font-headline text-2xl font-medium leading-tight tracking-tight sm:text-3xl">New pieces. First to know.</h2>
                        <p class="mt-3 max-w-md text-sm leading-relaxed text-on-surface-variant">A note in your inbox when a new collection arrives.</p>
                    </div>
                    <form @submit.prevent="subscribe" class="w-full max-w-xl lg:ml-auto" aria-label="Collection updates">
                        <label for="footer-email" class="mb-2 block text-sm font-medium text-on-surface">Email address</label>
                        <div class="flex flex-col gap-2 min-[400px]:flex-row">
                            <input id="footer-email" v-model="form.email" type="email" required autocomplete="email" placeholder="you@example.com" :aria-invalid="form.errors.email ? 'true' : undefined" :aria-describedby="form.errors.email ? 'footer-email-error' : 'footer-email-help'"
                                class="footer-email h-12 min-w-0 flex-1 rounded-xl border border-outline-variant bg-background px-4 py-0 text-base text-on-surface placeholder:text-on-surface-variant focus:border-primary focus:ring-1 focus:ring-primary" />
                            <button type="submit" :disabled="form.processing" class="action-primary h-12 shrink-0 px-6 py-0 text-sm">
                                {{ form.processing ? 'Subscribing…' : 'Subscribe' }}
                                <svg v-if="!form.processing" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" /></svg>
                            </button>
                        </div>
                        <p v-if="form.errors.email" id="footer-email-error" role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300">{{ form.errors.email }}</p>
                        <p v-else-if="form.recentlySuccessful" id="footer-email-help" role="status" class="mt-2 text-sm text-primary">You’re subscribed. Look out for our next collection.</p>
                        <p v-else id="footer-email-help" class="mt-2 text-xs leading-relaxed text-on-surface-variant">Collection news, straight from the studio.</p>
                    </form>
                </section>

                <div class="grid gap-10 py-10 sm:py-12 lg:grid-cols-[1fr_1.8fr] lg:gap-16 xl:gap-24">
                    <section aria-label="About Serana Closet" class="min-w-0">
                        <Link :href="route('home')" class="inline-flex rounded-sm" aria-label="Serana Closet home"><ApplicationLogo /></Link>
                        <p class="mt-5 max-w-xs text-sm leading-relaxed text-on-surface-variant">Ready-to-wear pieces and custom orders, designed and made in Nairobi.</p>
                        <address class="mt-4 flex max-w-xs items-start gap-2 text-sm not-italic leading-relaxed text-on-surface-variant">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="2.5" /></svg>
                            <span>{{ address }}</span>
                        </address>
                        <a :href="studioHref" :target="store.whatsapp_url ? '_blank' : undefined" :rel="store.whatsapp_url ? 'noopener' : undefined" class="mt-2 inline-flex min-h-11 items-center gap-2 text-sm font-medium text-primary hover:underline underline-offset-4">Talk to the studio <span aria-hidden="true">↗</span></a>
                        <SocialLinks class="mt-3" />
                    </section>

                    <nav aria-label="Footer" class="grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-3 sm:gap-8">
                        <div v-for="(column, index) in columns" :key="column.title" class="min-w-0" :class="index === 0 ? 'col-span-2 sm:col-span-1' : ''">
                            <h3 class="mb-3 text-xs font-semibold uppercase tracking-[.12em] text-on-surface">{{ column.title }}</h3>
                            <ul :class="index === 0 ? 'grid grid-cols-2 gap-x-6 sm:block' : ''">
                                <li v-for="link in column.links" :key="link.href">
                                    <Link :href="link.href" class="footer-link">{{ link.label }}</Link>
                                </li>
                            </ul>
                        </div>
                    </nav>
                </div>
            </template>

            <div class="flex flex-col items-start justify-between gap-2 text-xs leading-relaxed text-on-surface-variant sm:flex-row sm:items-center" :class="compact ? '' : 'border-t border-outline-variant/50 pt-4'">
                <p>{{ page.props.theme_settings?.footer_copyright || `© ${year} ${store.name}. All rights reserved.` }}</p>
                <p v-if="compact">{{ address }}</p>
                <button v-else type="button" class="inline-flex min-h-11 items-center gap-2 font-medium text-on-surface transition-colors hover:text-primary" @click="backToTop">Back to top <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5m-6 6 6-6 6 6" /></svg></button>
            </div>
        </div>
    </footer>
</template>

<style scoped lang="postcss">
#footer-email { min-height: 3rem; }
.footer-link {
    @apply flex min-h-11 items-center py-2 text-sm leading-relaxed text-on-surface-variant transition-colors hover:text-primary;
}
.footer-social {
    @apply inline-flex h-11 w-11 items-center justify-center rounded-full border border-outline-variant text-on-surface-variant transition-colors hover:border-primary hover:text-primary;
}
@media (prefers-reduced-motion: reduce) {
    .footer-link, .footer-social { transition: none; }
}
</style>
