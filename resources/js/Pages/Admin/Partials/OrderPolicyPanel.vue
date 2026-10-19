<script setup>
import { useForm } from '@inertiajs/vue3';

// Order policy (owner only; the server enforces the same): the bespoke deposit
// share, how long a customer pay link works, and the deposit rule customers
// see on the quote, the pay page and every receipt.
const props = defineProps({
    policy: { type: Object, required: true },
});

const form = useForm({
    deposit_percent: props.policy.deposit_percent,
    pay_link_days: props.policy.pay_link_days,
    deposit_policy: props.policy.deposit_policy || '',
    refund_working_days: props.policy.refund_working_days ?? 2,
    resolution_days: props.policy.resolution_days ?? 3,
});

function save() {
    form.put(route('admin.order_policy.update'), { preserveScroll: true });
}

const field = 'w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none';
</script>

<template>
    <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-6 mb-6" aria-labelledby="order-policy-heading">
        <h2 id="order-policy-heading" class="text-white font-headline text-xl mb-1">Orders &amp; deposits</h2>
        <p class="text-slate-500 text-sm mb-4">Custom orders start once the deposit is paid. Shop and pre-order items are paid in full.</p>
        <form @submit.prevent="save" class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="block text-sm text-slate-300 mb-2">Deposit (% of the quote)</span>
                <input v-model.number="form.deposit_percent" type="number" min="0" max="100" step="1" :class="field" />
            </label>
            <label class="block">
                <span class="block text-sm text-slate-300 mb-2">Pay link works for (days)</span>
                <input v-model.number="form.pay_link_days" type="number" min="1" max="90" step="1" :class="field" />
            </label>
            <label class="block">
                <span class="block text-sm text-slate-300 mb-2">Refunds sent within (working days)</span>
                <input v-model.number="form.refund_working_days" type="number" min="1" max="14" step="1" :class="field" />
                <span class="block text-xs text-slate-500 mt-1">The promise customers see when they are owed money back.</span>
            </label>
            <label class="block">
                <span class="block text-sm text-slate-300 mb-2">Unresolved payment problems become refunds after (days)</span>
                <input v-model.number="form.resolution_days" type="number" min="1" max="30" step="1" :class="field" />
                <span class="block text-xs text-slate-500 mt-1">For example, a late payment for an item that has since sold out.</span>
            </label>
            <label class="block sm:col-span-2">
                <span class="block text-sm text-slate-300 mb-2">Deposit rule customers see</span>
                <input v-model="form.deposit_policy" maxlength="300" :class="field" />
            </label>
            <div class="sm:col-span-2 flex items-center gap-3">
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-[#57d5fc] px-5 py-2.5 text-sm font-semibold text-slate-950 disabled:opacity-40">{{ form.processing ? 'Saving…' : 'Save order policy' }}</button>
                <p v-for="(message, key) in form.errors" :key="key" role="alert" class="text-sm text-rose-300">{{ message }}</p>
            </div>
        </form>
    </section>
</template>
