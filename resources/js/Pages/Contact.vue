<template>
    <StorefrontLayout>
        <Head>
            <title>The Studio | Contact Serana Closet</title>
            <meta name="description" content="Connect with the Serana Studio in Nairobi. Priority WhatsApp access, custom design inquiries, and support.">
        </Head>

        <main class="pt-32 pb-24 px-6 max-w-screen-xl mx-auto font-body">
            <!-- Header Section -->
            <header class="mb-20 text-center reveal">
                <h1 class="font-headline text-5xl md:text-8xl dark:text-white text-on-surface mb-8 tracking-tighter font-black">The Studio <span class="text-primary luminous-glow">Connect</span></h1>
                <p class="text-on-surface text-xl md:text-2xl max-w-2xl mx-auto font-medium leading-relaxed">
                    Direct access to the Serana Studio. Priority response for custom orders and elite acquisitions.
                </p>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-start">
                <!-- Contact Channels -->
                <div class="space-y-12 reveal">
                    <div class="space-y-8">
                        <div class="flex items-start gap-8 group">
                            <div class="w-16 h-16 bg-primary/10 border border-primary/20 rounded-2xl flex items-center justify-center shrink-0 group-hover:bg-primary transition-all duration-500">
                                <span class="material-symbols-outlined text-primary group-hover:text-background text-3xl">chat</span>
                            </div>
                            <div>
                                <h3 class="font-headline text-xl font-bold dark:text-white text-on-surface mb-2">Priority WhatsApp</h3>
                                <p class="text-on-surface-variant text-sm mb-4">Direct line for instant fitting updates and new design inquiries.</p>
                                <a href="https://wa.me/254700000000" target="_blank" class="text-primary font-headline text-lg font-black tracking-widest hover:dark:text-white text-on-surface transition-colors">
                                    +254 700 000 000
                                </a>
                            </div>
                        </div>

                        <div class="flex items-start gap-8 group">
                            <div class="w-16 h-16 dark:bg-white/5 dark:bg-black bg-white/5 border dark:border-white/10 border-black/10 rounded-2xl flex items-center justify-center shrink-0 dark:group-hover:bg-primary group-hover:bg-primary transition-all duration-500">
                                <span class="material-symbols-outlined text-on-surface-variant group-hover:text-background text-3xl">mail</span>
                            </div>
                            <div>
                                <h3 class="font-headline text-xl font-bold dark:text-white text-on-surface mb-2">Electronic Dispatch</h3>
                                <p class="text-on-surface-variant text-sm mb-4">For press, collaborations, and formal archival inquiries.</p>
                                <a href="mailto:studio@seranacloset.com" class="text-on-surface font-headline text-lg font-bold tracking-widest hover:text-primary transition-colors">
                                    studio@seranacloset.com
                                </a>
                            </div>
                        </div>

                        <div class="flex items-start gap-8 group">
                            <div class="w-16 h-16 dark:bg-white/5 dark:bg-black bg-white/5 border dark:border-white/10 border-black/10 rounded-2xl flex items-center justify-center shrink-0 dark:group-hover:bg-primary group-hover:bg-primary transition-all duration-500">
                                <span class="material-symbols-outlined text-on-surface-variant group-hover:text-background text-3xl">pin_drop</span>
                            </div>
                            <div>
                                <h3 class="font-headline text-xl font-bold dark:text-white text-on-surface mb-2">Nairobi Studio</h3>
                                <p class="text-on-surface-variant text-sm mb-4">By Appointment Only. Experience the Serana Standard in person.</p>
                                <p class="text-on-surface font-headline text-sm font-bold tracking-widest opacity-60">
                                    Karen, Nairobi • Kenya
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Studio Status -->
                    <div class="p-8 bg-surface-container/50 border dark:border-white/5 border-black/5 rounded-2xl space-y-4 relative overflow-hidden">
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                            </span>
                            <span class="text-[11px] font-headline font-black tracking-[0.2em] text-primary">Live studio pulse</span>
                        </div>
                        <p class="text-sm dark:text-white text-on-surface leading-relaxed font-black">
                            Our artisans are currently focused on **Batch #04**. Capacity at **85%**. Response times: approx **4 hours**.
                        </p>
                    </div>
                </div>

                <!-- Contact Form / Lead Capture -->
                <div class="bg-surface-container border dark:border-white/10 border-black/10 rounded-3xl p-8 lg:p-12 shadow-2xl reveal reveal-delay-200">
                    <form @submit.prevent="submitForm" class="space-y-8">
                        <div class="space-y-3">
                            <label class="text-[11px] font-headline tracking-[0.2em] text-primary font-black">Nature of inquiry</label>
                            <select v-model="form.type" class="w-full bg-background border dark:border-white/5 border-black/5 focus:border-primary px-6 py-4 text-sm dark:text-white text-on-surface rounded-xl transition-all appearance-none outline-none">
                                <option value="bespoke">Custom Order</option>
                                <option value="batch">Limited Batch Drop Inquiry</option>
                                <option value="support">Artisan Support / Fitting</option>
                                <option value="other">General Archival Request</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-3">
                                <label class="text-[11px] font-headline tracking-[0.2em] text-on-surface-variant font-black">Your name</label>
                                <input v-model="form.name" type="text" placeholder="John Doe" class="w-full bg-background border dark:border-white/5 border-black/5 focus:border-primary px-6 py-4 text-sm dark:text-white text-on-surface rounded-xl outline-none transition-all placeholder:opacity-50" required />
                            </div>
                            <div class="space-y-3">
                                <label class="text-[11px] font-headline tracking-[0.2em] text-on-surface-variant font-black">Email address</label>
                                <input v-model="form.email" type="email" placeholder="john@domain.com" class="w-full bg-background border dark:border-white/5 border-black/5 focus:border-primary px-6 py-4 text-sm dark:text-white text-on-surface rounded-xl outline-none transition-all placeholder:opacity-50" required />
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="text-[11px] font-headline tracking-[0.2em] text-on-surface-variant font-black">Message / specifications</label>
                            <textarea v-model="form.message" rows="5" placeholder="Tell us about your requirements..." class="w-full bg-background border dark:border-white/5 border-black/5 focus:border-primary px-6 py-4 text-sm dark:text-white text-on-surface rounded-xl outline-none resize-none transition-all placeholder:opacity-50" required></textarea>
                        </div>

                        <button type="submit" :disabled="isSubmitting" class="w-full py-6 dark:bg-white dark:text-background bg-on-surface text-surface font-headline text-[12.5px] tracking-[0.3em] font-black rounded-xl hover:bg-primary hover:text-background transition-all shadow-2xl disabled:opacity-50">
                            {{ isSubmitting ? 'Transmitting...' : 'Dispatch message' }}
                        </button>

                        <p class="text-[10.5px] text-on-surface-variant text-center font-black">
                            By dispatching, you agree to enter the Serana Artisan pipeline. We value discrete communication.
                        </p>
                    </form>
                </div>
            </div>
        </main>
    </StorefrontLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

const isSubmitting = ref(false);
const form = reactive({
    type: 'bespoke',
    name: '',
    email: '',
    message: ''
});

const submitForm = () => {
    isSubmitting.value = true;
    
    // Simulate API call for now or integrate with a lead controller if needed
    setTimeout(() => {
        isSubmitting.value = false;
        window.dispatchEvent(new CustomEvent('serana-toast', { 
            detail: { message: "Message dispatched to the Studio. We will respond shortly.", type: 'success' } 
        }));
        form.name = '';
        form.email = '';
        form.message = '';
    }, 1500);
};
</script>
