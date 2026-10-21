<!-- Studio → Till (Phase E, audit P1-4): every drawer, newest first. Who
     opened and closed it, what was expected and counted, any difference and
     why, and its Z report. Owners and managers; the till itself is at
     "Open the till". -->
<script setup>
import { onMounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    tills: { type: Object, default: null },
});
onMounted(() => { if (!props.tills) router.reload({ only: ['tills'], data: { tab: 'tills' } }); });

const filters = reactive({ till_from: props.tills?.filters?.till_from || '', till_to: props.tills?.filters?.till_to || '', till_actor: props.tills?.filters?.till_actor || '', till_status: props.tills?.filters?.till_status || '', till_q: props.tills?.filters?.till_q || '' });
const open = ref(null);
const loadingMore = ref(false);
function loadPage(number) {
    loadingMore.value = true;
    router.reload({ only: ['tills'], data: { ...filters, tab: 'tills', till_page: number }, preserveScroll: true, onFinish: () => { loadingMore.value = false; } });
}
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
        <form @submit.prevent="loadPage(1)" class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6 text-xs text-slate-300">
            <label>Opened from (Nairobi)<input v-model="filters.till_from" type="date" class="block mt-1 w-full rounded bg-slate-900 text-white" /></label>
            <label>Opened to (Nairobi)<input v-model="filters.till_to" type="date" :min="filters.till_from" class="block mt-1 w-full rounded bg-slate-900 text-white" /></label>
            <label>Opened / closed by<select v-model="filters.till_actor" class="block mt-1 w-full rounded bg-slate-900 text-white"><option value="">All staff</option><option v-for="actor in tills?.actors || []" :key="actor.id" :value="actor.id">{{ actor.name }}</option></select></label>
            <label>Drawer state<select v-model="filters.till_status" class="block mt-1 w-full rounded bg-slate-900 text-white"><option value="">All sessions</option><option value="open">Open</option><option value="closed">Closed</option></select></label>
            <label>Register<input v-model="filters.till_q" maxlength="80" class="block mt-1 w-full rounded bg-slate-900 text-white" /></label>
            <div class="flex items-end"><button type="submit" :disabled="loadingMore" class="table-action">Search sessions</button></div>
        </form>
        <p v-for="(message, key) in $page.props.errors" :key="key" role="alert" class="text-sm text-rose-300">{{ message }}</p>

        <p v-if="!tills" class="text-sm text-slate-400" role="status">Loading the till history…</p>
        <template v-else>
            <p class="text-sm text-slate-400">A difference over {{ ksh(tills.variance_alert) }} needs a reason and a manager’s PIN at closing. The owner can change the alert in Staff → The till.</p>
            <p v-if="!tills.sessions.length" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-6 text-sm text-slate-400">No drawers match these filters. Try another date range or clear the filters.</p>
            <p class="text-xs text-slate-400" role="status">Page {{ tills.page || 1 }} of {{ tills.last_page || 1 }} · {{ tills.total ?? tills.sessions.length }} sessions · open drawers first.</p>
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
                        <a :href="s.print_url" target="_blank" rel="noopener" class="table-action mb-3">Open / print closing report</a>
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
            <div class="flex gap-2" aria-label="Till history pages">
                <button v-if="tills.page > 1" type="button" @click="loadPage(tills.page - 1)" :disabled="loadingMore" class="table-action">Newer sessions</button>
                <button v-if="tills.page < tills.last_page" type="button" @click="loadPage(tills.page + 1)" :disabled="loadingMore" class="table-action">Older sessions</button>
            </div>
        </template>
    </div>
</template>
