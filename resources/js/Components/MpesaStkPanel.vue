<script setup>
import { ref } from 'vue';
const props = defineProps({
    amount: { type: [Number, String], required: true },
    reference: { type: String, default: null },
    phone: { type: String, default: '' },
    status: { type: String, default: 'idle' },
});
const emit = defineEmits(['pay', 'cancel']);
const phoneNumber = ref(props.phone.replace(/^\+?254/, '').trim());
function pay() {
    if (props.status === 'idle' && phoneNumber.value.trim()) emit('pay', { phone: phoneNumber.value.trim() });
}
</script>

<template>
    <section class="bg-surface text-on-surface border border-outline-variant rounded-3xl p-7 sm:p-9" aria-live="polite" :aria-busy="status === 'processing'">
        <p class="text-sm text-primary font-semibold mb-3">M-Pesa Payment</p>
        <h2 class="text-2xl font-semibold mb-3">{{ status === 'processing' ? 'Sending M-Pesa request…' : status === 'waiting' ? 'Waiting for payment confirmation' : 'Review and pay' }}</h2>
        <p v-if="reference" class="text-sm font-semibold mb-4 break-all">Order Reference: {{ reference }}</p>
        <p class="text-3xl font-semibold mb-6">KSh {{ Number(amount).toLocaleString() }}</p>
        <form v-if="status === 'idle'" @submit.prevent="pay" class="space-y-5">
            <p class="text-on-surface-variant text-sm">We’ll send a payment prompt to this number. Enter your PIN on your phone to approve it.</p>
            <div>
                <label for="stk-phone" class="block text-sm mb-2">M-Pesa phone number (required)</label>
                <input id="stk-phone" v-model="phoneNumber" type="tel" autocomplete="tel" required placeholder="0712 345 678" class="w-full rounded-xl bg-background border border-outline-variant p-3" />
            </div>
            <button :disabled="!phoneNumber.trim()" class="w-full rounded-full bg-primary text-background py-4 font-semibold disabled:opacity-50">Pay with M-Pesa</button>
        </form>
        <div v-else class="space-y-5">
            <span class="material-symbols-outlined text-primary text-4xl animate-pulse" aria-hidden="true">{{ status === 'processing' ? 'sync' : 'smartphone' }}</span>
            <p class="text-on-surface-variant">{{ status === 'processing' ? 'Please wait while we send your request. Keep this page open.' : 'Check your phone for the Safaricom prompt and enter your M-Pesa PIN. If you already approved it, wait here while we check with Safaricom.' }}</p>
            <p v-if="status === 'waiting'" class="text-sm">Payment is not confirmed yet. Please don’t send another payment while confirmation is pending.</p>
        </div>
        <p class="text-xs text-on-surface-variant mt-6">Your PIN stays on your phone. We never ask you to enter it here.</p>
        <button v-if="status === 'idle'" @click="emit('cancel')" class="block mx-auto mt-5 text-sm underline">Back to Checkout</button>
    </section>
</template>
