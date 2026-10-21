<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import MpesaStkPanel from '@/Components/MpesaStkPanel.vue';
import MpesaSuccessPanel from '@/Components/MpesaSuccessPanel.vue';
import SizingFigure from '@/Components/SizingFigure.vue';
import { useCurrency } from '@/Composables/useCurrency';
import { usePulseStore } from '@/Stores/usePulseStore';

const props = defineProps({
    drop: { type: Object, required: true },
});
const page = usePage();
const { formatAmount } = useCurrency();
const pulse = usePulseStore();

/* ───────── Live countdown ───────── */
const now = ref(Date.now());
let ticker = null;
onMounted(() => { ticker = setInterval(() => (now.value = Date.now()), 1000); });
onUnmounted(() => { clearInterval(ticker); if (pollingInterval) clearInterval(pollingInterval); });

const countdown = computed(() => {
    if (!props.drop.ends_at) return null;
    const diff = new Date(props.drop.ends_at).getTime() - now.value;
    if (diff <= 0) return null;
    const d = Math.floor(diff / 86400000);
    const h = Math.floor((diff % 86400000) / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    return { d, h, m };
});

/* ───────── Reserve + pay state machine ───────── */
const open = ref(false);
const reserving = ref(false);
const pendingConfirmation = ref(false);
const formError = ref('');
const step = ref('details');          // details | pay | done
const stkStatus = ref('idle');        // idle | processing | waiting (for MpesaStkPanel)
const form = ref({
    request_id: crypto.randomUUID(), full_name: '', phone: '', email: '', size: '', bust_cm: '', waist_cm: '', hips_cm: '', height_cm: '', fit_notes: '' });
const offersSizes = computed(() => Array.isArray(props.drop.sizes) && props.drop.sizes.length > 0);
const activeField = ref(null); // which measurement the figure should highlight
const orderId = ref(null);
const isPaid = ref(false);
const reference = ref(null);
const transactionId = ref(null);
const receiptUrl = ref(null);
const paidWhatsAppUrl = ref(null);
let pollingInterval = null;

function openReserve() {
    if (!props.drop.is_open) return;
    open.value = true;
    if (!orderId.value) { step.value = 'details'; stkStatus.value = 'idle'; }
}
function closeReserve() {
    open.value = false;
}

async function submitReserve() {
    if (reserving.value) return;
    if (orderId.value) { step.value = 'pay'; return; }
    formError.value = '';
    if (!form.value.full_name || !form.value.phone) {
        pulse.emit('Please add your name and phone number.', 'error');
        return;
    }
    if (offersSizes.value && !form.value.size) {
        pulse.emit('Please choose a size.', 'error');
        return;
    }
    reserving.value = true;
    try {
        const res = await axios.post(route('drops.reserve', props.drop.slug), form.value);
        if (res.data.success) {
            orderId.value = res.data.order_id;
            reference.value = res.data.reference;
            step.value = 'pay';
            initiateStk();
        }
    } catch (err) {
        formError.value = Object.values(err.response?.data?.errors || {}).flat().join(' ') || err.response?.data?.message || 'Could not create your reservation. Please check your details and try again.';
        pulse.emit(formError.value, 'error');
    } finally { reserving.value = false; }
}

async function initiateStk(data) {
    if (stkStatus.value !== 'idle' || pendingConfirmation.value) return;
    if (data?.phone) form.value.phone = data.phone;
    stkStatus.value = 'processing';
    try {
        const res = await axios.post(route('mpesa.stk'), { order_id: orderId.value, phone: form.value.phone });
        if (res.data.success) {
            stkStatus.value = 'waiting';
            startPolling();
        } else {
            stkStatus.value = 'idle';
            step.value = 'pay';
            pulse.emit(res.data.message || 'Failed to start payment.', 'error');
        }
    } catch (err) {
        if (!err.response || err.response.status >= 500) {
            pendingConfirmation.value = true;
            startPolling();
        } else {
            stkStatus.value = 'idle';
            step.value = 'pay';
        }
        pulse.emit(err.response?.data?.message || 'Payment could not be confirmed. Check your reservation before paying again.', 'error');
    }
}

function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    let attempts = 0;
    pollingInterval = setInterval(async () => {
        attempts++;
        if (attempts > 30) { clearInterval(pollingInterval); pendingConfirmation.value = true; return; }
        try {
            const res = await axios.get(route('mpesa.status', orderId.value));
            if (res.data.reference) reference.value = res.data.reference;
            if (!res.data.is_paid && ['Payment Failed', 'Expired', 'Amount Mismatch'].includes(res.data.status)) {
                clearInterval(pollingInterval);
                pendingConfirmation.value = res.data.status !== 'Payment Failed';
                stkStatus.value = 'idle';
                formError.value = 'Payment is not confirmed. Contact support if M-Pesa shows a charge before trying again.';
                return;
            }
            if (res.data.is_paid) {
                clearInterval(pollingInterval);
                isPaid.value = true;
                transactionId.value = res.data.transaction_id;
                receiptUrl.value = res.data.receipt_url;
                paidWhatsAppUrl.value = res.data.whatsapp_url || null;
                step.value = 'done';
                pulse.emit('Reservation confirmed!', 'success');
            }
        } catch { /* keep polling */ }
    }, 5000);
}
</script>

<template>
    <Head :title="`${drop.title} — Serana Drop`" />
    <StorefrontLayout>
        <section class="page-container pb-24 grid lg:grid-cols-2 gap-12 lg:gap-16">
            <!-- Image -->
            <div class="rounded-3xl overflow-hidden bg-black/5 dark:bg-white/5 aspect-[4/5] lg:sticky lg:top-28 self-start">
                <img v-if="drop.image_url" :src="drop.image_url" :alt="drop.title" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full grid place-items-center text-on-surface-variant/30"><span class="material-symbols-outlined text-6xl">checkroom</span></div>
            </div>

            <!-- Details -->
            <div>
                <p class="text-[10px] font-headline font-black tracking-[0.4em] uppercase text-primary mb-4">Serana Drop</p>
                <h1 class="text-4xl md:text-5xl font-headline font-light tracking-tight dark:text-white text-on-surface leading-tight">{{ drop.title }}</h1>
                <p class="mt-4 text-primary font-headline text-2xl">{{ formatAmount(drop.price, page.props) }}</p>

                <div v-if="offersSizes" class="mt-5 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-on-surface-variant mr-1">Sizes</span>
                    <span v-for="s in drop.sizes" :key="s" class="px-3 py-1 rounded-full text-xs font-semibold border dark:border-white/15 border-black/10 dark:text-white text-on-surface">{{ s }}</span>
                </div>

                <p v-if="drop.description" class="mt-6 text-on-surface-variant leading-relaxed whitespace-pre-line">{{ drop.description }}</p>

                <!-- Progress -->
                <div class="mt-8 p-6 rounded-2xl border dark:border-white/10 border-black/5 bg-black/[0.02] dark:bg-white/[0.02]">
                    <div class="flex items-end justify-between mb-3">
                        <div>
                            <span class="block text-2xl font-headline dark:text-white text-on-surface">{{ drop.reserved_count }}<span class="text-on-surface-variant text-base"> / {{ drop.target_quantity }}</span></span>
                            <span class="text-[11px] uppercase tracking-widest text-on-surface-variant font-bold">pieces reserved</span>
                        </div>
                        <div v-if="countdown" class="text-right">
                            <span class="block text-lg font-headline dark:text-white text-on-surface">
                                <span v-if="countdown.d">{{ countdown.d }}d </span>{{ countdown.h }}h {{ countdown.m }}m
                            </span>
                            <span class="text-[11px] uppercase tracking-widest text-on-surface-variant font-bold">left to reserve</span>
                        </div>
                    </div>
                    <div class="h-2 rounded-full bg-black/10 dark:bg-white/10 overflow-hidden">
                        <div class="h-full bg-primary transition-all duration-1000" :style="{ width: drop.funded_percent + '%' }"></div>
                    </div>
                    <p v-if="drop.is_funded" class="mt-3 text-sm text-primary font-bold flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base">verified</span> Greenlit — this drop is being made.
                    </p>
                    <p v-else class="mt-3 text-sm text-on-surface-variant">Reserve at {{ formatAmount(drop.price, page.props) }} now. Once we reach {{ drop.target_quantity }}, production begins.</p>
                </div>

                <!-- CTA -->
                <button v-if="drop.is_open" @click="openReserve"
                    class="mt-8 w-full sm:w-auto px-10 py-5 rounded-2xl bg-primary text-background font-headline font-black uppercase tracking-[0.2em] text-sm hover:brightness-105 active:scale-[0.98] transition-all">
                    Reserve your piece
                </button>
                <p v-else class="mt-8 text-on-surface-variant font-medium">This drop is closed for new reservations.</p>

                <p class="mt-5 text-sm text-on-surface-variant">This is a paid pre-order. Contact the studio about timing and what happens if the goal is not reached before reserving.</p>
                <Link :href="route('contact')" class="text-sm text-primary underline">Ask About This Drop</Link>
                <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-xs text-on-surface-variant">
                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-primary">lock</span> Pay securely with M-PESA</span>
                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-primary">verified_user</span> Production starts after the goal is reached</span>
                </div>
            </div>
        </section>

        <!-- Reserve / pay modal -->
        <teleport to="body">
            <div v-if="open" class="fixed inset-0 z-[300] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/85 backdrop-blur-md" @click="closeReserve"></div>
                <div data-notification-modal class="relative z-10 w-full max-w-md max-h-[92vh] overflow-y-auto no-scrollbar">
                    <div data-notification-host class="sticky top-0 z-[60] bg-surface rounded-t-2xl"></div>
                    <button aria-label="Close reservation" @click="closeReserve" class="absolute top-4 right-4 z-50 w-9 h-9 grid place-items-center bg-black/40 text-white/60 hover:text-white rounded-full"><span class="material-symbols-outlined text-lg">close</span></button>

                    <p v-if="formError" role="alert" class="bg-surface text-on-surface rounded-xl p-4 mb-3">{{ formError }}</p>
                    <div v-if="pendingConfirmation" class="bg-surface text-on-surface rounded-2xl p-8">
                        <h2 class="text-2xl mb-3">Payment confirmation pending</h2>
                        <p class="text-sm mb-4">Your reservation is saved. If you approved the M-Pesa prompt, please wait for confirmation before paying again.</p>
                        <p v-if="reference" class="text-sm mb-4">Reference: {{ reference }}</p>
                        <Link :href="route('track', { ref: reference })" class="action-primary">Track Order</Link>
                        <Link :href="route('contact')" class="block underline mt-4">Contact Support</Link>
                    </div>
                    <!-- Step 1: details -->
                    <div v-if="!pendingConfirmation && step === 'details'" class="bg-white dark:bg-[#0d1f0d] rounded-3xl p-8">
                        <h2 class="font-headline text-2xl dark:text-white text-on-surface mb-1">Reserve “{{ drop.title }}”</h2>
                        <p class="text-on-surface-variant text-sm mb-6">{{ formatAmount(drop.price, page.props) }} · paid now via M-PESA to secure your piece.</p>
                        <div class="space-y-4">
                            <label for="drop-full_name" class="block text-sm mb-2">Full Name (required)</label>
                            <input id="drop-full_name" v-model="form.full_name" placeholder="Full name" class="w-full bg-black/5 dark:bg-white/5 border dark:border-white/10 border-black/10 rounded-xl px-4 py-3 dark:text-white text-on-surface outline-none focus:border-primary/50" />
                            <label for="drop-phone" class="block text-sm mb-2">M-Pesa Phone Number (required)</label>
                            <input type="tel" id="drop-phone" v-model="form.phone" placeholder="M-PESA phone (07…)" class="w-full bg-black/5 dark:bg-white/5 border dark:border-white/10 border-black/10 rounded-xl px-4 py-3 dark:text-white text-on-surface outline-none focus:border-primary/50" />
                            <label for="drop-email" class="block text-sm mb-2">Email (optional)</label>
                            <input id="drop-email" v-model="form.email" type="email" placeholder="Email (for your receipt)" class="w-full bg-black/5 dark:bg-white/5 border dark:border-white/10 border-black/10 rounded-xl px-4 py-3 dark:text-white text-on-surface outline-none focus:border-primary/50" />

                            <!-- Size: pick from offered sizes, or free text -->
                            <div v-if="offersSizes">
                                <span class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">Choose your size</span>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="s in drop.sizes" :key="s" type="button" @click="form.size = s"
                                        :class="['px-4 py-2 rounded-lg text-sm font-semibold border transition-all',
                                                 form.size === s ? 'bg-primary text-background border-primary' : 'dark:border-white/10 border-black/10 dark:text-white text-on-surface hover:border-primary/50']">
                                        {{ s }}
                                    </button>
                                </div>
                            </div>
                            <input v-else aria-label="Size (optional)" v-model="form.size" placeholder="Size (e.g. M, or UK 12)" class="w-full bg-black/5 dark:bg-white/5 border dark:border-white/10 border-black/10 rounded-xl px-4 py-3 dark:text-white text-on-surface outline-none focus:border-primary/50" />

                            <!-- Measurements for a precise fit (optional but encouraged) -->
                            <div>
                                <span class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2">Your measurements (cm) — for the best fit</span>
                                <SizingFigure :activeField="activeField" :measurements="form" @pick="activeField = $event" class="mb-3" />
                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <div><label for="drop-bust_cm" class="block text-sm mb-2">Bust (cm, optional)</label>
                            <input id="drop-bust_cm" v-model="form.bust_cm" type="number" placeholder="Bust" @focus="activeField = 'bust_cm'"
                                        :class="['bg-black/5 dark:bg-white/5 border rounded-xl px-3 py-2.5 dark:text-white text-on-surface outline-none', activeField === 'bust_cm' ? 'border-primary' : 'dark:border-white/10 border-black/10 focus:border-primary/50']" /></div>
                                    <div><label for="drop-waist_cm" class="block text-sm mb-2">Waist (cm, optional)</label>
                            <input id="drop-waist_cm" v-model="form.waist_cm" type="number" placeholder="Waist" @focus="activeField = 'waist_cm'"
                                        :class="['bg-black/5 dark:bg-white/5 border rounded-xl px-3 py-2.5 dark:text-white text-on-surface outline-none', activeField === 'waist_cm' ? 'border-primary' : 'dark:border-white/10 border-black/10 focus:border-primary/50']" /></div>
                                    <div><label for="drop-hips_cm" class="block text-sm mb-2">Hips (cm, optional)</label>
                            <input id="drop-hips_cm" v-model="form.hips_cm" type="number" placeholder="Hips" @focus="activeField = 'hips_cm'"
                                        :class="['bg-black/5 dark:bg-white/5 border rounded-xl px-3 py-2.5 dark:text-white text-on-surface outline-none', activeField === 'hips_cm' ? 'border-primary' : 'dark:border-white/10 border-black/10 focus:border-primary/50']" /></div>
                                    <div><label for="drop-height_cm" class="block text-sm mb-2">Height (cm, optional)</label>
                            <input id="drop-height_cm" v-model="form.height_cm" type="number" placeholder="Height" @focus="activeField = 'height_cm'"
                                        :class="['bg-black/5 dark:bg-white/5 border rounded-xl px-3 py-2.5 dark:text-white text-on-surface outline-none', activeField === 'height_cm' ? 'border-primary' : 'dark:border-white/10 border-black/10 focus:border-primary/50']" /></div>
                                </div>
                            </div>
                            <label for="drop-fit_notes" class="block text-sm mb-2">Fit Notes (optional)</label>
                            <textarea id="drop-fit_notes" v-model="form.fit_notes" rows="2" placeholder="Fit notes (optional) — e.g. prefer relaxed, sleeves a touch longer" class="w-full bg-black/5 dark:bg-white/5 border dark:border-white/10 border-black/10 rounded-xl px-4 py-3 dark:text-white text-on-surface outline-none focus:border-primary/50"></textarea>
                        </div>
                        <button :disabled="reserving" @click="submitReserve" class="action-primary w-full py-4">
                            {{ reserving ? 'Creating Reservation…' : 'Reserve & Continue to Payment' }}
                        </button>
                    </div>

                    <!-- Step 2: pay -->
                    <MpesaStkPanel v-else-if="!pendingConfirmation && step === 'pay' && !isPaid" :amount="drop.price" :phone="form.phone" :reference="reference" :status="stkStatus" @pay="initiateStk" @cancel="closeReserve" />

                    <!-- Step 3: done -->
                    <MpesaSuccessPanel v-else-if="step === 'done'" :amount="drop.price" :orderId="orderId" :items="[]"
                        :transactionId="transactionId" :receiptUrl="receiptUrl" :reference="reference" :email="form.email" :whatsappUrl="paidWhatsAppUrl" />
                </div>
            </div>
        </teleport>
    </StorefrontLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
