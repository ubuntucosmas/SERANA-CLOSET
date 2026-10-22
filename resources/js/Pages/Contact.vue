<template>
    <StorefrontLayout>
        <Head>
            <title>The Studio | Contact Serana Closet</title>
        </Head>

        <main class="page-container pb-24 font-body">
            <!-- Header Section -->
            <PageHeader kicker="Contact" title="Contact" accent="Serana" subtitle="Ask about an order, sizing or a custom order. Send your message to our team on WhatsApp." />

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-16 items-start">
                <!-- Contact Channels -->
                <div class="space-y-7 sm:space-y-12 reveal">
                    <div class="space-y-8">
                        <div class="flex items-start gap-4 sm:gap-8 group">
                            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-primary/10 border border-primary/20 rounded-2xl flex items-center justify-center shrink-0 group-hover:bg-primary transition-all duration-500">
                                <span class="material-symbols-outlined text-primary group-hover:text-background text-3xl">chat</span>
                            </div>
                            <div>
                                <h3 class="font-headline text-xl font-bold dark:text-white text-on-surface mb-2">WhatsApp</h3>
                                <p class="text-on-surface-variant text-sm mb-4">Direct line for fitting updates and new design inquiries.</p>
                                <a :href="whatsappUrl" target="_blank" rel="noopener" class="text-primary font-headline text-lg font-black tracking-widest hover:dark:text-white text-on-surface transition-colors">
                                    {{ store.whatsapp_display }}
                                </a>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 sm:gap-8 group">
                            <div class="w-12 h-12 sm:w-16 sm:h-16 dark:bg-white/5 dark:bg-black bg-white/5 border dark:border-white/10 border-black/10 rounded-2xl flex items-center justify-center shrink-0 dark:group-hover:bg-primary group-hover:bg-primary transition-all duration-500">
                                <span class="material-symbols-outlined text-on-surface-variant group-hover:text-background text-3xl">mail</span>
                            </div>
                            <div>
                                <h3 class="font-headline text-xl font-bold dark:text-white text-on-surface mb-2">Email</h3>
                                <p class="text-on-surface-variant text-sm mb-4">For press, collaborations and other enquiries.</p>
                                <a :href="`mailto:${contactEmail}`" class="text-on-surface font-headline text-lg font-bold tracking-widest hover:text-primary transition-colors">
                                    {{ contactEmail }}
                                </a>
                            </div>
                        </div>

                        <div class="flex items-start gap-4 sm:gap-8 group">
                            <div class="w-12 h-12 sm:w-16 sm:h-16 dark:bg-white/5 dark:bg-black bg-white/5 border dark:border-white/10 border-black/10 rounded-2xl flex items-center justify-center shrink-0 dark:group-hover:bg-primary group-hover:bg-primary transition-all duration-500">
                                <span class="material-symbols-outlined text-on-surface-variant group-hover:text-background text-3xl">pin_drop</span>
                            </div>
                            <div>
                                <h3 class="font-headline text-xl font-bold dark:text-white text-on-surface mb-2">Nairobi Studio</h3>
                                <p class="text-on-surface-variant text-sm mb-4">Visit the studio or collect an online order. Message us on WhatsApp to check opening hours.</p>
                                <p class="text-on-surface font-headline text-sm font-bold tracking-wider opacity-60">
                                    {{ contactAddress }}
                                </p>
                            </div>
                        </div>

                        <!-- Calls, when a separate care line is set in the Studio -->
                        <div v-if="store.phone && store.phone !== store.whatsapp" class="flex items-start gap-4 sm:gap-8 group">
                            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-primary/10 border border-primary/20 rounded-2xl flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-primary text-3xl" aria-hidden="true">support_agent</span>
                            </div>
                            <div>
                                <h3 class="font-headline text-xl font-bold text-on-surface mb-2">Call us</h3>
                                <p class="text-on-surface-variant text-sm mb-4">For order help and delivery questions.</p>
                                <a :href="`tel:+${store.phone}`" class="text-on-surface font-headline text-lg font-bold tracking-widest hover:text-primary transition-colors">{{ store.phone_display }}</a>
                            </div>
                        </div>

                        <SocialLinks item-class="flex h-11 w-11 items-center justify-center rounded-full border border-black/10 text-on-surface-variant transition hover:text-primary dark:border-white/10" />
                    </div>

                    <!-- Studio Status -->
                    <div class="glass-panel p-4 sm:p-8 rounded-sm space-y-4 relative overflow-hidden">
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                            </span>
                            <span class="text-[11px] font-headline font-black tracking-wider text-primary">Studio status</span>
                        </div>
                        <p class="text-sm text-on-surface leading-relaxed font-light">
                            Message the studio to discuss current availability, fitting appointments or a new custom order.
                        </p>
                    </div>
                </div>

                <!-- Contact Form / Lead Capture -->
                <div class="glass-panel rounded-sm p-4 sm:p-8 lg:p-12 shadow-2xl reveal reveal-delay-200">
                    <form @submit.prevent="submitForm" class="space-y-8">
                        <div class="space-y-3">
                            <label class="text-[11px] font-headline tracking-wider text-primary font-black" for="contact-type">Nature of inquiry</label>
                            <select id="contact-type" v-model="form.type" class="w-full bg-background border dark:border-white/5 border-black/5 focus:border-primary px-6 py-4 text-sm dark:text-white text-on-surface rounded-xl transition-all appearance-none outline-none">
                                <option value="bespoke">Custom Order</option>
                                <option v-if="$page.props.drops_enabled" value="batch">Limited Batch Drop Inquiry</option>
                                <option value="support">Order Support / Fitting</option>
                                <option value="other">General Inquiry</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-8">
                            <div class="space-y-3">
                                <label class="text-[11px] font-headline tracking-wider text-on-surface-variant font-black" for="contact-name">Your name</label>
                                <input id="contact-name" v-model="form.name" type="text" placeholder="John Doe" class="w-full bg-background border dark:border-white/5 border-black/5 focus:border-primary px-6 py-4 text-sm dark:text-white text-on-surface rounded-xl outline-none transition-all placeholder:opacity-50" required />
                            </div>
                            <div class="space-y-3">
                                <label class="text-[11px] font-headline tracking-wider text-on-surface-variant font-black" for="contact-email">Email address</label>
                                <input id="contact-email" v-model="form.email" type="email" placeholder="john@domain.com" class="w-full bg-background border dark:border-white/5 border-black/5 focus:border-primary px-6 py-4 text-sm dark:text-white text-on-surface rounded-xl outline-none transition-all placeholder:opacity-50" required />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="text-[11px] font-headline tracking-wider text-on-surface-variant font-black" for="contact-message">Message / specifications</label>
                            <textarea id="contact-message" v-model="form.message" rows="5" minlength="5" placeholder="Tell us about your requirements..." class="w-full bg-background border dark:border-white/5 border-black/5 focus:border-primary px-6 py-4 text-sm dark:text-white text-on-surface rounded-xl outline-none resize-none transition-all placeholder:opacity-50" required></textarea>
                        </div>

                        <button type="submit" :disabled="isSubmitting" class="action-primary w-full py-4">
                            Open WhatsApp with Message
                        </button>

                        <p class="text-[10.5px] text-on-surface-variant text-center font-black">
                            This opens WhatsApp with your message. Review it and press Send there.
                        </p>
                    </form>
                </div>
            </div>
        </main>
    </StorefrontLayout>
</template>

<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import { reactive, ref, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SocialLinks from '@/Components/SocialLinks.vue';
import { useStore } from '@/Composables/useStore';

const page = usePage();
// Every contact here comes from the store profile (Studio → Settings).
const { store } = useStore();
const whatsappUrl = computed(() => store.value.whatsapp_url);

// Admin-managed contact details, with sensible fallbacks.
const contactEmail = computed(() => store.value.email);
const contactAddress = computed(() => store.value.address);

const isSubmitting = ref(false);
const form = reactive({
    type: 'bespoke',
    name: '',
    email: '',
    message: ''
});

const inquiryLabels = { bespoke: 'Custom order', batch: 'Limited batch drop', support: 'Order support / fitting', other: 'General inquiry' };
const submitForm = () => {
    const message = `Hello ${store.value.name},\n\nName: ${form.name}\nEmail: ${form.email}\nAbout: ${inquiryLabels[form.type] || form.type}\n\n${form.message}`;
    window.open(`${store.value.whatsapp_url}?text=${encodeURIComponent(message)}`, '_blank', 'noopener,noreferrer');
};
</script>
