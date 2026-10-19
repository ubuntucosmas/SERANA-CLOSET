<!-- Payment problems (Phase C): refunds owed, orders waiting for a decision,
     amount mismatches and unexpected amounts, oldest first. -->
<script setup>
defineProps({
    items: { type: Array, default: () => [] },
    count: { type: Number, default: 0 },
});
const emit = defineEmits(['openOrder', 'go']);

const money = (v) => 'KSh ' + Number(v || 0).toLocaleString('en-KE');
const age = (d) => d === 0 ? 'today' : d === 1 ? '1 day' : `${d} days`;
const tone = { refund_due: 'text-amber-300', needs_resolution: 'text-rose-300', amount_mismatch: 'text-rose-300', unexpected_amount: 'text-orange-300' };
const icon = { refund_due: 'currency_exchange', needs_resolution: 'gavel', amount_mismatch: 'error', unexpected_amount: 'warning' };
function open(item) {
    if (item.kind === 'refund_due') emit('go', 'refunds');
    else emit('openOrder', item.id);
}
</script>

<template>
    <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-6 mb-5" aria-labelledby="payment-attention-title">
        <div class="flex items-center justify-between gap-3 mb-1">
            <h2 id="payment-attention-title" class="text-white font-headline text-lg sm:text-xl">Payment problems</h2>
            <span class="rounded-full px-3 py-1 text-sm font-semibold tabular-nums" :class="count ? 'bg-amber-500/15 text-amber-300' : 'bg-white/5 text-slate-400'" :aria-label="`${count} payment problems`">{{ count }}</span>
        </div>
        <p class="text-slate-500 text-sm mb-4">Refunds owed, orders waiting for your decision and payments for the wrong amount. Oldest first.</p>
        <p v-if="!items.length" class="text-sm text-slate-400">Nothing to sort out. Every payment matches its order.</p>
        <ul v-else class="space-y-2">
            <li v-for="item in items" :key="item.kind + item.id">
                <button type="button" @click="open(item)" class="w-full flex items-center justify-between gap-4 bg-slate-950/50 hover:bg-slate-950 rounded-xl px-4 py-3 text-left transition-colors">
                    <span class="flex items-center gap-3 min-w-0">
                        <span class="material-symbols-outlined shrink-0" :class="tone[item.kind]">{{ icon[item.kind] }}</span>
                        <span class="min-w-0">
                            <span class="block text-sm text-white truncate"><span :class="tone[item.kind]">{{ item.label }}</span> · {{ item.full_name || 'Customer' }}</span>
                            <span class="block text-xs text-slate-400 truncate">{{ item.detail }}</span>
                            <span class="block text-xs text-slate-500 font-mono">{{ item.reference }} · {{ age(item.age_days) }}</span>
                        </span>
                    </span>
                    <span class="text-slate-300 text-sm shrink-0 tabular-nums">{{ money(item.amount) }}</span>
                </button>
            </li>
        </ul>
    </section>
</template>
