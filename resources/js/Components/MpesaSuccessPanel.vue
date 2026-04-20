<script setup>
import { Link } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    amount: { type: [Number, String], required: true },
    orderId: { type: [Number, String], required: true },
    transactionId: { type: String, default: 'RKH' + Math.random().toString(36).substring(2, 9).toUpperCase() },
    date: { type: String, default: () => new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }
});

const { formatAmount } = useCurrency();
</script>

<template>
    <div class="mpesa-success-atelier w-full max-w-5xl mx-auto">
        <!-- Transaction Canvas -->
        <div class="bg-white dark:bg-[#1d211d] rounded-[2rem] shadow-[0_24px_48px_-12px_rgba(25,28,27,0.04)] ring-1 ring-black/5 dark:ring-white/5 overflow-hidden reveal">
            
            <!-- Success Header -->
            <div class="p-8 md:p-12 text-center bg-gradient-to-br from-[#016e00] to-[#4fb33f] text-white">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 backdrop-blur-md rounded-full mb-6">
                    <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                </div>
                <h1 class="font-headline text-3xl md:text-4xl font-extrabold tracking-tight mb-2">Payment Confirmed</h1>
                <p class="font-body opacity-90 text-lg">M-PESA STK Push Successful</p>
            </div>

            <!-- Transaction Details Bento Grid -->
            <div class="p-8 md:p-12 space-y-8">
                <!-- Amount Highlight -->
                <div class="bg-[#f2f4f2] dark:bg-[#191c1b] rounded-2xl p-8 text-center ring-1 ring-black/5 dark:ring-white/5">
                    <span class="block text-[10px] font-bold uppercase tracking-[0.2em] text-[#3f4a3b] dark:text-[#becab6] mb-2">Transaction Amount</span>
                    <span class="font-headline text-5xl font-extrabold text-[#016e00] dark:text-[#4fb33f] tracking-tighter">
                        {{ formatAmount(amount) }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- ID Card -->
                    <div class="bg-[#f2f4f2]/50 dark:bg-[#191c1b]/50 p-6 rounded-2xl hover:bg-[#e1e3e1] dark:hover:bg-black transition-colors group">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-[#3f4a3b] dark:text-[#becab6] mb-1 block">Transaction ID</span>
                        <span class="font-body font-semibold text-[#191c1b] dark:text-white group-hover:text-[#016e00] transition-colors uppercase tracking-wider">{{ transactionId }}</span>
                    </div>

                    <!-- Date Card -->
                    <div class="bg-[#f2f4f2]/50 dark:bg-[#191c1b]/50 p-6 rounded-2xl hover:bg-[#e1e3e1] dark:hover:bg-black transition-colors group">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-[#3f4a3b] dark:text-[#becab6] mb-1 block">Payment Date</span>
                        <span class="font-body font-semibold text-[#191c1b] dark:text-white group-hover:text-[#016e00] transition-colors">{{ date }}</span>
                    </div>

                    <!-- Recipient Card -->
                    <div class="bg-[#f2f4f2]/50 dark:bg-[#191c1b]/50 p-6 rounded-2xl hover:bg-[#e1e3e1] dark:hover:bg-black transition-colors group md:col-span-2 flex items-center justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-[#3f4a3b] dark:text-[#becab6] mb-1 block">Sent To</span>
                            <span class="font-body font-semibold text-[#191c1b] dark:text-white group-hover:text-[#016e00] transition-colors">Serana Atelier Ltd.</span>
                        </div>
                        <div class="w-12 h-12 bg-white dark:bg-[#1d211d] rounded-xl flex items-center justify-center ring-1 ring-black/5 dark:ring-white/10 shadow-sm">
                            <span class="material-symbols-outlined text-[#016e00] dark:text-[#4fb33f]">business</span>
                        </div>
                    </div>
                </div>

                <!-- Action Cluster -->
                <div class="flex flex-col md:flex-row gap-4 pt-4">
                    <button class="flex-1 bg-[#016e00] text-white font-headline font-bold py-4 px-8 rounded-xl hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-[#016e00]/20">
                        <span class="material-symbols-outlined text-sm">receipt</span>
                        Download Receipt
                    </button>
                    <Link :href="route('home')" class="flex-1 bg-[#4fb33f]/10 dark:bg-[#4fb33f]/20 text-[#016e00] dark:text-[#4fb33f] font-headline font-bold py-4 px-8 rounded-xl hover:bg-opacity-80 active:scale-95 transition-all flex items-center justify-center gap-2 border border-[#016e00]/10">
                        <span class="material-symbols-outlined text-sm">home</span>
                        Back to Atelier
                    </Link>
                </div>
            </div>

            <!-- Footer Transparency Note -->
            <div class="px-8 pb-8 text-center">
                <p class="text-[#3f4a3b] dark:text-[#becab6] text-[10px] font-bold uppercase tracking-widest opacity-60">
                    Secure Protocol: 256-bit SSL encrypted. 
                    Reference: ORD-{{ String(orderId).substring(0, 8).toUpperCase() }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.font-headline { font-family: 'Manrope', sans-serif; }
.font-body { font-family: 'Inter', sans-serif; }
.reveal {
    animation: reveal-up 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes reveal-up {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
