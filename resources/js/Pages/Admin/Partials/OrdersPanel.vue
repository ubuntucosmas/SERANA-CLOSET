<!-- Orders list: custom + shop, with working filter + search, and an archive view. -->
<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import StatusBadge from '@/Components/StatusBadge.vue';
import { paymentStatus } from '@/Support/orderStatus';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    orders: { type: Array, default: () => [] },
    archived: { type: Array, default: () => [] },
    selectedId: { type: [Number, String], default: null },
});
const emit = defineEmits(['select']);

const page = usePage();
const { formatAmount } = useCurrency();

const filter = ref('all');      // all | custom | shop
const showArchived = ref(false);
const query = ref('');

const PRODUCTION = ['pending', 'designing', 'sewing', 'completed', 'shipped'];

function amountOf(o) { return o.price_quoted ?? o.total ?? 0; }
function isCustom(o) { return o.type !== 'standard'; }
function typeLabel(o) { return o.type === 'drop' ? 'Drop' : (o.type === 'standard' ? 'Shop' : 'Bespoke'); }
function itemOf(o) { return o.garment_name || o.outfit_type || (isCustom(o) ? 'Custom piece' : 'Shop order'); }

// One clear payment label regardless of the shared status column.
function payment(o) {
    if (o.is_paid) return { label: 'Paid', cls: 'text-emerald-400 bg-emerald-400/10' };
    if (o.status === 'Pending Payment') return { label: 'Awaiting payment', cls: 'text-amber-400 bg-amber-400/10' };
    if (['Payment Failed', 'Expired', 'Amount Mismatch'].includes(o.status)) return { label: o.status, cls: 'text-rose-400 bg-rose-400/10' };
    return { label: 'Unpaid', cls: 'text-slate-400 bg-white/5' };
}
// Production stage only applies once a custom order is in the workflow.
function stage(o) {
    return PRODUCTION.includes(o.status) ? o.status : null;
}

const pool = computed(() => showArchived.value ? props.archived : props.orders);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    return pool.value.filter(o => {
        if (filter.value === 'custom' && !isCustom(o)) return false;
        if (filter.value === 'shop' && isCustom(o)) return false;
        if (!q) return true;
        return [o.full_name, o.email, o.phone, o.reference, o.cipher, itemOf(o), String(o.id)]
            .filter(Boolean).some(v => String(v).toLowerCase().includes(q));
    });
});

const chips = [{ id: 'all', label: 'All' }, { id: 'custom', label: 'Bespoke & Drops' }, { id: 'shop', label: 'Shop' }];
</script>

<template>
    <div class="space-y-5">
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

        <!-- Empty -->
        <div v-if="filtered.length === 0" class="text-center text-slate-500 py-16 bg-slate-900/40 rounded-2xl ring-1 ring-white/5">
            <span class="material-symbols-outlined text-3xl">inbox</span>
            <p class="mt-2 text-sm">{{ query || filter !== 'all' ? 'No orders match these filters. Clear the search or select All.' : showArchived ? 'No archived orders.' : 'No orders yet. New shop orders, reservations and bespoke requests appear here.' }}</p>
        </div>

        <!-- List -->
        <div v-else class="grid grid-cols-1 gap-3">
            <button v-for="o in filtered" :key="o.id" @click="emit('select', o)"
                :class="['w-full text-left bg-slate-900/60 ring-1 rounded-2xl p-4 transition-colors',
                         selectedId === o.id ? 'ring-[#57d5fc]/50' : 'ring-white/10 hover:ring-white/25']">
                <div class="flex flex-col sm:flex-row items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-white font-medium truncate">{{ o.full_name || 'Customer' }}</span>
                            <span class="text-[10px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-white/5 text-slate-400">{{ typeLabel(o) }}</span>
                        </div>
                        <p class="text-slate-400 text-sm truncate">{{ itemOf(o) }}</p>
                        <p v-if="o.reference || o.cipher" class="text-slate-500 text-xs font-mono mt-0.5 break-all">{{ o.reference || o.cipher }}</p>
                    </div>
                    <div class="w-full sm:w-auto flex flex-wrap sm:block justify-between gap-2 text-left sm:text-right shrink-0">
                        <p class="text-white font-headline">{{ formatAmount(amountOf(o), page.props) }}</p>
                        <StatusBadge class="mt-1" v-bind="paymentStatus(o)" />
                    </div>
                </div>
                <div v-if="stage(o)" class="mt-3 flex items-center gap-1.5 text-xs text-slate-400">
                    <span class="material-symbols-outlined text-[15px] text-[#57d5fc]">content_cut</span>
                    Stage: <span class="capitalize text-slate-300">{{ stage(o) }}</span>
                </div>
            </button>
        </div>
    </div>
</template>

<style scoped>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300; vertical-align: middle; }
</style>
