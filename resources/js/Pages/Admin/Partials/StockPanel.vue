<!-- Studio → Stock (Phase D1): stock per size at the shop. Every change is a
     person's, with a reason, and lands in the product's history. The owner
     switches stock per size on (after counting every size) or back off. -->
<script setup>
import { computed, onMounted, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    stock: { type: Object, default: null },
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
const products = computed(() => {
    const q = query.value.trim().toLowerCase();
    const all = props.stock?.products || [];
    return q ? all.filter(p => p.name.toLowerCase().includes(q) || p.sizes.some(s => s.sku.toLowerCase().includes(q))) : all;
});
const open = ref(null);
const historyOpen = ref(null);

// One form for the size being edited: count (what's on hand now), receive, or write off.
const editing = ref(null);
const moveForm = useForm({ variant_id: null, action: 'count', quantity: '', reason: '' });
const actionLabels = { count: 'Count (set on hand)', receive: 'Receive', damage: 'Write off' };
function startMove(size, action) {
    editing.value = size.id;
    moveForm.reset();
    moveForm.clearErrors();
    moveForm.variant_id = size.id;
    moveForm.action = action;
    moveForm.quantity = action === 'count' ? size.on_hand : '';
    moveForm.reason = action === 'count' ? 'Stock take' : '';
}
function saveMove() {
    moveForm.post(route('studio.stock.move'), { preserveScroll: true, onSuccess: () => { editing.value = null; load(); } });
}

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
                    <template v-if="stock.mode.v2">The shop sells what you counted at {{ stock.location?.name }}, size by size. Receive new batches and write off damaged pieces here.</template>
                    <template v-else>The shop still sells from the old batch numbers. Count every size of every stocked product below, then switch on.</template>
                </p>
                <p v-if="stock.mode.forced" class="text-xs text-slate-400 mt-1">Fixed by the server setting STOCK_V2; the switch below has no effect until it's removed.</p>
                <template v-if="canSwitch && stock.mode.preview">
                    <ul v-if="!stock.mode.v2" class="mt-3 text-sm text-slate-300 space-y-1">
                        <li><strong class="text-white">{{ stock.mode.preview.uncounted.length }}</strong> size(s) not counted yet<template v-if="stock.mode.preview.uncounted.length">: {{ stock.mode.preview.uncounted.slice(0, 6).join(', ') }}<template v-if="stock.mode.preview.uncounted.length > 6">…</template></template></li>
                        <li v-if="stock.mode.preview.to_replay.length"><strong class="text-white">{{ stock.mode.preview.to_replay.length }}</strong> sale(s) since a count will be applied when you switch</li>
                        <li v-if="stock.mode.preview.unmapped_holds.length" class="text-amber-400">Orders waiting with a size we can't read: {{ stock.mode.preview.unmapped_holds.join(', ') }}</li>
                        <li v-for="issue in stock.mode.preview.integrity" :key="issue" class="text-rose-300">{{ issue }}</li>
                    </ul>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button v-if="!stock.mode.v2" type="button" @click="switchOn" :disabled="!stock.mode.preview.ready" class="rounded-xl bg-[#57d5fc] px-4 py-2 text-sm font-semibold text-slate-900 disabled:opacity-40">Switch to stock per size</button>
                        <button v-else type="button" @click="switchOff" class="rounded-xl bg-white/5 px-4 py-2 text-sm text-slate-200 hover:bg-white/10">Switch back to batch numbers</button>
                    </div>
                    <p v-if="cutoverError" role="alert" class="mt-2 text-sm text-rose-300">{{ cutoverError }}</p>
                </template>
            </section>

            <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                <div class="relative flex-1 sm:max-w-xs">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-lg">search</span>
                    <input v-model="query" type="search" aria-label="Search products or SKU" placeholder="Product or SKU…"
                        class="w-full pl-10 pr-3 py-2 bg-slate-900 border border-white/10 rounded-full text-sm text-white placeholder-slate-600 focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                </div>
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
                        </span>
                        <span class="material-symbols-outlined text-slate-400 shrink-0">{{ open === product.id ? 'expand_less' : 'expand_more' }}</span>
                    </button>

                    <div v-if="open === product.id" class="border-t border-white/10 p-4 space-y-4">
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="product.track_stock ? 'bg-[#57d5fc]/15 text-[#57d5fc]' : 'bg-white/5 text-slate-300'">{{ product.track_stock ? 'Stocked' : 'Made to order' }}</span>
                            <button type="button" @click="setTracking(product, !product.track_stock)" class="underline text-slate-300 hover:text-white">{{ product.track_stock ? 'Make to order instead' : 'Stock it instead' }}</button>
                        </div>

                        <div class="overflow-x-auto -mx-4 px-4">
                            <table class="w-full text-sm min-w-[520px]">
                                <thead><tr class="text-left text-xs text-slate-400"><th class="py-2 pr-3 font-medium">Size</th><th class="py-2 pr-3 font-medium">SKU</th><th class="py-2 pr-3 font-medium text-right">On hand</th><th class="py-2 pr-3 font-medium text-right">Held</th><th class="py-2 pr-3 font-medium text-right">Available</th><th class="py-2 pr-3 font-medium">Counted</th><th class="py-2 font-medium"><span class="sr-only">Actions</span></th></tr></thead>
                                <tbody>
                                    <template v-for="size in product.sizes" :key="size.id">
                                        <tr class="border-t border-white/5" :class="size.active ? 'text-slate-200' : 'text-slate-500'">
                                            <td class="py-2 pr-3">{{ size.label }}<span v-if="!size.active" class="text-xs"> (withdrawn)</span></td>
                                            <td class="py-2 pr-3 font-mono text-xs">{{ size.sku }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ size.on_hand }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ size.held }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums font-semibold">{{ product.track_stock ? size.available : '—' }}</td>
                                            <td class="py-2 pr-3 text-xs" :class="size.counted_at ? 'text-slate-400' : 'text-amber-400'">{{ counted(size.counted_at) }}</td>
                                            <td class="py-2 text-right whitespace-nowrap">
                                                <template v-if="product.track_stock && size.active">
                                                    <button type="button" @click="startMove(size, 'count')" class="table-action">Count</button>
                                                    <button type="button" @click="startMove(size, 'receive')" class="table-action">Receive</button>
                                                    <button type="button" @click="startMove(size, 'damage')" class="table-action">Write off</button>
                                                </template>
                                                <button type="button" @click="setSizeActive(product, size, !size.active)" class="table-action">{{ size.active ? 'Withdraw' : 'Offer again' }}</button>
                                            </td>
                                        </tr>
                                        <tr v-if="editing === size.id">
                                            <td colspan="7" class="pb-3">
                                                <form @submit.prevent="saveMove" class="grid gap-2 sm:grid-cols-[auto_8rem_1fr_auto] items-start rounded-xl bg-slate-950/60 p-3" :aria-label="`${actionLabels[moveForm.action]}: ${product.name} ${size.label}`">
                                                    <span class="text-sm text-white py-2">{{ actionLabels[moveForm.action] }} · {{ size.label }}</span>
                                                    <input v-model="moveForm.quantity" type="number" :min="moveForm.action === 'count' ? 0 : 1" inputmode="numeric" required :aria-label="moveForm.action === 'count' ? 'Units counted' : 'Units'"
                                                        class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm" />
                                                    <input v-model="moveForm.reason" maxlength="255" required aria-label="Reason" placeholder="Reason (e.g. batch from the workshop)"
                                                        class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm" />
                                                    <span class="flex gap-2">
                                                        <button type="submit" :disabled="moveForm.processing" class="rounded-xl bg-[#57d5fc] px-4 py-2 text-sm font-semibold text-slate-900 disabled:opacity-40">Save</button>
                                                        <button type="button" @click="editing = null" class="rounded-xl bg-white/5 px-3 py-2 text-sm text-slate-300">Cancel</button>
                                                    </span>
                                                    <p v-for="(message, field) in moveForm.errors" :key="field" role="alert" class="sm:col-span-4 text-sm text-rose-300">{{ message }}</p>
                                                </form>
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
                            <button type="button" @click="historyOpen = historyOpen === product.id ? null : product.id" class="text-sm text-[#57d5fc] hover:underline" :aria-expanded="historyOpen === product.id">
                                {{ historyOpen === product.id ? 'Hide history' : `Stock history (${product.history.length})` }}
                            </button>
                            <ul v-if="historyOpen === product.id" class="mt-2 divide-y divide-white/5 text-xs" aria-label="Stock history">
                                <li v-if="!product.history.length" class="py-2 text-slate-400">No stock movements yet.</li>
                                <li v-for="m in product.history" :key="m.id" class="py-2 flex flex-wrap gap-x-3 gap-y-1">
                                    <span class="text-slate-400 w-28 shrink-0">{{ m.at }}</span>
                                    <span class="text-white">{{ typeLabel[m.type] || m.type }} {{ m.size }}</span>
                                    <span class="tabular-nums" :class="m.quantity < 0 ? 'text-rose-300' : 'text-emerald-400'">{{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}</span>
                                    <span class="text-slate-400">{{ m.by }}<template v-if="m.reason"> · {{ m.reason }}</template><template v-if="m.order"> · {{ m.order }}</template></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </li>
            </ul>
        </template>
    </div>
</template>
