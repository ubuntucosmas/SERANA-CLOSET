<script setup>
import { Head, Link } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import MpesaStkPanel from '@/Components/MpesaStkPanel.vue';
import MpesaSuccessPanel from '@/Components/MpesaSuccessPanel.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { useCartStore } from '@/Stores/useCartStore';
import { ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePulseStore } from '@/Stores/usePulseStore';

const page = usePage();
const pulse = usePulseStore();

const cart = useCartStore();
const { formatAmount } = useCurrency();

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
    try {
        const payload = { ...form.value, items: cart.items, total: cart.totalPrice };
        if (!payload.height_cm) delete payload.height_cm;
        if (!payload.bust_cm) delete payload.bust_cm;
        if (!payload.waist_cm) delete payload.waist_cm;
        if (!payload.hips_cm) delete payload.hips_cm;

        const response = await axios.post(route('checkout.process'), payload);
        if (response.data.success) {
            orderId.value = response.data.order_id || Date.now();
            isWaitingForStk.value = true;
            pulse.emit('Order created successfully.', 'success');
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
    try {
        const response = await axios.post(route('mpesa.stk'), {
            order_id: orderId.value,
            phone: data.phone,
            amount: data.amount
        });
        if (response.data.success) {
            pulse.emit('STK Push sent to your phone.', 'success');
            isWaitingForStk.value = true;
            startPolling();
        }
    } catch (err) {
        console.error(err);
        pulse.emit('Failed to initiate M-Pesa payment.', 'error');
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
            pulse.emit('Payment verification timed out. Contact support.', 'error');
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
                pulse.emit('Payment Verified! Your order is now in production.', 'success');
            }
        } catch (err) { console.error('Polling error:', err); }
    }, 5000);
}
</script>

<template>
    <StorefrontLayout hideMobileCard>
        <Head title="Checkout — Serana" />

        <main class="relative pt-20 md:pt-28 pb-16 px-4 md:px-8 max-w-screen-xl mx-auto min-h-screen bg-background">

            <!-- M-Pesa STK -->
            <div v-if="isWaitingForStk && orderId && !isPaid" class="max-w-2xl mx-auto">
                <div class="mb-6">
                    <button @click="isWaitingForStk = false" class="text-xs uppercase tracking-widest text-white/50 hover:text-primary flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">arrow_back</span> Back
                    </button>
                </div>
                <MpesaStkPanel
                    :amount="cart.totalPrice"
                    :phone="form.phone"
                    :isProcessing="isProcessing"
                    :isWaiting="isWaitingForStk"
                    @pay="initiateMpesaStk"
                    @cancel="isWaitingForStk = false"
                />
            </div>

            <!-- Success -->
            <div v-else-if="isSuccess" class="max-w-2xl mx-auto">
                <MpesaSuccessPanel 
                    :amount="paidAmount" 
                    :orderId="orderId" 
                    :items="frozenItems" 
                    :transactionId="safaricomTransactionId" 
                />
            </div>

            <!-- Beautiful Simple Checkout -->
            <div v-else class="max-w-6xl mx-auto">
                <div class="mb-10">
                    <Link :href="route('shop')" class="text-xs uppercase tracking-[0.25em] text-white/50 hover:text-primary flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">arrow_back</span> Back to Collection
                    </Link>
                    <h1 class="font-headline text-5xl tracking-tighter mt-3">Checkout</h1>
                    <p class="text-white/50 mt-1">One simple step.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                    
                    <!-- Summary -->
                    <div class="lg:col-span-5 lg:sticky lg:top-24">
                        <div class="bg-surface-container border border-white/10 rounded-2xl p-8">
                            <h3 class="uppercase tracking-[0.3em] text-xs text-white/40 mb-6">Order Summary</h3>
                            
                            <div class="space-y-5">
                                <div v-for="item in cart.items" :key="item.id" class="flex gap-4">
                                    <div class="w-16 h-20 bg-white/5 rounded overflow-hidden flex-shrink-0">
                                        <img :src="item.image" class="w-full h-full object-cover" />
                                    </div>
                                    <div class="flex-1 pt-1">
                                        <div class="text-white font-medium">{{ item.name }}</div>
                                        <div class="text-xs text-white/50">×{{ item.quantity }}</div>
                                        <div class="text-primary font-mono mt-3">{{ formatAmount(item.price * item.quantity) }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 pt-6 border-t border-white/10">
                                <div class="flex justify-between text-sm">
                                    <span class="text-white/60">Total</span>
                                    <span class="font-headline text-2xl text-primary">{{ formatAmount(cart.totalPrice) }}</span>
                                </div>
                                <div class="text-xs text-primary mt-1">Shipping included</div>
                            </div>
                        </div>
                    </div>

                    <!-- Form -->
                    <div class="lg:col-span-7">
                        <div class="bg-surface-container border border-white/10 rounded-2xl p-8 md:p-10 space-y-7">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-xs uppercase tracking-widest text-white/40 mb-2 block">Full Name</label>
                                    <input v-model="form.full_name" type="text" placeholder="Your legal name" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white focus:border-primary outline-none" />
                                </div>
                                <div>
                                    <label class="text-xs uppercase tracking-widest text-white/40 mb-2 block">Phone (for M-Pesa)</label>
                                    <input v-model="form.phone" type="tel" placeholder="+254 7XX XXX XXX" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white focus:border-primary outline-none" />
                                </div>
                            </div>

                            <div>
                                <label class="text-xs uppercase tracking-widest text-white/40 mb-2 block">Email</label>
                                <input v-model="form.email" type="email" placeholder="you@email.com" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white focus:border-primary outline-none" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-xs uppercase tracking-widest text-white/40 mb-2 block">Address</label>
                                    <input v-model="form.address" type="text" placeholder="House & Street" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white focus:border-primary outline-none" />
                                </div>
                                <div>
                                    <label class="text-xs uppercase tracking-widest text-white/40 mb-2 block">City</label>
                                    <input v-model="form.city" type="text" placeholder="Nairobi" class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white focus:border-primary outline-none" />
                                </div>
                            </div>

                            <!-- Optional Sizing -->
                            <div>
                                <button @click="showSizing = !showSizing" class="text-xs flex items-center gap-2 text-primary hover:text-white uppercase tracking-widest">
                                    <span class="material-symbols-outlined text-base">{{ showSizing ? 'expand_less' : 'expand_more' }}</span>
                                    Need custom measurements? <span class="text-white/30">(optional)</span>
                                </button>
                                <div v-if="showSizing" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                    <div v-for="f in ['height_cm','bust_cm','waist_cm','hips_cm']" :key="f">
                                        <label class="text-xs text-white/40 mb-1 block">{{ f.replace('_cm','').toUpperCase() }} cm</label>
                                        <input v-model="form[f]" type="number" class="w-full bg-white/5 border border-white/10 rounded-2xl px-5 py-3 text-sm text-white focus:border-primary outline-none" />
                                    </div>
                                </div>
                            </div>

                            <button 
                                @click="handlePayment" 
                                :disabled="isProcessing || !form.full_name || !form.phone || !form.address"
                                class="mt-4 w-full py-5 rounded-2xl bg-primary text-black font-bold uppercase tracking-[0.3em] text-sm flex items-center justify-center gap-3 active:scale-[0.985] hover:bg-white transition disabled:opacity-50">
                                <span v-if="!isProcessing">Pay with M-Pesa • {{ formatAmount(cart.totalPrice) }}</span>
                                <span v-else>Creating your order...</span>
                            </button>

                            <p class="text-center text-[10px] text-white/40">Instant • Secure • Safaricom</p>
                        </div>
                    </div>

                </div>
            </div>

        </main>
    </StorefrontLayout>
</template>