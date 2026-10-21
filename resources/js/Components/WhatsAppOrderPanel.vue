<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';
import { sizeLabel as formatSize } from '@/Support/sizes';

// Shown once a WhatsApp order is recorded: a real order (code, held stock,
// tracking, Studio) that the customer now sends on WhatsApp and pays as the
// studio instructs. The studio confirms payment, then the receipt is emailed.
const props = defineProps({
    handover: { type: Object, default: null },
    reference: { type: String, required: true },
    url: { type: String, required: true },
    instructions: { type: String, default: null },
    holdHours: { type: Number, default: 48 },
    total: { type: Number, default: 0 },
    items: { type: Array, default: () => [] },
    email: { type: String, default: '' },
});

const page = usePage();
const { formatAmount } = useCurrency();
const sent = ref(false);
const copied = ref(false);

function send() {
    sent.value = true;
    window.open(props.url, '_blank', 'noopener');
}
async function copyCode() {
    try { await navigator.clipboard.writeText(props.reference); copied.value = true; setTimeout(() => (copied.value = false), 2000); } catch { /* clipboard unavailable */ }
}
const sizeLabel = (options) => formatSize(options);
</script>

<template>
    <section class="card mx-auto max-w-2xl p-6 sm:p-9" role="status" aria-labelledby="wa-order-heading">
        <div class="flex items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#25D366]/15">
                <span class="material-symbols-outlined text-2xl text-[#128C7E] dark:text-[#25D366]" aria-hidden="true">check_circle</span>
            </span>
            <div class="min-w-0">
                <h2 id="wa-order-heading" class="font-headline text-2xl font-bold text-on-surface sm:text-3xl">Order placed</h2>
                <p class="mt-1 text-on-surface-variant">One last step: send it to the studio on WhatsApp.</p>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-surface-container px-5 py-4">
            <div>
                <span class="block text-xs uppercase tracking-wider text-on-surface-variant">Order code</span>
                <span class="font-mono text-lg font-bold tracking-wider text-on-surface">{{ reference }}</span>
            </div>
            <button type="button" @click="copyCode" class="action-secondary px-4 py-2 text-sm">
                <span class="material-symbols-outlined text-base" aria-hidden="true">{{ copied ? 'check' : 'receipt_long' }}</span>
                {{ copied ? 'Copied' : 'Copy code' }}
            </button>
        </div>

        <ol class="mt-7 space-y-6">
            <li class="flex gap-4">
                <span class="step-dot" :class="sent ? 'step-done' : 'step-now'">1</span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-semibold text-on-surface">Send your order on WhatsApp</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">It opens with your order, sizes and handover details filled in. Just press Send.</p>
                    <button type="button" @click="send" class="wa-btn mt-3 w-full sm:w-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.57-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        {{ sent ? 'Open WhatsApp again' : 'Send order on WhatsApp' }}
                    </button>
                    <p v-if="sent" class="mt-2 text-xs text-on-surface-variant">Didn’t open? Tap again, or message us your order code {{ reference }}.</p>
                </div>
            </li>
            <li class="flex gap-4">
                <span class="step-dot">2</span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-semibold text-on-surface">Pay {{ formatAmount(total, page.props) }}</h3>
                    <p v-if="instructions" class="mt-1 whitespace-pre-line rounded-xl border border-black/5 bg-surface-container px-4 py-3 text-sm text-on-surface dark:border-white/5">{{ instructions }}</p>
                    <p v-else class="mt-1 text-sm text-on-surface-variant">The studio will reply on WhatsApp with how to pay.</p>
                    <p class="mt-2 text-xs text-on-surface-variant">Use your order code as the payment reference. Your pieces are held for {{ holdHours }} hours.</p>
                </div>
            </li>
            <li class="flex gap-4">
                <span class="step-dot">3</span>
                <div class="min-w-0 flex-1">
                    <h3 class="font-semibold text-on-surface">We confirm and dispatch</h3>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Once payment is confirmed you’ll get a receipt<span v-if="email"> at {{ email }}</span>, and tracking updates as your order is packed and sent.
                    </p>
                </div>
            </li>
        </ol>

        <div v-if="handover" class="mt-4 text-sm"><p class="font-semibold">{{ handover.label }} · {{ handover.location }}</p><p>{{ handover.address }}</p><p>{{ handover.next }}</p></div>
        <ul v-if="items.length" class="mt-7 space-y-2 border-t border-black/5 pt-5 text-sm dark:border-white/5">
            <li v-for="(item, i) in items" :key="i" class="flex justify-between gap-4">
                <span class="min-w-0 text-on-surface">{{ item.name }} × {{ item.quantity }}<span v-if="sizeLabel(item.options)" class="block text-xs text-on-surface-variant">{{ sizeLabel(item.options) }}</span></span>
                <span class="shrink-0 text-on-surface">{{ formatAmount(item.price * item.quantity, page.props) }}</span>
            </li>
            <li class="flex justify-between border-t border-black/5 pt-2 font-semibold dark:border-white/5"><span>Total</span><span>{{ formatAmount(total, page.props) }}</span></li>
        </ul>

        <div class="mt-7 flex flex-col gap-3 sm:flex-row">
            <Link :href="route('track', { ref: reference })" class="action-secondary flex-1">Track this order</Link>
            <Link :href="route('shop')" class="action-secondary flex-1">Continue shopping</Link>
        </div>
    </section>
</template>

<style scoped lang="postcss">
.step-dot {
    @apply flex h-8 w-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold text-on-surface-variant;
    border-color: var(--card-border);
}
.step-now { @apply border-[#25D366] text-[#128C7E] dark:text-[#25D366]; }
.step-done { @apply border-[#25D366] bg-[#25D366] text-white; }
.wa-btn {
    @apply inline-flex items-center justify-center gap-2 rounded-full bg-[#25D366] px-7 py-3.5 text-sm font-semibold text-[#06281c] shadow-lg transition hover:brightness-105 active:scale-[0.98];
}
</style>
