<!-- Studio → Stock (Phase D1): stock per size at the shop. Every change is a
     person's, with a reason, and lands in the product's history. The owner
     switches stock per size on (after counting every size) or back off. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { shopStatus } from '@/Support/stock';

const props = defineProps({
    stock: { type: Object, default: null },
    focusProduct: { type: Number, default: null },
});

const page = usePage();
const canSwitch = computed(() => page.props.auth?.user?.abilities?.includes('stock.cutover'));
const loading = ref(false);
function load() {
    loading.value = true;
    router.reload({ only: ['stock'], data: { tab: 'stock' }, onFinish: () => { loading.value = false; } });
}
onMounted(() => { if (!props.stock) load(); });

const query = ref('');
const filter = ref('all');
const products = computed(() => {
    const q = query.value.trim().toLowerCase();
    const all = props.stock?.products || [];
    return all.filter(p => (!q || p.name.toLowerCase().includes(q) || p.sizes.some(s => s.sku.toLowerCase().includes(q)))
        && (filter.value !== 'uncounted' || (p.track_stock && p.sizes.some(s => s.active && !s.counted_at)))
        && (filter.value !== 'zero' || (p.track_stock && p.sizes.some(s => s.active && s.available === 0)))
        && (filter.value !== 'held' || (p.track_stock && p.sizes.some(s => s.active && s.held > s.on_hand))));
});
const open = ref(null);
const historyOpen = ref(null);
// Each product's history loads when it is opened, and again after any stock change.
const histories = ref({});
const historyError = ref('');
function loadHistory(id) {
    historyError.value = '';
    axios.get(route('studio.stock.history', id))
        .then(({ data }) => { histories.value = { ...histories.value, [id]: data.history }; })
        .catch(() => { historyError.value = 'The history could not be loaded. Check the connection and open it again.'; });
}
function toggleHistory(id) {
    historyOpen.value = historyOpen.value === id ? null : id;
    if (historyOpen.value && !histories.value[id]) loadHistory(id);
}
watch(() => props.stock, () => { histories.value = {}; if (historyOpen.value) loadHistory(historyOpen.value); });

watch(() => props.focusProduct, id => { if (id) open.value = id; }, { immediate: true });
const entryProduct = ref(null);
const reviewing = ref(false);
const entry = useForm({ operation_id: '', action: 'receive', reason: '', reference: '', exclude_paid_pickups: false, rows: [] });
const actionLabels = { count: 'Count unsold stock', receive: 'Receive delivery', damage: 'Write off stock' };
function startEntry(product, action, onlySize = null) {
    if (entry.processing) return;
    entry.reset(); entry.clearErrors(); reviewing.value = false;
    entryProduct.value = product.id;
    entry.operation_id = crypto.randomUUID();
    entry.action = action;
    entry.rows = product.sizes.filter(size => size.active && (!onlySize || size.id === onlySize.id)).map(size => ({
        variant_id: size.id, label: size.label, on_hand: size.on_hand, held: size.held,
        expected_version: size.version, quantity: '', paid_awaiting_pickup: size.paid_awaiting_pickup || 0,
    }));
}
const changedRows = computed(() => entry.rows.filter(row => row.quantity !== '' && row.quantity !== null));
const afterEntry = row => entry.action === 'count' ? Number(row.quantity) : row.on_hand + (entry.action === 'damage' ? -1 : 1) * Number(row.quantity);
function reviewEntry() {
    if (!changedRows.value.length) { entry.setError('rows', 'Enter a quantity for at least one size. Leave other sizes blank.'); return; }
    entry.clearErrors(); reviewing.value = true;
}
function saveEntry(product) {
    entry.transform(data => ({ ...data, rows: changedRows.value.map(row => ({
        variant_id: row.variant_id, quantity: row.quantity, expected_version: row.expected_version,
    })) })).post(route('studio.stock.batch', product.id), {
        preserveScroll: true,
        onSuccess: () => { entryProduct.value = null; load(); },
        onError: () => { reviewing.value = false; },
    });
}
function refreshEntry() {
    if (entry.processing) return;
    entryProduct.value = null; entry.reset(); entry.clearErrors(); reviewing.value = false; load();
}
const uncounted = computed(() => (props.stock?.products || []).filter(p => p.track_stock).reduce((n, p) => n + p.sizes.filter(s => s.active && !s.counted_at).length, 0));

const sizeForm = useForm({ size: '', reason: '' });
function addSize(product) {
    sizeForm.post(route('studio.stock.sizes', product.id), { preserveScroll: true, onSuccess: () => { sizeForm.reset(); load(); } });
}
function setSizeActive(product, size, active) {
    const reason = window.prompt(`${active ? 'Offer' : 'Withdraw'} ${size.label} of ${product.name}. Reason:`);
    if (!reason) return;
    router.post(route('studio.stock.sizes', product.id), { size: size.size, active, reason }, { preserveScroll: true, onSuccess: load });
}
function setTracking(product, track) {
    const reason = window.prompt(track
        ? `Stock ${product.name}: it will sell only what you count per size. Reason:`
        : `Make ${product.name} to order: always available, no stock counts. Reason:`);
    if (!reason) return;
    router.post(route('studio.stock.track', product.id), { track_stock: track, reason }, { preserveScroll: true, onSuccess: load });
}

const cutoverError = computed(() => page.props.errors?.cutover);
function switchOn() {
    if (!confirm('Switch the shop to stock per size? From now on it sells only what you counted, size by size. You can switch back.')) return;
    router.post(route('studio.stock.cutover'), {}, { preserveScroll: true, onSuccess: load });
}
function switchOff() {
    const reason = window.prompt('Switch back to the old batch numbers. Reason:');
    if (!reason) return;
    router.post(route('studio.stock.rollback'), { reason }, { preserveScroll: true, onSuccess: load });
}

const tracked = computed(() => (props.stock?.products || []).filter(p => p.track_stock));
const typeLabel = { receive: 'Received', sale: 'Sold', return: 'Returned', count_correction: 'Count', damage: 'Written off', transfer_out: 'Sent', transfer_in: 'Arrived' };
const counted = (at) => at ? new Date(at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) : 'Not counted';
</script>

<template>
    <div class="space-y-5">
        <p v-if="!stock" class="text-sm text-slate-400" role="status">{{ loading ? 'Loading stock…' : '' }}</p>

        <template v-else>
            <!-- Where the switch stands -->
            <section class="rounded-2xl p-4 sm:p-5 ring-1" :class="stock.mode.v2 ? 'bg-emerald-500/10 ring-emerald-400/30' : 'bg-slate-900/60 ring-white/10'" aria-labelledby="stock-mode-title">
                <h2 id="stock-mode-title" class="text-white font-headline text-lg">{{ stock.mode.v2 ? 'Stock per size is on' : 'Stock per size is off' }}</h2>
                <p class="text-sm text-slate-300 mt-1">
                    <template v-if="stock.mode.v2">The shop sells unsold stock recorded at {{ stock.location?.name }}, size by size. Receive new batches and write off damaged pieces here.</template>
                    <template v-else>The shop still sells from the old batch numbers. Count every size of every stocked product below, then switch on.</template>
                </p>
                <p v-if="stock.mode.forced" class="text-xs text-slate-400 mt-1">Fixed by a server setting, so the switch below has no effect. Ask whoever runs the server to remove it.</p>
                <template v-if="canSwitch && stock.mode.preview">
                    <ul v-if="!stock.mode.v2" class="mt-3 text-sm text-slate-300 space-y-1">
                        <li><strong class="text-white">{{ stock.mode.preview.uncounted.length }}</strong> size(s) not counted yet<template v-if="stock.mode.preview.uncounted.length">: {{ stock.mode.preview.uncounted.slice(0, 6).join(', ') }}<template v-if="stock.mode.preview.uncounted.length > 6">…</template></template></li>
                        <li v-if="stock.mode.preview.to_replay.length"><strong class="text-white">{{ stock.mode.preview.to_replay.length }}</strong> sale(s) since a count will be applied when you switch</li>
                        <li v-if="stock.mode.preview.unmapped_holds.length" class="text-amber-400">Orders being paid now with a size we can't read: {{ stock.mode.preview.unmapped_holds.join(', ') }}. Let them finish or expire first.</li>
                        <li v-if="stock.mode.preview.unmapped_released?.length" class="text-slate-400">{{ stock.mode.preview.unmapped_released.length }} old order(s) with no size recorded: if one is ever paid, it waits for your decision.</li>
                        <li v-for="issue in stock.mode.preview.integrity" :key="issue" class="text-rose-300">{{ issue }}</li>
                    </ul>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button v-if="!stock.mode.v2" type="button" @click="switchOn" :disabled="!stock.mode.preview.ready" class="rounded-xl bg-[#57d5fc] px-4 py-2 text-sm font-semibold text-slate-900 disabled:opacity-40">Switch to stock per size</button>
                        <button v-else type="button" @click="switchOff" class="rounded-xl bg-white/5 px-4 py-2 text-sm text-slate-200 hover:bg-white/10">Switch back to batch numbers</button>
                    </div>
                    <p v-if="cutoverError" role="alert" class="mt-2 text-sm text-rose-300">{{ cutoverError }}</p>
                </template>
            </section>

            <section class="rounded-xl border border-amber-400/30 bg-amber-500/10 p-4 text-sm text-slate-200" aria-label="Counting stock safely">
                <p class="font-semibold">Count unsold stock only</p>
                <p class="mt-1">Keep paid orders awaiting pickup in a separate area. They have already been deducted and must not be included in your count. Include units held for unpaid orders; their holds are deducted automatically.</p>
                <p class="mt-2">{{ uncounted }} active size(s) still need an initial count. A blank count is not the same as a checked zero.</p>
            </section>
            <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                <div class="relative flex-1 sm:max-w-xs">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-lg">search</span>
                    <input v-model="query" type="search" aria-label="Search products or SKU" placeholder="Product or SKU…"
                        class="w-full pl-10 pr-3 py-2 bg-slate-900 border border-white/10 rounded-full text-sm text-white placeholder-slate-600 focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                </div>
                <select v-model="filter" aria-label="Stock status" class="rounded-xl bg-slate-900 border border-white/10 text-sm text-white"><option value="all">All products</option><option value="uncounted">Needs initial count</option><option value="zero">Zero available</option><option value="held">Holds need review</option></select>
                <p class="text-xs text-slate-400">{{ tracked.length }} stocked product(s) · counts at {{ stock.location?.name }}</p>
            </div>

            <ul class="space-y-3" aria-label="Products">
                <li v-for="product in products" :key="product.id" class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl">
                    <button type="button" @click="open = open === product.id ? null : product.id" :aria-expanded="open === product.id"
                        class="w-full flex items-center justify-between gap-3 p-4 text-left">
                        <span class="min-w-0">
                            <span class="block text-white font-medium truncate">{{ product.name }}</span>
                            <span class="block text-xs text-slate-400">
                                <template v-if="product.track_stock">{{ product.sizes.filter(s => s.active).map(s => `${s.label} ${s.available ?? 0}`).join(' · ') }}</template>
                                <template v-else>Made to order · always available</template>
                            </span>
                            <span class="mt-1 block text-xs font-medium" :class="shopStatus(product).tone === 'warn' ? 'text-amber-400' : 'text-emerald-400'">{{ shopStatus(product).text }}</span>
                        </span>
                        <span class="material-symbols-outlined text-slate-400 shrink-0">{{ open === product.id ? 'expand_less' : 'expand_more' }}</span>
                    </button>

                    <div v-if="open === product.id" class="border-t border-white/10 p-4 space-y-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="product.track_stock ? 'bg-[#57d5fc]/15 text-[#57d5fc]' : 'bg-white/5 text-slate-300'">{{ product.track_stock ? 'Stocked' : 'Made to order' }}</span>
                            <button type="button" @click="setTracking(product, !product.track_stock)" class="underline text-slate-300 hover:text-white">{{ product.track_stock ? 'Make to order instead' : 'Stock it instead' }}</button>
                        </div>

                        <div v-if="product.track_stock" class="flex flex-wrap gap-2">
                            <button v-for="action in ['receive', 'count', 'damage']" :key="action" type="button" @click="startEntry(product, action)" :disabled="entry.processing" class="table-action">{{ actionLabels[action] }}</button>
                        </div>
                        <form v-if="entryProduct === product.id" @submit.prevent="reviewing ? saveEntry(product) : reviewEntry()" class="space-y-4 rounded-xl border border-white/15 bg-slate-950/60 p-4" aria-label="Size quantities">
                            <h3 class="font-semibold text-white">{{ actionLabels[entry.action] }} · {{ product.name }}</h3>
                            <p class="text-sm text-slate-300">{{ entry.action === 'count' ? 'Enter the unsold units physically counted, including unpaid holds. Zero means you checked and found none.' : entry.action === 'receive' ? 'Enter only the additional units delivered, not the total already in stock.' : 'Enter the units to remove from unsold stock.' }} Leave unchanged sizes blank.</p>
                            <div class="relative overflow-x-auto">
                                <table class="w-full sm:min-w-[440px] text-sm"><caption class="sr-only">Size quantities and balances after saving</caption><thead><tr class="text-left text-slate-400"><th scope="col" class="px-1.5 py-2 sm:p-2">Size / part</th><th scope="col" class="px-1.5 py-2 sm:p-2 text-right">Unsold now</th><th scope="col" class="px-1.5 py-2 sm:p-2">{{ entry.action === 'count' ? 'Units counted' : entry.action === 'receive' ? 'Units received' : 'Units removed' }}</th><th scope="col" class="px-1.5 py-2 sm:p-2 text-right">After saving</th></tr></thead><tbody>
                                    <tr v-for="row in entry.rows" :key="row.variant_id" class="border-t border-white/10"><th scope="row" class="px-1.5 py-2 sm:p-2 text-left font-medium">{{ row.label }}<span v-if="row.paid_awaiting_pickup" class="block text-xs text-amber-400">{{ row.paid_awaiting_pickup }} paid awaiting pickup · exclude</span></th><td class="px-1.5 py-2 sm:p-2 text-right tabular-nums">{{ row.on_hand }}</td><td class="px-1.5 py-2 sm:p-2"><input v-model="row.quantity" :readonly="reviewing" type="number" step="1" :min="entry.action === 'count' ? 0 : 1" max="100000" :aria-label="`${row.label} quantity`" placeholder="—" class="w-20 sm:w-32 rounded-xl border border-white/15 bg-slate-900 text-white" /></td><td class="px-1.5 py-2 sm:p-2 text-right tabular-nums">{{ row.quantity === '' ? '—' : afterEntry(row) }}<span v-if="row.quantity !== '' && afterEntry(row) < row.held" class="block text-xs text-amber-400">Below unpaid holds</span></td></tr>
                                </tbody></table>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2"><label class="text-sm text-slate-300">Reason<input v-model="entry.reason" :readonly="reviewing" required maxlength="255" class="mt-1 w-full rounded-xl border border-white/15 bg-slate-900 text-white" /></label><label class="text-sm text-slate-300">Delivery / document reference (optional)<input v-model="entry.reference" :readonly="reviewing" maxlength="100" class="mt-1 w-full rounded-xl border border-white/15 bg-slate-900 text-white" /></label></div>
                            <label v-if="entry.action === 'count'" class="flex items-start gap-3 text-sm text-slate-200"><input v-model="entry.exclude_paid_pickups" :disabled="reviewing" type="checkbox" required class="mt-1" />I counted unsold stock only, excluded paid orders awaiting pickup, and kept the counted area unchanged while counting.</label>
                            <p v-for="(message, key) in entry.errors" :key="key" role="alert" class="text-sm text-rose-300">{{ message }}</p>
                            <p v-if="reviewing" role="status" class="text-sm text-emerald-400">Review {{ changedRows.length }} size(s) above. Save all changes together.</p>
                            <div class="flex flex-wrap gap-2"><button type="submit" :disabled="entry.processing" class="rounded-xl bg-[#57d5fc] px-4 py-2.5 text-sm font-semibold text-slate-900 disabled:opacity-40">{{ entry.processing ? 'Saving…' : reviewing ? 'Save stock entry' : 'Review changes' }}</button><button v-if="reviewing" type="button" @click="reviewing = false" :disabled="entry.processing" class="table-action">Edit entry</button><button type="button" @click="refreshEntry" :disabled="entry.processing" class="table-action">Refresh quantities / start again</button><button type="button" @click="entryProduct = null" :disabled="entry.processing" class="table-action">Cancel</button></div>
                        </form>
                        <div class="relative overflow-x-auto -mx-4 px-4">
                            <table class="w-full text-sm min-w-[520px]">
                                <thead><tr class="text-left text-xs text-slate-400"><th class="py-2 pr-3 font-medium">Size</th><th class="py-2 pr-3 font-medium">SKU</th><th class="py-2 pr-3 font-medium text-right">Unsold stock</th><th class="py-2 pr-3 font-medium text-right">Reserved</th><th class="py-2 pr-3 font-medium text-right">Available</th><th class="py-2 pr-3 font-medium">Counted</th><th class="py-2 font-medium"><span class="sr-only">Actions</span></th></tr></thead>
                                <tbody>
                                    <template v-for="size in product.sizes" :key="size.id">
                                        <tr class="border-t border-white/5" :class="size.active ? 'text-slate-200' : 'text-slate-500'">
                                            <td class="py-2 pr-3">{{ size.label }}<span v-if="product.track_stock && size.held > size.on_hand" class="block text-xs text-rose-300">Needs review: reserved exceeds on hand</span><span v-if="!size.active" class="text-xs"> (withdrawn)</span></td>
                                            <td class="py-2 pr-3 font-mono text-xs">{{ size.sku }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ size.on_hand }}<span v-if="size.paid_awaiting_pickup" class="block text-xs text-amber-400">{{ size.paid_awaiting_pickup }} paid awaiting pickup · excluded</span></td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ size.held }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums font-semibold">{{ product.track_stock ? size.available : '—' }}</td>
                                            <td class="py-2 pr-3 text-xs" :class="size.counted_at ? 'text-slate-400' : 'text-amber-400'">{{ counted(size.counted_at) }}</td>
                                            <td class="py-2 text-right whitespace-nowrap">
                                                <template v-if="product.track_stock && size.active">
                                                    <button type="button" @click="startEntry(product, 'count', size)" class="table-action">Count</button>
                                                    <button type="button" @click="startEntry(product, 'receive', size)" class="table-action">Receive</button>
                                                    <button type="button" @click="startEntry(product, 'damage', size)" class="table-action">Write off</button>
                                                </template>
                                                <button type="button" @click="setSizeActive(product, size, !size.active)" class="table-action">{{ size.active ? 'Withdraw' : 'Offer again' }}</button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <form @submit.prevent="addSize(product)" class="grid gap-2 sm:grid-cols-[8rem_1fr_auto]" aria-label="Offer a new size">
                            <input v-model="sizeForm.size" maxlength="30" required aria-label="New size" placeholder="New size, e.g. XS" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm" />
                            <input v-model="sizeForm.reason" maxlength="255" required aria-label="Reason for the new size" placeholder="Reason" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm" />
                            <button type="submit" :disabled="sizeForm.processing" class="rounded-xl bg-white/5 px-4 py-2 text-sm text-slate-200 hover:bg-white/10">Offer size</button>
                            <p v-for="(message, field) in sizeForm.errors" :key="field" role="alert" class="sm:col-span-3 text-sm text-rose-300">{{ message }}</p>
                        </form>

                        <div>
                            <button type="button" @click="toggleHistory(product.id)" class="text-sm text-[#57d5fc] hover:underline" :aria-expanded="historyOpen === product.id">
                                {{ historyOpen === product.id ? 'Hide history' : `Stock history (${product.history_count})` }}
                            </button>
                            <ul v-if="historyOpen === product.id" class="mt-2 divide-y divide-white/5 text-xs" aria-label="Stock history">
                                <li v-if="historyError" role="alert" class="py-2 text-rose-300">{{ historyError }}</li>
                                <li v-else-if="!histories[product.id]" class="py-2 text-slate-400">Loading…</li>
                                <li v-else-if="!histories[product.id].length" class="py-2 text-slate-400">No stock movements yet.</li>
                                <li v-for="m in histories[product.id] || []" :key="m.id" class="py-2 flex flex-wrap gap-x-3 gap-y-1">
                                    <span class="text-slate-400 w-28 shrink-0">{{ m.at }}</span>
                                    <span class="text-white">{{ typeLabel[m.type] || m.type }} {{ m.size }}</span>
                                    <span class="tabular-nums" :class="m.quantity < 0 ? 'text-rose-300' : 'text-emerald-400'">{{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}</span>
                                    <span class="text-slate-400">{{ m.by }}<template v-if="m.reason"> · {{ m.reason }}</template><template v-if="m.reference"> · {{ m.reference }}</template><template v-if="m.order"> · {{ m.order }}</template></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
            <p v-if="!products.length" class="text-sm text-slate-400">No products match this search and stock status.</p>
        </template>
    </div>
</template>
