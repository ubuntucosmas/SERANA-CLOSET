<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <StorefrontLayout>
        <Head title="Identity Access" />

        <div class="min-h-screen pt-32 pb-20 flex items-center justify-center px-6">
            <div class="w-full max-w-md bg-surface-container/40 border dark:border-white/5 border-black/5 rounded-3xl overflow-hidden backdrop-blur-2xl shadow-2xl">
                <!-- Luminous Top Accent -->
                <div class="h-1 bg-gradient-to-r from-transparent via-primary to-transparent opacity-50"></div>

                <div class="p-10">
                    <div class="text-center mb-10">
                        <h2 class="font-headline text-4xl font-black dark:text-white text-on-surface mb-2 tracking-tighter">Identity <span class="text-primary luminous-glow">Access</span></h2>
                        <p class="text-[10px] text-on-surface-variant tracking-[0.3em] font-bold">Authorized staff only</p>
                    </div>

                    <div v-if="status" class="mb-6 p-4 bg-primary/10 border border-primary/20 rounded-xl text-primary text-xs font-bold tracking-widest text-center">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Email -->
                        <div class="space-y-2">
                            <label class="text-[9px] tracking-widest text-on-surface-variant font-bold ml-1">Archive identifier</label>
                            <input 
                                v-model="form.email"
                                type="email" 
                                required
                                autofocus
                                placeholder="Email identifier"
                                class="w-full bg-surface-container/50 border dark:border-white/10 border-black/10 rounded-xl px-5 py-4 text-sm font-mono dark:text-white text-on-surface focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20 transition-all placeholder:opacity-20"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <!-- Password -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center ml-1">
                                <label class="text-[9px] tracking-widest text-on-surface-variant font-bold">Account password</label>
                                <Link v-if="canResetPassword" :href="route('password.request')" class="text-[8px] tracking-widest text-primary/60 hover:text-primary transition-colors font-bold">Forgot?</Link>
                            </div>
                            <input 
                                v-model="form.password"
                                type="password" 
                                required
                                placeholder="••••••••"
                                class="w-full bg-surface-container/50 border dark:border-white/10 border-black/10 rounded-xl px-5 py-4 text-sm font-mono dark:text-white text-on-surface focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20 transition-all placeholder:opacity-20"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <!-- Remember Me -->
                        <label class="flex items-center gap-3 cursor-pointer group px-1">
                            <input 
                                v-model="form.remember"
                                type="checkbox" 
                                class="w-4 h-4 rounded-md bg-surface-container dark:border-white/10 border-black/10 text-primary focus:ring-offset-0 focus:ring-primary/20 transition-all"
                            />
                            <span class="text-[10px] uppercase tracking-widest text-on-surface-variant group-hover:text-on-surface transition-colors font-bold">Retain Session</span>
                        </label>

                        <!-- Submit -->
                        <button 
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-5 bg-primary text-background rounded-2xl font-headline font-black tracking-[0.2em] text-xs hover:scale-[1.02] active:scale-[0.98] transition-all shadow-[0_0_30_rgba(57,255,20,0.2)] disabled:opacity-50 disabled:hover:scale-100"
                        >
                            <span v-if="!form.processing">Authorize access</span>
                            <span v-else class="flex items-center justify-center gap-2">
                                <span class="w-3 h-3 border-2 border-background/20 border-t-background rounded-full animate-spin"></span>
                                Validating...
                            </span>
                        </button>
                    </form>

                    <div class="mt-8 pt-8 border-t dark:border-white/5 border-black/5 text-center">
                        <p class="text-[9px] text-on-surface-variant tracking-widest leading-relaxed">
                            By accessing this portal, you agree to the <br/>
                            <span class="text-on-surface font-bold">Serana Digital Integrity protocols.</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>

<style scoped>
input::placeholder {
    font-family: 'Poppins', sans-serif;
}
</style>
