<script setup>
import { ref, onMounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { animate } from 'animejs';
import InputError from '@/Components/InputError.vue';

const emit = defineEmits(['close']);

const modalRef = ref(null);
const contentRef = ref(null);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
        onSuccess: () => emit('close'),
    });
};

onMounted(() => {
    // Cinematic Entry Animation
    animate(modalRef.value, {
        opacity: [0, 1],
        duration: 400,
        easing: 'easeOutQuad'
    });

    animate(contentRef.value, {
        translateY: [20, 0],
        scale: [0.95, 1],
        opacity: [0, 1],
        duration: 800,
        delay: 100,
        easing: 'spring(1, 80, 10, 0)'
    });
});

const handleClose = () => {
    animate(modalRef.value, {
        opacity: 0,
        duration: 300,
        easing: 'easeInQuad',
        onComplete: () => emit('close')
    });
};
</script>

<template>
    <div ref="modalRef" class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-background/80 backdrop-blur-md opacity-0">
        <!-- Close overlay -->
        <div class="absolute inset-0" @click="handleClose"></div>

        <div ref="contentRef" class="relative w-full max-w-md bg-surface-container/40 border dark:border-white/5 border-black/5 rounded-3xl overflow-hidden backdrop-blur-2xl shadow-2xl opacity-0">
            <!-- Luminous Top Accent -->
            <div class="h-1 bg-gradient-to-r from-transparent via-primary to-transparent opacity-50"></div>

            <div class="p-10">
                <div class="text-center mb-10">
                    <h2 class="font-headline text-4xl font-black dark:text-white text-on-surface mb-2 tracking-tighter">Account <span class="text-primary luminous-glow">Login</span></h2>
                    <p class="text-[10px] text-on-surface-variant tracking-[0.3em] font-black">Authorized staff only</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Email -->
                    <div class="space-y-2">
                        <label class="text-[9px] tracking-widest text-on-surface-variant font-bold ml-1">Login email</label>
                        <input 
                            v-model="form.email"
                            type="email" 
                            required
                            placeholder="Email address"
                            class="w-full bg-surface-container/50 border dark:border-white/10 border-black/10 rounded-xl px-5 py-4 text-sm font-mono dark:text-white text-on-surface focus:outline-none focus:border-primary/50 focus:ring-1 focus:ring-primary/20 transition-all placeholder:opacity-20"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Password -->
                    <div class="space-y-2">
                        <div class="flex justify-between items-center ml-1">
                            <label class="text-[9px] tracking-widest text-on-surface-variant font-bold">Account password</label>
                            <Link :href="route('password.request')" class="text-[8px] tracking-widest text-primary/60 hover:text-primary transition-colors font-bold">Forgot?</Link>
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
                        <span class="text-[10px] tracking-widest text-on-surface-variant group-hover:text-on-surface transition-colors font-bold">Remember me</span>
                    </label>

                    <!-- Submit -->
                    <button 
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-5 bg-primary text-background rounded-2xl font-headline font-black tracking-[0.2em] text-xs hover:scale-[1.02] active:scale-[0.98] transition-all shadow-[0_0_30px_rgba(57,255,20,0.2)] disabled:opacity-50 disabled:hover:scale-100"
                    >
                        <span v-if="!form.processing">Log in now</span>
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
</template>

<style scoped>
input::placeholder {
    font-family: 'Manrope', sans-serif;
}
</style>
