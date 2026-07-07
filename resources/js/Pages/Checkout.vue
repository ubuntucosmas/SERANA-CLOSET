<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import MpesaStkPanel from '@/Components/MpesaStkPanel.vue';
import MpesaSuccessPanel from '@/Components/MpesaSuccessPanel.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { useCartStore } from '@/Stores/useCartStore';
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePulseStore } from '@/Stores/usePulseStore';

const page = usePage();
const pulse = usePulseStore();

const cart = useCartStore();
const { formatAmount } = useCurrency();

const isProcessing = ref(false);
const isWaitingForStk = ref(false);
// True only once the STK push has actually been dispatched. Kept separate from
// isWaitingForStk (which opens the modal) so the panel gets a real transition to
// its "check your phone" phase — otherwise it stalls on the initiating spinner.
const stkSent = ref(false);
// Explicit phase for the STK panel, derived from the request lifecycle.
const stkPanelStatus = computed(() => {
    if (stkSent.value) return 'waiting';
    if (isProcessing.value) return 'processing';
    return 'idle';
});
const isSuccess = ref(false);
const isPaid = ref(false);
// Shown when polling ends before the callback confirms — the payment may still
// be completing. We reassure rather than alarm: reconcile + the receipt email
// settle it within minutes.
const paymentPending = ref(false);
const orderId = ref(null);
const paidAmount = ref(0);
const safaricomTransactionId = ref(null);
const receiptUrl = ref(null);
const orderReference = ref(null);
const frozenItems = ref([]);
const frozenTotal = ref(0);
let pollingInterval = null;

const showSizing = ref(false);

const form = ref({
    email: '',
    full_name: '',
    address: '',
    city: '',
    phone: '',
    height_cm: '',
    bust_cm: '',
    waist_cm: '',
    hips_cm: '',
});

async function handlePayment() {
    isProcessing.value = true;
    stkSent.value = false;
    try {
        // The server re-prices every line from the catalog — send only what it
        // needs: ids, quantities and the customer's size selections.
        const payload = {
            ...form.value,
            items: cart.items.map(i => ({ id: i.id, quantity: i.quantity, options: i.options ?? null })),
        };
        if (!payload.height_cm) delete payload.height_cm;
        if (!payload.bust_cm) delete payload.bust_cm;
        if (!payload.waist_cm) delete payload.waist_cm;
        if (!payload.hips_cm) delete payload.hips_cm;

        const response = await axios.post(route('checkout.process'), payload);
        if (response.data.success && response.data.order_id) {
            orderId.value = response.data.order_id;
            isWaitingForStk.value = true;
            pulse.emit('Order created successfully.', 'success');
        } else {
            pulse.emit(response.data.message || 'Order processing failed.', 'error');
        }
    } catch (err) {
        console.error(err);
        if (err.response?.data?.errors) {
            pulse.emit('Please check the form.', 'error');
        } else {
            pulse.emit(err.response?.data?.message || 'Order processing failed.', 'error');
        }
    } finally {
        isProcessing.value = false;
    }
}

async function initiateMpesaStk(data) {
    isProcessing.value = true;
    stkSent.value = false;
    paymentPending.value = false;
    isWaitingForStk.value = true; // keep the modal open across the request
    try {
        const response = await axios.post(route('mpesa.stk'), {
            order_id: orderId.value,
            phone: data.phone
        });
        if (response.data.success) {
            pulse.emit('STK Push sent to your phone.', 'success');
            stkSent.value = true; // advances the panel to "check your phone"
            startPolling();
        } else {
            pulse.emit(response.data.message || 'Failed to initiate M-Pesa payment.', 'error');
        }
    } catch (err) {
        console.error(err);
        pulse.emit(err.response?.data?.message || 'Failed to initiate M-Pesa payment.', 'error');
    } finally {
        isProcessing.value = false;
    }
}

function closePaymentModal() {
    if (pollingInterval) clearInterval(pollingInterval);
    isWaitingForStk.value = false;
    stkSent.value = false;
    isSuccess.value = false;
    paymentPending.value = false;
}

// Lock background scroll while the payment modal is open.
watch(
    [isWaitingForStk, isSuccess, isPaid],
    () => {
        const open = (isWaitingForStk.value && !isPaid.value) || isSuccess.value || paymentPending.value;
        if (typeof document !== 'undefined') {
            document.body.style.overflow = open ? 'hidden' : '';
        }
    }
);

function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    let attempts = 0;
    // ~2.5 min: customers are often slow entering their PIN, and the Safaricom
    // callback can lag. If it still hasn't confirmed, reconcile + the receipt
    // email take over — so we hand off calmly instead of erroring out.
    const maxAttempts = 30;
    pollingInterval = setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(pollingInterval);
            isWaitingForStk.value = false;
            stkSent.value = false;
            paymentPending.value = true;
            return;
        }
        try {
            const response = await axios.get(route('mpesa.status', orderId.value));
            // Capture the tracking reference as soon as it's known, so the
            // pending hand-off can show it even before payment confirms.
            if (response.data.reference) orderReference.value = response.data.reference;
            if (response.data.is_paid) {
                clearInterval(pollingInterval);
                isPaid.value = true;
                isWaitingForStk.value = false;
                isSuccess.value = true;
                // Server-confirmed figure — the local cart is not authoritative.
                paidAmount.value = response.data.amount ?? cart.totalPrice;
                safaricomTransactionId.value = response.data.transaction_id;
                receiptUrl.value = response.data.receipt_url;
                frozenItems.value = [...cart.items];
                frozenTotal.value = cart.totalPrice;
                cart.clearBag();
                pulse.emit('Payment Verified! Your order is now in production.', 'success');
            }
        } catch (err) { console.error('Polling error:', err); }
    }, 5000);
}
</script>

<template>
    <StorefrontLayout hideMobileCard>
        <Head title="Checkout — Serana" />

        <main class="relative pb-24 px-6 sm:px-8 max-w-screen-xl mx-auto min-h-screen bg-background">

            <!-- Lipa na M-PESA Express Checkout -->
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-5 mb-10">
                    <div>
                        <Link :href="route('shop')" class="text-[11px] tracking-wider text-on-surface-variant hover:text-primary flex items-center gap-2 transition-colors">
                            <span class="material-symbols-outlined text-sm">arrow_back</span> Back to Collection
                        </Link>
                        <h1 class="font-headline text-4xl md:text-5xl tracking-tighter mt-4 text-on-surface">Secure Checkout</h1>
                        <p class="text-on-surface-variant mt-2 text-sm">Complete your order with M-PESA — fast, safe, no card required.</p>
                    </div>
                    <!-- Lipa na M-PESA lockup -->
                    <div class="inline-flex items-center gap-3 self-start px-4 py-3 bg-[#4fb33f]/10 border border-[#4fb33f]/30">
                        <span class="material-symbols-outlined text-[#4fb33f] text-2xl" style="font-variation-settings:'FILL' 1">account_balance_wallet</span>
                        <div class="leading-tight">
                            <div class="text-[9px] tracking-wider text-on-surface-variant">Powered by</div>
                            <div class="text-sm font-black text-[#4fb33f] tracking-tight">Lipa na M-PESA</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">

                    <!-- LEFT: Order Summary -->
                    <aside class="lg:col-span-5 lg:sticky lg:top-28 h-fit space-y-5">
                        <div class="bg-surface-container border border-white/10 p-7 md:p-8">
                            <div class="flex items-center justify-between mb-7">
                                <h3 class="tracking-wider text-[11px] text-on-surface-variant font-bold">Order Summary</h3>
                                <span class="text-[11px] font-mono text-on-surface-variant">{{ cart.items.length }} item{{ cart.items.length === 1 ? '' : 's' }}</span>
                            </div>

                            <div class="space-y-5">
                                <div v-for="item in cart.items" :key="item.id" class="flex gap-4">
                                    <div class="always-dark w-16 h-20 bg-white/5 overflow-hidden flex-shrink-0 border border-white/5">
                                        <img :src="item.image" class="w-full h-full object-cover" />
                                    </div>
                                    <div class="flex-1 min-w-0 pt-0.5">
                                        <div class="text-on-surface font-medium truncate">{{ item.name }}</div>
                                        <div class="text-xs text-on-surface-variant mt-0.5">Qty · {{ item.quantity }}</div>
                                        <div class="text-primary font-mono text-sm mt-2">{{ formatAmount(item.price * item.quantity) }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-7 pt-6 border-t border-white/10 space-y-2.5">
                                <div class="flex justify-between text-sm text-on-surface-variant">
                                    <span>Subtotal</span>
                                    <span class="font-mono">{{ formatAmount(cart.totalPrice) }}</span>
                                </div>
                                <div class="flex justify-between text-sm text-on-surface-variant">
                                    <span>Delivery</span>
                                    <span class="text-[#4fb33f] font-medium">Included</span>
                                </div>
                                <div class="flex justify-between items-baseline pt-3 mt-1 border-t border-white/5">
                                    <span class="tracking-widest text-[11px] text-on-surface-variant">Total</span>
                                    <span class="font-headline text-3xl text-primary">{{ formatAmount(cart.totalPrice) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Trust strip -->
                        <div class="grid grid-cols-3 gap-2">
                            <div v-for="t in [
                                { icon: 'lock', label: 'Encrypted' },
                                { icon: 'verified_user', label: 'Safaricom' },
                                { icon: 'bolt', label: 'Instant' },
                            ]" :key="t.label" class="flex flex-col items-center gap-1.5 py-4 bg-surface-container border border-white/5">
                                <span class="material-symbols-outlined text-[#4fb33f] text-lg">{{ t.icon }}</span>
                                <span class="text-[9px] tracking-widest text-on-surface-variant font-bold">{{ t.label }}</span>
                            </div>
                        </div>
                    </aside>

                    <!-- RIGHT: Details + Pay -->
                    <div class="lg:col-span-7 space-y-6">

                        <!-- 1 · Delivery details -->
                        <div class="bg-surface-container border border-white/10 p-7 md:p-8">
                            <div class="flex items-center gap-3 mb-7">
                                <span class="w-7 h-7 flex items-center justify-center bg-primary/15 text-primary text-xs font-black">1</span>
                                <h2 class="tracking-wider text-xs font-bold text-on-surface">Delivery Details</h2>
                            </div>

                            <div class="space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="text-[10px] tracking-widest text-on-surface-variant mb-2 block">Full Name</label>
                                        <input v-model="form.full_name" type="text" placeholder="Your legal name" class="w-full bg-background border border-white/10 px-5 py-3.5 text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary outline-none transition-colors" />
                                    </div>
                                    <div>
                                        <label class="text-[10px] tracking-widest text-on-surface-variant mb-2 block">Email</label>
                                        <input v-model="form.email" type="email" placeholder="you@email.com" class="w-full bg-background border border-white/10 px-5 py-3.5 text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary outline-none transition-colors" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="text-[10px] tracking-widest text-on-surface-variant mb-2 block">Address</label>
                                        <input v-model="form.address" type="text" placeholder="House & Street" class="w-full bg-background border border-white/10 px-5 py-3.5 text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary outline-none transition-colors" />
                                    </div>
                                    <div>
                                        <label class="text-[10px] tracking-widest text-on-surface-variant mb-2 block">City</label>
                                        <input v-model="form.city" type="text" placeholder="Nairobi" class="w-full bg-background border border-white/10 px-5 py-3.5 text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary outline-none transition-colors" />
                                    </div>
                                </div>

                                <!-- Optional measurements -->
                                <div class="pt-1">
                                    <button @click="showSizing = !showSizing" class="text-[11px] flex items-center gap-2 text-primary hover:opacity-80 tracking-widest transition-opacity">
                                        <span class="material-symbols-outlined text-base">{{ showSizing ? 'expand_less' : 'expand_more' }}</span>
                                        Add custom measurements <span class="text-on-surface-variant/60 normal-case tracking-normal">— optional</span>
                                    </button>
                                    <div v-if="showSizing" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                        <div v-for="f in ['height_cm','bust_cm','waist_cm','hips_cm']" :key="f">
                                            <label class="text-[10px] tracking-widest text-on-surface-variant mb-1.5 block">{{ f.replace('_cm','') }} (cm)</label>
                                            <input v-model="form[f]" type="number" class="w-full bg-background border border-white/10 px-4 py-3 text-sm text-on-surface focus:border-primary outline-none transition-colors" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2 · Pay with M-PESA -->
                        <div class="bg-surface-container border border-[#4fb33f]/25 p-7 md:p-8">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="w-7 h-7 flex items-center justify-center bg-[#4fb33f]/15 text-[#4fb33f] text-xs font-black">2</span>
                                <h2 class="tracking-wider text-xs font-bold text-on-surface">Pay with M-PESA</h2>
                                <span class="ml-auto w-2 h-2 rounded-full bg-[#4fb33f] animate-pulse"></span>
                            </div>

                            <!-- M-PESA number -->
                            <label class="text-[10px] tracking-widest text-on-surface-variant mb-2 block">M-PESA Number</label>
                            <div class="flex">
                                <span class="flex items-center px-4 bg-[#4fb33f]/12 border border-r-0 border-[#4fb33f]/30 text-[#4fb33f] font-mono font-bold text-sm">+254</span>
                                <input v-model="form.phone" type="tel" inputmode="numeric" placeholder="7XX XXX XXX" class="flex-1 min-w-0 bg-background border border-white/10 px-5 py-3.5 text-on-surface font-mono placeholder:text-on-surface-variant/50 focus:border-[#4fb33f] outline-none transition-colors" />
                            </div>

                            <!-- How it works -->
                            <div class="flex items-center gap-1.5 mt-6 mb-6">
                                <template v-for="(s, i) in ['Confirm order', 'Get STK prompt', 'Enter your PIN']" :key="s">
                                    <div class="flex items-center gap-2 flex-1">
                                        <span class="w-5 h-5 flex-shrink-0 flex items-center justify-center rounded-full bg-[#4fb33f]/15 text-[#4fb33f] text-[10px] font-black">{{ i + 1 }}</span>
                                        <span class="text-[10px] text-on-surface-variant leading-tight">{{ s }}</span>
                                    </div>
                                    <span v-if="i < 2" class="material-symbols-outlined text-on-surface-variant/30 text-sm">chevron_right</span>
                                </template>
                            </div>

                            <!-- Pay button -->
                            <button
                                @click="handlePayment"
                                :disabled="isProcessing || !form.full_name || !form.phone || !form.address"
                                class="w-full py-5 bg-[#4fb33f] text-black font-black tracking-wider text-sm flex items-center justify-center gap-3 hover:bg-[#5cc94a] active:scale-[0.99] transition-all disabled:opacity-40 disabled:cursor-not-allowed shadow-[0_20px_40px_-12px_rgba(79,179,63,0.5)]">
                                <template v-if="!isProcessing">
                                    <span class="material-symbols-outlined">lock</span>
                                    Pay {{ formatAmount(cart.totalPrice) }}
                                </template>
                                <template v-else>
                                    <span class="material-symbols-outlined animate-spin">progress_activity</span>
                                    Creating your order…
                                </template>
                            </button>

                            <p class="text-center text-[10px] text-on-surface-variant mt-4 flex items-center justify-center gap-1.5">
                                <span class="material-symbols-outlined text-xs text-[#4fb33f]">shield</span>
                                You'll receive a secure STK push on your phone
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── M-PESA Payment Modal ─────────────────────────────────── -->
            <teleport to="body">
                <transition name="pay-modal">
                    <div
                        v-if="((isWaitingForStk || paymentPending) && orderId && !isPaid) || isSuccess"
                        class="fixed inset-0 z-[300] flex items-center justify-center p-4 sm:p-6"
                    >
                        <!-- Backdrop -->
                        <div
                            class="pay-modal-backdrop absolute inset-0 bg-black/85 backdrop-blur-md"
                            @click="closePaymentModal"
                        ></div>

                        <!-- Dialog -->
                        <div
                            class="pay-modal-card relative z-10 w-full max-h-[92vh] overflow-y-auto no-scrollbar"
                            :class="isSuccess ? 'max-w-2xl' : 'max-w-md'"
                        >
                            <!-- Close -->
                            <button
                                @click="closePaymentModal"
                                class="absolute top-4 right-4 z-50 w-9 h-9 flex items-center justify-center bg-black/40 text-white/60 hover:text-white hover:bg-black/70 backdrop-blur-md transition-colors"
                                title="Close"
                            >
                                <span class="material-symbols-outlined text-lg">close</span>
                            </button>

                            <!-- Payment still confirming (polling ended before the
                                 callback). Reassure — reconcile + email finish the job. -->
                            <div
                                v-if="paymentPending && !isPaid"
                                class="bg-white dark:bg-[#1d211d] rounded-[2rem] p-8 md:p-10 text-center ring-1 ring-black/5 dark:ring-white/5"
                            >
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-400/15 mb-5">
                                    <span class="material-symbols-outlined text-3xl text-amber-500" style="font-variation-settings:'FILL' 1">hourglass_top</span>
                                </div>
                                <h2 class="font-headline text-2xl md:text-3xl tracking-tight text-[#191c1b] dark:text-white mb-3">
                                    Still confirming your payment
                                </h2>
                                <p class="text-[#3f4a3b] dark:text-[#becab6] text-sm leading-relaxed mb-6">
                                    If you completed the M-Pesa prompt, you're all set — confirmation can
                                    take a few minutes. We'll <strong>email your receipt</strong> the moment
                                    it clears. No need to pay again.
                                </p>
                                <div v-if="orderReference" class="bg-black/5 dark:bg-black/30 rounded-xl px-4 py-3 mb-6">
                                    <span class="block text-[10px] uppercase tracking-widest text-[#3f4a3b] dark:text-[#becab6] mb-1">Order reference</span>
                                    <span class="font-mono font-bold tracking-wider text-[#191c1b] dark:text-white">{{ orderReference }}</span>
                                </div>
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <Link
                                        :href="orderReference ? route('track', { ref: orderReference }) : route('track')"
                                        class="flex-1 bg-primary text-background font-headline font-black py-4 px-6 rounded-xl text-[11px] uppercase tracking-widest hover:bg-on-surface transition-all"
                                    >
                                        Track my order
                                    </Link>
                                    <button
                                        @click="initiateMpesaStk({ phone: form.phone })"
                                        class="flex-1 bg-black/5 dark:bg-black text-[#191c1b] dark:text-white font-headline font-black py-4 px-6 rounded-xl text-[11px] uppercase tracking-widest border border-black/10 dark:border-white/10 hover:bg-black/10 transition-all"
                                    >
                                        Try payment again
                                    </button>
                                </div>
                            </div>

                            <MpesaStkPanel
                                v-else-if="!isPaid"
                                :amount="cart.totalPrice"
                                :phone="form.phone"
                                :status="stkPanelStatus"
                                @pay="initiateMpesaStk"
                                @cancel="closePaymentModal"
                            />
                            <MpesaSuccessPanel
                                v-else
                                :amount="paidAmount"
                                :orderId="orderId"
                                :items="frozenItems"
                                :transactionId="safaricomTransactionId"
                                :receiptUrl="receiptUrl"
                                :reference="orderReference"
                                :email="form.email"
                            />
                        </div>
                    </div>
                </transition>
            </teleport>

        </main>
    </StorefrontLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

/* Opening modal animation */
.pay-modal-enter-active,
.pay-modal-leave-active { transition: opacity 0.3s ease; }
.pay-modal-enter-active .pay-modal-card,
.pay-modal-leave-active .pay-modal-card { transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease; }
.pay-modal-enter-from,
.pay-modal-leave-to { opacity: 0; }
.pay-modal-enter-from .pay-modal-card,
.pay-modal-leave-to .pay-modal-card { transform: translateY(24px) scale(0.97); opacity: 0; }
</style>