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
        <Head title="Sign In" />

        <div class="page-container flex justify-center pb-20">
            <div class="w-full max-w-md bg-surface-container/40 border dark:border-white/5 border-black/5 rounded-3xl overflow-hidden backdrop-blur-2xl shadow-2xl">
                <!-- Luminous Top Accent -->
                <div class="h-1 bg-gradient-to-r from-transparent via-primary to-transparent opacity-50"></div>

                <div class="p-6 sm:p-10">
                    <div class="text-center mb-10">
                        <h1 class="font-headline text-4xl font-black dark:text-white text-on-surface mb-2 tracking-tighter">Sign <span class="text-primary">In</span></h1>
                        <p class="text-[10px] text-on-surface-variant tracking-[0.3em] font-bold">Sign in to view your orders and account details</p>
                    </div>

                    <div v-if="status" class="mb-6 p-4 bg-primary/10 border border-primary/20 rounded-xl text-primary text-xs font-bold tracking-widest text-center">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Email -->
                        <div class="space-y-2">
                            <label class="text-[9px] tracking-widest text-on-surface-variant font-bold ml-1" for="login-email">Email Address</label>
                            <input 
                                id="login-email" v-model="form.email"
                                type="email" 
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="you@example.com"
                                class="field py-3.5"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <!-- Password -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center ml-1">
                                <label class="text-[9px] tracking-widest text-on-surface-variant font-bold" for="login-password">Account password</label>
                                <Link v-if="canResetPassword" :href="route('password.request')" class="text-xs font-semibold text-primary hover:underline transition-colors font-bold">Forgot?</Link>
                            </div>
                            <input 
                                id="login-password" v-model="form.password"
                                type="password" 
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="field py-3.5"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <!-- Remember Me -->
                        <label class="flex min-h-[44px] items-center gap-3 cursor-pointer group px-1">
                            <input 
                                v-model="form.remember"
                                type="checkbox" 
                                class="w-5 h-5 rounded-md bg-surface-container dark:border-white/10 border-black/10 text-primary focus:ring-offset-0 focus:ring-primary/20 transition-all"
                            />
                            <span class="text-[10px] uppercase tracking-widest text-on-surface-variant group-hover:text-on-surface transition-colors font-bold">Remember Me</span>
                        </label>

                        <!-- Submit -->
                        <button 
                            type="submit"
                            :disabled="form.processing"
                            class="action-primary w-full py-4"
                        >
                            <span v-if="!form.processing">Sign In</span>
                            <span v-else class="flex items-center justify-center gap-2">
                                <span class="w-3 h-3 border-2 border-background/20 border-t-background rounded-full animate-spin"></span>
                                Signing In…
                            </span>
                        </button>
                    </form>

                    <div class="mt-8 pt-8 border-t dark:border-white/5 border-black/5 text-center">
                        <p class="text-sm text-on-surface-variant">New to Serana? <Link :href="route('register')" class="text-primary underline">Create Account</Link></p>
                        <Link :href="route('shop')" class="block text-sm mt-4 underline">Continue Shopping as Guest</Link>
                    </div>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>

<style scoped>
input::placeholder {
    font-family: 'Satoshi', sans-serif;
}
</style>
