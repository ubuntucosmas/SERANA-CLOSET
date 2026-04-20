<script setup>
import { ref } from 'vue';

const props = defineProps({
    amount: { type: [Number, String], required: true },
    phone: { type: String, default: '' },
    isProcessing: { type: Boolean, default: false }
});

const emit = defineEmits(['pay', 'cancel']);

const phoneNumber = ref(props.phone.replace('+254', '').trim());

function handleSubmit() {
    emit('pay', {
        amount: props.amount,
        phone: phoneNumber.value
    });
}
</script>

<template>
    <div class="mpesa-atelier bg-[#f8faf8] dark:bg-[#191c1b] text-[#191c1b] dark:text-[#f8faf8] font-body selection:bg-[#4fb33f]/30 rounded-3xl overflow-hidden shadow-2xl transition-all duration-700">
        <!-- Content Container -->
        <div class="p-8 md:p-12">
            <!-- Editorial Header -->
            <div class="mb-12 max-w-2xl">
                <h1 class="font-headline text-4xl font-extrabold tracking-tight text-[#016e00] dark:text-[#4fb33f] mb-4 leading-tight">Secure Payment<br/>Authorization</h1>
                <p class="text-[#3f4a3b] dark:text-[#becab6] text-sm leading-relaxed">Initiate an instant M-PESA transaction. Your funds will be moved securely via STK Push technology directly from your mobile device.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
                <!-- Left: Payment Form -->
                <section class="lg:col-span-7 bg-white dark:bg-[#1d211d] p-8 rounded-xl shadow-[0_24px_48px_-12px_rgba(25,28,27,0.04)] ring-1 ring-black/5 dark:ring-white/5">
                    <div class="flex items-center gap-3 mb-10">
                        <div class="w-12 h-12 bg-[#016e00]/10 rounded-xl flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#016e00] dark:text-[#4fb33f]">account_balance_wallet</span>
                        </div>
                        <div>
                            <h2 class="font-headline text-lg font-bold">M-PESA Credentials</h2>
                            <p class="text-[10px] text-[#3f4a3b] dark:text-[#becab6] uppercase tracking-widest font-black">Secure STK Protocol</p>
                        </div>
                    </div>

                    <form @submit.prevent="handleSubmit" class="space-y-8">
                        <!-- Amount (Read Only for Checkout) -->
                        <div class="group">
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-[#3f4a3b] dark:text-[#becab6] mb-3 ml-1">Payment Amount</label>
                            <div class="relative">
                                <span class="absolute left-6 top-1/2 -translate-y-1/2 font-headline font-bold text-xl text-[#3f4a3b]/40">KES</span>
                                <div class="w-full pl-20 pr-6 py-5 bg-[#f2f4f2] dark:bg-[#191c1b] rounded-xl font-headline text-3xl font-extrabold text-[#191c1b] dark:text-white flex items-center">
                                    {{ Number(amount).toLocaleString() }}
                                </div>
                            </div>
                        </div>

                        <!-- Phone Number -->
                        <div class="group">
                            <label class="block text-[10px] font-bold uppercase tracking-widest text-[#3f4a3b] dark:text-[#becab6] mb-3 ml-1">Phone Number</label>
                            <div class="flex gap-4">
                                <div class="w-24 px-4 py-4 bg-[#f2f4f2] dark:bg-[#191c1b] rounded-xl flex items-center justify-center font-body font-bold text-[#191c1b] dark:text-white border border-transparent">
                                    +254
                                </div>
                                <input v-model="phoneNumber" 
                                       type="tel" 
                                       placeholder="700 000 000"
                                       class="flex-1 px-6 py-4 bg-[#f2f4f2] dark:bg-[#191c1b] border-none rounded-xl font-body text-lg font-medium text-[#191c1b] dark:text-white focus:ring-1 focus:ring-[#016e00] focus:bg-white dark:focus:bg-black transition-all placeholder:text-[#becab6]/50" />
                            </div>
                        </div>

                        <!-- CTA -->
                        <button :disabled="isProcessing"
                                class="w-full bg-[#016e00] hover:bg-[#028a00] text-white font-headline text-lg font-bold py-5 rounded-xl transition-all active:scale-95 flex items-center justify-center gap-3 shadow-lg shadow-[#016e00]/20 disabled:opacity-50 disabled:grayscale">
                            <span>{{ isProcessing ? 'Initializing...' : 'Pay with M-PESA' }}</span>
                            <span v-if="!isProcessing" class="material-symbols-outlined">arrow_forward</span>
                            <div v-else class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                        </button>
                    </form>

                    <!-- Trust Bar -->
                    <div class="mt-8 flex items-center justify-center gap-4 py-4 border-t border-[#f2f4f2] dark:border-white/5">
                        <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-tighter text-[#3f4a3b]/40">
                            <span class="material-symbols-outlined text-[14px]">lock</span>
                            <span>Encrypted</span>
                        </div>
                        <div class="w-1 h-1 bg-[#becab6] rounded-full"></div>
                        <div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-tighter text-[#3f4a3b]/40">
                            <span class="material-symbols-outlined text-[14px]">verified_user</span>
                            <span>PCI DSS</span>
                        </div>
                        <div class="w-1 h-1 bg-[#becab6] rounded-full"></div>
                        <button @click="$emit('cancel')" class="text-[10px] font-bold uppercase tracking-widest text-[#ab2a65] hover:underline transition-all">
                            Change Method
                        </button>
                    </div>
                </section>

                <!-- Right: Process Info -->
                <aside class="lg:col-span-5 space-y-6">
                    <div class="bg-[#f2f4f2] dark:bg-[#1d211d] p-8 rounded-xl ring-1 ring-black/5 dark:ring-white/5">
                        <h3 class="font-headline text-lg font-bold mb-6">How it works</h3>
                        <div class="space-y-8">
                            <div class="flex gap-6 group">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[#016e00] text-white flex items-center justify-center font-bold text-sm shadow-lg shadow-[#016e00]/20">1</div>
                                <div>
                                    <p class="font-bold text-sm mb-1">Initiate Request</p>
                                    <p class="text-[11px] text-[#3f4a3b] dark:text-[#becab6] leading-relaxed">Fill in your M-PESA number and click 'Pay' to trigger the secure request.</p>
                                </div>
                            </div>
                            <div class="flex gap-6 group">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[#016e00] text-white flex items-center justify-center font-bold text-sm shadow-lg shadow-[#016e00]/20">2</div>
                                <div>
                                    <p class="font-bold text-sm mb-1">Check Your Phone</p>
                                    <p class="text-[11px] text-[#3f4a3b] dark:text-[#becab6] leading-relaxed">A prompt will automatically appear on your phone asking for your M-PESA PIN.</p>
                                </div>
                            </div>
                            <div class="flex gap-6 group">
                                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-[#016e00] text-white flex items-center justify-center font-bold text-sm shadow-lg shadow-[#016e00]/20">3</div>
                                <div>
                                    <p class="font-bold text-sm mb-1">Confirm Transaction</p>
                                    <p class="text-[11px] text-[#3f4a3b] dark:text-[#becab6] leading-relaxed">Enter your PIN to authorize. You will receive a confirmation instantly.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Graphic Card -->
                    <div class="relative overflow-hidden bg-[#016e00] rounded-xl h-48 flex items-center justify-center p-8 text-center group">
                        <div class="absolute inset-0 bg-[url('https://lh3.googleusercontent.com/aida-public/AB6AXuCmf5J7gIg1XObGEUmXMcWbj8FANTbx60Pxvs2ooXG32EpRFsgjf93l9R53TBRcQfqv9DpsHKUh6wzViRU1dRV2eFFJH5jsz9T0EV5QSqLEV8nCDMLoXngUoN9a0GwugkDnQtjR-5sv7VwVtxta3m8C4sIfmz2qQO9fEzHkheNDAJdAbS-HcggJ_loZo7sJRmmH_dEjgBMLK0PmSgDFLTWMQlF6hhV-W7mM17bJK0CGWaoQzZDukgVFPK2Nse-i8P2arsVEbdmPHi0')] bg-cover bg-center opacity-30 mix-blend-overlay group-hover:scale-110 transition-transform duration-[2000ms]"></div>
                        <div class="relative z-10">
                            <span class="material-symbols-outlined text-white text-4xl mb-3" style="font-variation-settings: 'FILL' 1;">security</span>
                            <h4 class="text-white font-headline text-lg font-bold">Safe & Seamless</h4>
                            <p class="text-white/60 text-[10px] uppercase tracking-widest mt-1 font-black">Financial Atelier Protocol</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-headline { font-family: 'Manrope', sans-serif; }
.font-body { font-family: 'Inter', sans-serif; }
</style>
