<!-- Payment problems (Phase C): refunds owed, orders waiting for a decision,
     amount mismatches and unexpected amounts, oldest first. -->
<script setup>
import { ref, computed } from 'vue';
const props = defineProps({
    items: { type: Array, default: () => [] },
    count: { type: Number, default: 0 },
});
const emit = defineEmits(['openOrder', 'go']);
const expanded = ref(false);
const visibleItems = computed(() => expanded.value ? props.items : props.items.slice(0, 5));

const money = (v) => 'KSh ' + Number(v || 0).toLocaleString('en-KE');
const age = (d) => d === 0 ? 'today' : d === 1 ? '1 day' : `${d} days`;
// Shades the Studio light theme remaps for contrast (app.css).
const tone = { refund_due: 'text-amber-400', needs_resolution: 'text-rose-300', amount_mismatch: 'text-rose-300', unexpected_amount: 'text-amber-400' };
// Only icons in the subset loaded by app.blade.php.
const icon = { refund_due: 'paid', needs_resolution: 'hourglass_top', amount_mismatch: 'warning', unexpected_amount: 'warning' };
function open(item) {
    if (item.kind === 'refund_due') emit('go', 'refunds');
    else emit('openOrder', item.id);
}
</script>

<template>
    <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-6 mb-5" aria-labelledby="payment-attention-title">
        <div class="flex items-center justify-between gap-3 mb-1">
            <h2 id="payment-attention-title" class="text-white font-headline text-lg sm:text-xl">Financial exceptions</h2>
            <span class="rounded-full px-3 py-1 text-sm font-semibold tabular-nums" :class="count ? 'bg-amber-500/15 text-amber-400' : 'bg-white/5 text-slate-400'" :aria-label="`${count} payment problems`">{{ count }}</span>
        </div>
        <p class="text-slate-500 text-sm mb-4">Refunds owed, orders waiting for your decision and payments for the wrong amount. Oldest first.</p>
        <p v-if="!items.length" class="text-sm text-slate-400">No unresolved payment exceptions in this snapshot.</p>
        <ul v-else class="space-y-2">
            <li v-for="(item, index) in visibleItems" :key="item.kind + ':' + item.id + ':' + index">
                <button type="button" @click="open(item)" class="w-full flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-950/50 hover:bg-slate-950 rounded-xl px-4 py-3 text-left transition-colors">
                    <span class="flex items-center gap-3 min-w-0">
                        <span class="material-symbols-outlined shrink-0" :class="tone[item.kind]">{{ icon[item.kind] }}</span>
                        <span class="min-w-0">
                            <span class="block text-sm text-white break-words"><span :class="tone[item.kind]">{{ item.label }}</span> · {{ item.full_name || 'Customer' }}</span>
                            <span class="block text-xs text-slate-400 whitespace-normal">{{ item.detail }}</span>
                            <span class="block text-xs text-slate-500 font-mono">{{ item.reference }} · {{ age(item.age_days) }}</span>
                        </span>
                    </span>
                    <span class="text-slate-300 text-sm shrink-0 tabular-nums">{{ money(item.amount) }}<span class="block text-xs text-cyan-300 mt-1">{{ item.kind === 'refund_due' ? 'Open refunds →' : 'Review order →' }}</span></span>
                </button>
            </li>
        </ul>
        <button v-if="items.length > 5" type="button" @click="expanded = !expanded" :aria-expanded="expanded" class="table-action mt-3">{{ expanded ? 'Show fewer cases' : `Show all ${items.length} cases` }}</button>
    </section>
</template>
