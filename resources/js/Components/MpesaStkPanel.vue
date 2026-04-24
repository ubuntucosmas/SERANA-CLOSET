<script setup>
import { ref, watch, onUnmounted } from 'vue';

const props = defineProps({
    amount: { type: [Number, String], required: true },
    phone: { type: String, default: '' },
    isProcessing: { type: Boolean, default: false },
    isWaiting: { type: Boolean, default: false }
});

const emit = defineEmits(['pay', 'cancel']);

// Phase: 'idle' | 'processing' | 'waiting'
const phase = ref('idle');
const phoneNumber = ref(props.phone.replace('+254', '').trim());
const pulseStep = ref(0);

let pulseTimer = null;

watch(() => props.isProcessing, (val) => {
    if (val) phase.value = 'processing';
});

watch(() => props.isWaiting, (val) => {
    if (val) {
        phase.value = 'waiting';
        startPulse();
    }
});

function startPulse() {
    pulseTimer = setInterval(() => {
        pulseStep.value = (pulseStep.value + 1) % 4;
    }, 800);
}

onUnmounted(() => clearInterval(pulseTimer));

function handleSubmit() {
    if (!phoneNumber.value) return;
    emit('pay', { amount: props.amount, phone: phoneNumber.value });
}

const trustPoints = [
    { icon: 'enhanced_encryption', text: '256-bit SSL Encryption' },
    { icon: 'verified_user', text: 'PCI DSS Compliant' },
    { icon: 'shield', text: 'Safaricom Daraja Certified' },
    { icon: 'fingerprint', text: 'PIN-Secured Transaction' },
];

const steps = [
    { label: 'Request Sent', sub: 'Secure channel established' },
    { label: 'Check Your Phone', sub: 'M-PESA prompt delivered' },
    { label: 'Enter Your PIN', sub: 'Authorize with your secret PIN' },
    { label: 'Confirming', sub: 'Verifying with Safaricom...' },
];
</script>

<template>
    <!-- PHASE: IDLE — the main input form -->
    <div v-if="phase === 'idle'" class="stk-panel relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-[#0a151f] to-[#0d1f0d] shadow-[0_60px_120px_rgba(0,0,0,0.8)]">
        <!-- Scanning line -->
        <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-transparent via-[#4fb33f]/60 to-transparent animate-scan-x z-10"></div>

        <!-- Header -->
        <div class="relative p-8 border-b border-white/5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="relative w-12 h-12 flex items-center justify-center">
                        <div class="absolute inset-0 bg-[#4fb33f]/20 rounded-xl animate-pulse"></div>
                        <span class="material-symbols-outlined text-[#4fb33f] text-2xl relative z-10">smartphone</span>
                    </div>
                    <div>
                    <h2 class="text-white font-headline font-black text-lg tracking-tight">M-PESA Secure Payment</h2>
                    <p class="text-[10px] text-blue-400 uppercase tracking-[0.3em] font-black">Daraja API · Encrypted Channel</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-[#4fb33f] animate-pulse"></div>
                    <span class="text-[9px] text-white/30 uppercase tracking-widest font-black">Live</span>
                </div>
            </div>
        </div>

        <!-- Amount Display -->
        <div class="p-8 border-b border-white/5">
            <p class="text-[9px] text-white/30 uppercase tracking-[0.4em] font-black mb-4">Order Value</p>
            <div class="flex items-baseline gap-3">
                <span class="text-white/30 font-headline text-xl font-bold">KSh</span>
                <span class="text-white font-headline font-black text-6xl tracking-tighter leading-none">{{ Number(amount).toLocaleString() }}</span>
            </div>
            <p class="text-[9px] text-white/20 mt-3 font-mono uppercase tracking-widest">Serana Atelier · Premium Delivery Included</p>
        </div>

        <!-- Phone Input -->
        <div class="p-8 space-y-6">
            <div>
                <label class="block text-[9px] text-white/30 uppercase tracking-[0.4em] font-black mb-4">M-PESA Registered Number</label>
                <div class="flex gap-3">
                    <div class="w-20 flex items-center justify-center bg-[#4fb33f]/10 border border-[#4fb33f]/20 rounded-xl text-[#4fb33f] font-mono font-bold text-sm">+254</div>
                    <input
                        v-model="phoneNumber"
                        type="tel"
                        placeholder="7XX XXX XXX"
                        class="flex-1 bg-white/5 border border-white/10 hover:border-[#4fb33f]/30 focus:border-[#4fb33f]/60 focus:ring-0 rounded-xl px-5 py-4 text-white font-mono text-lg placeholder:text-white/20 transition-all outline-none"
                        @keydown.enter="handleSubmit"
                    />
                </div>
            </div>

            <!-- Trust Badges -->
            <div class="grid grid-cols-2 gap-2">
                <div v-for="trust in trustPoints" :key="trust.icon"
                    class="flex items-center gap-2 p-3 bg-white/[0.02] border border-white/5 rounded-lg">
                    <span class="material-symbols-outlined text-[#4fb33f] text-sm opacity-60">{{ trust.icon }}</span>
                    <span class="text-[9px] font-black text-white/20 uppercase tracking-widest leading-tight">{{ trust.text }}</span>
                </div>
            </div>

            <!-- CTA -->
            <button
                @click="handleSubmit"
                :disabled="!phoneNumber || isProcessing"
                class="w-full relative overflow-hidden group py-5 rounded-2xl font-headline font-black text-sm uppercase tracking-[0.2em] transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                :class="phoneNumber ? 'bg-[#4fb33f] text-black shadow-[0_0_40px_rgba(79,179,63,0.4)] hover:shadow-[0_0_60px_rgba(79,179,63,0.6)] hover:scale-[1.02]' : 'bg-white/10 text-white/40'"
            >
                <span class="relative z-10 flex items-center justify-center gap-3">
                    <span class="material-symbols-outlined text-lg">lock</span>
                    Authorize Secure Payment
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </span>
                <div v-if="phoneNumber" class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
            </button>

            <!-- Cancel -->
            <button @click="$emit('cancel')" class="w-full text-center text-[10px] uppercase tracking-widest text-white/20 hover:text-white/50 transition-colors py-2 font-black">
                ← Change Payment Method
            </button>
        </div>
    </div>

    <!-- PHASE: PROCESSING — request is being sent -->
    <div v-else-if="phase === 'processing'" class="stk-panel relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-[#0a1a0a] to-[#0d1f0d] shadow-[0_60px_120px_rgba(0,0,0,0.8)] flex flex-col items-center justify-center py-20 px-10 text-center min-h-[400px]">
        <div class="absolute inset-0 bg-[#4fb33f]/3 animate-pulse"></div>

        <!-- Animated Spinner -->
        <div class="relative w-24 h-24 mb-8">
            <div class="absolute inset-0 border-2 border-[#4fb33f]/20 rounded-full"></div>
            <div class="absolute inset-0 border-t-2 border-[#4fb33f] rounded-full animate-spin"></div>
            <div class="absolute inset-3 border-t border-[#4fb33f]/40 rounded-full animate-spin" style="animation-duration:1.5s;animation-direction:reverse"></div>
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="material-symbols-outlined text-[#4fb33f] text-2xl" style="font-variation-settings:'FILL' 1">lock</span>
            </div>
        </div>

        <h3 class="text-white font-headline font-medium text-2xl mb-3 uppercase tracking-tight">Initiating Secure Session</h3>
        <p class="text-white/60 text-sm font-light max-w-xs leading-relaxed">Establishing encrypted channel with Safaricom Daraja API. This takes a moment.</p>

        <!-- Animated Dots -->
        <div class="flex gap-2 mt-8">
            <div class="w-2 h-2 rounded-full bg-[#4fb33f] animate-bounce" style="animation-delay:0ms"></div>
            <div class="w-2 h-2 rounded-full bg-[#4fb33f] animate-bounce" style="animation-delay:200ms"></div>
            <div class="w-2 h-2 rounded-full bg-[#4fb33f] animate-bounce" style="animation-delay:400ms"></div>
        </div>
    </div>

    <!-- PHASE: WAITING — STK dispatched, user needs to check phone -->
    <div v-else-if="phase === 'waiting'" class="stk-panel relative overflow-hidden rounded-3xl border border-rose-500/30 bg-gradient-to-br from-[#1a0a0a] to-[#0d1f0d] shadow-[0_0_80px_rgba(225,29,72,0.2)]">
        <!-- Top scanning line - Ruby -->
        <div class="absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-transparent via-rose-500 to-transparent animate-scan-x z-10"></div>

        <!-- Success Header -->
        <div class="relative p-8 border-b border-[#4fb33f]/10 text-center">
            <!-- Pulsing phone graphic -->
            <div class="relative inline-flex items-center justify-center">
                <div class="absolute w-20 h-20 border border-rose-500/20 rounded-full animate-ping"></div>
                <div class="absolute w-28 h-28 border border-rose-500/10 rounded-full animate-ping" style="animation-duration:2s"></div>
                <div class="relative w-16 h-16 bg-rose-500/15 border border-rose-500/30 rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-rose-500 text-3xl" style="font-variation-settings:'FILL' 1">smartphone</span>
                </div>
            </div>

            <h2 class="text-white font-headline font-light text-2xl mt-6 mb-2 uppercase tracking-wide">Check Your Phone!</h2>
            <p class="text-[#4fb33f] text-sm font-medium uppercase tracking-widest">M-PESA PIN Prompt Incoming</p>
        </div>

        <!-- Body -->
        <div class="p-8 space-y-8">
            <!-- Amount reminder -->
            <div class="flex justify-between items-center p-5 bg-[#4fb33f]/5 border border-[#4fb33f]/15 rounded-2xl">
                <div>
                    <p class="text-[9px] text-white/40 uppercase tracking-widest font-medium mb-1">Authorization Required</p>
                    <p class="text-white font-headline font-light text-2xl">KSh {{ Number(amount).toLocaleString() }}</p>
                </div>
                <div class="text-right">
                    <p class="text-[9px] text-white/30 uppercase tracking-widest font-black mb-1">To</p>
                    <p class="text-white/80 font-black text-sm">Serana Atelier</p>
                </div>
            </div>

            <!-- Step Progress -->
            <div class="space-y-4">
                <p class="text-[9px] text-white/20 uppercase tracking-[0.4em] font-black">Transaction Pipeline</p>
                <div class="space-y-3">
                    <div v-for="(step, i) in steps" :key="i" class="flex items-center gap-4 group">
                        <!-- Step indicator -->
                        <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center transition-all duration-500"
                            :class="i < pulseStep ? 'bg-[#4fb33f] shadow-[0_0_15px_rgba(79,179,63,0.5)]' : i === pulseStep ? 'bg-[#4fb33f]/20 border-2 border-[#4fb33f] animate-pulse' : 'bg-white/5 border border-white/10'">
                            <span v-if="i < pulseStep" class="material-symbols-outlined text-black text-sm">check</span>
                            <span v-else class="text-[10px] font-black" :class="i === pulseStep ? 'text-[#4fb33f]' : 'text-white/20'">{{ i + 1 }}</span>
                        </div>
                        <!-- Step text -->
                        <div class="transition-all duration-500" :class="i <= pulseStep ? 'opacity-100' : 'opacity-30'">
                            <p class="text-white font-black text-sm" :class="i === pulseStep ? 'text-[#4fb33f]' : ''">{{ step.label }}</p>
                            <p class="text-white/30 text-[10px] mt-0.5">{{ step.sub }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instructional Callout -->
            <div class="p-5 bg-white/[0.02] border border-white/5 rounded-2xl flex gap-4">
                <span class="material-symbols-outlined text-amber-400 flex-shrink-0 mt-0.5" style="font-variation-settings:'FILL' 1">info</span>
                <div class="text-[11px] text-white/40 leading-relaxed">
                    A <strong class="text-white/70">Safaricom M-PESA notification</strong> has been sent to your phone. Open it, enter your <strong class="text-white/70">M-PESA PIN</strong>, and your order will be confirmed instantly.
                </div>
            </div>

            <!-- Polling indicator -->
            <div class="flex items-center justify-center gap-3">
                <div class="w-1.5 h-1.5 rounded-full bg-[#4fb33f] animate-pulse"></div>
                <p class="text-[10px] text-white/40 uppercase tracking-widest font-medium">Listening for confirmation...</p>
                <div class="w-1.5 h-1.5 rounded-full bg-[#4fb33f] animate-pulse" style="animation-delay:500ms"></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes scan-x {
    0% { transform: translateX(-100%); opacity: 0; }
    50% { opacity: 1; }
    100% { transform: translateX(100%); opacity: 0; }
}
.animate-scan-x { animation: scan-x 3s ease-in-out infinite; }
</style>
