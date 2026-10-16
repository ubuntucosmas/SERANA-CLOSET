<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <StorefrontLayout>
        <Head title="Create Account" />

        <div class="page-container flex justify-center pb-20">
            <div class="w-full max-w-md bg-surface-container/40 border dark:border-white/5 border-black/5 rounded-3xl overflow-hidden backdrop-blur-2xl shadow-2xl">
                <div class="h-1 bg-gradient-to-r from-transparent via-primary to-transparent opacity-50"></div>

                <div class="p-6 sm:p-10">
                    <div class="text-center mb-8">
                        <h1 class="font-headline text-4xl font-black text-on-surface mb-2 tracking-tighter">Create <span class="text-primary">Account</span></h1>
                        <p class="text-sm text-on-surface-variant">Keep your orders in one place. You can always check out as a guest.</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-5">
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-on-surface-variant ml-1" for="register-name">Name</label>
                            <input id="register-name" v-model="form.name" type="text" required autofocus autocomplete="name" class="field py-3.5" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-on-surface-variant ml-1" for="register-email">Email</label>
                            <input id="register-email" v-model="form.email" type="email" required autocomplete="username" placeholder="you@example.com" class="field py-3.5" />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-on-surface-variant ml-1" for="register-password">Password</label>
                            <input id="register-password" v-model="form.password" type="password" required autocomplete="new-password" class="field py-3.5" />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-on-surface-variant ml-1" for="register-password_confirmation">Confirm password</label>
                            <input id="register-password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="field py-3.5" />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>

                        <button type="submit" :disabled="form.processing" class="action-primary w-full py-4">
                            {{ form.processing ? 'Creating your account…' : 'Create Account' }}
                        </button>
                    </form>

                    <div class="mt-8 pt-8 border-t dark:border-white/5 border-black/5 text-center">
                        <p class="text-sm text-on-surface-variant">Already have an account? <Link :href="route('login')" class="text-primary underline">Sign In</Link></p>
                        <Link :href="route('shop')" class="block text-sm mt-4 underline">Continue Shopping as Guest</Link>
                    </div>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
