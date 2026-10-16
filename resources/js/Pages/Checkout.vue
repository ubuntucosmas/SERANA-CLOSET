<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import MpesaStkPanel from '@/Components/MpesaStkPanel.vue';
import MpesaSuccessPanel from '@/Components/MpesaSuccessPanel.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { useCartStore } from '@/Stores/useCartStore';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePulseStore } from '@/Stores/usePulseStore';
import { rememberTrackingPhone } from '@/Support/trackingMemory';

const page = usePage();
const pulse = usePulseStore();

const cart = useCartStore();
const { formatAmount } = useCurrency();

const errors = ref({});
const orderAmount = ref(null);
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
    request_id: crypto.randomUUID(),
    // Signed-in shoppers shouldn't retype what we already know.
    email: page.props.auth?.user?.email || '',
    full_name: page.props.auth?.user?.name || '',
    address: '',
    city: '',
    phone: '',
    height_cm: '',
    bust_cm: '',
    waist_cm: '',
    hips_cm: '',
});

// Remember the pending order for this exact bag, so a refresh, the back button
// or a return visit resumes it instead of creating a duplicate order.
const STORE_KEY = 'serana_checkout';
const CONTEXT_TTL_MS = 60 * 60 * 1000;
const bagSignature = computed(() => JSON.stringify(cart.items.map(i => [i.id, i.quantity, i.options ?? null])));
const resumedOrder = ref(false);
// 'review' when M-Pesa took a different amount: paying again is blocked until support reviews it.
const paymentBlocked = ref(null);

function saveContext() {
    try {
        const { email, full_name, address, city, phone } = form.value;
        localStorage.setItem(STORE_KEY, JSON.stringify({
            sig: bagSignature.value, request_id: form.value.request_id,
            order_id: orderId.value, reference: orderReference.value, amount: orderAmount.value,
            items: frozenItems.value, details: { email, full_name, address, city, phone }, at: Date.now(),
        }));
    } catch {}
}
function clearContext() { try { localStorage.removeItem(STORE_KEY); } catch {} }

function startOver() {
    clearContext();
    orderId.value = null; orderReference.value = null; orderAmount.value = null;
    resumedOrder.value = false; paymentBlocked.value = null; errors.value = {};
    form.value.request_id = crypto.randomUUID();
}

onMounted(async () => {
    let ctx = null;
    try { ctx = JSON.parse(localStorage.getItem(STORE_KEY) || 'null'); } catch {}
    if (!ctx) return;
    if (ctx.sig !== bagSignature.value || Date.now() - ctx.at > CONTEXT_TTL_MS) { clearContext(); return; }
    form.value.request_id = ctx.request_id || form.value.request_id;
    Object.assign(form.value, ctx.details || {});
    if (!ctx.order_id) return;
    orderId.value = ctx.order_id; orderReference.value = ctx.reference; orderAmount.value = ctx.amount;
    frozenItems.value = ctx.items || [];
    resumedOrder.value = true;
    try {
        const { data } = await axios.get(route('mpesa.status', orderId.value));
        applyStatus(data);
    } catch (err) {
        if (err.response?.status === 404) startOver();
    }
});

// One place that turns the server's payment status into what the customer sees.
// Returns 'paid' | 'failed' | 'blocked' | 'expired' | 'pending'.
function applyStatus(data) {
    if (data.reference) orderReference.value = data.reference;
    if (data.is_paid) {
        isPaid.value = true;
        isWaitingForStk.value = false;
        isSuccess.value = true;
        resumedOrder.value = false;
        // Server-confirmed figure — the local cart is not authoritative.
        paidAmount.value = data.amount ?? cart.totalPrice;
        safaricomTransactionId.value = data.transaction_id;
        receiptUrl.value = data.receipt_url;
        frozenTotal.value = cart.totalPrice;
        cart.clearBag();
        clearContext();
        rememberTrackingPhone(form.value.phone);
        return 'paid';
    }
    if (data.status === 'Expired') {
        // The hold lapsed: a new order is needed. Keep the delivery details.
        startOver();
        pulse.emit('Your earlier unpaid order expired. Review your bag and pay again to place a new order.', 'error');
        return 'expired';
    }
    if (data.status === 'Amount Mismatch') {
        paymentBlocked.value = 'review';
        errors.value = { payment: ['M-Pesa reported a different amount for this order. Please contact support with your order reference before paying again.'] };
        return 'blocked';
    }
    if (data.status === 'Payment Failed') {
        errors.value = { payment: ['Payment was not completed. Press Resume Payment to try again, or contact support if M-Pesa shows a charge.'] };
        return 'failed';
    }
    return 'pending';
}

// Required delivery fields, in screen order. Tapping Pay with gaps jumps to
// the first one and says what is missing, instead of a silently disabled button.
const REQUIRED = [
    ['full_name', 'Enter your full name.'],
    ['email', 'Enter your email so we can send your receipt.'],
    ['address', 'Enter the delivery address.'],
    ['city', 'Enter your town or city.'],
    ['phone', 'Enter the M-Pesa number to charge.'],
];
function firstMissingField() {
    const missing = REQUIRED.filter(([field]) => !String(form.value[field] || '').trim());
    if (!missing.length) return false;
    errors.value = Object.fromEntries(missing.map(([field, message]) => [field, [message]]));
    const el = document.getElementById(`checkout-${missing[0][0]}`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el?.focus({ preventScroll: true });
    return true;
}
const payTotal = computed(() => orderAmount.value ?? cart.totalPrice);
// A field's message disappears as soon as it is filled in.
watch(form, (value) => {
    const fixed = REQUIRED.filter(([field]) => errors.value[field] && String(value[field] || '').trim());
    if (fixed.length) errors.value = Object.fromEntries(Object.entries(errors.value).filter(([field]) => !fixed.some(([f]) => f === field)));
}, { deep: true });
// Field errors sit under their field; only the rest (payment, server) go in the top box.
const FIELD_KEYS = ['full_name', 'email', 'address', 'city', 'phone'];
const generalErrors = computed(() => Object.entries(errors.value).filter(([field]) => !FIELD_KEYS.includes(field)));

async function handlePayment() {
    if (isProcessing.value || !cart.items.length) return;
    if (orderId.value) { isWaitingForStk.value = true; return; }
    if (firstMissingField()) return;
    errors.value = {};
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

        saveContext(); // a refresh mid-request re-sends the same request_id and gets this order back
        const response = await axios.post(route('checkout.process'), payload);
        if (response.data.success && response.data.order_id) {
            orderId.value = response.data.order_id;
            orderReference.value = response.data.reference;
            orderAmount.value = response.data.amount;
            frozenItems.value = response.data.items ? Object.values(response.data.items).filter(item => item?.id) : [...cart.items];
            saveContext();
            isWaitingForStk.value = true;
        } else {
            pulse.emit(response.data.message || 'Order processing failed.', 'error');
        }
    } catch (err) {
        console.error(err);
        if (err.response?.data?.errors) {
            errors.value = err.response.data.errors;
            document.querySelector('[aria-invalid="true"]')?.focus();
        } else {
            pulse.emit(err.response?.data?.message || 'Order processing failed.', 'error');
        }
    } finally {
        isProcessing.value = false;
    }
}

async function initiateMpesaStk(data) {
    if (isProcessing.value || stkSent.value || paymentPending.value) return;
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
            stkSent.value = true; // advances the panel to "check your phone"
            startPolling();
        } else {
            pulse.emit(response.data.message || 'Failed to initiate M-Pesa payment.', 'error');
        }
    } catch (err) {
        console.error(err);
        if (!err.response || err.response.status >= 500) {
            paymentPending.value = true;
            stkSent.value = true;
            startPolling();
            pulse.emit('We could not confirm whether the request reached M-Pesa. Check this order before trying another payment.', 'error');
        } else {
            pulse.emit(err.response?.data?.message || 'M-Pesa could not start this request. Check your phone number and try again.', 'error');
        }
    } finally {
        isProcessing.value = false;
    }
}

function closePaymentModal() {
    isWaitingForStk.value = false;
    isSuccess.value = false;
}

// Lock background scroll while the payment modal is open.
watch(
    [isWaitingForStk, isSuccess, isPaid, paymentPending],
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
            const outcome = applyStatus(response.data);
            if (outcome !== 'pending') {
                clearInterval(pollingInterval);
                stkSent.value = false;
                paymentPending.value = false;
                if (outcome !== 'paid') isWaitingForStk.value = false;
            }
        } catch (err) { console.error('Polling error:', err); }
    }, 5000);
}
onUnmounted(() => {
    if (pollingInterval) clearInterval(pollingInterval);
    document.body.style.overflow = '';
});
</script>

<template>
    <StorefrontLayout focus>
        <Head title="Checkout — Serana" />

        <main class="page-container relative pb-24 min-h-screen">

            <!-- Lipa na M-PESA Express Checkout -->
            <div class="max-w-6xl mx-auto">
                <!-- Header -->
                <PageHeader kicker="Checkout" title="Secure Checkout" subtitle="Complete your order with M-PESA. Fast, safe, no card required.">
                    <template #before>
                        <Link :href="route('shop')" class="mb-4 flex w-fit items-center gap-2 text-sm text-on-surface-variant transition-colors hover:text-primary">
                            <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_back</span> Back to Collection
                        </Link>
                    </template>
                    <template #actions>
                    <!-- Lipa na M-PESA lockup -->
                    <div class="inline-flex items-center gap-3 rounded-2xl border border-[#4fb33f]/30 bg-[#4fb33f]/10 px-4 py-3">
                        <span class="material-symbols-outlined text-[#276b1d] dark:text-[#4fb33f] text-2xl" style="font-variation-settings:'FILL' 1">account_balance_wallet</span>
                        <div class="leading-tight">
                            <div class="text-[9px] tracking-wider text-on-surface-variant">Powered by</div>
                            <div class="text-sm font-black text-[#276b1d] dark:text-[#4fb33f] tracking-tight">Lipa na M-PESA</div>
                        </div>
                    </div>
                    </template>
                </PageHeader>

                <div v-if="resumedOrder && !isPaid && !paymentBlocked" role="status" class="card mb-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-semibold text-on-surface">You have an unpaid order for this bag</p>
                        <p class="mt-1 text-sm text-on-surface-variant">Order <strong>{{ orderReference }}</strong> is waiting for payment. Resume it, or start over to change your details.</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-3">
                        <button type="button" class="action-primary" @click="handlePayment">Resume Payment</button>
                        <button type="button" class="action-secondary" @click="startOver">Start Over</button>
                    </div>
                </div>
                <p v-else-if="orderReference && !isPaid" class="text-sm mb-5">Order Reference: <strong>{{ orderReference }}</strong> · <Link :href="route('track', { ref: orderReference })" class="text-primary underline">Track Order</Link></p>
                <div v-if="generalErrors.length" role="alert" class="mb-6 rounded-xl border border-red-400/40 p-5">
                    <p class="font-semibold mb-2">Please check these details</p>
                    <p v-for="[field, messages] in generalErrors" :key="field" class="text-sm">{{ messages[0] }}</p>
                </div>
                <div v-if="!cart.items.length && !isPaid" class="p-8 bg-surface-container rounded-2xl mb-8">
                    <h2 class="text-xl mb-3">Your bag is empty</h2>
                    <Link :href="route('shop')" class="text-primary underline">Shop Collection</Link>
                </div>
                <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">

                    <!-- LEFT: Order Summary -->
                    <aside class="order-2 lg:order-1 lg:col-span-5 lg:sticky lg:top-28 h-fit space-y-5">
                        <div class="bg-surface-container border border-white/10 p-7 md:p-8">
                            <div class="flex items-center justify-between mb-7">
                                <h3 class="tracking-wider text-[11px] text-on-surface-variant font-bold">Order Summary</h3>
                                <span class="text-[11px] font-mono text-on-surface-variant">{{ cart.items.length }} item{{ cart.items.length === 1 ? '' : 's' }}</span>
                            </div>

                            <div class="space-y-5">
                                <div v-for="item in cart.items" :key="item.line_key" class="flex gap-4">
                                    <div class="always-dark w-16 h-20 bg-white/5 overflow-hidden flex-shrink-0 border border-white/5">
                                        <img :src="item.image" class="w-full h-full object-cover" />
                                    </div>
                                    <div class="flex-1 min-w-0 pt-0.5">
                                        <div class="text-on-surface font-medium truncate">{{ item.name }}</div>
                                        <p v-if="item.options?.top_size || item.options?.bottom_size" class="text-xs text-on-surface-variant">{{ item.options.top_size && item.options.bottom_size ? `Top ${item.options.top_size} · Bottom ${item.options.bottom_size}` : `Size ${item.options.top_size || item.options.bottom_size}` }}</p>
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
                                    <span class="text-[#276b1d] dark:text-[#4fb33f] font-medium">Included</span>
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
                                { icon: 'receipt_long', label: 'Receipt by email' },
                            ]" :key="t.label" class="flex flex-col items-center gap-1.5 py-4 bg-surface-container border border-white/5">
                                <span class="material-symbols-outlined text-[#276b1d] dark:text-[#4fb33f] text-lg">{{ t.icon }}</span>
                                <span class="text-[9px] tracking-widest text-on-surface-variant font-bold">{{ t.label }}</span>
                            </div>
                        </div>
                    </aside>

                    <!-- RIGHT: Details + Pay -->
                    <div class="order-1 lg:order-2 lg:col-span-7 space-y-6">
                        <!-- Phones: one line of what's being bought; the full summary follows the form. -->
                        <button type="button" @click="cart.isOpen = true" class="card flex w-full items-center justify-between gap-3 px-5 py-4 text-left lg:hidden">
                            <span class="text-sm text-on-surface-variant">{{ cart.totalItems }} {{ cart.totalItems === 1 ? 'piece' : 'pieces' }} · <span class="font-semibold text-on-surface">{{ formatAmount(cart.totalPrice) }}</span></span>
                            <span class="text-sm font-medium text-primary">View bag</span>
                        </button>

                        <!-- 1 · Delivery details -->
                        <div class="bg-surface-container border border-white/10 p-7 md:p-8">
                            <div class="flex items-center gap-3 mb-7">
                                <span class="w-7 h-7 flex items-center justify-center bg-primary/15 text-primary text-xs font-black">1</span>
                                <h2 class="tracking-wider text-xs font-bold text-on-surface">Delivery Details</h2>
                            </div>

                            <div class="space-y-5">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="text-[10px] tracking-widest text-on-surface-variant mb-2 block" for="checkout-full_name">Full Name</label>
                                        <input id="checkout-full_name" autocomplete="name" required :aria-invalid="!!errors.full_name" v-model="form.full_name" type="text" placeholder="Your legal name" class="w-full bg-background border border-white/10 px-5 py-3.5 text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary outline-none transition-colors" />
                                        <p v-if="errors.full_name" class="text-sm text-red-400 mt-2">{{ errors.full_name[0] }}</p>
                                    </div>
                                    <div>
                                        <label class="text-[10px] tracking-widest text-on-surface-variant mb-2 block" for="checkout-email">Email</label>
                                        <input id="checkout-email" autocomplete="email" required :aria-invalid="!!errors.email" v-model="form.email" type="email" placeholder="you@email.com" class="w-full bg-background border border-white/10 px-5 py-3.5 text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary outline-none transition-colors" />
                                        <p v-if="errors.email" class="text-sm text-red-400 mt-2">{{ errors.email[0] }}</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label class="text-[10px] tracking-widest text-on-surface-variant mb-2 block" for="checkout-address">Address</label>
                                        <input id="checkout-address" autocomplete="street-address" required :aria-invalid="!!errors.address" v-model="form.address" type="text" placeholder="House & Street" class="w-full bg-background border border-white/10 px-5 py-3.5 text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary outline-none transition-colors" />
                                        <p v-if="errors.address" class="text-sm text-red-400 mt-2">{{ errors.address[0] }}</p>
                                    </div>
                                    <div>
                                        <label class="text-[10px] tracking-widest text-on-surface-variant mb-2 block" for="checkout-city">City</label>
                                        <input id="checkout-city" autocomplete="address-level2" required :aria-invalid="!!errors.city" v-model="form.city" type="text" placeholder="Nairobi" class="w-full bg-background border border-white/10 px-5 py-3.5 text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary outline-none transition-colors" />
                                        <p v-if="errors.city" class="text-sm text-red-400 mt-2">{{ errors.city[0] }}</p>
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
                                            <label :for="`checkout-${f}`" class="text-[10px] tracking-widest text-on-surface-variant mb-1.5 block">{{ f.replace('_cm','') }} (cm)</label>
                                            <input :id="`checkout-${f}`" v-model="form[f]" type="number" class="w-full bg-background border border-white/10 px-4 py-3 text-sm text-on-surface focus:border-primary outline-none transition-colors" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2 · Pay with M-PESA -->
                        <div class="bg-surface-container border border-[#4fb33f]/25 p-7 md:p-8">
                            <div class="flex items-center gap-3 mb-6">
                                <span class="w-7 h-7 flex items-center justify-center bg-[#4fb33f]/15 text-[#276b1d] dark:text-[#4fb33f] text-xs font-black">2</span>
                                <h2 class="tracking-wider text-xs font-bold text-on-surface">Pay with M-PESA</h2>
                                <span class="ml-auto w-2 h-2 rounded-full bg-[#4fb33f] animate-pulse"></span>
                            </div>

                            <!-- M-PESA number -->
                            <label class="text-[10px] tracking-widest text-on-surface-variant mb-2 block" for="checkout-phone">M-PESA Number</label>
                            <div class="flex">
                                <span class="flex items-center px-4 bg-[#4fb33f]/12 border border-r-0 border-[#4fb33f]/30 text-[#276b1d] dark:text-[#4fb33f] font-mono font-bold text-sm">+254</span>
                                <input id="checkout-phone" autocomplete="tel" required :aria-invalid="!!errors.phone" v-model="form.phone" type="tel" inputmode="numeric" placeholder="7XX XXX XXX" class="flex-1 min-w-0 bg-background border border-white/10 px-5 py-3.5 text-on-surface font-mono placeholder:text-on-surface-variant/50 focus:border-[#4fb33f] outline-none transition-colors" />
                            </div>
                            <p v-if="errors.phone" class="text-sm text-red-400 mt-2">{{ errors.phone[0] }}</p>

                            <!-- How it works -->
                            <div class="flex items-center gap-1.5 mt-6 mb-6">
                                <template v-for="(s, i) in ['Confirm order', 'Get STK prompt', 'Enter your PIN']" :key="s">
                                    <div class="flex items-center gap-2 flex-1">
                                        <span class="w-5 h-5 flex-shrink-0 flex items-center justify-center rounded-full bg-[#4fb33f]/15 text-[#276b1d] dark:text-[#4fb33f] text-[10px] font-black">{{ i + 1 }}</span>
                                        <span class="text-[10px] text-on-surface-variant leading-tight">{{ s }}</span>
                                    </div>
                                    <span v-if="i < 2" class="material-symbols-outlined text-on-surface-variant/30 text-sm">chevron_right</span>
                                </template>
                            </div>

                            <p class="text-sm text-on-surface-variant mb-5">You’ll confirm the final total, then approve it with your M-Pesa PIN on your phone.</p>
                            <!-- Amount mismatch: no pay action until support has reviewed it -->
                            <div v-if="paymentBlocked" class="flex flex-col gap-3 sm:flex-row">
                                <Link :href="route('track', { ref: orderReference })" class="action-primary flex-1">Track This Order</Link>
                                <Link :href="route('contact')" class="action-secondary flex-1">Contact Support</Link>
                            </div>
                            <!-- Pay button -->
                            <button
                                v-else
                                @click="handlePayment"
                                :disabled="isProcessing"
                                class="action-primary w-full py-4 text-base">
                                <template v-if="!isProcessing">
                                    <span class="material-symbols-outlined" aria-hidden="true">lock</span>
                                    {{ orderId ? 'Resume Payment' : `Pay ${formatAmount(payTotal)} with M-Pesa` }}
                                </template>
                                <template v-else>
                                    <span class="material-symbols-outlined animate-spin">progress_activity</span>
                                    Creating your order…
                                </template>
                            </button>

                            <p class="text-center text-[10px] text-on-surface-variant mt-4 flex items-center justify-center gap-1.5">
                                <span class="material-symbols-outlined text-xs text-[#276b1d] dark:text-[#4fb33f]">shield</span>
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
                            @click="!stkSent && closePaymentModal()"
                        ></div>

                        <!-- Dialog -->
                        <div
                            class="pay-modal-card relative z-10 w-full max-h-[92vh] overflow-y-auto no-scrollbar"
                            :class="isSuccess ? 'max-w-2xl' : 'max-w-md'"
                        >
                            <!-- Close -->
                            <button
                                @click="closePaymentModal"
                                class="absolute top-4 right-4 z-50 w-11 h-11 rounded-full flex items-center justify-center bg-black/40 text-white/70 hover:text-white hover:bg-black/70 backdrop-blur-md transition-colors"
                                aria-label="Close"
                            >
                                <span class="material-symbols-outlined text-lg" aria-hidden="true">close</span>
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

                                </div>
                            </div>

                            <MpesaStkPanel
                                v-else-if="!isPaid"
                                :amount="orderAmount ?? cart.totalPrice"
                                :reference="orderReference"
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