<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import MpesaStkPanel from '@/Components/MpesaStkPanel.vue';
import MpesaSuccessPanel from '@/Components/MpesaSuccessPanel.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { useCartStore } from '@/Stores/useCartStore';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const whatsappUrl = computed(() => `https://wa.me/${page.props.whatsapp_number}?text=Hello%2C%20I've%20just%20placed%20order%20%23${orderId.value}%20and%20I'd%20like%20to%20complete%20payment.`);

const cart = useCartStore();
const { formatAmount } = useCurrency();
const step = ref(1);
const isProcessing = ref(false);
const isWaitingForStk = ref(false);
const isSuccess = ref(false);
const isPaid = ref(false);
const orderId = ref(null);
const paidAmount = ref(0);
const frozenItems = ref([]);
const frozenTotal = ref(0);
const orderSummaryOpen = ref(false);
let pollingInterval = null;

const form = ref({
    email: '',
    full_name: '',
    address: '',
    city: '',
    phone: '',
    payment_method: 'mpesa',
    height_cm: '',
    bust_cm: '',
    waist_cm: '',
    hips_cm: '',
});

async function handlePayment() {
    isProcessing.value = true;
    try {
        const payload = { ...form.value, items: cart.items, total: cart.totalPrice };
        if (!payload.height_cm) delete payload.height_cm;
        if (!payload.bust_cm) delete payload.bust_cm;
        if (!payload.waist_cm) delete payload.waist_cm;
        if (!payload.hips_cm) delete payload.hips_cm;

        const response = await axios.post(route('checkout.process'), payload);
        if (response.data.success) {
            orderId.value = response.data.order_id || Date.now();
            if (form.value.payment_method === 'mpesa') {
                isWaitingForStk.value = true;
                step.value = 4;
            } else {
                isSuccess.value = true;
                cart.clearBag();
            }
            window.dispatchEvent(new CustomEvent('serana-toast', { detail: { message: 'Order created successfully.', type: 'success' } }));
        }
    } catch (err) {
        console.error(err);
        if (err.response?.data?.errors) {
            alert('Please check the form:\n' + Object.values(err.response.data.errors).map(e => e.join(', ')).join('\n'));
        } else {
            alert(err.response?.data?.message || 'Order processing failed.');
        }
    } finally {
        isProcessing.value = false;
    }
}

async function initiateMpesaStk(data) {
    isProcessing.value = true;
    try {
        const response = await axios.post(route('mpesa.stk'), {
            order_id: orderId.value,
            phone: data.phone,
            amount: data.amount
        });
        if (response.data.success) {
            window.dispatchEvent(new CustomEvent('serana-toast', { detail: { message: 'STK Push sent to your phone.', type: 'info' } }));
            isWaitingForStk.value = true;
            startPolling();
        }
    } catch (err) {
        console.error(err);
        alert('Failed to initiate M-Pesa payment. Please check your phone number and try again.');
    } finally {
        isProcessing.value = false;
    }
}

function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    let attempts = 0;
    const maxAttempts = 12;
    pollingInterval = setInterval(async () => {
        attempts++;
        if (attempts > maxAttempts) {
            clearInterval(pollingInterval);
            isWaitingForStk.value = false;
            alert('Payment verification timed out. Contact support with your Order ID.');
            return;
        }
        try {
            const response = await axios.get(route('mpesa.status', orderId.value));
            if (response.data.is_paid) {
                clearInterval(pollingInterval);
                isPaid.value = true;
                isWaitingForStk.value = false;
                isSuccess.value = true;
                paidAmount.value = cart.totalPrice;
                frozenItems.value = [...cart.items];
                frozenTotal.value = cart.totalPrice;
                cart.clearBag();
                window.dispatchEvent(new CustomEvent('serana-toast', { detail: { message: 'Payment Verified! Your order is now in production.', type: 'success' } }));
            }
        } catch (err) { console.error('Polling error:', err); }
    }, 5000);
}

function nextStep() { if (step.value < 3) step.value++; }
function prevStep() { if (step.value > 1) step.value--; }

/* Input / label shared classes */
const inp = "w-full bg-transparent border-b border-outline-variant py-4 px-0 text-on-surface focus:border-primary focus:outline-none transition-all placeholder:text-on-surface-variant/30 placeholder:italic text-base";
const lbl = "block text-[10px] tracking-[0.2em] uppercase text-primary font-bold mb-2";
</script>

<template>
    <StorefrontLayout>
        <Head title="Secure Checkout — Serana Atelier" />

        <!-- ─── STK Phase (full screen on mobile) ─── -->
        <div v-if="step === 4 && !isPaid" class="min-h-screen pt-24 pb-32 px-4 sm:px-8 max-w-2xl mx-auto bg-background">
            <Link :href="route('checkout')" class="flex items-center gap-2 text-[10px] uppercase tracking-widest text-on-surface-variant hover:text-primary transition-colors font-bold mb-8">
                <span class="material-symbols-outlined text-sm">arrow_back</span> Back
            </Link>
            <MpesaStkPanel :amount="cart.totalPrice" :phone="form.phone" :isProcessing="isProcessing" :isWaiting="isWaitingForStk"
                @pay="initiateMpesaStk" @cancel="step = 3; isWaitingForStk = false" />
        </div>

        <!-- ─── Success: M-Pesa ─── -->
        <div v-else-if="isSuccess && form.payment_method === 'mpesa'" class="min-h-screen pt-24 pb-12 px-4 sm:px-8 max-w-2xl mx-auto bg-background">
            <MpesaSuccessPanel :amount="paidAmount" :orderId="orderId" />
        </div>

        <!-- ─── Success: Pending ─── -->
        <div v-else-if="isSuccess" class="min-h-screen pt-24 px-6 bg-background flex flex-col items-center justify-center text-center space-y-6 pb-32">
            <div class="w-16 h-16 border border-primary/30 bg-primary/5 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-3xl" style="font-variation-settings:'FILL' 1">check</span>
            </div>
            <h1 class="font-headline text-3xl font-light text-on-surface">Order Received</h1>
            <p class="text-on-surface-variant text-sm max-w-sm">Order #{{ String(orderId).slice(-6) }} is registered. Complete payment to begin production.</p>
            <a :href="whatsappUrl" target="_blank" class="bg-primary text-black py-4 px-8 text-[10px] uppercase tracking-[0.2em] font-bold hover:opacity-90 transition-all">
                Complete via WhatsApp
            </a>
        </div>

        <!-- ─────────────────────── MAIN FLOW ─────────────────────── -->
        <main v-else class="pt-20 bg-background min-h-screen pb-32 lg:pb-16">

            <!-- ══════ MOBILE ORDER SUMMARY PILL (top of screen) ══════ -->
            <div class="lg:hidden sticky top-[64px] z-40 bg-surface-container border-b border-outline-variant/30 shadow-lg">
                <button @click="orderSummaryOpen = !orderSummaryOpen"
                    class="w-full flex items-center justify-between px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary text-base">shopping_bag</span>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-on-surface">
                            Order Summary
                        </span>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-primary">
                            — {{ formatAmount(isSuccess ? frozenTotal : cart.totalPrice, page.props) }}
                        </span>
                    </div>
                    <span class="material-symbols-outlined text-on-surface-variant transition-transform duration-300"
                        :class="orderSummaryOpen ? 'rotate-180' : ''">expand_more</span>
                </button>

                <!-- Expanded summary -->
                <div v-if="orderSummaryOpen" class="px-5 pb-5 space-y-4 border-t border-outline-variant/20 animate-fade-up">
                    <div v-for="item in (isSuccess ? frozenItems : cart.items)" :key="item.id" class="flex items-center gap-4 py-3">
                        <div class="w-14 h-20 bg-surface overflow-hidden flex-shrink-0 border border-outline-variant/20">
                            <img :src="item.image" :alt="item.name" class="w-full h-full object-cover" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-on-surface text-sm font-semibold truncate">{{ item.name }}</p>
                            <p class="text-primary text-[10px] uppercase tracking-widest font-bold mt-0.5">Qty {{ item.quantity }}</p>
                        </div>
                        <span class="text-on-surface text-sm font-bold flex-shrink-0">{{ formatAmount(item.price * item.quantity, page.props) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-3 border-t border-outline-variant/20">
                        <span class="text-on-surface-variant text-sm">Total</span>
                        <span class="text-primary font-black text-lg font-headline">{{ formatAmount(isSuccess ? frozenTotal : cart.totalPrice, page.props) }}</span>
                    </div>
                </div>
            </div>

            <!-- ══════ INNER GRID ══════ -->
            <div class="max-w-screen-xl mx-auto px-4 sm:px-8 lg:px-12 grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-16 items-start pt-8 lg:pt-16">

                <!-- ─── LEFT: Checkout steps ─── -->
                <div class="lg:col-span-7 space-y-10 lg:space-y-16">

                    <!-- Header (desktop only heading) -->
                    <div class="space-y-3">
                        <Link :href="route('shop')" class="hidden sm:flex items-center gap-2 text-[10px] uppercase tracking-widest text-on-surface-variant hover:text-primary transition-colors font-bold">
                            <span class="material-symbols-outlined text-sm">arrow_back</span> Collection
                        </Link>
                        <h1 class="font-headline text-3xl sm:text-4xl font-light tracking-tight text-on-surface">Secure Checkout</h1>
                    </div>

                    <!-- Step Progress Bar -->
                    <div class="flex items-center">
                        <template v-for="s in 3" :key="s">
                            <button @click="step = s" class="flex flex-col items-center gap-1.5 group focus:outline-none">
                                <div class="w-9 h-9 sm:w-10 sm:h-10 flex items-center justify-center border text-[11px] font-black transition-all duration-300"
                                    :class="step >= s ? 'border-primary text-primary bg-primary/10' : 'border-outline-variant text-on-surface-variant'">
                                    <span v-if="step > s" class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1">check</span>
                                    <span v-else>{{ String(s).padStart(2,'0') }}</span>
                                </div>
                                <span class="hidden sm:block text-[9px] uppercase tracking-widest font-bold transition-colors"
                                    :class="step >= s ? 'text-primary' : 'text-on-surface-variant/40'">
                                    {{ s === 1 ? 'Contact' : s === 2 ? 'Shipping' : 'Payment' }}
                                </span>
                            </button>
                            <div v-if="s < 3" class="flex-1 h-px mx-2 sm:mx-3 mt-0 sm:mt-[-10px] transition-colors duration-300"
                                :class="step > s ? 'bg-primary/60' : 'bg-outline-variant/40'"></div>
                        </template>
                    </div>

                    <!-- ── STEP 1: Contact ── -->
                    <section v-if="step === 1" class="space-y-8 animate-fade-up">
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 flex items-center justify-center border border-outline-variant text-[10px] font-bold text-on-surface-variant">01</span>
                            <h2 class="font-headline text-lg font-light uppercase tracking-wide text-on-surface">Contact Information</h2>
                        </div>

                        <div class="space-y-7">
                            <div class="space-y-1">
                                <label :class="lbl">Email Address</label>
                                <input v-model="form.email" type="email" inputmode="email" placeholder="your@email.com" :class="inp" />
                            </div>
                            <div class="space-y-1">
                                <label :class="lbl">Phone Number</label>
                                <input v-model="form.phone" type="tel" inputmode="tel" placeholder="+254 7XX XXX XXX" :class="inp" />
                            </div>
                            <div class="space-y-1">
                                <label :class="lbl">Full Name</label>
                                <input v-model="form.full_name" type="text" autocomplete="name" placeholder="As on your ID" :class="inp" />
                            </div>
                        </div>

                        <!-- Mobile: next button is just here; desktop: sticky CTA at bottom also works -->
                        <button @click="nextStep" class="hidden lg:flex w-full items-center justify-center gap-2 border border-primary/30 bg-primary/5 hover:bg-primary/10 text-primary py-5 text-[10px] uppercase tracking-[0.3em] font-black transition-all">
                            Continue to Shipping <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </button>
                    </section>

                    <!-- ── STEP 2: Shipping ── -->
                    <section v-if="step === 2" class="space-y-8 animate-fade-up">
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 flex items-center justify-center border border-outline-variant text-[10px] font-bold text-on-surface-variant">02</span>
                            <h2 class="font-headline text-lg font-light uppercase tracking-wide text-on-surface">Delivery Address</h2>
                        </div>

                        <div class="space-y-7">
                            <div class="space-y-1">
                                <label :class="lbl">Street Address</label>
                                <input v-model="form.address" type="text" autocomplete="street-address" placeholder="House, street, apartment..." :class="inp" />
                            </div>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="space-y-1">
                                    <label :class="lbl">City</label>
                                    <input v-model="form.city" type="text" autocomplete="address-level2" placeholder="Nairobi" :class="inp" />
                                </div>
                                <div class="space-y-1">
                                    <label :class="lbl">Country</label>
                                    <select v-model="form.country" class="w-full bg-transparent border-b border-outline-variant py-4 px-0 text-on-surface focus:border-primary focus:outline-none transition-all appearance-none cursor-pointer text-base">
                                        <option class="bg-surface text-on-surface">Kenya</option>
                                        <option class="bg-surface text-on-surface">United Kingdom</option>
                                        <option class="bg-surface text-on-surface">France</option>
                                        <option class="bg-surface text-on-surface">United States</option>
                                        <option class="bg-surface text-on-surface">UAE</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Sizing (collapsible on mobile) -->
                        <details class="group">
                            <summary class="flex items-center gap-3 cursor-pointer list-none select-none py-4 border-t border-outline-variant/20">
                                <span class="material-symbols-outlined text-primary text-sm">straighten</span>
                                <span class="text-[10px] uppercase tracking-widest font-black text-on-surface-variant group-open:text-primary transition-colors">Precision Sizing <span class="text-primary">(Optional)</span></span>
                                <span class="material-symbols-outlined text-on-surface-variant text-sm ml-auto group-open:rotate-180 transition-transform">expand_more</span>
                            </summary>
                            <div class="grid grid-cols-2 gap-6 pt-4">
                                <div v-for="field in ['height_cm','bust_cm','waist_cm','hips_cm']" :key="field" class="space-y-1">
                                    <label :class="lbl">{{ field.replace('_cm','') }} (cm)</label>
                                    <input v-model="form[field]" type="number" inputmode="numeric"
                                        :placeholder="field === 'height_cm' ? '168' : field === 'bust_cm' ? '90' : field === 'waist_cm' ? '74' : '96'"
                                        :class="inp" />
                                </div>
                            </div>
                        </details>

                        <button @click="nextStep" class="hidden lg:flex w-full items-center justify-center gap-2 border border-primary/30 bg-primary/5 hover:bg-primary/10 text-primary py-5 text-[10px] uppercase tracking-[0.3em] font-black transition-all">
                            Continue to Payment <span class="material-symbols-outlined text-sm">arrow_forward</span>
                        </button>
                    </section>

                    <!-- ── STEP 3: Payment ── -->
                    <section v-if="step === 3" class="space-y-8 animate-fade-up">
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 flex items-center justify-center border border-outline-variant text-[10px] font-bold text-on-surface-variant">03</span>
                            <h2 class="font-headline text-lg font-light uppercase tracking-wide text-on-surface">Payment Method</h2>
                        </div>

                        <div class="space-y-3">
                            <!-- M-Pesa -->
                            <label @click="form.payment_method = 'mpesa'"
                                class="flex items-center justify-between p-5 border cursor-pointer transition-all active:scale-[0.98]"
                                :class="form.payment_method === 'mpesa' ? 'border-primary bg-primary/5' : 'border-outline-variant'">
                                <div class="flex items-center gap-4">
                                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                                        :class="form.payment_method === 'mpesa' ? 'border-primary' : 'border-outline-variant'">
                                        <div v-if="form.payment_method === 'mpesa'" class="w-2.5 h-2.5 rounded-full bg-primary"></div>
                                    </div>
                                    <div>
                                        <p class="text-on-surface font-bold text-sm">M-Pesa</p>
                                        <p class="text-on-surface-variant text-[10px]">Kenya Mobile Money</p>
                                    </div>
                                </div>
                                <div class="w-12 h-7 bg-[#4DB53C] flex items-center justify-center text-[7px] font-black text-white italic">M-PESA</div>
                            </label>

                            <!-- Card -->
                            <label @click="form.payment_method = 'card'"
                                class="flex items-center justify-between p-5 border cursor-pointer transition-all active:scale-[0.98]"
                                :class="form.payment_method === 'card' ? 'border-primary bg-primary/5' : 'border-outline-variant'">
                                <div class="flex items-center gap-4">
                                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                                        :class="form.payment_method === 'card' ? 'border-primary' : 'border-outline-variant'">
                                        <div v-if="form.payment_method === 'card'" class="w-2.5 h-2.5 rounded-full bg-primary"></div>
                                    </div>
                                    <div>
                                        <p class="text-on-surface font-bold text-sm">Credit / Debit Card</p>
                                        <p class="text-on-surface-variant text-[10px]">Visa, Mastercard</p>
                                    </div>
                                </div>
                                <span class="material-symbols-outlined text-on-surface-variant">credit_card</span>
                            </label>

                            <!-- PayPal -->
                            <label @click="form.payment_method = 'paypal'"
                                class="flex items-center justify-between p-5 border cursor-pointer transition-all active:scale-[0.98]"
                                :class="form.payment_method === 'paypal' ? 'border-primary bg-primary/5' : 'border-outline-variant'">
                                <div class="flex items-center gap-4">
                                    <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                                        :class="form.payment_method === 'paypal' ? 'border-primary' : 'border-outline-variant'">
                                        <div v-if="form.payment_method === 'paypal'" class="w-2.5 h-2.5 rounded-full bg-primary"></div>
                                    </div>
                                    <div>
                                        <p class="text-on-surface font-bold text-sm">PayPal Express</p>
                                        <p class="text-on-surface-variant text-[10px]">Fast checkout</p>
                                    </div>
                                </div>
                                <span class="material-symbols-outlined text-[#003087]">account_balance_wallet</span>
                            </label>
                        </div>

                        <!-- Desktop CTA -->
                        <button @click="handlePayment" :disabled="isProcessing"
                            class="hidden lg:flex w-full items-center justify-center gap-2 bg-primary text-black py-5 text-[10px] uppercase tracking-[0.3em] font-black transition-all hover:opacity-90 active:scale-[0.98] disabled:opacity-40">
                            <span class="material-symbols-outlined text-sm">lock</span>
                            <span v-if="!isProcessing">Place Order — {{ formatAmount(cart.totalPrice, page.props) }}</span>
                            <span v-else>Processing…</span>
                        </button>

                        <p class="hidden lg:block text-center text-[10px] text-on-surface-variant/50 italic">
                            By placing this order you agree to our <a href="#" class="underline underline-offset-4 hover:text-primary">Terms of Service</a>.
                        </p>
                    </section>

                    <!-- Trust signals -->
                    <div class="flex flex-wrap gap-6 pt-6 border-t border-outline-variant/20">
                        <div v-for="t in [{icon:'verified_user',text:'SSL Secured'},{icon:'local_shipping',text:'Priority Delivery'},{icon:'policy',text:'30-Day Guarantee'}]" :key="t.icon"
                            class="flex items-center gap-2 text-on-surface-variant text-[10px] tracking-widest uppercase font-medium">
                            <span class="material-symbols-outlined text-sm text-primary/50">{{ t.icon }}</span>{{ t.text }}
                        </div>
                    </div>
                </div>

                <!-- ─── RIGHT: Desktop Order Summary ─── -->
                <aside class="hidden lg:block lg:col-span-5 lg:sticky lg:top-32 space-y-5">
                    <div class="bg-surface-container border border-outline-variant/30 p-8 space-y-8">
                        <h3 class="text-[11px] tracking-[0.3em] uppercase font-bold text-on-surface pb-5 border-b border-outline-variant/20">Order Summary</h3>
                        <div class="space-y-5 max-h-64 overflow-y-auto no-scrollbar">
                            <div v-for="item in (isSuccess ? frozenItems : cart.items)" :key="item.id" class="flex gap-4">
                                <div class="w-16 aspect-[3/4] bg-surface overflow-hidden flex-shrink-0 border border-outline-variant/20">
                                    <img :src="item.image" :alt="item.name" class="w-full h-full object-cover" />
                                </div>
                                <div class="flex flex-col justify-between py-0.5">
                                    <div>
                                        <h4 class="font-headline text-sm font-semibold text-on-surface leading-snug">{{ item.name }}</h4>
                                        <p class="text-[10px] text-primary uppercase tracking-widest font-bold mt-0.5">Qty: {{ item.quantity }}</p>
                                    </div>
                                    <p class="text-sm font-bold text-on-surface">{{ formatAmount(item.price * item.quantity, page.props) }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-3 pt-5 border-t border-outline-variant/20">
                            <div class="flex justify-between text-sm">
                                <span class="text-on-surface-variant">Subtotal</span>
                                <span class="font-semibold text-on-surface">{{ formatAmount(isSuccess ? frozenTotal : cart.totalPrice, page.props) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-on-surface-variant">Shipping</span>
                                <span class="text-primary font-semibold">Complimentary</span>
                            </div>
                            <div class="flex justify-between items-baseline pt-4 border-t border-outline-variant/10">
                                <span class="font-headline uppercase text-on-surface tracking-wide font-light">Total</span>
                                <span class="font-headline text-2xl font-bold text-primary">{{ formatAmount(isSuccess ? frozenTotal : cart.totalPrice, page.props) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border border-outline-variant/30 flex items-center gap-5 bg-surface-container hover:border-primary/30 transition-colors">
                        <span class="material-symbols-outlined text-primary text-2xl flex-shrink-0">support_agent</span>
                        <div>
                            <p class="text-[11px] font-bold tracking-widest uppercase text-on-surface">Need Help?</p>
                            <p class="text-[11px] text-on-surface-variant mt-0.5 leading-relaxed">Chat with a Serana stylist.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </main>

        <!-- ██████ MOBILE STICKY BOTTOM CTA ██████ -->
        <div v-if="!isSuccess && step !== 4" class="lg:hidden fixed bottom-0 left-0 right-0 z-50 bg-surface-container border-t border-outline-variant/30 px-4 py-4 space-y-2 safe-bottom">
            <!-- Amount mini bar -->
            <div class="flex justify-between items-center text-[11px] text-on-surface-variant uppercase tracking-widest font-bold px-1">
                <span>{{ step === 1 ? 'Step 1 — Contact' : step === 2 ? 'Step 2 — Shipping' : 'Step 3 — Payment' }}</span>
                <span class="text-primary">{{ formatAmount(cart.totalPrice, page.props) }}</span>
            </div>

            <!-- Navigation buttons -->
            <div class="flex gap-3">
                <button v-if="step > 1" @click="prevStep"
                    class="w-14 flex items-center justify-center border border-outline-variant text-on-surface-variant py-4 hover:border-primary transition-all active:scale-95">
                    <span class="material-symbols-outlined text-sm">arrow_back</span>
                </button>
                <!-- Continue / Place Order -->
                <button v-if="step < 3" @click="nextStep"
                    class="flex-1 flex items-center justify-center gap-2 bg-primary text-black py-4 text-[11px] uppercase tracking-widest font-black hover:opacity-90 active:scale-[0.98] transition-all">
                    Continue <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </button>
                <button v-else @click="handlePayment" :disabled="isProcessing"
                    class="flex-1 flex items-center justify-center gap-2 bg-primary text-black py-4 text-[11px] uppercase tracking-widest font-black hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-40">
                    <span class="material-symbols-outlined text-sm">lock</span>
                    <span v-if="!isProcessing">Place Order</span>
                    <span v-else class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                        </svg>
                        Processing…
                    </span>
                </button>
            </div>
        </div>
    </StorefrontLayout>
</template>

<style scoped>
@keyframes fade-up {
    from { opacity: 0; transform: translateY(12px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fade-up { animation: fade-up 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.safe-bottom { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
</style>
