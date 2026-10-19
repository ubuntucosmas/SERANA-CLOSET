<!-- Home: real, honest KPIs + a "needs your attention" list. No fake widgets. -->
<script setup>
import { managerQueues } from '@/Support/studioWorkflow';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    workflow: { type: Object, default: () => ({}) },
    generatedAt: { type: String, default: '' },
    stats: { type: Object, default: () => ({}) },
    attention: { type: Object, default: () => ({ awaiting: [], low_stock: [] }) },
});
const emit = defineEmits(['go', 'openOrder', 'queue', 'refresh', 'create']);

const page = usePage();
const { formatAmount } = useCurrency();

const ownerName = computed(() => (page.props.auth?.user?.name || '').split(' ')[0] || '');
const greeting = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
});

const cards = computed(() => [
    { label: 'New orders · last 7 days', value: props.stats.new_orders_week ?? 0, icon: 'add_shopping_cart', tab: 'orders' },
    { label: 'Paid order value · last 7 days', value: formatAmount(props.stats.revenue_week ?? 0, page.props), icon: 'payments', tab: 'orders' },
    { label: 'In production', value: props.stats.in_production ?? 0, icon: 'content_cut', tab: 'orders' },
    { label: 'Subscribers', value: props.stats.subscribers_total ?? 0, icon: 'group', tab: 'subscribers' },
]);

const nothingToDo = computed(() =>
    !(props.attention?.awaiting?.length) && !(props.attention?.low_stock?.length));
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-2"><p class="text-xs text-slate-400">Active orders unless stated · KES · <span v-if="generatedAt">Updated {{ new Date(generatedAt).toLocaleString('en-GB', { timeZone: 'Africa/Nairobi', dateStyle: 'medium', timeStyle: 'short' }) }} (Nairobi)</span></p><button @click="emit('refresh')" class="table-action">Refresh figures</button></div>
        <section aria-labelledby="manager-workflow-title">
            <h2 id="manager-workflow-title" class="text-lg font-semibold text-white">Daily decision desk</h2>
            <p class="text-sm text-slate-400 mt-1 mb-3">Resolve payment problems, agree quotes, then move confirmed orders through production and dispatch. Open a queue to work oldest-first.</p>
            <div class="grid grid-cols-2 xl:grid-cols-3 gap-3"><button v-for="queue in managerQueues" :key="queue.id" @click="emit('queue', queue.id)" class="text-left rounded-xl border border-white/10 bg-slate-900/60 p-4 hover:border-cyan-400">
                <div class="flex items-center justify-between gap-2"><span class="stage-badge" :class="queue.tone">{{ queue.label }}</span><strong class="text-2xl tabular-nums text-white">{{ workflow[queue.id] ?? 0 }}</strong></div>
                <p class="text-xs text-slate-400 mt-3 leading-relaxed">{{ queue.why }}</p><span class="block text-sm text-cyan-300 mt-3">{{ queue.action }} →</span>
            </button></div>
        </section>
        <p v-if="ownerName" class="text-slate-400 -mt-2">{{ greeting }}, <span class="text-white">{{ ownerName }}</span >.</p>

        <nav aria-label="Manager shortcuts" class="flex flex-wrap gap-2"><button @click="emit('create', 'product')" class="table-action">Add product</button><button @click="emit('create', 'campaign')" class="table-action">New pre-order campaign</button><button @click="emit('go', 'products')" class="table-action">Review stock</button><button @click="emit('go', 'settings')" class="table-action">Payment &amp; store settings</button></nav>
        <!-- KPI cards -->
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            <button v-for="c in cards" :key="c.label" @click="emit('go', c.tab)"
                class="text-left bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 hover:ring-[#57d5fc]/40 transition-colors">
                <span class="material-symbols-outlined text-[#57d5fc] text-2xl">{{ c.icon }}</span>
                <p class="mt-2 text-2xl lg:text-3xl font-headline text-white leading-none">{{ c.value }}</p>
                <p class="mt-2 text-[11px] uppercase tracking-wider text-slate-500 font-medium">{{ c.label }}</p>
            </button>
        </div>

        <details class="rounded-xl border border-white/10 bg-slate-900/60 p-4"><summary class="text-sm font-semibold text-white cursor-pointer">How to read these figures</summary><p class="mt-3 text-sm text-slate-400">Queues describe current work, not deadlines or overdue promises. New orders leave out abandoned checkouts (expired after the payment window) and cancelled orders. A failed M-Pesa payment stays under Payment problems for the payment window while the customer can retry, then expires; nothing was charged. Production includes orders in design or sewing even if payment is outstanding, so check payment before committing work. Paid order value uses the payment date and includes archived paid orders; it is not profit, a bank balance or a refund-adjusted ledger. Repeated payment alerts require manual reconciliation. Refresh figures after changes.</p></details>
        <!-- Needs your attention -->
        <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-6">
            <h2 class="text-white font-headline text-xl mb-1">Needs your attention</h2>
            <p class="text-slate-500 text-sm mb-5">Recent attention snapshot, including repeated-payment alerts. This is a limited list; decision queue counts cover all matching active orders.</p>

            <div v-if="nothingToDo" class="flex items-center gap-3 text-slate-400 py-6 justify-center">
                <span class="material-symbols-outlined text-[#57d5fc]">check_circle</span>
                No items in this attention snapshot. Use the decision queues above to review all active work.
            </div>

            <div v-else class="space-y-3">
                <button v-for="o in attention.awaiting" :key="'a' + o.id" @click="emit('openOrder', o.id)"
                    class="w-full flex items-center justify-between gap-4 bg-slate-950/50 hover:bg-slate-950 rounded-xl px-4 py-3 text-left transition-colors">
                    <span class="flex items-center gap-3 min-w-0">
                        <span class="material-symbols-outlined text-amber-400 shrink-0">hourglass_top</span>
                        <span class="min-w-0">
                            <span class="block text-white text-sm truncate">{{ o.full_name || 'Customer' }} — {{ o.next_action }}</span>
                            <span class="block text-slate-500 text-xs font-mono">{{ o.reference || ('#' + o.id) }}</span>
                        </span>
                    </span>
                    <span class="text-slate-300 text-sm shrink-0">{{ formatAmount(o.amount || 0, page.props) }}</span>
                </button>

                <button v-for="p in attention.low_stock" :key="'l' + p.id" @click="emit('go', 'products')"
                    class="w-full flex items-center gap-3 bg-slate-950/50 hover:bg-slate-950 rounded-xl px-4 py-3 text-left transition-colors">
                    <span class="material-symbols-outlined text-rose-400 shrink-0">inventory</span>
                    <span class="text-white text-sm truncate">{{ p.name }} — {{ p.note || 'Review availability' }}</span>
                </button>
            </div>
        </section>
    </div>
</template>
