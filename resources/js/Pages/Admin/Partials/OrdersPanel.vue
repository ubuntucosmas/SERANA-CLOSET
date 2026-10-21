<!-- Orders list: custom + shop, with working filter + search, and an archive view. -->
<script setup>
import { managerQueues } from '@/Support/studioWorkflow';
import { ref, computed, watch, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import StatusBadge from '@/Components/StatusBadge.vue';
import { paymentStatus, fulfilmentStatus } from '@/Support/orderStatus';
import { useCurrency } from '@/Composables/useCurrency';

// Orders arrive a page at a time ({ data, page, last_page, total, archived });
// search, filters and the archive view are answered by the server, so the
// Studio stays fast with thousands of orders.
const props = defineProps({
    orders: { type: Object, default: () => ({ data: [], page: 1, last_page: 1, total: 0, archived: false }) },
    selectedId: { type: [Number, String], default: null },
});
const emit = defineEmits(['select']);

const page = usePage();
const { formatAmount } = useCurrency();

const filter = ref(props.orders.filter || 'all');      // all | custom | shop | till
const showArchived = ref(!!props.orders.archived);
const query = ref(props.orders.query || '');
const queue = ref(props.orders.queue || 'all');
const sort = ref(props.orders.sort || 'newest');
const activeQueue = computed(() => managerQueues.find(item => item.id === queue.value));

const PRODUCTION = ['pending', 'designing', 'sewing', 'completed', 'ready_for_pickup', 'shipped', 'collected']; // collected: handed over at the till

function amountOf(o) { return o.price_quoted ?? o.total ?? 0; }
function isCustom(o) { return o.type !== 'standard'; }
// Where it was sold: a till sale says so, with who sold it and any paper slip (audit P1-5).
function typeLabel(o) {
    if (o.channel === 'counter') return `Till${o.seller_name ? ' · ' + o.seller_name : ''}${o.slip_number ? ' · slip ' + o.slip_number : ''}`;
    return typeLabelOnline(o);
}
function typeLabelOnline(o) { return o.type === 'drop' ? 'Drop' : (o.type === 'standard' ? (o.payment_method === 'whatsapp' ? 'Shop · WhatsApp' : 'Shop') : 'Custom'); }
// What the order is, in words staff recognise: the pieces for shop orders,
// the Drop title, or the requested piece for a bespoke brief.
function itemOf(o) {
    if (o.type === 'standard') {
        const items = o.items_with_urls || [];
        return items.length ? items.map(i => `${i.name}${i.size_label ? ' (' + i.size_label + ')' : ''}${(i.quantity || 1) > 1 ? ' ×' + i.quantity : ''}`).join(', ') : 'Shop order';
    }
    if (o.type === 'drop') return o.items_json?.drop ? `${o.items_json.drop}${o.items_json.size ? ' · ' + o.items_json.size : ''}` : 'Drop reservation';
    return o.type && o.type !== 'custom' ? `Custom ${o.type}` : 'Custom piece';
}


// Production stage only applies once a custom order is in the workflow.
function stage(o) {
    return PRODUCTION.includes(o.status) ? o.status : null;
}

// Accumulated list: page 1 replaces, later pages (Load more) append.
const list = ref([...(props.orders.data || [])]);
watch(() => props.orders, (o) => {
    list.value = o.page > 1 ? [...list.value, ...o.data.filter(n => !list.value.some(x => x.id === n.id))] : [...(o.data || [])];
});
const loading = ref(false);
function fetchOrders(page = 1) {
    loading.value = true;
    router.reload({
        only: ['orders'],
        data: { o_queue: queue.value === 'all' ? undefined : queue.value, o_sort: sort.value, o_q: query.value.trim() || undefined, o_filter: filter.value === 'all' ? undefined : filter.value, o_archived: showArchived.value ? 1 : undefined, o_page: page > 1 ? page : undefined },
        preserveScroll: true,
        onFinish: () => (loading.value = false),
    });
}
let typing = null;
watch(query, () => { clearTimeout(typing); typing = setTimeout(() => fetchOrders(1), 300); });
onUnmounted(() => clearTimeout(typing));
watch([filter, showArchived, queue, sort], () => fetchOrders(1));
const canLoadMore = computed(() => props.orders.page < props.orders.last_page);
const filtered = list;

const chips = [{ id: 'all', label: 'All' }, { id: 'custom', label: 'Custom & pre-orders' }, { id: 'shop', label: 'Shop' }, { id: 'till', label: 'Till sales' }];
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-end gap-3">
            <label class="text-xs text-slate-400">Work queue<select v-model="queue" class="block mt-1 bg-slate-900 text-white border border-white/10 rounded-lg text-sm py-2"><option value="all">All orders</option><option v-for="item in managerQueues" :key="item.id" :value="item.id">{{ item.label }}</option></select></label>
            <label class="text-xs text-slate-400">Order by<select v-model="sort" class="block mt-1 bg-slate-900 text-white border border-white/10 rounded-lg text-sm py-2"><option value="oldest">Oldest first</option><option value="newest">Newest first</option></select></label>
            <button @click="fetchOrders(1)" :disabled="loading" class="table-action">Refresh list</button>
        </div>
        <p v-if="activeQueue" class="text-sm text-slate-300 border-l-2 border-cyan-400 pl-3">{{ activeQueue.why }}</p>
        <!-- Controls -->
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
            <div class="flex gap-2">
                <button v-for="c in chips" :key="c.id" @click="filter = c.id"
                    :class="['px-4 py-2 rounded-full text-xs font-semibold transition-colors',
                             filter === c.id ? 'bg-[#57d5fc] text-slate-900' : 'bg-white/5 text-slate-400 hover:text-white']">
                    {{ c.label }}
                </button>
            </div>
            <div class="flex gap-2">
                <div class="relative flex-1 min-w-0 sm:w-64">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-lg">search</span>
                    <input v-model="query" type="search" aria-label="Search orders" placeholder="Search name, phone, reference…"
                        class="w-full min-w-0 pl-10 pr-3 py-2 bg-slate-900 border border-white/10 rounded-full text-sm text-white placeholder-slate-600 focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                </div>
                <button @click="showArchived = !showArchived"
                    :class="['px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-colors',
                             showArchived ? 'bg-[#57d5fc] text-slate-900' : 'bg-white/5 text-slate-400 hover:text-white']">
                    {{ showArchived ? 'Active' : 'Archive' }}
                </button>
            </div>
        </div>

        <p class="text-xs text-slate-500" aria-live="polite">{{ loading ? 'Loading…' : `${orders.total} order${orders.total === 1 ? '' : 's'}${query ? ` matching “${query}”` : ''}${showArchived ? ' in the archive' : ''}` }}</p>

        <!-- Empty -->
        <div v-if="filtered.length === 0" class="text-center text-slate-500 py-16 bg-slate-900/40 rounded-2xl ring-1 ring-white/5">
            <span class="material-symbols-outlined text-3xl">inbox</span>
            <p class="mt-2 text-sm">{{ query || filter !== 'all' || queue !== 'all' ? 'No orders match these filters. Clear the search or select All.' : showArchived ? 'No archived orders.' : 'No orders yet. New shop orders, reservations and custom orders appear here.' }}</p>
        </div>

        <div v-else class="space-y-3">
            <div class="studio-table-wrap" role="region" aria-label="Orders table — scroll horizontally for all columns" tabindex="0" :aria-busy="loading">
                <table class="studio-table">
                    <caption class="sr-only">Orders with customer, items, total, payment, production stage and actions</caption>
                    <thead><tr><th scope="col">Reference / type</th><th scope="col">Created</th><th scope="col">Customer</th><th scope="col">Items / sizes</th><th scope="col" class="numeric">Total</th><th scope="col">Payment</th><th scope="col">Production</th><th scope="col" class="row-action">Action</th></tr></thead>
                    <tbody>
                        <tr v-for="o in filtered" :key="o.id" :class="{ 'selected-row': selectedId === o.id }">
                            <td><button @click="emit('select', o)" class="text-cyan-300 hover:underline font-mono text-xs">{{ o.reference || o.cipher || '#' + o.id }}</button><span class="cell-note">{{ typeLabel(o) }}</span></td>
                            <td class="whitespace-nowrap text-xs">{{ o.created_at ? new Date(o.created_at).toLocaleDateString('en-GB', { timeZone: 'Africa/Nairobi', day: 'numeric', month: 'short', year: 'numeric' }) : 'Unknown' }}</td>
                            <td><span class="font-medium text-white">{{ o.full_name || 'Customer' }}</span><span class="cell-note">{{ o.whatsapp_number || o.phone || 'No phone supplied' }}</span></td>
                            <td class="max-w-72 min-w-48 whitespace-normal">{{ itemOf(o) }}</td>
                            <td class="numeric font-medium text-white">{{ formatAmount(amountOf(o), page.props) }}</td>
                            <td><StatusBadge v-bind="paymentStatus(o)" /></td>
                            <td><span class="stage-badge" :class="['shipped', 'collected'].includes(stage(o)) ? 'stage-complete' : stage(o) === 'completed' ? 'stage-ready' : stage(o) ? 'stage-working' : 'stage-waiting'">{{ stage(o) ? fulfilmentStatus(o) : o.status === 'Cancelled' ? 'Cancelled' : o.is_paid ? 'Awaiting fulfilment' : 'Not started' }}</span></td>
                            <td class="row-action"><button @click="emit('select', o)" :aria-label="`Open order ${o.reference || o.cipher || o.id}`" class="table-action">Open order</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button v-if="canLoadMore" type="button" @click="fetchOrders(orders.page + 1)" :disabled="loading" class="table-action disabled:opacity-40">{{ loading ? 'Loading…' : `Load more (${list.length} of ${orders.total})` }}</button>
        </div>
    </div>
</template>

<style scoped>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300; vertical-align: middle; }
</style>
