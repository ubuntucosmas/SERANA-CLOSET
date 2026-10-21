<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';
import { managerQueues } from '@/Support/studioWorkflow';
import PaymentAttention from './PaymentAttention.vue';

const props = defineProps({
    workflow: { type: Object, default: () => ({}) },
    generatedAt: { type: String, default: '' },
    stats: { type: Object, default: () => ({}) },
    attention: { type: Object, default: () => ({ awaiting: [], low_stock: [] }) },
});
const emit = defineEmits(['go', 'openOrder', 'queue', 'refresh', 'create']);
const page = usePage();
const { formatAmount } = useCurrency();
const money = value => formatAmount(value || 0, page.props);
const updated = computed(() => {
    const date = new Date(props.generatedAt);
    return Number.isNaN(date.getTime()) ? 'Refresh to update this snapshot' : `Updated ${date.toLocaleString('en-GB', { timeZone: 'Africa/Nairobi', dateStyle: 'medium', timeStyle: 'short' })} · Nairobi`;
});
const metrics = computed(() => [
    { label: 'Paid order value', value: money(props.stats.revenue_week), period: 'Last 7 days · includes archived paid orders', tab: 'orders' },
    { label: 'New orders', value: props.stats.new_orders_week ?? 0, period: 'Last 7 days · excludes expired and cancelled', tab: 'orders' },
    { label: 'In production', value: props.stats.in_production ?? 0, period: 'Current · design and sewing', queue: 'production' },
    { label: 'Email subscribers', value: props.stats.subscribers_total ?? 0, period: 'Current subscriber list', tab: 'subscribers' },
]);
const paymentCases = computed(() => props.attention.payments || []);
const stock = computed(() => props.attention.low_stock || []);
const focus = computed(() => {
    if (paymentCases.value.length) {
        const count = props.attention.payments_count ?? paymentCases.value.length;
        return { title: 'Review financial exceptions first', detail: `${count} payment ${count === 1 ? 'case needs' : 'cases need'} review. Check the evidence and agree the next step before further fulfilment.`, action: 'Review cases', anchor: '#overview-payment-cases', tone: 'stage-error' };
    }
    const priority = ['review', 'dispatch', 'quotes', 'fulfilment', 'payment', 'production'];
    const queue = priority.map(id => managerQueues.find(item => item.id === id)).find(item => Number(props.workflow[item.id]) > 0);
    if (queue) return { title: queue.label, detail: `${props.workflow[queue.id]} matching orders. ${queue.why}`, action: queue.action, queue: queue.id, tone: queue.tone };
    if (stock.value.length) return { title: 'Review product availability', detail: 'Some products are unavailable or running low. Check their availability before promoting them.', action: 'Review stock', tab: 'products', tone: 'stage-waiting' };
    return { title: 'No orders in the current work queues', detail: 'Review the order list for other activity, or update your products. This snapshot does not confirm that every business task is complete.', action: 'View orders', tab: 'orders', tone: 'stage-complete' };
});
const summaries = {
    review: 'Verify the payment or receipt before requesting another payment.',
    quotes: 'Agree requirements, price and timing with the customer.',
    payment: 'Follow up and verify money received before confirming payment.',
    fulfilment: 'Check items, sizes and delivery details before starting work.',
    production: 'Check progress, blockers and customer updates.',
    dispatch: 'Check the parcel and address before marking dispatched.',
};
const allFollowups = computed(() => {
    const financialIds = new Set(paymentCases.value.map(item => item.id));
    const seen = new Set();
    return (props.attention.awaiting || []).filter(item => {
        if (financialIds.has(item.id) || seen.has(item.id)) return false;
        seen.add(item.id);
        return true;
    });
});
const expanded = ref(false);
const followups = computed(() => expanded.value ? allFollowups.value : allFollowups.value.slice(0, 5));
</script>

<template>
    <div class="overview space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-slate-400" aria-live="polite">{{ updated }} · Amounts in KES</p>
            <button type="button" @click="emit('refresh')" class="table-action">Refresh overview</button>
        </div>

        <section aria-labelledby="overview-snapshot-title">
            <h2 id="overview-snapshot-title" class="overview-label mb-2">Business snapshot</h2>
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-2 sm:gap-3">
                <button v-for="metric in metrics" :key="metric.label" type="button" @click="metric.queue ? emit('queue', metric.queue) : emit('go', metric.tab)" class="overview-panel metric text-left">
                    <span class="block text-xs font-medium text-slate-300">{{ metric.label }}</span>
                    <strong class="block mt-2 text-xl sm:text-2xl font-semibold text-white tabular-nums break-words">{{ metric.value }}</strong>
                    <span class="block mt-2 text-xs text-slate-400 leading-snug">{{ metric.period }}</span>
                </button>
            </div>
        </section>

        <section aria-labelledby="overview-focus-title" class="overview-panel flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="min-w-0"><span class="stage-badge mb-2" :class="focus.tone">Next decision</span><h2 id="overview-focus-title" class="text-base font-semibold text-white">{{ focus.title }}</h2><p class="mt-1 text-sm text-slate-300 max-w-3xl">{{ focus.detail }}</p></div>
            <a v-if="focus.anchor" :href="focus.anchor" class="table-action shrink-0 self-start sm:self-center">{{ focus.action }} <span aria-hidden="true" class="ml-2">↓</span></a>
            <button v-else type="button" @click="focus.queue ? emit('queue', focus.queue) : emit('go', focus.tab)" class="table-action shrink-0 self-start sm:self-center">{{ focus.action }} <span aria-hidden="true" class="ml-2">→</span></button>
        </section>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(260px,1fr)] items-start">
            <div class="min-w-0 space-y-5">
                <div v-if="paymentCases.length" id="overview-payment-cases" class="scroll-mt-20">
                    <PaymentAttention :items="paymentCases" :count="attention.payments_count ?? paymentCases.length" @openOrder="emit('openOrder', $event)" @go="emit('go', $event)" />
                </div>
                <section class="overview-panel !p-0 overflow-hidden" aria-labelledby="overview-queues-title">
                    <header class="p-4 border-b border-white/10"><h2 id="overview-queues-title" class="text-base font-semibold text-white">Order work queues</h2><p class="mt-1 text-xs text-slate-400">Current active orders. Open a queue to work oldest-first.</p></header>
                    <div class="divide-y divide-white/10">
                        <div v-for="queue in managerQueues" :key="queue.id" class="queue-row">
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="stage-badge" :class="Number(workflow[queue.id]) ? queue.tone : 'queue-empty'">{{ queue.label }}</span><strong class="text-white tabular-nums text-base" :aria-label="`${workflow[queue.id] ?? 0} orders`">{{ workflow[queue.id] ?? 0 }}</strong></div><p class="mt-1 text-xs text-slate-400 leading-snug">{{ summaries[queue.id] }}</p></div>
                            <button type="button" @click="emit('queue', queue.id)" class="table-action" :aria-label="`${queue.action}: ${workflow[queue.id] ?? 0} orders`">{{ queue.action }}</button>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="min-w-0 space-y-5" aria-label="Stock and quick actions">
                <section class="overview-panel" aria-labelledby="overview-stock-title">
                    <div class="flex items-center justify-between gap-2"><h2 id="overview-stock-title" class="text-base font-semibold text-white">Stock to review</h2><span class="text-xs text-slate-400">{{ stock.length }} shown</span></div>
                    <p class="mt-1 text-xs text-slate-400">Up to 8 unavailable or low-stock products. Unpaid holds affect availability.</p>
                    <ul v-if="stock.length" class="mt-3 divide-y divide-white/10"><li v-for="product in stock" :key="product.id" class="py-3"><p class="text-sm font-medium text-white break-words">{{ product.name }}</p><p class="mt-1 text-xs text-slate-400">{{ product.note || 'Review availability' }}</p></li></ul>
                    <p v-else class="py-4 text-sm text-slate-400">No products in this stock alert snapshot.</p>
                    <button type="button" @click="emit('go', 'products')" class="table-action mt-3 w-full">Review products &amp; stock</button>
                </section>
                <section class="overview-panel" aria-labelledby="overview-actions-title">
                    <h2 id="overview-actions-title" class="text-base font-semibold text-white">Quick actions</h2><p class="mt-1 mb-3 text-xs text-slate-400">Create or maintain customer-facing content.</p>
                    <div class="grid grid-cols-2 gap-2"><button type="button" @click="emit('create', 'product')" class="table-action">Add product</button><button type="button" @click="emit('create', 'campaign')" class="table-action">New pre-order</button><button type="button" @click="emit('go', 'showcase')" class="table-action">Photo gallery</button><button type="button" @click="emit('go', 'settings')" class="table-action">Store settings</button></div>
                </section>
            </aside>
        </div>

        <section aria-labelledby="overview-followups-title" class="space-y-3">
            <div><h2 id="overview-followups-title" class="text-base font-semibold text-white">Recent order follow-ups</h2><p class="mt-1 text-xs text-slate-400">From the latest 12 flagged orders. Financial cases above are excluded; work queues show the full active backlog.</p></div>
            <div v-if="followups.length" class="studio-table-wrap" role="region" aria-label="Recent order follow-ups" tabindex="0">
                <table class="studio-table"><caption class="sr-only">Customer, order reference, next action, order value and open order</caption><thead><tr><th scope="col">Customer / reference</th><th scope="col">Next action</th><th scope="col" class="numeric">Order value</th><th scope="col" class="row-action">Action</th></tr></thead><tbody><tr v-for="order in followups" :key="order.id"><td><span class="font-medium text-white">{{ order.full_name || 'Customer' }}</span><span class="cell-note">{{ order.reference || '#' + order.id }}</span></td><td class="min-w-48 whitespace-normal">{{ order.next_action }}</td><td class="numeric">{{ money(order.amount) }}</td><td class="row-action"><button type="button" @click="emit('openOrder', order.id)" class="table-action" :aria-label="`Open order ${order.reference || order.id}`">Open order</button></td></tr></tbody></table>
            </div>
            <p v-else class="overview-panel text-sm text-slate-400">No additional orders in this follow-up snapshot. Use the queues to check current work.</p>
            <button v-if="allFollowups.length > 5" type="button" @click="expanded = !expanded" :aria-expanded="expanded" class="table-action">{{ expanded ? 'Show fewer' : `Show all ${allFollowups.length} follow-ups` }}</button>
        </section>

        <details class="overview-panel text-sm text-slate-400"><summary class="cursor-pointer font-medium text-slate-300">What these figures mean</summary><div class="mt-3 space-y-2"><p class="text-sm">The 7-day figures use a rolling period. Paid order value follows the payment date and includes archived paid orders; it is not profit, bank balance or a refund-adjusted ledger.</p><p class="text-sm">Queues can overlap: custom work in production may still await payment. Counts are not added together. Dates and age do not establish a promised delivery deadline.</p><p class="text-sm">Financial exceptions require checking payment evidence. A failed payment does not establish whether a customer was charged. Refresh the overview after changes.</p></div></details>
    </div>
</template>

<style scoped>
.overview-panel { padding: 16px; border: 1px solid var(--card-border, #334155); border-radius: 12px; background: var(--surface); }
.overview-label { font-size: 12px; font-weight: 600; color: var(--on-surface-variant); letter-spacing: .04em; }
.metric { display: flex; flex-direction: column; align-items: stretch; transition: border-color .15s; }
.metric > span:last-child { margin-top: auto; padding-top: 8px; }
.queue-row + .queue-row { border-top: 1px solid var(--card-border, #334155); }
.metric:hover { border-color: var(--primary); }
.queue-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 12px 16px; }
.queue-empty { color: var(--on-surface-variant); border-color: var(--card-border); background: var(--bg-card); }
@media (max-width: 639px) { .overview-panel { padding: 12px; } .queue-row { gap: 8px; padding: 12px; } .queue-row .table-action { max-width: 116px; white-space: normal; text-align: center; } }
</style>
