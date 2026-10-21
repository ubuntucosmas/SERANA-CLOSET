<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';
import { sizeLabel as formatSize } from '@/Support/sizes';

const page = usePage();
const props = defineProps({
    amount: { type: [Number, String], required: true },
    orderId: { type: [Number, String], required: true },
    items: { type: Array, default: () => [] },
    // Null when the payment was confirmed by reconciliation (Safaricom sends no code then).
    transactionId: { type: String, default: null },
    receiptUrl: { type: String, default: null },
    reference: { type: String, default: null },
    email: { type: String, default: null },
    // Built by the server from the settled order: code, amount, time, items, delivery.
    whatsappUrl: { type: String, default: null },
});

const { formatAmount } = useCurrency();

// After paying, the customer posts the payment record to the studio on
// WhatsApp, so both sides hold the same details and can check them against
// M-Pesa. It opens by itself after a short, visible countdown the customer can
// stop; once per order, so coming back to this page never loops.
const COUNTDOWN = 6;
const secondsLeft = ref(null);
let timer = null;
const sentKey = () => `serana_paid_wa_${props.reference || props.orderId}`;
function markOpened() { try { sessionStorage.setItem(sentKey(), '1'); } catch {} }
function alreadyOpened() { try { return sessionStorage.getItem(sentKey()) === '1'; } catch { return false; } }
function stopCountdown() { clearInterval(timer); timer = null; secondsLeft.value = null; }

onMounted(() => {
    if (!props.whatsappUrl || alreadyOpened()) return;
    secondsLeft.value = COUNTDOWN;
    timer = setInterval(() => {
        secondsLeft.value -= 1;
        if (secondsLeft.value > 0) return;
        stopCountdown();
        markOpened();
        // Same tab: a new tab opened without a tap is blocked by browsers.
        window.location.assign(props.whatsappUrl);
    }, 1000);
});
onBeforeUnmount(stopCountdown);
function openedByTap() { stopCountdown(); markOpened(); }

// Same wording as the bag and checkout: "Top M · Bottom L", "Size M" or "One size".
const sizeLabel = (item) => formatSize(item.options);
</script>

<template>
    <section class="bg-surface text-on-surface rounded-3xl p-7 sm:p-9 border border-outline-variant" role="status">
        <span class="material-symbols-outlined text-emerald-500 text-4xl mb-4" aria-hidden="true">check_circle</span>
        <h2 class="text-3xl font-headline mb-3">Payment Confirmed</h2>
        <p class="text-on-surface-variant mb-6">Your payment has been received and your order is confirmed.</p>
        <dl class="space-y-3 text-sm mb-6">
            <div class="flex flex-wrap justify-between gap-2"><dt>Order code</dt><dd class="font-semibold">{{ reference || orderId }}</dd></div>
            <div class="flex flex-wrap justify-between gap-2"><dt>Amount paid</dt><dd class="font-semibold">{{ formatAmount(amount, page.props) }}</dd></div>
            <div class="flex flex-wrap justify-between gap-2"><dt>M-Pesa code</dt><dd>{{ transactionId || 'In your M-Pesa SMS' }}</dd></div>
        </dl>
        <ul v-if="items.length" class="space-y-3 border-t border-outline-variant pt-5 mb-6">
            <li v-for="(item, index) in items" :key="index" class="text-sm">
                <p>{{ item.name }} × {{ item.quantity }} · {{ formatAmount(item.price * item.quantity, page.props) }}</p>
                <p v-if="sizeLabel(item)" class="text-on-surface-variant">{{ sizeLabel(item) }}</p>
            </li>
        </ul>

        <!-- Next step: post the payment record to the studio -->
        <div v-if="whatsappUrl" class="rounded-2xl border border-outline-variant p-5 mb-5">
            <p class="font-semibold">Last step: send your payment record to the studio</p>
            <p class="text-sm text-on-surface-variant mt-1">It holds your order code, M-Pesa code, amount, time and delivery details. The studio checks it against M-Pesa and confirms delivery in the same chat, so you both keep the same record.</p>
            <p v-if="!transactionId" class="text-sm mt-2">Add the code from your M-Pesa SMS where the message asks for it.</p>
            <a :href="whatsappUrl" target="_blank" rel="noopener" @click="openedByTap" class="wa-action w-full py-4 mt-4">
                <span class="material-symbols-outlined" aria-hidden="true">chat</span>
                Send on WhatsApp{{ secondsLeft ? ` (opens in ${secondsLeft}s)` : '' }}
            </a>
            <p v-if="secondsLeft" class="text-xs text-on-surface-variant mt-3 text-center" aria-live="polite">
                WhatsApp opens in {{ secondsLeft }} seconds. Review the message, then press Send.
                <button type="button" @click="stopCountdown" class="underline ml-1">Not now</button>
            </p>
        </div>

        <Link :href="route('track', { ref: reference })" :class="whatsappUrl ? 'action-secondary w-full' : 'action-primary w-full'">Track order</Link>
        <div class="flex flex-wrap justify-center gap-5 mt-5 text-sm">
            <a v-if="receiptUrl" :href="receiptUrl" class="underline">Download receipt</a>
        </div>
        <p v-if="email" class="text-sm text-on-surface-variant mt-6">A receipt will be sent to {{ email }}.</p>
    </section>
</template>
