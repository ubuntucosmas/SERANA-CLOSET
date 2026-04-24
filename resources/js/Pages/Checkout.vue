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
const summaryExpanded = ref(false);
let pollingInterval = null;

const form = ref({
    email: '', full_name: '', address: '', city: '', phone: '',
    payment_method: 'mpesa',
    height_cm: '', bust_cm: '', waist_cm: '', hips_cm: '',
});

async function handlePayment() {
    isProcessing.value = true;
    try {
        const payload = { ...form.value, items: cart.items, total: cart.totalPrice };
        ['height_cm','bust_cm','waist_cm','hips_cm'].forEach(k => { if (!payload[k]) delete payload[k]; });
        const res = await axios.post(route('checkout.process'), payload);
        if (res.data.success) {
            orderId.value = res.data.order_id || Date.now();
            if (form.value.payment_method === 'mpesa') { isWaitingForStk.value = true; step.value = 4; }
            else { isSuccess.value = true; cart.clearBag(); }
            window.dispatchEvent(new CustomEvent('serana-toast', { detail: { message: 'Order created!', type: 'success' } }));
        }
    } catch (err) {
        const data = err.response?.data;
        if (data?.errors) alert('Check form:\n' + Object.values(data.errors).flat().join('\n'));
        else alert(data?.message || 'Order failed. Please try again.');
    } finally { isProcessing.value = false; }
}

async function initiateMpesaStk(data) {
    isProcessing.value = true;
    try {
        const res = await axios.post(route('mpesa.stk'), { order_id: orderId.value, phone: data.phone, amount: data.amount });
        if (res.data.success) {
            window.dispatchEvent(new CustomEvent('serana-toast', { detail: { message: 'Check your phone!', type: 'info' } }));
            isWaitingForStk.value = true;
            startPolling();
        }
    } catch (err) { alert('M-Pesa failed. Check your number and try again.'); }
    finally { isProcessing.value = false; }
}

function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    let attempts = 0;
    pollingInterval = setInterval(async () => {
        if (++attempts > 12) { clearInterval(pollingInterval); isWaitingForStk.value = false; return; }
        try {
            const res = await axios.get(route('mpesa.status', orderId.value));
            if (res.data.is_paid) {
                clearInterval(pollingInterval);
                isPaid.value = isSuccess.value = true;
                isWaitingForStk.value = false;
                paidAmount.value = frozenTotal.value = cart.totalPrice;
                frozenItems.value = [...cart.items];
                cart.clearBag();
                window.dispatchEvent(new CustomEvent('serana-toast', { detail: { message: 'Payment confirmed!', type: 'success' } }));
            }
        } catch {}
    }, 5000);
}

const steps = ['Contact', 'Shipping', 'Payment'];
</script>

<template>
    <StorefrontLayout>
        <Head title="Checkout — Serana Atelier" />

        <!-- STK -->
        <div v-if="step === 4 && !isPaid" class="min-h-screen pt-20 pb-32 px-4 max-w-lg mx-auto bg-background">
            <button @click="step = 3; isWaitingForStk = false" class="flex items-center gap-2 text-[11px] uppercase tracking-widest text-on-surface-variant hover:text-primary transition-colors font-bold mb-8 mt-4">
                <span class="material-symbols-outlined text-sm">arrow_back</span> Back
            </button>
            <MpesaStkPanel :amount="cart.totalPrice" :phone="form.phone" :isProcessing="isProcessing" :isWaiting="isWaitingForStk"
                @pay="initiateMpesaStk" @cancel="step = 3; isWaitingForStk = false" />
        </div>

        <!-- M-Pesa Success -->
        <div v-else-if="isSuccess && form.payment_method === 'mpesa'" class="min-h-screen pt-20 pb-12 px-4 max-w-lg mx-auto bg-background">
            <MpesaSuccessPanel :amount="paidAmount" :orderId="orderId" />
        </div>

        <!-- Other Success -->
        <div v-else-if="isSuccess" class="min-h-screen bg-background flex flex-col items-center justify-center px-6 text-center space-y-6">
            <div class="w-16 h-16 border border-primary/30 bg-primary/5 flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-3xl" style="font-variation-settings:'FILL' 1">check</span>
            </div>
            <h1 class="font-headline text-3xl font-light text-on-surface">Order Received</h1>
            <p class="text-on-surface-variant text-sm max-w-xs">Order #{{ String(orderId).slice(-6) }} is created. Complete payment to begin production.</p>
            <a :href="whatsappUrl" target="_blank" class="bg-primary text-black py-4 px-8 text-[11px] uppercase tracking-widest font-black hover:opacity-90 transition-all">
                Pay via WhatsApp
            </a>
        </div>

        <!-- ═══════════════ MAIN CHECKOUT ═══════════════ -->
        <main v-else class="bg-background min-h-screen pt-20 pb-[200px] lg:pb-16">
            <div class="max-w-screen-xl mx-auto px-4 sm:px-8 lg:px-12">

                <!-- PAGE TITLE -->
                <div class="pt-4 pb-5 lg:pt-10 lg:pb-12">
                    <Link :href="route('shop')" class="flex items-center gap-2 text-[10px] uppercase tracking-widest text-on-surface-variant hover:text-primary transition-colors font-bold mb-4">
                        <span class="material-symbols-outlined text-sm">arrow_back</span> Collection
                    </Link>
                    <h1 class="font-headline text-3xl lg:text-5xl font-light tracking-tight text-on-surface">Secure Checkout</h1>
                </div>

                <!-- PROGRESS BAR -->
                <div class="flex items-center mb-5 lg:mb-12">
                    <template v-for="(label, i) in steps" :key="i">
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-8 h-8 lg:w-9 lg:h-9 flex items-center justify-center border text-[10px] font-black transition-all duration-300 relative"
                                :class="step > i+1 ? 'border-primary bg-primary text-black'
                                      : step === i+1 ? 'border-primary text-primary bg-primary/10'
                                      : 'border-outline-variant text-on-surface-variant/50'">
                                <span v-if="step > i+1" class="material-symbols-outlined text-sm" style="font-variation-settings:'FILL' 1">check</span>
                                <span v-else>{{ String(i+1).padStart(2,'0') }}</span>
                            </div>
                            <span class="text-[9px] uppercase tracking-widest font-bold hidden sm:block transition-colors"
                                :class="step >= i+1 ? 'text-primary' : 'text-on-surface-variant/30'">{{ label }}</span>
                        </div>
                        <div v-if="i < 2" class="flex-1 h-px mx-2 transition-colors duration-500"
                            :class="step > i+1 ? 'bg-primary' : 'bg-outline-variant/30'"></div>
                    </template>
                </div>

                <!-- GRID -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-16 items-start">

                    <!-- LEFT COLUMN -->
                    <div class="lg:col-span-7 space-y-3 lg:space-y-6">

                        <!-- ▌STEP 1: Contact ▐ -->
                        <div v-if="step === 1" class="animate-fade-up space-y-3">
                            <!-- Card -->
                            <div class="bg-surface-container border border-outline-variant/30 p-4 lg:p-8 space-y-4">
                                <div class="flex items-center gap-3 pb-3 border-b border-outline-variant/20">
                                    <div class="w-7 h-7 flex items-center justify-center border border-primary/40 text-[10px] font-black text-primary">01</div>
                                    <h2 class="font-headline text-base lg:text-lg font-semibold uppercase tracking-wide text-on-surface">Contact</h2>
                                </div>

                                <div class="space-y-4">
                                    <div class="space-y-1.5">
                                        <label class="text-[10px] uppercase tracking-widest font-bold text-primary">Full Name</label>
                                        <input v-model="form.full_name" type="text" autocomplete="name"
                                            placeholder="Your full name"
                                            class="w-full bg-background border border-outline-variant/50 focus:border-primary py-3 px-3 text-on-surface text-sm focus:outline-none transition-all placeholder:text-on-surface-variant/30" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-[10px] uppercase tracking-widest font-bold text-primary">Email Address</label>
                                        <input v-model="form.email" type="email" inputmode="email" autocomplete="email"
                                            placeholder="you@email.com"
                                            class="w-full bg-background border border-outline-variant/50 focus:border-primary py-3 px-3 text-on-surface text-sm focus:outline-none transition-all placeholder:text-on-surface-variant/30" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="text-[10px] uppercase tracking-widest font-bold text-primary">Phone Number</label>
                                        <div class="flex gap-2">
                                            <div class="flex items-center justify-center border border-outline-variant/50 px-3 text-on-surface-variant text-sm font-bold bg-surface-container flex-shrink-0">+254</div>
                                            <input v-model="form.phone" type="tel" inputmode="tel" autocomplete="tel"
                                                placeholder="7XX XXX XXX"
                                                class="flex-1 bg-background border border-outline-variant/50 focus:border-primary py-3 px-3 text-on-surface text-sm focus:outline-none transition-all placeholder:text-on-surface-variant/30" />
                                        </div>
                                        <p class="text-[10px] text-on-surface-variant/50">Used for M-Pesa payment and delivery updates.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ▌STEP 2: Shipping ▐ -->
                        <div v-if="step === 2" class="animate-fade-up space-y-3">
                            <div class="bg-surface-container border border-outline-variant/30 p-4 lg:p-8 space-y-4">
                                <div class="flex items-center gap-3 pb-4 border-b border-outline-variant/20">
                                    <div class="w-7 h-7 flex items-center justify-center border border-primary/40 text-[10px] font-black text-primary">02</div>
                                    <h2 class="font-headline text-base lg:text-lg font-semibold uppercase tracking-wide text-on-surface">Delivery Address</h2>
                                </div>

                                <div class="space-y-5">
                                    <div class="space-y-1.5">
                                        <label class="text-[10px] uppercase tracking-widest font-bold text-primary">Street Address</label>
                                        <input v-model="form.address" type="text" autocomplete="street-address"
                                            placeholder="Street, apartment, estate..."
                                            class="w-full bg-background border border-outline-variant/50 focus:border-primary py-3.5 px-4 text-on-surface text-sm focus:outline-none transition-all placeholder:text-on-surface-variant/30" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] uppercase tracking-widest font-bold text-primary">City</label>
                                            <input v-model="form.city" type="text" autocomplete="address-level2"
                                                placeholder="Nairobi"
                                                class="w-full bg-background border border-outline-variant/50 focus:border-primary py-3.5 px-4 text-on-surface text-sm focus:outline-none transition-all placeholder:text-on-surface-variant/30" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-[10px] uppercase tracking-widest font-bold text-primary">Country</label>
                                            <select v-model="form.country"
                                                class="w-full bg-background border border-outline-variant/50 focus:border-primary py-3.5 px-4 text-on-surface text-sm focus:outline-none transition-all appearance-none">
                                                <option class="bg-surface">Kenya</option>
                                                <option class="bg-surface">UK</option>
                                                <option class="bg-surface">France</option>
                                                <option class="bg-surface">USA</option>
                                                <option class="bg-surface">UAE</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Sizing card (collapsed by default) -->
                            <details class="bg-surface-container border border-outline-variant/20 group">
                                <summary class="flex items-center justify-between px-6 py-4 cursor-pointer list-none select-none">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-primary text-sm">straighten</span>
                                        <span class="text-[11px] font-bold uppercase tracking-widest text-on-surface">Precision Sizing</span>
                                        <span class="text-[10px] text-primary font-bold">(Optional)</span>
                                    </div>
                                    <span class="material-symbols-outlined text-on-surface-variant text-sm group-open:rotate-180 transition-transform duration-300">expand_more</span>
                                </summary>
                                <div class="px-6 pb-6 pt-2 border-t border-outline-variant/20 grid grid-cols-2 gap-4">
                                    <div v-for="f in [{k:'height_cm',p:'168'},{k:'bust_cm',p:'90'},{k:'waist_cm',p:'74'},{k:'hips_cm',p:'96'}]" :key="f.k" class="space-y-1.5">
                                        <label class="text-[10px] uppercase tracking-widest font-bold text-primary">{{ f.k.replace('_cm',' (cm)') }}</label>
                                        <input v-model="form[f.k]" type="number" inputmode="numeric" :placeholder="f.p"
                                            class="w-full bg-background border border-outline-variant/50 focus:border-primary py-3 px-3 text-on-surface text-sm focus:outline-none transition-all placeholder:text-on-surface-variant/30" />
                                    </div>
                                </div>
                            </details>
                        </div>

                        <!-- ▌STEP 3: Payment ▐ -->
                        <div v-if="step === 3" class="animate-fade-up space-y-3">
                            <div class="bg-surface-container border border-outline-variant/30 p-4 lg:p-8 space-y-3">
                                <div class="flex items-center gap-3 pb-4 border-b border-outline-variant/20">
                                    <div class="w-7 h-7 flex items-center justify-center border border-primary/40 text-[10px] font-black text-primary">03</div>
                                    <h2 class="font-headline text-base lg:text-lg font-semibold uppercase tracking-wide text-on-surface">Payment Method</h2>
                                </div>

                                <!-- Payment options -->
                                <div class="space-y-2">
                                    <div v-for="opt in [
                                        {id:'mpesa', label:'M-Pesa', sub:'Kenya Mobile Money', badge:'M-PESA', badgeColor:'bg-[#4DB53C]'},
                                        {id:'card', label:'Card', sub:'Visa or Mastercard', icon:'credit_card'},
                                        {id:'paypal', label:'PayPal Express', sub:'Fast checkout', icon:'account_balance_wallet'},
                                    ]" :key="opt.id"
                                        @click="form.payment_method = opt.id"
                                        class="flex items-center justify-between p-4 border cursor-pointer transition-all active:scale-[0.99]"
                                        :class="form.payment_method === opt.id ? 'border-primary bg-primary/5' : 'border-outline-variant/40 hover:border-primary/30'">
                                        <div class="flex items-center gap-4">
                                            <!-- Radio dot -->
                                            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-all"
                                                :class="form.payment_method === opt.id ? 'border-primary' : 'border-outline-variant'">
                                                <div v-if="form.payment_method === opt.id" class="w-2.5 h-2.5 rounded-full bg-primary"></div>
                                            </div>
                                            <div>
                                                <p class="text-on-surface font-bold text-sm leading-tight">{{ opt.label }}</p>
                                                <p class="text-on-surface-variant text-[11px] mt-0.5">{{ opt.sub }}</p>
                                            </div>
                                        </div>
                                        <div v-if="opt.badge" :class="opt.badgeColor" class="w-12 h-7 flex items-center justify-center text-[7px] font-black text-white italic flex-shrink-0">{{ opt.badge }}</div>
                                        <span v-else class="material-symbols-outlined text-on-surface-variant text-xl flex-shrink-0">{{ opt.icon }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop CTA -->
                            <div class="hidden lg:block">
                                <button @click="handlePayment" :disabled="isProcessing"
                                    class="w-full flex items-center justify-center gap-3 bg-primary text-black py-5 text-[11px] uppercase tracking-widest font-black hover:opacity-90 active:scale-[0.99] transition-all disabled:opacity-40">
                                    <span v-if="isProcessing" class="flex items-center gap-2">
                                        <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                                        Processing…
                                    </span>
                                    <span v-else class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm">lock</span>
                                        Place Order — {{ formatAmount(cart.totalPrice, page.props) }}
                                    </span>
                                </button>
                                <p class="text-center text-[10px] text-on-surface-variant/40 italic mt-3">
                                    By continuing you agree to our <a href="#" class="underline underline-offset-4 hover:text-primary">Terms of Service</a>.
                                </p>
                            </div>
                        </div>

                        <!-- Trust signals -->
                        <div class="flex flex-wrap gap-5 pt-6 border-t border-outline-variant/15">
                            <div v-for="t in [{icon:'verified_user',text:'SSL Encrypted'},{icon:'local_shipping',text:'Priority Delivery'},{icon:'policy',text:'30-Day Guarantee'}]" :key="t.icon"
                                class="flex items-center gap-2 text-[10px] uppercase tracking-widest font-bold text-on-surface-variant">
                                <span class="material-symbols-outlined text-sm text-primary/50">{{ t.icon }}</span>{{ t.text }}
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Desktop Summary -->
                    <aside class="hidden lg:block lg:col-span-5 lg:sticky lg:top-28 space-y-4">
                        <div class="bg-surface-container border border-outline-variant/30 p-8 space-y-6">
                            <h3 class="text-[11px] uppercase tracking-[0.3em] font-black text-on-surface pb-5 border-b border-outline-variant/20">Order Summary</h3>
                            <div class="space-y-4 max-h-60 overflow-y-auto no-scrollbar">
                                <div v-for="item in (isSuccess ? frozenItems : cart.items)" :key="item.id" class="flex gap-4 items-center">
                                    <div class="w-14 h-20 bg-surface border border-outline-variant/20 overflow-hidden flex-shrink-0">
                                        <img :src="item.image" :alt="item.name" class="w-full h-full object-cover" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-on-surface font-semibold text-sm truncate">{{ item.name }}</p>
                                        <p class="text-primary text-[10px] uppercase tracking-widest font-bold mt-0.5">Qty {{ item.quantity }}</p>
                                        <p class="text-on-surface font-bold text-sm mt-1">{{ formatAmount(item.price * item.quantity, page.props) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3 pt-5 border-t border-outline-variant/20 text-sm">
                                <div class="flex justify-between"><span class="text-on-surface-variant">Subtotal</span><span class="font-semibold text-on-surface">{{ formatAmount(isSuccess ? frozenTotal : cart.totalPrice, page.props) }}</span></div>
                                <div class="flex justify-between"><span class="text-on-surface-variant">Shipping</span><span class="text-primary font-semibold">Complimentary</span></div>
                                <div class="flex justify-between items-baseline pt-4 border-t border-outline-variant/10">
                                    <span class="font-headline uppercase tracking-wide text-on-surface font-light">Total</span>
                                    <span class="font-headline text-2xl font-black text-primary">{{ formatAmount(isSuccess ? frozenTotal : cart.totalPrice, page.props) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="border border-outline-variant/30 p-5 flex items-center gap-4 bg-surface-container">
                            <span class="material-symbols-outlined text-primary text-xl flex-shrink-0">support_agent</span>
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-widest text-on-surface">Need Help?</p>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">Chat with a Serana stylist.</p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>

            <!-- ████ MOBILE ORDER SUMMARY PILL ████ -->
            <div class="lg:hidden fixed top-[56px] left-0 right-0 z-40">
                <button @click="summaryExpanded = !summaryExpanded"
                    class="w-full flex items-center justify-between px-4 py-3.5 bg-surface-container border-b border-outline-variant/30">
                    <div class="flex items-center gap-2.5">
                        <span class="material-symbols-outlined text-primary text-base">receipt_long</span>
                        <span class="text-[11px] font-black uppercase tracking-widest text-on-surface">Your Order</span>
                        <span class="text-[11px] font-black text-primary">· {{ formatAmount(isSuccess ? frozenTotal : cart.totalPrice, page.props) }}</span>
                    </div>
                    <span class="material-symbols-outlined text-on-surface-variant text-base transition-transform duration-300"
                        :class="summaryExpanded ? 'rotate-180' : ''">expand_more</span>
                </button>
                <div v-if="summaryExpanded" class="bg-surface-container border-b border-outline-variant/30 px-4 py-4 space-y-3 animate-fade-up shadow-lg">
                    <div v-for="item in (isSuccess ? frozenItems : cart.items)" :key="item.id" class="flex items-center gap-3">
                        <div class="w-10 h-14 bg-surface border border-outline-variant/20 overflow-hidden flex-shrink-0">
                            <img :src="item.image" :alt="item.name" class="w-full h-full object-cover" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-on-surface text-sm font-semibold truncate">{{ item.name }}</p>
                            <p class="text-primary text-[10px] uppercase tracking-widest font-bold">Qty {{ item.quantity }}</p>
                        </div>
                        <span class="text-on-surface font-black text-sm flex-shrink-0">{{ formatAmount(item.price * item.quantity, page.props) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-3 border-t border-outline-variant/20">
                        <span class="text-on-surface-variant text-sm font-medium">Total (Shipping Free)</span>
                        <span class="text-primary font-black text-lg font-headline">{{ formatAmount(isSuccess ? frozenTotal : cart.totalPrice, page.props) }}</span>
                    </div>
                </div>
            </div>

            <!-- ████ MOBILE STICKY BOTTOM CTA ████ -->
            <!-- Uses 'bottom-cta' style which accounts for browser chrome + safe area -->
            <div class="lg:hidden fixed left-0 right-0 z-50 bg-surface-container border-t border-outline-variant/40 px-3 pt-2 bottom-cta">
                <p class="text-[9px] uppercase tracking-widest text-on-surface-variant/60 font-bold mb-1.5 text-center">
                    Step {{ step }}/3 — {{ steps[step-1] }}
                </p>
                <div class="flex gap-2">
                    <button v-if="step > 1" @click="step--"
                        class="w-11 h-12 flex items-center justify-center border border-outline-variant text-on-surface-variant hover:border-primary hover:text-primary transition-all active:scale-95 flex-shrink-0">
                        <span class="material-symbols-outlined text-sm">arrow_back</span>
                    </button>
                    <button v-if="step < 3" @click="step++"
                        class="flex-1 h-12 flex items-center justify-center gap-2 bg-primary text-black text-[11px] uppercase tracking-widest font-black hover:opacity-90 active:scale-[0.98] transition-all">
                        Continue <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </button>
                    <button v-else @click="handlePayment" :disabled="isProcessing"
                        class="flex-1 h-12 flex items-center justify-center gap-2 bg-primary text-black text-[11px] uppercase tracking-widest font-black hover:opacity-90 active:scale-[0.98] transition-all disabled:opacity-40">
                        <template v-if="isProcessing">
                            <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                            Processing…
                        </template>
                        <template v-else>
                            <span class="material-symbols-outlined text-sm">lock</span>
                            Place Order — {{ formatAmount(cart.totalPrice, page.props) }}
                        </template>
                    </button>
                </div>
            </div>
        </main>
    </StorefrontLayout>
</template>

<style scoped>
@keyframes fade-up {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fade-up { animation: fade-up 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.no-scrollbar::-webkit-scrollbar { display: none; }

/**
 * Positions the bottom CTA above phone browser chrome (address bar + nav).
 * env(safe-area-inset-bottom) handles iPhone notch/home indicator.
 * The extra 60px covers Android Chrome bottom toolbar & iOS Safari bar.
 */
.bottom-cta {
    bottom: max(60px, calc(60px + env(safe-area-inset-bottom)));
    padding-bottom: 8px;
}
</style>
