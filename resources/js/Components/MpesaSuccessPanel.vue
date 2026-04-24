<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';
import { computed } from 'vue';

const page = usePage();
const props = defineProps({
    amount: { type: [Number, String], required: true },
    orderId: { type: [Number, String], required: true },
    items: { type: Array, default: () => [] },
    transactionId: { type: String, required: true },
    date: { type: String, default: () => new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }
});

const { formatAmount } = useCurrency();

function shareToWhatsApp() {
    const orderRef = `ORD-${String(props.orderId).substring(0, 8).toUpperCase()}`;
    const productList = props.items.map(item => `• ${item.name} (x${item.quantity})`).join('\n');
    
    const message = `*PAYMENT CONFIRMED — SERANA ATELIER*\n\n` +
                  `I have successfully paid for Order *#${orderRef}* via M-Pesa.\n\n` +
                  `*TRANSACTION DETAILS*\n` +
                  `• Status: Paid\n` +
                  `• Amount: ${formatAmount(props.amount, page.props)}\n` +
                  `• M-Pesa ID: ${props.transactionId}\n` +
                  `• Date: ${props.date}\n\n` +
                  `*PURCHASED ITEMS*\n` +
                  `${productList}\n\n` +
                  `Please proceed with production allocation.`;
    
    const whatsappNumber = page.props.whatsapp_number || '254712345678';
    const url = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;
    window.open(url, '_blank');
}

function downloadReceipt() {
    window.location.href = route('orders.receipt', props.orderId);
}
</script>

<template>
    <div class="mpesa-success-atelier w-full max-w-5xl mx-auto">
        <!-- Transaction Canvas -->
        <div class="bg-white dark:bg-[#1d211d] rounded-[2rem] shadow-[0_24px_48px_-12px_rgba(25,28,27,0.04)] ring-1 ring-black/5 dark:ring-white/5 overflow-hidden reveal">
            
            <!-- Success Header -->
            <div class="p-6 md:p-12 text-center bg-gradient-to-br from-[#016e00] to-[#4fb33f] text-white">
                <div class="inline-flex items-center justify-center w-16 h-16 md:w-20 md:h-20 bg-white/20 backdrop-blur-md rounded-full mb-4 md:mb-6">
                    <span class="material-symbols-outlined text-3xl md:text-4xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                </div>
                <h1 class="text-2xl md:text-4xl font-extrabold tracking-tight mb-1 md:mb-2 leading-tight">Payment Confirmed</h1>
                <p class="font-body opacity-90 text-sm md:text-lg tracking-wide">M-PESA STK Push Successful</p>
            </div>

            <!-- Transaction Details Bento Grid -->
            <div class="p-8 md:p-12 space-y-8">
                <!-- Amount Highlight -->
                <div class="bg-[#f2f4f2] dark:bg-[#191c1b] rounded-2xl p-6 md:p-8 text-center ring-1 ring-black/5 dark:ring-white/5">
                    <span class="block text-[10px] font-bold uppercase tracking-[0.2em] text-[#3f4a3b] dark:text-[#becab6] mb-1">Transaction Amount</span>
                    <span class="font-headline text-4xl md:text-5xl font-extrabold text-[#016e00] dark:text-[#4fb33f] tracking-tighter">
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

                    <!-- Purchased Items Summary (New) -->
                    <div v-if="items.length > 0" class="md:col-span-2 bg-[#f2f4f2]/30 dark:bg-[#191c1b]/30 p-6 rounded-2xl ring-1 ring-black/5 dark:ring-white/5 space-y-4">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-[#3f4a3b] dark:text-[#becab6] block">Purchased Garments</span>
                        <div class="space-y-3">
                            <div v-for="item in items" :key="item.id" class="flex justify-between items-center text-sm font-body">
                                <span class="text-on-surface/80">
                                    <span class="font-bold text-[#016e00]">x{{ item.quantity }}</span> {{ item.name }}
                                </span>
                                <span class="font-bold">{{ formatAmount(item.price * item.quantity) }}</span>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- Action Cluster -->
                <div class="grid grid-cols-1 md:flex md:flex-row gap-3 md:gap-4 pt-2 md:pt-4">
                    <button @click="shareToWhatsApp" class="w-full md:flex-1 bg-[#25D366] text-white font-headline font-bold py-4 px-6 rounded-xl hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2 shadow-lg shadow-[#25D366]/20 text-xs md:text-sm">
                        <svg class="w-4 h-4 md:w-5 md:h-5 fill-current" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                        WhatsApp
                    </button>
                    <button @click="downloadReceipt" class="flex-1 bg-black/5 dark:bg-white/10 text-on-surface font-headline font-bold py-4 px-6 rounded-xl hover:bg-opacity-80 active:scale-95 transition-all flex items-center justify-center gap-2 border border-outline-variant/10 text-xs md:text-sm">
                        <span class="material-symbols-outlined text-sm md:text-base">receipt</span>
                        Receipt
                    </button>
                    <Link :href="route('home')" class="flex-1 bg-black/5 dark:bg-white/10 text-on-surface font-headline font-bold py-4 px-6 rounded-xl hover:bg-opacity-80 active:scale-95 transition-all flex items-center justify-center gap-2 border border-outline-variant/10 text-xs md:text-sm">
                        <span class="material-symbols-outlined text-sm md:text-base">home</span>
                        Return
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
.reveal {
    animation: reveal-up 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes reveal-up {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
