<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import SizingIntelligence from '@/Components/SizingIntelligence.vue';
import MpesaStkPanel from '@/Components/MpesaStkPanel.vue';
import MpesaSuccessPanel from '@/Components/MpesaSuccessPanel.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { useCartStore } from '@/Stores/useCartStore';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const showMobileSummary = ref(false);
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
const safaricomTransactionId = ref(null);
const frozenItems = ref([]);
const frozenTotal = ref(0);
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
                safaricomTransactionId.value = response.data.transaction_id;
                frozenItems.value = [...cart.items];
                frozenTotal.value = cart.totalPrice;
                cart.clearBag();
                window.dispatchEvent(new CustomEvent('serana-toast', { detail: { message: 'Payment Verified! Your order is now in production.', type: 'success' } }));
            }
        } catch (err) { console.error('Polling error:', err); }
    }, 5000);
}

const stepTheme = computed(() => {
    switch (step.value) {
        case 1: return { 
            color: 'text-blue-800', 
            border: 'border-blue-900/50', 
            bg: 'bg-blue-900', 
            bgAlpha: 'bg-blue-900/10', 
            glow: 'shadow-[0_0_20px_rgba(30,58,138,0.2)]',
            mobileCard: 'bg-white/5',
            mobileBorder: 'border-blue-900/60',
            onMobile: 'text-on-surface'
        };
        case 2: return { 
            color: 'text-emerald-800', 
            border: 'border-emerald-900/50', 
            bg: 'bg-emerald-900', 
            bgAlpha: 'bg-emerald-900/10', 
            glow: 'shadow-[0_0_20px_rgba(6,78,59,0.2)]',
            mobileCard: 'bg-white/5',
            mobileBorder: 'border-emerald-900/60',
            onMobile: 'text-on-surface'
        };
        case 3: return { 
            color: 'text-rose-800', 
            border: 'border-rose-900/50', 
            bg: 'bg-rose-900', 
            bgAlpha: 'bg-rose-900/10', 
            glow: 'shadow-[0_0_20px_rgba(136,19,55,0.2)]',
            mobileCard: 'bg-white/5',
            mobileBorder: 'border-rose-900/60',
            onMobile: 'text-on-surface'
        };
        default: return { 
            color: 'text-primary', 
            border: 'border-primary/50', 
            bg: 'bg-primary', 
            bgAlpha: 'bg-primary/10', 
            glow: 'shadow-[0_0_20px_rgba(57,255,20,0.15)]',
            mobileCard: 'bg-white/5',
            mobileBorder: 'border-primary/60',
            onMobile: 'text-on-surface'
        };
    }
});

function nextStep() { if (step.value < 3) step.value++; }
</script>

<template>
    <StorefrontLayout hideMobileCard>
        <Head title="Secure Checkout — Serana" />

        <main class="relative pt-24 md:pt-32 pb-12 md:pb-24 px-4 md:px-12 max-w-screen-xl mx-auto min-h-screen bg-background">

            <!-- Checkout DNA Background -->
            <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden" aria-hidden="true">
                <!-- Background image -->
                <img src="/images/checkout_bg.png" alt="" class="absolute inset-0 w-full h-full object-cover opacity-[0.15]" />
                <!-- Dark overlay so content stays readable -->
                <div class="absolute inset-0 bg-background/60"></div>
                <!-- Ambient glow orbs (Dynamic Color) -->
                <div :class="['absolute top-[-10%] left-[-5%] w-[40vw] h-[40vw] rounded-full blur-[120px] animate-orb-1 transition-all duration-1000', stepTheme.bgAlpha]"></div>
                <div :class="['absolute bottom-[-10%] right-[-5%] w-[35vw] h-[35vw] rounded-full blur-[100px] animate-orb-2 transition-all duration-1000', stepTheme.bgAlpha]"></div>
            </div>

            <!-- M-Pesa STK Phase -->
            <div v-if="step === 4 && !isPaid" class="max-w-2xl mx-auto">
                <div class="mb-10">
                    <Link :href="route('checkout')" class="text-[10px] uppercase tracking-[0.2em] text-on-surface-variant hover:text-primary transition-colors flex items-center gap-2 font-bold">
                        <span class="material-symbols-outlined text-sm">arrow_back</span> Return to Checkout
                    </Link>
                </div>
                <MpesaStkPanel
                    :amount="cart.totalPrice"
                    :phone="form.phone"
                    :isProcessing="isProcessing"
                    :isWaiting="isWaitingForStk"
                    @pay="initiateMpesaStk"
                    @cancel="step = 3; isWaitingForStk = false"
                />
            </div>

            <!-- M-Pesa Success -->
            <div v-else-if="isSuccess && form.payment_method === 'mpesa'" class="max-w-2xl mx-auto">
                <MpesaSuccessPanel 
                    :amount="paidAmount" 
                    :orderId="orderId" 
                    :items="frozenItems" 
                    :transactionId="safaricomTransactionId" 
                />
            </div>

            <!-- Non-Mpesa Success (Pending Payment) -->
            <div v-else-if="isSuccess && form.payment_method !== 'mpesa'" class="max-w-2xl mx-auto text-center py-24 space-y-8">
                <div class="inline-flex items-center justify-center w-16 h-16 border border-primary/30 bg-primary/5 mb-4">
                    <span class="material-symbols-outlined text-primary text-3xl" style="font-variation-settings:'FILL' 1">check</span>
                </div>
                <h1 class="font-headline text-4xl font-light text-on-surface tracking-tight">Order Received</h1>
                <p class="text-on-surface-variant text-sm tracking-wide">Order #{{ String(orderId).slice(-6) }} is registered. Complete payment to begin production.</p>
                <a :href="whatsappUrl" target="_blank" class="inline-block bg-primary text-black py-4 px-10 text-[10px] uppercase tracking-[0.2em] font-bold hover:opacity-90 transition-all">
                    Complete via WhatsApp
                </a>
            </div>

            <!-- Main Checkout Flow -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-8 md:gap-16 items-start">
                
                <!-- On mobile, we wrap everything in a floating glass card -->
                <div class="lg:col-span-7 block md:hidden">
                    <div :class="[
                        'p-6 space-y-10 relative z-10 transition-all duration-1000 backdrop-blur-xl border-2',
                        stepTheme.mobileCard,
                        stepTheme.mobileBorder,
                        stepTheme.glow
                    ]">
                        <!-- We will repeat the step content here but condensed for mobile -->
                        <!-- Header -->
                        <header class="space-y-2">
                            <Link :href="route('shop')" :class="['text-[8px] uppercase tracking-[0.2em] font-bold flex items-center gap-1 transition-colors', stepTheme.onMobile === 'text-white' ? 'text-white/60 hover:text-white' : 'text-on-surface-variant hover:text-primary']">
                                <span class="material-symbols-outlined text-[10px]">arrow_back</span> Collection
                            </Link>
                            <h1 :class="['font-headline text-2xl font-light tracking-tight', stepTheme.onMobile]">Secure Checkout</h1>
                            <p :class="['text-[9px] tracking-[0.15em] uppercase font-bold opacity-60', stepTheme.onMobile]">
                                {{ step === 1 ? 'Contact' : step === 2 ? 'Shipping' : 'Payment' }} — Step {{ step }}/3
                            </p>
                        </header>

                        <!-- Step Indicator (Condensed) -->
                        <div class="flex items-center gap-2 mb-8">
                            <template v-for="s in 3" :key="s">
                                <div class="flex items-center gap-1 cursor-pointer" @click="step = s">
                                    <div :class="[
                                        'w-6 h-6 flex items-center justify-center border text-[9px] font-bold transition-all duration-700',
                                        step === s ? `${stepTheme.border} ${stepTheme.color} ${stepTheme.bgAlpha}` : 
                                        step > s ? 'border-primary text-primary opacity-40' : 'border-outline-variant text-on-surface-variant'
                                    ]">
                                        {{ s }}
                                    </div>
                                </div>
                                <div v-if="s < 3" :class="[
                                    'flex-1 h-[1px] transition-all duration-700',
                                    step > s ? 'bg-primary/50' : 'bg-outline-variant/50'
                                ]"></div>
                            </template>
                        </div>

                        <div class="dense-steps">
                             <!-- Order Summary Toggle for Mobile -->
                             <div :class="['mb-6 p-4 border transition-all duration-700', stepTheme.onMobile === 'text-white' ? 'bg-white/10 border-white/20' : 'bg-primary/5 border-primary/20']">
                                <div class="flex justify-between items-center" @click="showMobileSummary = !showMobileSummary">
                                    <span :class="['text-[9px] uppercase tracking-widest font-bold', stepTheme.onMobile === 'text-white' ? 'text-white' : 'text-primary']">Summary</span>
                                    <div class="flex items-center gap-2">
                                        <span :class="['text-[10px] font-bold', stepTheme.onMobile]">{{ formatAmount(cart.totalPrice) }}</span>
                                        <span :class="['material-symbols-outlined text-sm transition-transform', stepTheme.onMobile, showMobileSummary ? 'rotate-180' : '']">expand_more</span>
                                    </div>
                                </div>
                                <div v-if="showMobileSummary" class="mt-4 space-y-3 pt-4 border-t border-white/10 transition-all animate-fade-up">
                                    <div v-for="item in cart.items" :key="item.id" class="flex justify-between items-center text-[9px]">
                                        <span :class="['italic opacity-70', stepTheme.onMobile]">{{ item.name }} (x{{ item.quantity }})</span>
                                        <span :class="['font-bold', stepTheme.onMobile]">{{ formatAmount(item.price * item.quantity) }}</span>
                                    </div>
                                </div>
                             </div>

                             <!-- STEP 1: Contact -->
                            <section v-if="step === 1" class="space-y-6 animate-fade-up">
                                <div class="grid grid-cols-1 gap-5">
                                    <div class="space-y-1.5">
                                        <label :class="['block text-[8px] tracking-[0.1em] uppercase font-bold', step === 1 ? 'text-blue-500' : 'text-primary']">Email</label>
                                        <input v-model="form.email" type="email" placeholder="your@email.com"
                                            class="w-full bg-transparent border-b border-outline-variant/30 py-2 px-0 text-on-surface focus:border-primary focus:outline-none transition-all placeholder:text-on-surface-variant/20 text-xs" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label :class="['block text-[8px] tracking-[0.1em] uppercase font-bold', step === 1 ? 'text-blue-500' : 'text-primary']">Phone</label>
                                        <input v-model="form.phone" type="tel" placeholder="+254 7XX..."
                                            class="w-full bg-transparent border-b border-outline-variant/30 py-2 px-0 text-on-surface focus:border-primary focus:outline-none transition-all placeholder:text-on-surface-variant/20 text-xs" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label :class="['block text-[8px] tracking-[0.1em] uppercase font-bold', step === 1 ? 'text-blue-500' : 'text-primary']">Full Name</label>
                                        <input v-model="form.full_name" type="text" placeholder="Legal Name"
                                            class="w-full bg-transparent border-b border-outline-variant/30 py-2 px-0 text-on-surface focus:border-primary focus:outline-none transition-all placeholder:text-on-surface-variant/20 text-xs" />
                                    </div>
                                </div>
                                <button @click="nextStep"
                                    :class="[
                                        'w-full py-4 text-[9px] uppercase tracking-[0.25em] font-bold transition-all duration-500 active:scale-[0.98]',
                                        `${stepTheme.bg} text-black hover:brightness-110`
                                    ]">
                                    Continue
                                    <span class="material-symbols-outlined text-[10px] align-middle ml-1">arrow_forward</span>
                                </button>
                            </section>

                            <!-- STEP 2: Shipping -->
                            <section v-if="step === 2" class="space-y-6 animate-fade-up">
                                <div class="grid grid-cols-1 gap-5">
                                    <div class="space-y-1.5">
                                        <label :class="['block text-[8px] tracking-[0.1em] uppercase font-bold', stepTheme.onMobile === 'text-white' ? 'text-white/60' : 'text-primary']">Address</label>
                                        <input v-model="form.address" type="text" placeholder="Building, Street"
                                            :class="['w-full bg-transparent border-b border-white/20 py-2 px-0 focus:border-white focus:outline-none transition-all placeholder:text-white/20 text-xs', stepTheme.onMobile]" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <label :class="['block text-[8px] tracking-[0.1em] uppercase font-bold', stepTheme.onMobile === 'text-white' ? 'text-white/60' : 'text-primary']">City / Area</label>
                                        <input v-model="form.city" type="text" placeholder="e.g. Kilimani, Nairobi"
                                            :class="['w-full bg-transparent border-b border-white/20 py-2 px-0 focus:border-white focus:outline-none transition-all placeholder:text-white/20 text-xs', stepTheme.onMobile]" />
                                    </div>
                                </div>
                                <button @click="nextStep"
                                    class="w-full bg-primary text-black py-4 text-[9px] uppercase tracking-[0.25em] font-bold transition-all active:scale-[0.98]">
                                    Choose Payment
                                </button>
                            </section>

                            <!-- STEP 3: Payment -->
                            <section v-if="step === 3" class="space-y-6 animate-fade-up">
                                <div class="grid grid-cols-2 gap-3">
                                    <div v-for="method in ['mpesa', 'card']" :key="method" 
                                        @click="form.payment_method = method"
                                        class="p-4 border transition-all cursor-pointer flex flex-col items-center gap-2"
                                        :class="form.payment_method === method ? 'border-primary bg-primary/5' : 'border-outline-variant/30 opacity-50'">
                                        <span class="material-symbols-outlined text-lg">{{ method === 'mpesa' ? 'smartphone' : 'credit_card' }}</span>
                                        <span class="text-[8px] uppercase tracking-widest font-bold">{{ method === 'mpesa' ? 'M-Pesa' : 'Card' }}</span>
                                    </div>
                                </div>

                                <div v-if="form.payment_method === 'mpesa'" class="bg-primary/5 p-4 space-y-2">
                                     <p class="text-[8px] text-primary/80 uppercase tracking-widest font-bold">STK Protocol</p>
                                     <p class="text-[9px] text-on-surface-variant leading-tight">Instant M-Pesa push will be sent to your phone for verification.</p>
                                </div>

                                <button @click="handlePayment" :disabled="isProcessing"
                                    :class="[
                                        'w-full py-4 text-[9px] uppercase tracking-[0.25em] font-bold transition-all duration-500 active:scale-[0.98] disabled:opacity-50',
                                        `${stepTheme.bg} text-black hover:brightness-110`
                                    ]">
                                    {{ isProcessing ? 'Processing...' : 'Complete Payment' }}
                                </button>
                            </section>
                        </div>

                        <!-- Mobile Footer Trust Signals -->
                        <div class="pt-6 border-t border-outline-variant/20 flex flex-wrap gap-x-4 gap-y-2">
                             <div v-for="t in ['Secure SSL', 'Nairobi Delivery', '30-Day Guarantee']" :key="t" 
                                class="flex items-center gap-1.5 opacity-40">
                                <span class="material-symbols-outlined text-[10px]">verified</span>
                                <span class="text-[8px] uppercase tracking-widest font-medium">{{ t }}</span>
                             </div>
                        </div>
                    </div>

                    <!-- Compact Mobile Assistance -->
                    <div class="mt-4 p-4 glass-panel flex items-center gap-4">
                        <span class="material-symbols-outlined text-primary text-xl">support_agent</span>
                        <p class="text-[9px] text-on-surface-variant leading-tight">Need help? <span class="text-primary font-bold">Chat with a stylist</span></p>
                    </div>
                </div>

                <!-- Desktop / Original View (Hidden on mobile) -->
                <div class="hidden md:block lg:col-span-7 space-y-20">

                    <!-- Header -->
                    <header class="space-y-4">
                        <Link :href="route('shop')" class="text-[10px] uppercase tracking-[0.2em] text-on-surface-variant hover:text-primary transition-colors flex items-center gap-2 font-bold">
                            <span class="material-symbols-outlined text-sm">arrow_back</span> Return to Collection
                        </Link>
                        <h1 class="font-headline text-4xl md:text-5xl font-light tracking-tight text-on-surface">Secure Checkout</h1>
                        <p class="text-on-surface-variant text-[11px] tracking-[0.2em] uppercase font-bold">
                            Step {{ step }} of 3 — {{ step === 1 ? 'Identification' : step === 2 ? 'Shipping Address' : 'Payment Method' }}
                        </p>
                    </header>

                    <!-- Step Indicator -->
                    <div class="flex items-center">
                        <template v-for="s in 3" :key="s">
                            <div class="flex flex-col items-center gap-2 cursor-pointer" @click="step = s">
                                <div :class="[
                                    'w-8 h-8 flex items-center justify-center border text-[11px] font-bold transition-all duration-700',
                                    step === s ? `${stepTheme.border} ${stepTheme.color} ${stepTheme.bgAlpha}` : 
                                    step > s ? 'border-primary text-primary opacity-40' : 'border-outline-variant text-on-surface-variant'
                                ]">
                                    {{ String(s).padStart(2, '0') }}
                                </div>
                                <span :class="[
                                    'text-[9px] uppercase tracking-[0.2em] font-bold transition-all duration-700',
                                    step === s ? stepTheme.color : 
                                    step > s ? 'text-primary opacity-40' : 'text-on-surface-variant/40'
                                ]">
                                    {{ s === 1 ? 'Contact' : s === 2 ? 'Shipping' : 'Payment' }}
                                </span>
                            </div>
                            <div v-if="s < 3" :class="[
                                'w-16 h-px mx-2 mt-[-20px] transition-all duration-700',
                                step > s ? 'bg-primary/50' : 'bg-outline-variant/50'
                            ]"></div>
                        </template>
                    </div>

                    <!-- ── STEP 1: Contact ── -->
                    <section v-if="step === 1" class="space-y-10 animate-fade-up">
                        <div class="flex items-center gap-4">
                            <span class="w-8 h-8 flex items-center justify-center border border-outline-variant text-[11px] font-bold text-on-surface-variant">01</span>
                            <h2 class="font-headline text-xl font-light tracking-tight uppercase text-on-surface">Contact Information</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <label class="block text-[10px] tracking-[0.2em] uppercase text-primary font-bold">Email Address</label>
                                <input v-model="form.email" type="email" placeholder="your@email.com"
                                    class="w-full bg-transparent border-b border-outline-variant py-4 px-0 text-on-surface focus:border-primary focus:outline-none transition-all placeholder:text-on-surface-variant/30 placeholder:italic text-sm" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] tracking-[0.2em] uppercase text-primary font-bold">Phone Number</label>
                                <input v-model="form.phone" type="tel" placeholder="+254 7XX XXX XXX"
                                    class="w-full bg-transparent border-b border-outline-variant py-4 px-0 text-on-surface focus:border-primary focus:outline-none transition-all placeholder:text-on-surface-variant/30 text-sm" />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <label class="block text-[10px] tracking-[0.2em] uppercase text-primary font-bold">Full Legal Name</label>
                                <input v-model="form.full_name" type="text" placeholder="As it appears on your ID"
                                    class="w-full bg-transparent border-b border-outline-variant py-4 px-0 text-on-surface focus:border-primary focus:outline-none transition-all placeholder:text-on-surface-variant/30 placeholder:italic text-sm" />
                            </div>
                        </div>
                        <button @click="nextStep"
                            :class="[
                                'w-full border py-5 text-[10px] uppercase tracking-[0.3em] font-bold transition-all duration-500',
                                `${stepTheme.border} ${stepTheme.bgAlpha} ${stepTheme.color} hover:brightness-125`
                            ]">
                            Continue to Shipping
                            <span class="material-symbols-outlined text-sm align-middle ml-2">arrow_forward</span>
                        </button>
                    </section>

                    <!-- ── STEP 2: Shipping ── -->
                    <section v-if="step === 2" class="space-y-10 animate-fade-up">
                        <div class="flex items-center gap-4">
                            <span class="w-8 h-8 flex items-center justify-center border border-outline-variant text-[11px] font-bold text-on-surface-variant">02</span>
                            <h2 class="font-headline text-xl font-light tracking-tight uppercase text-on-surface">Shipping Address</h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2 md:col-span-2">
                                <label class="block text-[10px] tracking-[0.2em] uppercase text-primary font-bold">Street Address</label>
                                <input v-model="form.address" type="text" placeholder="House, street, apartment..."
                                    class="w-full bg-transparent border-b border-outline-variant py-4 px-0 text-on-surface focus:border-primary focus:outline-none transition-all placeholder:text-on-surface-variant/30 placeholder:italic text-sm" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] tracking-[0.2em] uppercase text-primary font-bold">City</label>
                                <input v-model="form.city" type="text" placeholder="Nairobi"
                                    class="w-full bg-transparent border-b border-outline-variant py-4 px-0 text-on-surface focus:border-primary focus:outline-none transition-all placeholder:text-on-surface-variant/30 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] tracking-[0.2em] uppercase text-primary font-bold">Country</label>
                                <select v-model="form.country"
                                    class="w-full bg-transparent border-b border-outline-variant py-4 px-0 text-on-surface focus:border-primary focus:outline-none transition-all appearance-none cursor-pointer text-sm">
                                    <option class="bg-surface text-on-surface">Kenya</option>
                                    <option class="bg-surface text-on-surface">United Kingdom</option>
                                    <option class="bg-surface text-on-surface">France</option>
                                    <option class="bg-surface text-on-surface">United States</option>
                                    <option class="bg-surface text-on-surface">United Arab Emirates</option>
                                </select>
                            </div>
                        </div>

                        <!-- Optional Precision Sizing -->
                        <div class="space-y-6 pt-8 border-t border-outline-variant/20">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-primary text-sm">straighten</span>
                                <div>
                                    <h3 class="text-[11px] font-bold uppercase tracking-[0.2em] text-on-surface">Precision Sizing <span class="text-primary">(Optional)</span></h3>
                                    <p class="text-[10px] text-on-surface-variant mt-0.5">All measurements in centimetres.</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                                <div v-for="field in ['height_cm','bust_cm','waist_cm','hips_cm']" :key="field" class="space-y-2">
                                    <label class="block text-[10px] tracking-[0.2em] uppercase text-primary font-bold">{{ field.replace('_cm','') }}</label>
                                    <input v-model="form[field]" type="number"
                                        :placeholder="field === 'height_cm' ? '168' : field === 'bust_cm' ? '90' : field === 'waist_cm' ? '74' : '96'"
                                        class="w-full bg-transparent border-b border-outline-variant py-4 px-0 text-on-surface focus:border-primary focus:outline-none transition-all placeholder:text-on-surface-variant/30 text-sm" />
                                </div>
                            </div>
                        </div>

                        <button @click="nextStep"
                            class="w-full border border-primary/30 bg-primary/5 hover:bg-primary/10 text-primary py-5 text-[10px] uppercase tracking-[0.3em] font-bold transition-all duration-300">
                            Continue to Payment
                            <span class="material-symbols-outlined text-sm align-middle ml-2">arrow_forward</span>
                        </button>
                    </section>

                    <!-- ── STEP 3: Payment ── -->
                    <section v-if="step === 3" class="space-y-10 animate-fade-up">
                        <div class="flex items-center gap-4">
                            <span class="w-8 h-8 flex items-center justify-center border border-outline-variant text-[11px] font-bold text-on-surface-variant">03</span>
                            <h2 class="font-headline text-xl font-light tracking-tight uppercase text-on-surface">Payment Method</h2>
                        </div>

                        <div class="space-y-3">
                            <!-- M-Pesa -->
                            <div @click="form.payment_method = 'mpesa'"
                                class="border p-6 transition-all cursor-pointer flex items-center justify-between group"
                                :class="form.payment_method === 'mpesa' ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-primary/30 hover:bg-surface-container'">
                                <div class="flex items-center gap-5">
                                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all"
                                        :class="form.payment_method === 'mpesa' ? 'border-primary' : 'border-outline-variant'">
                                        <div v-if="form.payment_method === 'mpesa'" class="w-2 h-2 rounded-full bg-primary"></div>
                                    </div>
                                    <span class="text-sm font-bold tracking-wide text-on-surface">M-Pesa <span class="text-on-surface-variant font-normal">(Kenya Mobile Money)</span></span>
                                </div>
                                <div class="w-12 h-7 bg-[#4DB53C] flex items-center justify-center text-[7px] font-black text-white italic tracking-wide">M-PESA</div>
                            </div>

                            <!-- Card -->
                            <div @click="form.payment_method = 'card'"
                                class="border p-6 transition-all cursor-pointer flex items-center justify-between"
                                :class="form.payment_method === 'card' ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-primary/30 hover:bg-surface-container'">
                                <div class="flex items-center gap-5">
                                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all"
                                        :class="form.payment_method === 'card' ? 'border-primary' : 'border-outline-variant'">
                                        <div v-if="form.payment_method === 'card'" class="w-2 h-2 rounded-full bg-primary"></div>
                                    </div>
                                    <span class="text-sm font-bold tracking-wide text-on-surface">Credit or Debit Card</span>
                                </div>
                                <span class="material-symbols-outlined text-on-surface-variant">credit_card</span>
                            </div>

                            <!-- PayPal -->
                            <div @click="form.payment_method = 'paypal'"
                                class="border p-6 transition-all cursor-pointer flex items-center justify-between"
                                :class="form.payment_method === 'paypal' ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-primary/30 hover:bg-surface-container'">
                                <div class="flex items-center gap-5">
                                    <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all"
                                        :class="form.payment_method === 'paypal' ? 'border-primary' : 'border-outline-variant'">
                                        <div v-if="form.payment_method === 'paypal'" class="w-2 h-2 rounded-full bg-primary"></div>
                                    </div>
                                    <span class="text-sm font-bold tracking-wide text-on-surface">PayPal Express</span>
                                </div>
                                <span class="material-symbols-outlined text-primary/60">account_balance_wallet</span>
                            </div>
                        </div>

                        <!-- CTA -->
                        <button @click="handlePayment" :disabled="isProcessing"
                            :class="[
                                'w-full py-5 text-[10px] uppercase tracking-[0.3em] font-bold transition-all duration-500 disabled:opacity-50 mt-4',
                                isProcessing ? 'bg-surface-container text-on-surface-variant cursor-not-allowed' : `${stepTheme.bg} text-black hover:opacity-90 active:scale-[0.98]`
                            ]">
                            <span v-if="!isProcessing" class="flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined text-sm">lock</span>
                                Complete Bespoke Order
                            </span>
                            <span v-else class="flex items-center justify-center gap-3">
                                <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/>
                                </svg>
                                Processing Order...
                            </span>
                        </button>
                        <p class="text-center text-[10px] text-on-surface-variant italic">
                            By placing an order, you agree to our <a href="#" class="underline underline-offset-4 hover:text-primary transition-colors">Terms of Service</a>.
                        </p>
                    </section>

                    <!-- Trust Signals -->
                    <div class="flex flex-wrap items-center gap-8 pt-8 border-t border-outline-variant/20">
                        <div v-for="trust in [
                            { icon: 'verified_user', text: 'Secure SSL Encryption' },
                            { icon: 'local_shipping', text: 'Priority Nairobi Delivery' },
                            { icon: 'policy', text: '30-Day Bespoke Guarantee' }
                        ]" :key="trust.icon" class="flex items-center gap-2 text-on-surface-variant text-[10px] tracking-[0.15em] uppercase font-medium">
                            <span class="material-symbols-outlined text-sm text-primary/60">{{ trust.icon }}</span>
                            {{ trust.text }}
                        </div>
                    </div>
                </div>

                <!-- ╔══════════════════════════════╗ -->
                <!-- ║  RIGHT: Order Summary        ║ -->
                <!-- ╚══════════════════════════════╝ -->
                <aside class="hidden md:block lg:col-span-5 lg:sticky lg:top-32 space-y-4 md:space-y-6">
                    <div class="bg-surface-container p-6 md:p-10 space-y-6 md:space-y-8 border border-outline-variant/30">
                        <h3 class="text-[10px] md:text-[11px] tracking-[0.2em] md:tracking-[0.3em] uppercase font-bold text-on-surface pb-4 md:pb-6 border-b border-outline-variant/30">Order Summary</h3>

                        <!-- Items -->
                        <div class="space-y-4 md:space-y-6 max-h-60 md:max-h-72 overflow-y-auto pr-2 no-scrollbar">
                            <div v-if="(isSuccess ? frozenItems : cart.items).length === 0" class="text-center py-6 text-on-surface-variant text-xs italic">
                                No items.
                            </div>
                            <div v-for="item in (isSuccess ? frozenItems : cart.items)" :key="item.id" class="flex gap-4 md:gap-5">
                                <div class="w-16 md:w-20 aspect-[3/4] bg-surface overflow-hidden flex-shrink-0 border border-outline-variant/20">
                                    <img :src="item.image" :alt="item.name" class="w-full h-full object-cover" />
                                </div>
                                <div class="flex flex-col justify-between py-0.5 md:py-1">
                                    <div class="space-y-0.5">
                                        <h4 class="font-headline text-xs md:text-sm font-semibold tracking-tight text-on-surface leading-snug">{{ item.name }}</h4>
                                        <p class="text-[9px] md:text-[10px] tracking-widest text-primary uppercase font-semibold">Qty: {{ item.quantity }}</p>
                                    </div>
                                    <p class="text-xs md:text-sm font-bold tracking-wider text-on-surface">{{ formatAmount(item.price * item.quantity, page.props) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="space-y-3 md:space-y-4 pt-4 md:pt-6 border-t border-outline-variant/30">
                            <div class="flex justify-between text-xs md:text-sm">
                                <span class="text-on-surface-variant tracking-wide font-medium">Subtotal</span>
                                <span class="font-semibold text-on-surface">{{ formatAmount(isSuccess ? frozenTotal : cart.totalPrice, page.props) }}</span>
                            </div>
                            <div class="flex justify-between text-xs md:text-sm">
                                <span class="text-on-surface-variant tracking-wide font-medium">Shipping</span>
                                <span class="text-primary font-semibold">Complimentary</span>
                            </div>
                            <div class="flex justify-between items-baseline pt-4 md:pt-6 border-t border-outline-variant/20">
                                <span class="font-headline text-sm md:text-base font-light uppercase tracking-wider text-on-surface">Total</span>
                                <span class="font-headline text-xl md:text-2xl font-bold text-primary">{{ formatAmount(isSuccess ? frozenTotal : cart.totalPrice, page.props) }}</span>
                            </div>
                        </div>

                        <!-- Place Order CTA (mirrors Step 3) -->
                        <button v-if="step === 3" @click="handlePayment" :disabled="isProcessing"
                            class="w-full bg-primary text-black py-4 md:py-5 text-[9px] md:text-[10px] uppercase tracking-[0.2em] md:tracking-[0.3em] font-bold hover:opacity-90 active:scale-[0.98] transition-all duration-300 disabled:opacity-50">
                            Place Order
                        </button>

                        <p class="text-center text-[9px] md:text-[10px] text-on-surface-variant/50 italic">
                            Exclusively crafted in Nairobi.
                        </p>
                    </div>

                    <!-- Assistance Card -->
                    <div class="p-6 md:p-8 border border-outline-variant/30 flex items-center gap-4 md:gap-6 bg-surface-container hover:border-primary/30 transition-colors">
                        <span class="material-symbols-outlined text-primary text-2xl md:text-3xl flex-shrink-0">support_agent</span>
                        <div class="space-y-0.5 md:space-y-1">
                            <p class="text-[10px] md:text-[11px] font-bold tracking-widest uppercase text-on-surface">Need Assistance?</p>
                            <p class="text-[10px] md:text-[11px] text-on-surface-variant leading-relaxed">Chat with a Serana stylist for sizing advisory.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </main>
    </StorefrontLayout>
</template>

<style scoped>
@keyframes fade-up {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-up { animation: fade-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
.no-scrollbar::-webkit-scrollbar { display: none; }

/* Checkout DNA Background */
/* .checkout-grid removed — using image instead */

.scan-lines {
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 3px,
        rgba(185, 195, 255, 0.3) 3px,
        rgba(185, 195, 255, 0.3) 4px
    );
}


@keyframes orb-pulse-1 {
    0%, 100% { transform: scale(1) translate(0, 0); opacity: 0.6; }
    50% { transform: scale(1.15) translate(2%, 3%); opacity: 0.9; }
}
@keyframes orb-pulse-2 {
    0%, 100% { transform: scale(1) translate(0, 0); opacity: 0.5; }
    50% { transform: scale(1.1) translate(-3%, -2%); opacity: 0.8; }
}
@keyframes orb-pulse-3 {
    0%, 100% { transform: scale(1); opacity: 0.4; }
    50% { transform: scale(1.2); opacity: 0.7; }
}
.animate-orb-1 { animation: orb-pulse-1 12s ease-in-out infinite; }
.animate-orb-2 { animation: orb-pulse-2 15s ease-in-out infinite; }
.animate-orb-3 { animation: orb-pulse-3 9s ease-in-out infinite; }
</style>
