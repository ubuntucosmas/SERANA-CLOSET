<!-- Studio → Till (Phase E, audit P1-4): every drawer, newest first. Who
     opened and closed it, what was expected and counted, any difference and
     why, and its Z report. Owners and managers; the till itself is at
     "Open the till". -->
<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    tills: { type: Object, default: null },
});
onMounted(() => { if (!props.tills) router.reload({ only: ['tills'], data: { tab: 'tills' } }); });

const open = ref(null);
const ksh = (n) => 'KSh ' + Number(n || 0).toLocaleString('en-KE');
const methodLabel = { cash: 'Cash', mpesa_manual: 'M-Pesa code', mpesa_stk: 'M-Pesa prompt', credit: 'Exchange credit' };
const byMethod = (m) => Object.entries(m || {}).map(([k, v]) => `${methodLabel[k] || k} ${ksh(v)}`).join(' · ') || 'none';
function difference(s) {
    const v = s.report.variance;
    if (v === null || v === undefined) return 'Open now';
    return v === 0 ? 'Balanced' : v < 0 ? `${ksh(-v)} short` : `${ksh(v)} over`;
}
</script>

<template>
    <div class="space-y-4">
        <p v-if="!tills" class="text-sm text-slate-400" role="status">Loading the till history…</p>
        <template v-else>
            <p class="text-sm text-slate-400">A difference over {{ ksh(tills.variance_alert) }} needs a reason and a manager’s PIN at closing. Change the alert in Staff → The till.</p>
            <p v-if="!tills.sessions.length" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-6 text-sm text-slate-400">No drawers yet. The first one opens at the till.</p>
            <ul class="space-y-3" aria-label="Till sessions">
                <li v-for="s in tills.sessions" :key="s.id" class="bg-slate-900/60 ring-1 rounded-2xl" :class="s.over_alert ? 'ring-amber-400/40' : 'ring-white/10'">
                    <button type="button" @click="open = open === s.id ? null : s.id" :aria-expanded="open === s.id" class="w-full p-4 flex flex-wrap items-center justify-between gap-3 text-left">
                        <span class="min-w-0">
                            <span class="block text-white font-medium">{{ s.register }} · {{ s.report.opened_at }}<template v-if="s.report.closed_at"> → {{ s.report.closed_at }}</template></span>
                            <span class="block text-xs text-slate-400">Opened by {{ s.opened_by }}<template v-if="s.closed_by"> · closed by {{ s.closed_by }}</template> · {{ s.report.sales_count }} sale(s) paid</span>
                        </span>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold"
                            :class="s.status === 'open' ? 'bg-[#57d5fc]/15 text-[#57d5fc]' : s.over_alert ? 'bg-amber-500/15 text-amber-300' : s.report.variance === 0 ? 'bg-emerald-500/15 text-emerald-300' : 'bg-white/5 text-slate-300'">
                            {{ difference(s) }}
                        </span>
                    </button>
                    <div v-if="open === s.id" class="border-t border-white/10 p-4">
                        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-2 text-sm">
                            <div><dt class="text-xs text-slate-400">Float</dt><dd class="tabular-nums text-white">{{ ksh(s.report.opening_float) }}</dd></div>
                            <div><dt class="text-xs text-slate-400">Expected</dt><dd class="tabular-nums text-white">{{ ksh(s.report.expected_cash) }}</dd></div>
                            <div><dt class="text-xs text-slate-400">Counted</dt><dd class="tabular-nums text-white">{{ s.report.counted_cash === null ? '—' : ksh(s.report.counted_cash) }}</dd></div>
                            <div><dt class="text-xs text-slate-400">Difference</dt><dd class="tabular-nums text-white">{{ difference(s) }}</dd></div>
                            <div><dt class="text-xs text-slate-400">Cash sales</dt><dd class="tabular-nums">{{ ksh(s.report.cash.sale) }}</dd></div>
                            <div><dt class="text-xs text-slate-400">Cash refunds</dt><dd class="tabular-nums">{{ ksh(s.report.cash.refund) }}</dd></div>
                            <div><dt class="text-xs text-slate-400">Paid out</dt><dd class="tabular-nums">{{ ksh(s.report.cash.payout) }}</dd></div>
                            <div><dt class="text-xs text-slate-400">To the bank</dt><dd class="tabular-nums">{{ ksh(s.report.cash.pickup) }}</dd></div>
                        </dl>
                        <p class="mt-3 text-sm text-slate-300">Taken: {{ byMethod(s.report.sales_by_method) }}</p>
                        <p class="text-sm text-slate-300">Refunded: {{ byMethod(s.report.refunds_by_method) }}</p>
                        <p class="text-sm text-slate-400">{{ s.report.voids }} void(s) · discounts {{ ksh(s.report.discounts) }}</p>
                        <p v-if="s.report.variance_reason" class="mt-2 text-sm text-amber-300">“{{ s.report.variance_reason }}”<template v-if="s.acknowledged_by"> · approved by {{ s.acknowledged_by }}</template></p>
                        <p v-if="s.report.unfinished?.length" class="mt-2 text-sm text-amber-300">Still owed, part paid: {{ s.report.unfinished.map(u => `${u.reference} (${ksh(u.balance_due)})`).join(', ') }}</p>
                    </div>
                </li>
            </ul>
        </template>
    </div>
</template>
