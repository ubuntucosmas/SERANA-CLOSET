<!-- M-Pesa payment review (OD-1): money only the statement explains. Everyone sees it; the owner settles it. -->
<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({ items: { type: Array, default: () => [] } });
const emit = defineEmits(['openOrder']);
const page = usePage();
const { formatAmount } = useCurrency();
const money = value => formatAmount(value || 0, page.props);
const canSettle = computed(() => (page.props.auth?.user?.abilities || []).includes('payments.review'));

const openId = ref(null);
const form = reactive({ action: '', reference: '', receipt: '', refund_reference: '', reason: '', phone_checked: false });
const errors = ref({});
const busy = ref(false);
const keyOf = item => `${item.kind}:${item.id}`;

function start(item, action, reference = '') {
    openId.value = keyOf(item);
    Object.assign(form, { action, reference, receipt: '', refund_reference: '', reason: '', phone_checked: false });
    errors.value = {};
}
const ROUTES = {
    match: 'studio.payment_review.match', refund_required: 'studio.payment_review.refund_required', refunded: 'studio.payment_review.refunded',
    note: 'studio.payment_review.note', prompt_confirm: 'studio.payment_review.prompt_confirm', prompt_close: 'studio.payment_review.prompt_close',
};
function submit(item) {
    if (busy.value) return;
    busy.value = true;
    router.post(route(ROUTES[form.action], item.id), { ...form }, {
        preserveScroll: true,
        onSuccess: () => { openId.value = null; },
        onError: e => { errors.value = e; },
        onFinish: () => { busy.value = false; },
    });
}

// Money found on the statement that the system has no record of.
const adding = ref(false);
const statement = reactive({ amount: '', receipt: '', phone: '', paid_at: '', reason: '' });
function addStatement() {
    if (busy.value) return;
    busy.value = true;
    router.post(route('studio.payment_review.store'), { ...statement }, {
        preserveScroll: true,
        onSuccess: () => { adding.value = false; Object.assign(statement, { amount: '', receipt: '', phone: '', paid_at: '', reason: '' }); errors.value = {}; },
        onError: e => { errors.value = e; },
        onFinish: () => { busy.value = false; },
    });
}
const title = item => item.kind === 'prompt' ? 'Prompt never confirmed' : item.status === 'refund_required' ? 'Refund required' : 'M-Pesa payment needs review';
const field = 'w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:ring-1 focus:ring-[#57d5fc] outline-none';
</script>

<template>
    <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-6 mb-5" aria-labelledby="payment-review-title">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
            <h2 id="payment-review-title" class="text-white font-headline text-lg sm:text-xl">M-Pesa payments to review</h2>
            <span class="rounded-full px-3 py-1 text-sm font-semibold tabular-nums" :class="items.length ? 'bg-amber-500/15 text-amber-400' : 'bg-white/5 text-slate-400'">{{ items.length }}</span>
        </div>
        <p class="text-slate-500 text-sm mb-4">Money M-Pesa took that no order shows yet, and prompts nobody could confirm. Compare each with the M-Pesa statement, then match it to its order or mark it for refund. Nothing here is lost or removed until you do.</p>
        <p v-if="!canSettle && items.length" class="text-xs text-slate-400 mb-3">Only the owner settles these. Tell the owner if a customer asks about one.</p>
        <p v-if="!items.length" class="text-sm text-slate-400">No M-Pesa payments need review.</p>

        <ul v-else class="space-y-3">
            <li v-for="item in items" :key="keyOf(item)" class="bg-slate-950/50 rounded-xl px-4 py-3 text-sm">
                <div class="flex flex-col sm:flex-row sm:justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-amber-400 font-semibold">{{ title(item) }}</p>
                        <p class="text-white">{{ money(item.amount) }}<template v-if="item.receipt"> · code <span class="font-mono">{{ item.receipt }}</span></template>
                            <template v-if="item.phone_ending"> · phone ending {{ item.phone_ending }}</template> · {{ item.paid_at }}</p>
                        <p v-if="item.kind === 'prompt'" class="text-slate-400">Sent for <button type="button" class="underline" @click="emit('openOrder', item.order.id)">{{ item.order.reference }}</button> ({{ item.order.customer }}, now {{ item.order.state }}). Safaricom never told us the result and the automatic check stopped after a day.</p>
                        <p v-else-if="item.source === 'callback'" class="text-slate-400">Safaricom confirmed this payment, but it matches no order we recorded.</p>
                        <p v-else class="text-slate-400">Found on the M-Pesa statement.</p>
                        <p v-if="item.likely_orders?.length" class="text-slate-400 mt-1">Likely order:
                            <template v-for="(o, i) in item.likely_orders" :key="o.id"><span v-if="i">, </span><button type="button" class="underline" @click="emit('openOrder', o.id)">{{ o.reference }}</button> ({{ o.customer }}, {{ money(o.balance_due) }} due{{ o.phone_matches ? ', same phone' : '' }})</template></p>
                        <p v-else-if="item.kind === 'unmatched'" class="text-slate-500 mt-1">No unpaid order with this amount around that time.</p>
                        <pre v-if="item.notes" class="mt-2 whitespace-pre-wrap text-xs text-slate-500 font-sans">{{ item.notes }}</pre>
                    </div>
                    <div v-if="canSettle && openId !== keyOf(item)" class="flex flex-wrap gap-2 shrink-0 self-start">
                        <template v-if="item.kind === 'prompt'">
                            <button type="button" class="table-action" @click="start(item, 'prompt_confirm')">Found on statement</button>
                            <button type="button" class="table-action" @click="start(item, 'prompt_close')">Not on statement</button>
                        </template>
                        <template v-else-if="item.status === 'needs_review'">
                            <button type="button" class="table-action" @click="start(item, 'match', item.likely_orders?.[0]?.reference || '')">Match to order</button>
                            <button type="button" class="table-action" @click="start(item, 'refund_required')">Refund required</button>
                            <button type="button" class="table-action" @click="start(item, 'note')">No match yet</button>
                        </template>
                        <button v-else type="button" class="table-action" @click="start(item, 'refunded')">Record refund sent</button>
                    </div>
                </div>

                <form v-if="openId === keyOf(item)" class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit(item)">
                    <label v-if="form.action === 'match'" class="block"><span class="block text-xs text-slate-300 mb-1">Order reference</span>
                        <input v-model="form.reference" required placeholder="SRN-…" :class="field" /><span v-if="errors.reference" class="text-xs text-rose-400">{{ errors.reference }}</span></label>
                    <label v-if="form.action === 'prompt_confirm'" class="block"><span class="block text-xs text-slate-300 mb-1">M-Pesa code on the statement</span>
                        <input v-model="form.receipt" required :class="field" /><span v-if="errors.receipt" class="text-xs text-rose-400">{{ errors.receipt }}</span></label>
                    <label v-if="form.action === 'refunded'" class="block"><span class="block text-xs text-slate-300 mb-1">M-Pesa code of the refund you sent</span>
                        <input v-model="form.refund_reference" required :class="field" /><span v-if="errors.refund_reference" class="text-xs text-rose-400">{{ errors.refund_reference }}</span></label>
                    <label class="block sm:col-span-2"><span class="block text-xs text-slate-300 mb-1">{{ form.action === 'note' ? 'What you checked' : 'Reason: what you checked on the statement and with the customer' }}</span>
                        <input v-model="form.reason" required maxlength="255" :class="field" /><span v-if="errors.reason" class="text-xs text-rose-400">{{ errors.reason }}</span></label>
                    <label v-if="form.action === 'match'" class="flex items-start gap-2 sm:col-span-2 text-xs text-slate-300">
                        <input v-model="form.phone_checked" type="checkbox" class="mt-0.5" />
                        <span>If the phone that paid isn’t the one on the order: I confirmed with the customer that this payment is theirs.</span></label>
                    <p v-if="errors.phone_checked || errors.order" class="sm:col-span-2 text-xs text-rose-400">{{ errors.phone_checked || errors.order }}</p>
                    <p class="sm:col-span-2 text-xs text-slate-500">{{ form.action === 'match' ? 'The money is booked on that order exactly like an M-Pesa payment: if it pays more than is owed, the rest goes to Refunds.'
                        : form.action === 'prompt_confirm' ? 'Books the prompt’s amount on its order, as if Safaricom had confirmed it.'
                        : form.action === 'prompt_close' ? 'Closes the prompt as unpaid. The order can be paid again, or it expires.'
                        : form.action === 'refund_required' ? 'It stays in this list until you record the refund you sent.' : 'Saved with your name. The payment stays open.' }}</p>
                    <div class="sm:col-span-2 flex gap-2">
                        <button type="submit" :disabled="busy" class="table-action">{{ busy ? 'Saving…' : 'Confirm' }}</button>
                        <button type="button" class="table-action" @click="openId = null">Cancel</button>
                    </div>
                </form>
            </li>
        </ul>

        <div v-if="canSettle" class="mt-4">
            <button v-if="!adding" type="button" class="table-action" @click="adding = true">Add a payment from the M-Pesa statement</button>
            <form v-else class="grid gap-3 sm:grid-cols-2" @submit.prevent="addStatement">
                <p class="sm:col-span-2 text-xs text-slate-400">For money on the statement that no order or prompt shows. It joins this list for matching or refund.</p>
                <label class="block"><span class="block text-xs text-slate-300 mb-1">Amount (KSh)</span><input v-model="statement.amount" type="number" min="1" required :class="field" /><span v-if="errors.amount" class="text-xs text-rose-400">{{ errors.amount }}</span></label>
                <label class="block"><span class="block text-xs text-slate-300 mb-1">M-Pesa code</span><input v-model="statement.receipt" required :class="field" /><span v-if="errors.receipt" class="text-xs text-rose-400">{{ errors.receipt }}</span></label>
                <label class="block"><span class="block text-xs text-slate-300 mb-1">Phone that paid (optional)</span><input v-model="statement.phone" inputmode="tel" :class="field" /></label>
                <label class="block"><span class="block text-xs text-slate-300 mb-1">Date and time on the statement</span><input v-model="statement.paid_at" type="datetime-local" required :class="field" /><span v-if="errors.paid_at" class="text-xs text-rose-400">{{ errors.paid_at }}</span></label>
                <label class="block sm:col-span-2"><span class="block text-xs text-slate-300 mb-1">Reason</span><input v-model="statement.reason" required maxlength="255" :class="field" /><span v-if="errors.reason" class="text-xs text-rose-400">{{ errors.reason }}</span></label>
                <div class="sm:col-span-2 flex gap-2"><button type="submit" :disabled="busy" class="table-action">Add for review</button><button type="button" class="table-action" @click="adding = false">Cancel</button></div>
            </form>
        </div>
    </section>
</template>
