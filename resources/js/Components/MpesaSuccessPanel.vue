<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const page = usePage();
const props = defineProps({
    amount: { type: [Number, String], required: true },
    orderId: { type: [Number, String], required: true },
    items: { type: Array, default: () => [] },
    transactionId: { type: String, required: true },
    receiptUrl: { type: String, default: null },
    reference: { type: String, default: null },
    email: { type: String, default: null },
    date: { type: String, default: () => new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }
});

const { formatAmount } = useCurrency();

// A short, professional note to the studio confirming payment — reference
// and M-Pesa code up front, items listed plainly underneath.
function shareToWhatsApp() {
    const orderRef = props.reference || `ORD-${String(props.orderId).substring(0, 8).toUpperCase()}`;
    const productList = props.items
        .map((item, index) => `${index + 1}. ${item.name} ×${item.quantity}`)
        .join('\n');

    const lines = [
        'Hello Serana,',
        '',
        `I've completed payment for order *${orderRef}*.`,
        '',
        `Amount: ${formatAmount(props.amount, page.props)}`,
        props.transactionId ? `M-Pesa code: ${props.transactionId}` : null,
        `Date: ${props.date}`,
        productList ? `\nItems:\n${productList}` : null,
        '',
        'Kindly confirm my order. Thank you.',
    ].filter(line => line !== null);

    const message = lines.join('\n');

    const whatsappNumber = page.props.whatsapp_number || '';
    const base = whatsappNumber ? `https://wa.me/${whatsappNumber}` : 'https://wa.me/';
    window.open(`${base}?text=${encodeURIComponent(message)}`, '_blank');
}

function downloadReceipt() {
    // Signed, time-limited URL minted server-side after payment confirmation.
    if (props.receiptUrl) {
        window.location.href = props.receiptUrl;
    }
}
</script>

<template>
    <section class="bg-surface text-on-surface rounded-3xl p-7 sm:p-9 border border-outline-variant" role="status">
        <span class="material-symbols-outlined text-emerald-500 text-4xl mb-4" aria-hidden="true">check_circle</span>
        <h2 class="text-3xl font-headline mb-3">Payment Confirmed</h2>
        <p class="text-on-surface-variant mb-6">Your payment has been received. Track your order for fulfilment updates.</p>
        <dl class="space-y-3 text-sm mb-6">
            <div class="flex flex-wrap justify-between gap-2"><dt>Order Reference</dt><dd class="font-semibold">{{ reference || orderId }}</dd></div>
            <div class="flex flex-wrap justify-between gap-2"><dt>Amount Paid</dt><dd class="font-semibold">{{ formatAmount(amount, page.props) }}</dd></div>
            <div v-if="transactionId" class="flex flex-wrap justify-between gap-2"><dt>M-Pesa Receipt</dt><dd>{{ transactionId }}</dd></div>
        </dl>
        <ul v-if="items.length" class="space-y-3 border-t border-outline-variant pt-5 mb-6">
            <li v-for="(item, index) in items" :key="index" class="text-sm">
                <p>{{ item.name }} × {{ item.quantity }} · {{ formatAmount(item.price * item.quantity, page.props) }}</p>
                <p v-for="(value, key) in Object.fromEntries(Object.entries(item.options || {}).filter(([, value]) => value))" :key="key" class="text-on-surface-variant">{{ key.replaceAll('_', ' ') }}: {{ value }}</p>
            </li>
        </ul>
        <Link :href="route('track', { ref: reference })" class="action-primary w-full">Track Order</Link>
        <div class="flex flex-wrap justify-center gap-5 mt-5 text-sm">
            <a v-if="receiptUrl" :href="receiptUrl" class="underline">Download Receipt</a>
            <button @click="shareToWhatsApp" class="underline">Contact Studio on WhatsApp</button>
        </div>
        <p v-if="email" class="text-sm text-on-surface-variant mt-6">A receipt will be sent to {{ email }}.</p>
    </section>
</template>
