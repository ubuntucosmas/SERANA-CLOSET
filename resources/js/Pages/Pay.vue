<script setup>
import { computed, onUnmounted, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import PageHeader from '@/Components/PageHeader.vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { useCurrency } from '@/Composables/useCurrency';

// The customer pay link (design B): the quote, what is paid, and an M-Pesa
// prompt for the deposit or the balance. The server decides every amount;
// this page only sends the customer's choice.
const props = defineProps({
    valid: { type: Boolean, default: false },
    order: { type: Object, default: null },
});

const page = usePage();
const { formatAmount } = useCurrency();
const money = (amount) => formatAmount(amount, page.props);

const phone = ref('');
const state = ref('idle'); // idle | sending | waiting | paid | failed
const message = ref('');
let timer = null;

const choices = computed(() => {
    if (!props.order?.can_pay) return [];
    const options = [];
    if (props.order.deposit_due) options.push({ pay: 'deposit', label: `Pay deposit ${money(props.order.deposit_due)}` });
    options.push({ pay: 'balance', label: `Pay ${props.order.amount_paid ? 'the balance' : 'in full'} ${money(props.order.balance_due)}` });
    return options;
});

async function pay(choice) {
    if (state.value === 'sending' || state.value === 'waiting') return;
    state.value = 'sending';
    message.value = '';
    try {
        const { data } = await axios.post(route('mpesa.stk'), { order_id: props.order.id, phone: phone.value, pay: choice });
        state.value = 'waiting';
        message.value = 'Check your phone and enter your M-Pesa PIN.';
        poll(data.CheckoutRequestID);
    } catch (err) {
        state.value = 'failed';
        message.value = err.response?.data?.message || err.response?.data?.errors?.phone?.[0] || 'M-Pesa could not send the prompt. Try again in a minute.';
    }
}

function poll(attempt) {
    let tries = 0;
    clearInterval(timer);
    timer = setInterval(async () => {
        tries++;
        try {
            const { data } = await axios.get(route('mpesa.status', props.order.id), { params: { attempt } });
            if (data.attempt_status === 'paid') {
                clearInterval(timer);
                state.value = 'paid';
                message.value = 'Payment received. Your receipt is on its way by email.';
                router.reload({ only: ['order'] });
            } else if (data.attempt_status === 'failed') {
                clearInterval(timer);
                state.value = 'failed';
                message.value = 'The payment did not go through. You have not been charged. You can try again.';
            }
        } catch { /* keep waiting */ }
        if (tries >= 30 && state.value === 'waiting') {
            clearInterval(timer);
            message.value = 'Still waiting for M-Pesa. If you paid, it will show here shortly; your receipt will be emailed.';
        }
    }, 4000);
}
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <StorefrontLayout focus>
        <Head title="Pay for your order | Serana Closet" />
        <!-- The layout provides <main>; one landmark per page. -->
        <div class="page-container pb-24 max-w-2xl mx-auto">
            <template v-if="!valid">
                <PageHeader kicker="Payment" title="This link has expired" subtitle="Pay links work for a limited time. Ask the studio on WhatsApp for a new one, or track your order with its reference." />
                <Link :href="route('track')" class="btn-primary inline-block mt-4">Track an order</Link>
            </template>

            <template v-else>
                <PageHeader kicker="Payment" :title="order.first_name ? `Hello ${order.first_name}` : 'Your order'" :subtitle="`Order ${order.reference}${order.garment ? ' · ' + order.garment : ''}`" />

                <section class="card p-6 space-y-3" aria-labelledby="pay-summary">
                    <h2 id="pay-summary" class="sr-only">What you owe</h2>
                    <div v-for="(line, i) in order.lines" :key="i" class="flex justify-between gap-4 text-sm">
                        <span class="text-on-surface-variant">{{ line.description }}</span><span class="tabular-nums">{{ money(line.amount) }}</span>
                    </div>
                    <div class="flex justify-between gap-4 border-t border-outline-variant pt-3 font-semibold"><span>Total</span><span class="tabular-nums">{{ money(order.total) }}</span></div>
                    <div class="flex justify-between gap-4 text-sm"><span class="text-on-surface-variant">Paid so far</span><span class="tabular-nums">{{ money(order.amount_paid) }}</span></div>
                    <div v-if="order.handover" class="text-sm"><p>{{ order.handover.label }}<template v-if="order.handover.location"> · {{ order.handover.location }}</template></p><p v-if="order.handover.address">{{ order.handover.address }}</p><p>{{ order.handover.next }}</p></div>
                    <p v-if="order.needs_delivery_quote" role="status" class="text-sm">The Studio is pricing your delivery. You will get the total on WhatsApp before you pay.</p>
                    <div v-if="order.refund_due > 0" class="flex justify-between gap-4 text-lg font-semibold"><span>To be refunded to you</span><span class="tabular-nums">{{ money(order.refund_due) }}</span></div>
                    <div v-else class="flex justify-between gap-4 text-lg font-semibold"><span>Balance due</span><span class="tabular-nums">{{ money(order.balance_due) }}</span></div>
                    <p v-if="order.deposit_policy" class="text-xs text-on-surface-variant pt-2">{{ order.deposit_policy }}</p>
                </section>

                <section v-if="choices.length" class="card p-6 mt-6 space-y-4" aria-labelledby="pay-now">
                    <h2 id="pay-now" class="text-lg font-semibold">Pay by M-Pesa</h2>
                    <label class="block">
                        <span class="block text-sm text-on-surface-variant mb-2">Your M-Pesa number</span>
                        <input v-model="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="07XX XXX XXX" class="field py-3.5 text-base" />
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <button v-for="c in choices" :key="c.pay" type="button" @click="pay(c.pay)" :disabled="!phone || state === 'sending' || state === 'waiting'" class="btn-primary disabled:opacity-40">{{ c.label }}</button>
                    </div>
                </section>
                <p v-else-if="order.refund_due > 0" class="card p-6 mt-6 text-center">{{ money(order.refund_due) }} will be refunded to you<template v-if="order.refund_days"> within {{ order.refund_days }} working {{ order.refund_days === 1 ? 'day' : 'days' }}</template>. Nothing more to pay.</p>
                <p v-else-if="order.balance_due === 0" class="card p-6 mt-6 text-center">Paid in full. Thank you.</p>
                <p v-else class="card p-6 mt-6 text-sm text-on-surface-variant">M-Pesa payment isn’t available on this link right now. Message the studio on WhatsApp to pay.</p>

                <p v-if="message" role="status" aria-live="polite" class="mt-4 text-sm" :class="state === 'failed' ? 'text-error' : 'text-on-surface'">{{ message }}</p>
                <Link :href="order.track_url" class="inline-block mt-6 text-sm underline text-on-surface-variant">Track this order</Link>
            </template>
        </div>
    </StorefrontLayout>
</template>
