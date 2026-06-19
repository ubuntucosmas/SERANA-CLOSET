<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import MpesaStkPanel from '@/Components/MpesaStkPanel.vue';
import MpesaSuccessPanel from '@/Components/MpesaSuccessPanel.vue';
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
const step = ref('details');          // details | pay | done
const stkStatus = ref('idle');        // idle | processing | waiting (for MpesaStkPanel)
const form = ref({ full_name: '', phone: '', email: '', size: '' });
const orderId = ref(null);
const isPaid = ref(false);
const reference = ref(null);
const transactionId = ref(null);
const receiptUrl = ref(null);
let pollingInterval = null;

function openReserve() {
    if (!props.drop.is_open) return;
    open.value = true;
    step.value = 'details';
    stkStatus.value = 'idle';
    isPaid.value = false;
}
function closeReserve() {
    open.value = false;
    if (pollingInterval) clearInterval(pollingInterval);
}

async function submitReserve() {
    if (!form.value.full_name || !form.value.phone) {
        pulse.emit('Please add your name and phone number.', 'error');
        return;
    }
    try {
        const res = await axios.post(route('drops.reserve', props.drop.slug), form.value);
        if (res.data.success) {
            orderId.value = res.data.order_id;
            step.value = 'pay';
            initiateStk();
        }
    } catch (err) {
        pulse.emit(err.response?.data?.message || 'Could not create your reservation.', 'error');
    }
}

async function initiateStk() {
    stkStatus.value = 'processing';
    try {
        const res = await axios.post(route('mpesa.stk'), { order_id: orderId.value, phone: form.value.phone });
        if (res.data.success) {
            stkStatus.value = 'waiting';
            startPolling();
        } else {
            stkStatus.value = 'idle';
            step.value = 'details';
            pulse.emit(res.data.message || 'Failed to start payment.', 'error');
        }
    } catch (err) {
        stkStatus.value = 'idle';
        step.value = 'details';
        pulse.emit(err.response?.data?.message || 'Failed to start payment.', 'error');
    }
}

function startPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
    let attempts = 0;
    pollingInterval = setInterval(async () => {
        attempts++;
        if (attempts > 30) { clearInterval(pollingInterval); return; }
        try {
            const res = await axios.get(route('mpesa.status', orderId.value));
            if (res.data.reference) reference.value = res.data.reference;
            if (res.data.is_paid) {
                clearInterval(pollingInterval);
                isPaid.value = true;
                transactionId.value = res.data.transaction_id;
                receiptUrl.value = res.data.receipt_url;
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
        <section class="max-w-screen-xl mx-auto px-6 pt-28 pb-24 grid lg:grid-cols-2 gap-12 lg:gap-16">
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

                <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-xs text-on-surface-variant">
                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-primary">lock</span> Pay securely with M-PESA</span>
                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-sm text-primary">verified_user</span> Made only if it reaches the goal</span>
                </div>
            </div>
        </section>

        <!-- Reserve / pay modal -->
        <teleport to="body">
            <div v-if="open" class="fixed inset-0 z-[300] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/85 backdrop-blur-md" @click="closeReserve"></div>
                <div class="relative z-10 w-full max-w-md max-h-[92vh] overflow-y-auto no-scrollbar">
                    <button @click="closeReserve" class="absolute top-4 right-4 z-50 w-9 h-9 grid place-items-center bg-black/40 text-white/60 hover:text-white rounded-full"><span class="material-symbols-outlined text-lg">close</span></button>

                    <!-- Step 1: details -->
                    <div v-if="step === 'details'" class="bg-white dark:bg-[#0d1f0d] rounded-3xl p-8">
                        <h2 class="font-headline text-2xl dark:text-white text-on-surface mb-1">Reserve “{{ drop.title }}”</h2>
                        <p class="text-on-surface-variant text-sm mb-6">{{ formatAmount(drop.price, page.props) }} · paid now via M-PESA to secure your piece.</p>
                        <div class="space-y-4">
                            <input v-model="form.full_name" placeholder="Full name" class="w-full bg-black/5 dark:bg-white/5 border dark:border-white/10 border-black/10 rounded-xl px-4 py-3 dark:text-white text-on-surface outline-none focus:border-primary/50" />
                            <input v-model="form.phone" placeholder="M-PESA phone (07…)" class="w-full bg-black/5 dark:bg-white/5 border dark:border-white/10 border-black/10 rounded-xl px-4 py-3 dark:text-white text-on-surface outline-none focus:border-primary/50" />
                            <input v-model="form.email" type="email" placeholder="Email (for your receipt)" class="w-full bg-black/5 dark:bg-white/5 border dark:border-white/10 border-black/10 rounded-xl px-4 py-3 dark:text-white text-on-surface outline-none focus:border-primary/50" />
                            <input v-model="form.size" placeholder="Size / fit notes (optional)" class="w-full bg-black/5 dark:bg-white/5 border dark:border-white/10 border-black/10 rounded-xl px-4 py-3 dark:text-white text-on-surface outline-none focus:border-primary/50" />
                        </div>
                        <button @click="submitReserve" class="mt-6 w-full py-4 rounded-xl bg-primary text-background font-headline font-black uppercase tracking-widest text-sm hover:brightness-105 transition-all">
                            Continue to payment
                        </button>
                    </div>

                    <!-- Step 2: pay -->
                    <MpesaStkPanel v-else-if="step === 'pay' && !isPaid" :amount="drop.price" :phone="form.phone" :status="stkStatus" @cancel="closeReserve" />

                    <!-- Step 3: done -->
                    <MpesaSuccessPanel v-else-if="step === 'done'" :amount="drop.price" :orderId="orderId" :items="[]"
                        :transactionId="transactionId" :receiptUrl="receiptUrl" :reference="reference" :email="form.email" />
                </div>
            </div>
        </teleport>
    </StorefrontLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
