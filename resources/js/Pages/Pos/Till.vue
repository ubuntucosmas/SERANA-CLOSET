<!-- The till (Phase E, design §15.3–15.5). The tablet stays signed in as a
     staff account; each person unlocks it with their PIN and every action is
     recorded as them. It locks itself after the idle minutes. Sell, take
     money, hand over orders, take bespoke orders, returns, and close the
     drawer — all against the till endpoints, which hold every rule. -->
<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    operator: { type: Object, default: null },
    shop: { type: String, default: null },
    tills: { type: Array, default: () => [] },
    policy: { type: Object, required: true },
    garments: { type: Array, default: () => [] },
});

const ksh = (n) => 'KSh ' + Number(n || 0).toLocaleString('en-KE');
const operator = ref(props.operator);
const tills = ref(props.tills);
const register = ref(props.tills[0]?.register || 'Till 1');
const till = computed(() => tills.value.find(t => t.register === register.value) || null);
const tab = ref('sell');
const tabs = [
    { key: 'sell', label: 'Sell', icon: 'point_of_sale' },
    { key: 'orders', label: 'Orders', icon: 'inventory_2' },
    { key: 'bespoke', label: 'Bespoke', icon: 'design_services' },
    { key: 'till', label: 'Drawer', icon: 'payments' },
];

// ---- Talking to the till: every call says which register; a 423 means the tablet locked itself. ----
const errors = ref({});
const busy = ref(false);
async function call(method, url, data = {}, opts = {}) {
    busy.value = true;
    errors.value = {};
    try {
        const payload = data instanceof FormData ? (data.append('register', register.value), data) : { register: register.value, ...data };
        const res = method === 'get' ? await axios.get(url, { params: { register: register.value, ...data } }) : await axios[method](url, payload, opts);
        return res.data;
    } catch (e) {
        const status = e.response?.status;
        if (status === 423) { operator.value = null; }
        else if (status === 422 || status === 429) { errors.value = e.response.data.errors || { _: [e.response.data.message || 'Too many tries. Wait a moment.'] }; }
        else if (status === 404) { errors.value = { _: [e.response.data.message || 'Not found.'] }; }
        else { errors.value = { _: ['Something went wrong. Try again.'] }; }
        throw e;
    } finally {
        busy.value = false;
    }
}
const firstError = computed(() => Object.values(errors.value)[0]?.[0] || null);
// HTTP errors are shown by call(); anything else is a bug on this screen, and must never pass silently.
function fail(e) {
    if (!e?.response) { console.error(e); errors.value = { _: ['Something went wrong on this screen. Try again.'] }; }
}
const err = (field) => errors.value[field]?.[0] || null;

// ---- Lock screen ----
const pin = ref('');
async function unlock() {
    try {
        const data = await call('post', route('pos.unlock'), { pin: pin.value });
        operator.value = data.operator;
        touch();
    } catch (e) { fail(e); } finally { pin.value = ''; }
}
function press(d) { if (pin.value.length < 6) pin.value += d; }
async function lock() {
    try { await axios.post(route('pos.lock')); } catch { /* locking never fails the screen */ }
    operator.value = null;
}

// The server locks after the idle minutes too; this only stops the screen showing someone else's session.
let idleTimer = null;
function touch() {
    clearTimeout(idleTimer);
    if (operator.value) idleTimer = setTimeout(() => { operator.value = null; }, props.policy.idle_minutes * 60 * 1000);
}
onMounted(() => { touch(); ['pointerdown', 'keydown'].forEach(e => window.addEventListener(e, touch)); });
onBeforeUnmount(() => { clearTimeout(idleTimer); clearInterval(promptPoll); ['pointerdown', 'keydown'].forEach(e => window.removeEventListener(e, touch)); });

// ---- Open the drawer ----
const openFloat = ref('');
async function openTill() {
    try {
        const data = await call('post', route('pos.till.open'), { float: Number(openFloat.value || 0) });
        tills.value = [...tills.value.filter(t => t.register !== register.value), { ...data.till, opened_by: operator.value.name, opened_at: new Date().toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) }];
        openFloat.value = '';
    } catch (e) { fail(e); }
}

// ---- Sell: find, pick sizes, ring up ----
const query = ref('');
const results = ref([]);
let searchTimer = null;
async function search(scan = false) {
    try {
        const data = await call('get', route('pos.catalogue'), { q: query.value.trim() });
        results.value = data.products;
        // A scanned barcode or SKU picks its size straight onto the sale.
        if (scan && data.exact) {
            const product = data.products.find(p => p.id === data.exact.product_id);
            const size = product?.sizes.find(s => s.id === data.exact.variant_id);
            if (product && size && product.garment_type !== 'set') { addLine(product, { [size.part]: size }); query.value = ''; }
            else if (product) openSizes(product);
        }
    } catch (e) { fail(e); }
}
watch(query, () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => search(false), 250); });

const sizing = ref(null); // the product whose size sheet is open
const chosen = reactive({});
const partsOf = (p) => [...new Set(p.sizes.map(s => s.part))];
const partLabel = { single: 'Size', top: 'Top', bottom: 'Bottom' };
function openSizes(product) {
    const parts = partsOf(product);
    // One size and nothing to choose: straight onto the sale.
    if (parts.every(part => product.sizes.filter(s => s.part === part).length === 1)) {
        addLine(product, Object.fromEntries(parts.map(part => [part, product.sizes.find(s => s.part === part)])));
        return;
    }
    sizing.value = product;
    Object.keys(chosen).forEach(k => delete chosen[k]);
}
const sizingReady = computed(() => sizing.value && partsOf(sizing.value).every(part => chosen[part]));
function confirmSizes() { addLine(sizing.value, { ...chosen }); sizing.value = null; }

const cart = ref([]);
function addLine(product, byPart) {
    const sizes = Object.values(byPart);
    const key = product.id + ':' + sizes.map(s => s.id).sort().join('-');
    const existing = cart.value.find(l => l.key === key);
    if (existing) { existing.quantity++; return; }
    const options = {};
    for (const [part, size] of Object.entries(byPart)) options[part === 'bottom' ? 'bottom_size' : 'top_size'] = size.size;
    cart.value.push({ key, id: product.id, name: product.name, price: product.price, quantity: 1, options, variant_ids: sizes.map(s => s.id),
        label: sizes.map(s => (s.part === 'single' ? '' : partLabel[s.part] + ' ') + s.size).join(' · '),
        available: Math.min(...sizes.map(s => s.available ?? Infinity)) });
}
const subtotal = computed(() => cart.value.reduce((n, l) => n + l.price * l.quantity, 0));
const customer = reactive({ name: '', phone: '' });
const discount = reactive({ amount: '', reason: '', pin: '' });
const discountLimit = computed(() => Math.min(Math.floor(subtotal.value * props.policy.discount_percent / 100), props.policy.discount_cap));
const needsDiscountPin = computed(() => Number(discount.amount || 0) > discountLimit.value && !operator.value?.can_approve);

const sale = ref(null);
async function ringUp() {
    try {
        const data = await call('post', route('pos.sales.store'), {
            items: cart.value.map(l => ({ id: l.id, quantity: l.quantity, options: l.options, variant_ids: l.variant_ids })),
            customer: customer.name || customer.phone ? { name: customer.name || null, phone: customer.phone || null } : null,
            discount: Number(discount.amount || 0) || null, discount_reason: discount.reason || null, approval_pin: discount.pin || null,
        });
        sale.value = data.sale;
        change.value = null;
        startTender('sale');
    } catch (e) { fail(e); }
}
function newSale() {
    sale.value = null; cart.value = []; query.value = ''; results.value = [];
    Object.assign(customer, { name: '', phone: '' }); Object.assign(discount, { amount: '', reason: '', pin: '' });
    errors.value = {}; change.value = null; clearInterval(promptPoll);
}

// ---- Take money (a sale, a collection's balance, a bespoke deposit) ----
const tender = reactive({ method: 'cash', amount: '', tendered: '', reference: '', phone: '', credit_ref: '' });
const change = ref(null);
let promptPoll = null;
// What money is being taken on: the sale, an order being collected, or a bespoke deposit.
function startTender(which) {
    const owed = targets[which].value?.balance_due || 0;
    Object.assign(tender, { method: tender.method === 'mpesa_prompt' ? 'cash' : tender.method, amount: owed || '', tendered: owed || '', reference: '', phone: '', credit_ref: '' });
}
const quick = computed(() => {
    const owed = Number(tender.amount || 0);
    return [...new Set([owed, Math.ceil(owed / 500) * 500, Math.ceil(owed / 1000) * 1000])].filter(n => n > 0).slice(0, 3);
});
async function takeMoney(which) {
    const order = targets[which].value;
    const refresh = refreshers[which];
    change.value = null;
    const body = { method: tender.method, amount: Number(tender.amount || 0) };
    if (tender.method === 'cash') body.tendered = Number(tender.tendered || 0);
    if (tender.method === 'mpesa_manual') body.reference = tender.reference.trim().toUpperCase();
    if (tender.method === 'mpesa_prompt') body.phone = tender.phone;
    try {
        if (tender.method === 'credit') {
            const found = await call('get', route('pos.orders.find'), { q: tender.credit_ref });
            body.credit_order_id = found.order.id;
        }
        const data = await call('post', route('pos.sales.tender', order.id), body);
        await refresh(data.sale);
        if (data.change > 0) change.value = data.change;
        if (tender.method === 'mpesa_prompt') watchPrompt(order.id, refresh);
        else startTender(which);
    } catch (e) { fail(e); }
}
function watchPrompt(id, refresh) {
    clearInterval(promptPoll);
    let tries = 0;
    promptPoll = setInterval(async () => {
        tries++;
        try {
            const { data } = await axios.get(route('pos.sales.show', id));
            await refresh(data.sale);
            if (data.sale.paid || data.sale.balance_due <= 0 || (data.sale.prompt && data.sale.prompt.status !== 'pending') || tries > 40) clearInterval(promptPoll);
        } catch { clearInterval(promptPoll); }
    }, 3000);
}
const voiding = reactive({ open: false, reason: '', pin: '' });
async function voidSale() {
    try {
        const data = await call('post', route('pos.sales.void', sale.value.id), { reason: voiding.reason, approval_pin: voiding.pin || null });
        sale.value = data.sale; voiding.open = false;
    } catch (e) { fail(e); }
}
const receiptUrl = (id) => route('pos.sales.receipt', id);
const methodLabel = { cash: 'Cash', mpesa_manual: 'M-Pesa code', mpesa_prompt: 'M-Pesa prompt', credit: 'Exchange credit', mpesa_stk: 'M-Pesa', counter: 'Counter' };

// ---- Orders desk: collect, returns ----
const desk = reactive({ ref: '', phone: '', note: '' });
const collectOrder = ref(null);
async function findForCollection() {
    try {
        collectOrder.value = (await call('get', route('pos.collect.find'), { ref: desk.ref.trim(), phone: desk.phone })).order;
        change.value = null;
        startTender('collect');
    } catch { collectOrder.value = null; }
}
async function refreshCollect() {
    // The desk's own view (balance, can it be handed over) after money is taken.
    collectOrder.value = (await call('get', route('pos.collect.find'), { ref: collectOrder.value.reference, phone: desk.phone })).order;
}
const collected = ref(null);
async function markCollected() {
    try {
        const data = await call('post', route('pos.collect.store', collectOrder.value.id), { phone: desk.phone, note: desk.note || null });
        collected.value = data.order.reference;
        collectOrder.value = null;
        Object.assign(desk, { ref: '', phone: '', note: '' });
    } catch (e) { fail(e); }
}

const returnRef = ref('');
const returnOrder = ref(null);
const returnForm = reactive({ lines: {}, disposition: {}, reason: '', refund_method: 'cash', refund_reference: '', pin: '' });
async function findForReturn() {
    try {
        returnOrder.value = (await call('get', route('pos.orders.find'), { q: returnRef.value.trim() })).order;
        Object.assign(returnForm, { lines: {}, disposition: {}, reason: '', refund_method: 'cash', refund_reference: '', pin: '' });
    } catch { returnOrder.value = null; }
}
const returnValue = computed(() => (returnOrder.value?.lines || []).reduce((n, l) => n + (Number(returnForm.lines[l.id] || 0) * l.unit), 0));
const returned = ref(null);
async function submitReturn() {
    const lines = Object.entries(returnForm.lines).filter(([, q]) => Number(q) > 0)
        .map(([id, q]) => ({ order_line_id: Number(id), quantity: Number(q), disposition: returnForm.disposition[id] || 'restock' }));
    try {
        const data = await call('post', route('pos.returns.store'), { order_id: returnOrder.value.id, lines, reason: returnForm.reason,
            refund_method: returnForm.refund_method, refund_reference: returnForm.refund_reference || null, approval_pin: returnForm.pin || null });
        returned.value = { reference: returnOrder.value.reference, refunded: data.refunded ?? 0, method: returnForm.refund_method };
        returnOrder.value = null; returnRef.value = '';
    } catch (e) { fail(e); }
}

// ---- Bespoke intake ----
const blank = () => ({ full_name: '', phone: '', email: '', garment: '', notes: '', occasion: '', deadline: '', fabric_preference: '', selected_color: '',
    bust_cm: '', waist_cm: '', hips_cm: '', length_cm: '', height_cm: '', quote: '' });
const intake = reactive(blank());
const intakeImages = ref([]);
const intakeOrder = ref(null);
async function submitIntake() {
    const form = new FormData();
    Object.entries(intake).forEach(([k, v]) => { if (v !== '' && v !== null) form.append(k, v); });
    intakeImages.value.forEach(f => form.append('images[]', f));
    try {
        intakeOrder.value = (await call('post', route('pos.intake'), form)).order;
        change.value = null;
        startTender('intake');
        tender.amount = intakeOrder.value.deposit_required || '';
        tender.tendered = tender.amount;
    } catch (e) { fail(e); }
}
async function refreshIntake(s) { intakeOrder.value = { ...intakeOrder.value, paid: s.paid_so_far, balance_due: s.balance_due }; }
function newIntake() { Object.assign(intake, blank()); intakeImages.value = []; intakeOrder.value = null; errors.value = {}; }

// ---- The drawer: take cash out, report, close ----
const report = ref(null);
async function loadReport() { try { report.value = (await call('get', route('pos.till.report'))).report; } catch { report.value = null; } }
watch([tab, register, operator], () => { if (tab.value === 'till' && till.value && operator.value) loadReport(); });
const payout = reactive({ type: 'payout', amount: '', reason: '', pin: '' });
async function takeOut() {
    try {
        await call('post', route('pos.till.payout'), { type: payout.type, amount: Number(payout.amount || 0), reason: payout.reason, approval_pin: payout.pin });
        Object.assign(payout, { type: 'payout', amount: '', reason: '', pin: '' });
        loadReport();
    } catch (e) { fail(e); }
}
const closing = reactive({ counted: '', reason: '', pin: '' });
const closed = ref(null);
async function closeTill() {
    if (!confirm(`Close ${register.value} with ${ksh(closing.counted)} counted?`)) return;
    try {
        closed.value = (await call('post', route('pos.till.close'), { counted: Number(closing.counted || 0), variance_reason: closing.reason || null, approval_pin: closing.pin || null })).report;
        tills.value = tills.value.filter(t => t.register !== register.value);
        Object.assign(closing, { counted: '', reason: '', pin: '' });
        report.value = null;
    } catch (e) { fail(e); }
}
const late = reactive({ open: false, slip_number: '', occurred_at: '', reason: '', cash: '', mpesa: '', reference: '', pin: '' });
const lateDone = ref(null);
async function enterLate() {
    const payments = [];
    if (Number(late.cash) > 0) payments.push({ method: 'cash', amount: Number(late.cash) });
    if (Number(late.mpesa) > 0) payments.push({ method: 'mpesa_manual', amount: Number(late.mpesa), reference: late.reference.trim().toUpperCase() });
    try {
        const data = await call('post', route('pos.late_entry'), { slip_number: late.slip_number, occurred_at: late.occurred_at ? new Date(late.occurred_at).toISOString() : '',
            reason: late.reason, approval_pin: late.pin || null, payments,
            items: cart.value.map(l => ({ id: l.id, quantity: l.quantity, options: l.options, variant_ids: l.variant_ids })) });
        lateDone.value = data.sale;
        Object.assign(late, { open: false, slip_number: '', occurred_at: '', reason: '', cash: '', mpesa: '', reference: '', pin: '' });
        cart.value = [];
        loadReport();
    } catch (e) { fail(e); }
}

const targets = { sale, collect: collectOrder, intake: intakeOrder };
const refreshers = { sale: (s) => { sale.value = s; }, collect: refreshCollect, intake: refreshIntake };

// The till is always dark (a shop floor tablet), whatever the site theme; the theme is left as it was.
let wasDark = true;
onMounted(() => { wasDark = document.documentElement.classList.contains('dark'); document.documentElement.classList.add('dark'); });
onBeforeUnmount(() => { if (!wasDark) document.documentElement.classList.remove('dark'); });

const input = 'w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-white text-sm placeholder-slate-600 focus:ring-1 focus:ring-[#57d5fc] outline-none';
const primary = 'rounded-xl bg-[#57d5fc] px-4 py-2.5 text-sm font-semibold text-slate-900 disabled:opacity-40';
const quiet = 'rounded-xl bg-white/5 px-4 py-2.5 text-sm text-slate-200 hover:bg-white/10 disabled:opacity-40';
</script>

<template>
    <Head title="Till" />
    <div class="min-h-screen bg-slate-950 text-slate-200">
        <!-- Locked: someone's PIN -->
        <div v-if="!operator" class="min-h-screen flex items-center justify-center p-4">
            <form @submit.prevent="unlock" class="w-full max-w-xs space-y-5 text-center" aria-label="Unlock the till">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ shop || 'Serana Closet' }}</p>
                    <h1 class="mt-1 text-2xl font-headline text-white">Enter your PIN</h1>
                </div>
                <div class="flex justify-center gap-3" aria-hidden="true">
                    <span v-for="i in 6" :key="i" class="h-3 w-3 rounded-full" :class="i <= pin.length ? 'bg-[#57d5fc]' : 'bg-white/10'"></span>
                </div>
                <input v-model="pin" type="password" inputmode="numeric" autocomplete="off" maxlength="6" aria-label="PIN" class="sr-only" autofocus />
                <div class="grid grid-cols-3 gap-3">
                    <button v-for="d in ['1','2','3','4','5','6','7','8','9']" :key="d" type="button" @click="press(d)" class="h-16 rounded-2xl bg-white/5 text-2xl text-white hover:bg-white/10">{{ d }}</button>
                    <button type="button" @click="pin = pin.slice(0, -1)" class="h-16 rounded-2xl text-slate-400 hover:bg-white/5" aria-label="Delete"><span class="material-symbols-outlined">backspace</span></button>
                    <button type="button" @click="press('0')" class="h-16 rounded-2xl bg-white/5 text-2xl text-white hover:bg-white/10">0</button>
                    <button type="submit" :disabled="pin.length < 4 || busy" class="h-16 rounded-2xl bg-[#57d5fc] text-slate-900 font-semibold disabled:opacity-40" aria-label="Unlock"><span class="material-symbols-outlined">arrow_forward</span></button>
                </div>
                <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
            </form>
        </div>

        <template v-else>
            <header class="sticky top-0 z-20 bg-slate-950/95 backdrop-blur border-b border-white/10">
                <div class="mx-auto max-w-6xl px-4 py-3 flex flex-wrap items-center gap-3 justify-between">
                    <div class="min-w-0">
                        <p class="text-white font-headline truncate">{{ shop || 'Shop' }} · {{ register }}</p>
                        <p class="text-xs text-slate-400">{{ operator.name }}<template v-if="till"> · drawer open since {{ till.opened_at }}</template><template v-else> · drawer closed</template></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="sr-only" for="register">Register</label>
                        <select id="register" v-model="register" class="w-32 !rounded-xl bg-slate-900 border border-white/10 px-3 py-2 text-sm text-white">
                            <option v-for="r in [...new Set(['Till 1', ...tills.map(t => t.register)])]" :key="r" :value="r">{{ r }}</option>
                        </select>
                        <button type="button" @click="lock" :class="quiet" class="flex items-center gap-1"><span class="material-symbols-outlined text-base">lock</span>Lock</button>
                    </div>
                </div>
                <nav class="mx-auto max-w-6xl px-4 flex gap-1 overflow-x-auto" aria-label="Till sections">
                    <button v-for="t in tabs" :key="t.key" type="button" @click="tab = t.key; errors = {}" :aria-current="tab === t.key ? 'page' : undefined"
                        class="flex items-center gap-1.5 px-3 py-2.5 text-sm border-b-2 whitespace-nowrap" :class="tab === t.key ? 'border-[#57d5fc] text-white' : 'border-transparent text-slate-400 hover:text-white'">
                        <span class="material-symbols-outlined text-base">{{ t.icon }}</span>{{ t.label }}
                    </button>
                </nav>
            </header>

            <main class="mx-auto max-w-6xl p-4">
                <!-- Nothing happens until the drawer is open -->
                <section v-if="!till && tab !== 'till'" class="max-w-sm mx-auto mt-10 rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-5 space-y-3" aria-labelledby="open-title">
                    <h2 id="open-title" class="text-white font-headline text-lg">Open {{ register }}</h2>
                    <p class="text-sm text-slate-400">Count the cash in the drawer before the first sale.</p>
                    <form @submit.prevent="openTill" class="space-y-3">
                        <label class="block text-sm">Opening float (KSh)<input v-model="openFloat" type="number" min="0" inputmode="numeric" required :class="input" class="mt-1" /></label>
                        <button type="submit" :disabled="busy" :class="primary" class="w-full">Open the drawer</button>
                        <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                    </form>
                </section>

                <!-- SELL -->
                <div v-else-if="tab === 'sell'" class="grid gap-4 lg:grid-cols-[1fr_24rem]">
                    <section v-if="!sale" aria-label="Find pieces" class="space-y-3 min-w-0">
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">barcode_scanner</span>
                            <input v-model="query" @keydown.enter.prevent="search(true)" type="search" aria-label="Scan or search" placeholder="Scan a barcode, or type a name or SKU…" :class="input" class="pl-10 py-3" autofocus />
                        </div>
                        <ul class="grid grid-cols-2 sm:grid-cols-3 gap-3" aria-label="Pieces">
                            <li v-for="p in results" :key="p.id">
                                <button type="button" @click="openSizes(p)" class="w-full text-left rounded-2xl bg-slate-900/60 ring-1 ring-white/10 hover:ring-[#57d5fc]/50 overflow-hidden">
                                    <img v-if="p.image_url" :src="p.image_url" alt="" class="h-28 w-full object-cover" loading="lazy" @error="$event.target.remove()" />
                                    <span class="block p-3">
                                        <span class="block text-sm text-white truncate">{{ p.name }}</span>
                                        <span class="block text-xs text-slate-400">{{ ksh(p.price) }} · {{ p.sizes.filter(s => (s.available ?? 1) > 0).length ? 'in stock' : 'sold out here' }}</span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                        <p v-if="query && !results.length && !busy" class="text-sm text-slate-400">Nothing matches “{{ query }}”.</p>
                    </section>

                    <!-- Taking the money -->
                    <section v-else aria-labelledby="sale-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-4 min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 id="sale-title" class="text-white font-headline text-lg">Sale {{ sale.reference }}</h2>
                                <p class="text-xs text-slate-400">{{ sale.customer }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="sale.paid ? 'bg-emerald-500/15 text-emerald-300' : sale.state === 'cancelled' ? 'bg-rose-500/15 text-rose-300' : 'bg-amber-500/15 text-amber-300'">
                                {{ sale.paid ? 'Paid' : sale.state === 'cancelled' ? 'Voided' : ksh(sale.balance_due) + ' to pay' }}
                            </span>
                        </div>
                        <ul class="text-sm divide-y divide-white/5">
                            <li v-for="(l, i) in sale.lines" :key="i" class="py-2 flex justify-between gap-3"><span>{{ l.quantity }} × {{ l.description }}</span><span class="tabular-nums" :class="l.amount < 0 ? 'text-emerald-300' : ''">{{ ksh(l.amount) }}</span></li>
                            <li v-for="(p, i) in sale.payments" :key="'p' + i" class="py-2 flex justify-between gap-3 text-slate-400"><span>{{ methodLabel[p.method] || p.method }}<template v-if="p.reference"> · {{ p.reference }}</template></span><span class="tabular-nums">{{ p.type === 'refund' ? '−' : '' }}{{ ksh(p.amount) }}</span></li>
                        </ul>
                        <p v-if="change" class="rounded-xl bg-emerald-500/10 ring-1 ring-emerald-400/30 p-3 text-emerald-200 text-lg" role="status">Change: <strong>{{ ksh(change) }}</strong></p>

                        <template v-if="!sale.paid && sale.state === 'open'">
                            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="How they pay">
                                <button v-for="m in ['cash', 'mpesa_manual', 'mpesa_prompt', 'credit']" :key="m" type="button" role="radio" :aria-checked="tender.method === m" @click="tender.method = m"
                                    class="rounded-xl px-3 py-2 text-sm" :class="tender.method === m ? 'bg-[#57d5fc] text-slate-900 font-semibold' : 'bg-white/5 text-slate-300'">{{ methodLabel[m] }}</button>
                            </div>
                            <form @submit.prevent="takeMoney('sale')" class="grid gap-2 sm:grid-cols-2">
                                <label class="text-sm">Amount<input v-model="tender.amount" type="number" min="1" inputmode="numeric" required :class="input" class="mt-1" /></label>
                                <label v-if="tender.method === 'cash'" class="text-sm">Cash handed over<input v-model="tender.tendered" type="number" min="1" inputmode="numeric" required :class="input" class="mt-1" /></label>
                                <label v-if="tender.method === 'mpesa_manual'" class="text-sm">M-Pesa code<input v-model="tender.reference" required maxlength="100" autocapitalize="characters" :class="input" class="mt-1 uppercase" /></label>
                                <label v-if="tender.method === 'mpesa_prompt'" class="text-sm">Customer’s M-Pesa number<input v-model="tender.phone" type="tel" required :class="input" class="mt-1" placeholder="07XX XXX XXX" /></label>
                                <label v-if="tender.method === 'credit'" class="text-sm">Returned sale reference<input v-model="tender.credit_ref" required :class="input" class="mt-1 uppercase" placeholder="SRN-…" /></label>
                                <div v-if="tender.method === 'cash'" class="sm:col-span-2 flex gap-2">
                                    <button v-for="n in quick" :key="n" type="button" @click="tender.tendered = n" :class="quiet">{{ ksh(n) }}</button>
                                </div>
                                <button type="submit" :disabled="busy" :class="primary" class="sm:col-span-2">{{ tender.method === 'mpesa_prompt' ? 'Send the prompt' : 'Take ' + ksh(tender.amount) }}</button>
                            </form>
                            <p v-if="sale.prompt && sale.prompt.status === 'pending'" class="text-sm text-amber-300" role="status">Waiting for the customer to enter their M-Pesa PIN…</p>
                        </template>
                        <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>

                        <div class="flex flex-wrap gap-2 pt-2 border-t border-white/10">
                            <a v-if="sale.paid" :href="receiptUrl(sale.id)" target="_blank" rel="noopener" :class="primary">Print receipt</a>
                            <button v-if="sale.paid || sale.state === 'cancelled'" type="button" @click="newSale" :class="sale.paid ? quiet : primary">New sale</button>
                            <button v-if="!sale.paid && sale.state === 'open' && !sale.paid_so_far" type="button" @click="voiding.open = !voiding.open" :class="quiet">Void</button>
                        </div>
                        <form v-if="voiding.open" @submit.prevent="voidSale" class="grid gap-2 sm:grid-cols-[1fr_8rem_auto]" aria-label="Void the sale">
                            <input v-model="voiding.reason" required maxlength="255" placeholder="Why it’s voided" aria-label="Reason" :class="input" />
                            <input v-model="voiding.pin" type="password" inputmode="numeric" placeholder="Manager PIN" aria-label="Manager PIN (another person’s sale)" :class="input" />
                            <button type="submit" :disabled="busy" class="rounded-xl bg-rose-500/80 px-4 py-2.5 text-sm text-white">Void sale</button>
                        </form>
                    </section>

                    <!-- The sale being built -->
                    <aside v-if="!sale" aria-labelledby="cart-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-3 lg:sticky lg:top-28 self-start">
                        <h2 id="cart-title" class="text-white font-headline text-lg">This sale</h2>
                        <p v-if="!cart.length" class="text-sm text-slate-400">Scan or tap a piece to add it.</p>
                        <ul class="space-y-2">
                            <li v-for="(l, i) in cart" :key="l.key" class="flex items-center gap-2 text-sm">
                                <span class="min-w-0 flex-1"><span class="block text-white truncate">{{ l.name }}</span><span class="block text-xs text-slate-400">{{ l.label }} · {{ ksh(l.price) }}</span></span>
                                <button type="button" @click="l.quantity > 1 ? l.quantity-- : cart.splice(i, 1)" class="h-8 w-8 rounded-lg bg-white/5" :aria-label="`One fewer ${l.name}`">−</button>
                                <span class="w-6 text-center tabular-nums">{{ l.quantity }}</span>
                                <button type="button" @click="l.quantity++" :disabled="l.quantity >= l.available" class="h-8 w-8 rounded-lg bg-white/5 disabled:opacity-30" :aria-label="`One more ${l.name}`">+</button>
                            </li>
                        </ul>
                        <template v-if="cart.length">
                            <details class="text-sm">
                                <summary class="cursor-pointer text-slate-400">Customer (optional)</summary>
                                <div class="mt-2 grid gap-2">
                                    <input v-model="customer.name" maxlength="255" placeholder="Name" aria-label="Customer name" :class="input" />
                                    <input v-model="customer.phone" type="tel" placeholder="Phone, for the receipt by SMS" aria-label="Customer phone" :class="input" />
                                </div>
                            </details>
                            <details class="text-sm">
                                <summary class="cursor-pointer text-slate-400">Discount</summary>
                                <div class="mt-2 grid gap-2">
                                    <input v-model="discount.amount" type="number" min="0" inputmode="numeric" placeholder="KSh off" aria-label="Discount in shillings" :class="input" />
                                    <input v-model="discount.reason" maxlength="255" placeholder="Why" aria-label="Reason for the discount" :class="input" />
                                    <p class="text-xs text-slate-500">Up to {{ ksh(discountLimit) }} without a manager.</p>
                                    <input v-if="needsDiscountPin" v-model="discount.pin" type="password" inputmode="numeric" placeholder="Manager PIN" aria-label="Manager PIN for the discount" :class="input" />
                                </div>
                            </details>
                            <div class="flex justify-between text-white text-lg pt-2 border-t border-white/10"><span>Total</span><span class="tabular-nums">{{ ksh(subtotal - Number(discount.amount || 0)) }}</span></div>
                            <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                            <button type="button" @click="ringUp" :disabled="busy" :class="primary" class="w-full py-3">Take payment</button>
                            <button type="button" @click="late.open = !late.open" class="w-full text-xs text-slate-400 underline">Enter a paper slip from an outage instead</button>
                            <form v-if="late.open" @submit.prevent="enterLate" class="grid gap-2 rounded-xl bg-slate-950/60 p-3" aria-label="Late entry from a paper slip">
                                <p class="text-xs text-slate-400">These pieces, sold on paper while the till was down. Stock moves now; the sale keeps the slip’s time.</p>
                                <input v-model="late.slip_number" required maxlength="50" placeholder="Slip number" aria-label="Slip number" :class="input" />
                                <label class="text-xs text-slate-400">When it was sold<input v-model="late.occurred_at" type="datetime-local" required :class="input" class="mt-1" /></label>
                                <input v-model="late.reason" required maxlength="255" placeholder="Why it was on paper" aria-label="Why it was on paper" :class="input" />
                                <div class="grid grid-cols-2 gap-2">
                                    <input v-model="late.cash" type="number" min="0" placeholder="Cash KSh" aria-label="Cash taken" :class="input" />
                                    <input v-model="late.mpesa" type="number" min="0" placeholder="M-Pesa KSh" aria-label="M-Pesa taken" :class="input" />
                                </div>
                                <input v-if="Number(late.mpesa) > 0" v-model="late.reference" required placeholder="M-Pesa code on the slip" aria-label="M-Pesa code" :class="input" class="uppercase" />
                                <input v-if="!operator.can_approve" v-model="late.pin" type="password" inputmode="numeric" placeholder="Manager PIN" aria-label="Manager PIN for the late entry" :class="input" />
                                <button type="submit" :disabled="busy" :class="primary">Enter the slip</button>
                            </form>
                        </template>
                        <p v-if="lateDone" class="text-sm text-emerald-300" role="status">Slip entered as {{ lateDone.reference }}.</p>
                    </aside>
                </div>

                <!-- ORDERS: collect and returns -->
                <div v-else-if="tab === 'orders'" class="grid gap-4 lg:grid-cols-2">
                    <section aria-labelledby="collect-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-3">
                        <h2 id="collect-title" class="text-white font-headline text-lg">Collect an order</h2>
                        <p class="text-sm text-slate-400">The reference from their message and the phone number on the order.</p>
                        <form @submit.prevent="findForCollection" class="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
                            <input v-model="desk.ref" required placeholder="SRN-…" aria-label="Order reference" :class="input" class="uppercase" />
                            <input v-model="desk.phone" type="tel" required placeholder="07XX XXX XXX" aria-label="Customer phone" :class="input" />
                            <button type="submit" :disabled="busy" :class="quiet">Find</button>
                        </form>
                        <p v-if="collected" class="text-sm text-emerald-300" role="status">{{ collected }} handed over.</p>
                        <div v-if="collectOrder" class="space-y-3 border-t border-white/10 pt-3">
                            <div class="flex justify-between gap-3">
                                <p class="text-white">{{ collectOrder.reference }} · {{ collectOrder.customer }}</p>
                                <p class="text-sm tabular-nums">{{ ksh(collectOrder.paid) }} of {{ ksh(collectOrder.total) }}</p>
                            </div>
                            <ul class="text-sm text-slate-300"><li v-for="(l, i) in collectOrder.lines" :key="i">{{ l.quantity }} × {{ l.description }}</li></ul>
                            <template v-if="collectOrder.balance_due > 0 && collectOrder.state === 'open'">
                                <p class="text-amber-300 text-sm">{{ collectOrder.collect_blocker }}</p>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="m in ['cash', 'mpesa_manual', 'mpesa_prompt']" :key="m" type="button" @click="tender.method = m"
                                        class="rounded-xl px-3 py-2 text-sm" :class="tender.method === m ? 'bg-[#57d5fc] text-slate-900 font-semibold' : 'bg-white/5 text-slate-300'">{{ methodLabel[m] }}</button>
                                </div>
                                <form @submit.prevent="takeMoney('collect')" class="grid gap-2 sm:grid-cols-2">
                                    <input v-model="tender.amount" type="number" min="1" required aria-label="Amount" :class="input" />
                                    <input v-if="tender.method === 'cash'" v-model="tender.tendered" type="number" min="1" required aria-label="Cash handed over" placeholder="Cash handed over" :class="input" />
                                    <input v-if="tender.method === 'mpesa_manual'" v-model="tender.reference" required aria-label="M-Pesa code" placeholder="M-Pesa code" :class="input" class="uppercase" />
                                    <input v-if="tender.method === 'mpesa_prompt'" v-model="tender.phone" type="tel" required aria-label="M-Pesa number" placeholder="07XX XXX XXX" :class="input" />
                                    <button type="submit" :disabled="busy" :class="primary" class="sm:col-span-2">Take the balance</button>
                                </form>
                                <p v-if="change" class="text-emerald-300">Change: {{ ksh(change) }}</p>
                            </template>
                            <template v-else-if="collectOrder.can_collect">
                                <input v-model="desk.note" maxlength="255" placeholder="Note (e.g. collected by her sister)" aria-label="Note" :class="input" />
                                <button type="button" @click="markCollected" :disabled="busy" :class="primary" class="w-full">Hand it over</button>
                            </template>
                            <p v-else class="text-sm text-amber-300">{{ collectOrder.collect_blocker }}</p>
                        </div>
                        <p v-if="firstError && (collectOrder || !returnOrder)" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                    </section>

                    <section aria-labelledby="return-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-3">
                        <h2 id="return-title" class="text-white font-headline text-lg">Return or exchange</h2>
                        <form @submit.prevent="findForReturn" class="grid gap-2 grid-cols-[1fr_auto]">
                            <input v-model="returnRef" required placeholder="Receipt reference SRN-…" aria-label="Sale reference" :class="input" class="uppercase" />
                            <button type="submit" :disabled="busy" :class="quiet">Find</button>
                        </form>
                        <p v-if="returned" class="text-sm text-emerald-300" role="status">
                            {{ returned.reference }}: {{ returned.method === 'credit' ? 'credit ready — take it on the new sale with this reference.' : returned.method === 'later' ? 'refund left for the Studio.' : ksh(returned.refunded) + ' refunded.' }}
                        </p>
                        <form v-if="returnOrder" @submit.prevent="submitReturn" class="space-y-3 border-t border-white/10 pt-3">
                            <p class="text-white">{{ returnOrder.reference }} · {{ returnOrder.customer }} · paid {{ ksh(returnOrder.paid) }}</p>
                            <div v-for="l in returnOrder.lines" :key="l.id" class="grid grid-cols-[1fr_5rem_8rem] gap-2 items-center text-sm">
                                <span>{{ l.description }} <span class="text-xs text-slate-400">({{ l.returnable }} of {{ l.quantity }} returnable)</span></span>
                                <input v-model="returnForm.lines[l.id]" type="number" min="0" :max="l.returnable" :disabled="!l.returnable" :aria-label="`How many ${l.description} back`" :class="input" />
                                <select v-model="returnForm.disposition[l.id]" :aria-label="`Condition of ${l.description}`" :class="input"><option value="restock">Back on sale</option><option value="write_off">Damaged</option></select>
                            </div>
                            <input v-model="returnForm.reason" required maxlength="255" placeholder="Why it came back" aria-label="Why it came back" :class="input" />
                            <div class="grid gap-2 sm:grid-cols-2">
                                <select v-model="returnForm.refund_method" aria-label="Refund" :class="input">
                                    <option value="cash">Cash from the drawer</option><option value="mpesa_manual">M-Pesa (sent now)</option>
                                    <option value="credit">Exchange (credit)</option><option value="later">Refund later from the Studio</option>
                                </select>
                                <input v-if="returnForm.refund_method === 'mpesa_manual'" v-model="returnForm.refund_reference" required placeholder="Code of the M-Pesa you sent" aria-label="Refund M-Pesa code" :class="input" class="uppercase" />
                                <input v-if="!operator.can_approve" v-model="returnForm.pin" type="password" inputmode="numeric" placeholder="Manager PIN" aria-label="Manager PIN for the return" :class="input" />
                            </div>
                            <button type="submit" :disabled="busy || !returnValue" :class="primary" class="w-full">Return {{ ksh(returnValue) }}</button>
                            <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                        </form>
                    </section>
                </div>

                <!-- BESPOKE -->
                <section v-else-if="tab === 'bespoke'" aria-labelledby="intake-title" class="max-w-3xl rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-4">
                    <h2 id="intake-title" class="text-white font-headline text-lg">Bespoke order</h2>
                    <form v-if="!intakeOrder" @submit.prevent="submitIntake" class="grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">Name<input v-model="intake.full_name" required maxlength="255" :class="input" class="mt-1" /></label>
                        <label class="text-sm">Phone<input v-model="intake.phone" type="tel" required :class="input" class="mt-1" placeholder="07XX XXX XXX" /></label>
                        <label class="text-sm">Email (optional)<input v-model="intake.email" type="email" :class="input" class="mt-1" /></label>
                        <label class="text-sm">Garment<input v-model="intake.garment" list="garments" maxlength="100" :class="input" class="mt-1" /><datalist id="garments"><option v-for="g in garments" :key="g" :value="g" /></datalist></label>
                        <label class="text-sm">Occasion<input v-model="intake.occasion" maxlength="100" :class="input" class="mt-1" /></label>
                        <label class="text-sm">Needed by<input v-model="intake.deadline" type="date" :class="input" class="mt-1" /></label>
                        <label class="text-sm">Fabric<input v-model="intake.fabric_preference" maxlength="255" :class="input" class="mt-1" /></label>
                        <label class="text-sm">Colour<input v-model="intake.selected_color" maxlength="100" :class="input" class="mt-1" /></label>
                        <fieldset class="sm:col-span-2 grid grid-cols-3 sm:grid-cols-5 gap-2">
                            <legend class="text-sm mb-1">Measurements (cm)</legend>
                            <label v-for="m in ['bust', 'waist', 'hips', 'length', 'height']" :key="m" class="text-xs text-slate-400 capitalize">{{ m }}<input v-model="intake[m + '_cm']" type="number" step="0.5" min="10" max="250" :class="input" class="mt-1" /></label>
                        </fieldset>
                        <label class="text-sm sm:col-span-2">Notes<textarea v-model="intake.notes" rows="3" maxlength="5000" :class="input" class="mt-1"></textarea></label>
                        <label class="text-sm">Reference photos (up to 6)<input type="file" multiple accept="image/*,application/pdf" @change="intakeImages = [...$event.target.files].slice(0, 6)" class="mt-1 block text-sm text-slate-400" /></label>
                        <label class="text-sm">Quote now (KSh, optional)<input v-model="intake.quote" type="number" min="1" inputmode="numeric" :class="input" class="mt-1" /></label>
                        <p v-if="firstError" role="alert" class="sm:col-span-2 text-sm text-rose-300">{{ firstError }}</p>
                        <button type="submit" :disabled="busy" :class="primary" class="sm:col-span-2">Create the order</button>
                    </form>
                    <div v-else class="space-y-3">
                        <p class="text-white">{{ intakeOrder.reference }} · {{ intakeOrder.customer }}<template v-if="intakeOrder.garment"> · {{ intakeOrder.garment }}</template></p>
                        <p v-if="!intakeOrder.total" class="text-sm text-slate-400">No quote yet: the Studio quotes it, and the customer pays the deposit from their link or here.</p>
                        <template v-else>
                            <p class="text-sm">Quote {{ ksh(intakeOrder.total) }} · deposit {{ ksh(intakeOrder.deposit_required) }} · paid {{ ksh(intakeOrder.paid) }}</p>
                            <form v-if="intakeOrder.balance_due > 0" @submit.prevent="takeMoney('intake')" class="grid gap-2 sm:grid-cols-[auto_1fr_1fr_auto] items-center">
                                <select v-model="tender.method" aria-label="How they pay" :class="input"><option value="cash">Cash</option><option value="mpesa_manual">M-Pesa code</option><option value="mpesa_prompt">M-Pesa prompt</option></select>
                                <input v-model="tender.amount" type="number" min="1" required aria-label="Amount" :class="input" />
                                <input v-if="tender.method === 'cash'" v-model="tender.tendered" type="number" min="1" required aria-label="Cash handed over" placeholder="Handed over" :class="input" />
                                <input v-else-if="tender.method === 'mpesa_manual'" v-model="tender.reference" required aria-label="M-Pesa code" placeholder="M-Pesa code" :class="input" class="uppercase" />
                                <input v-else v-model="tender.phone" type="tel" required aria-label="M-Pesa number" placeholder="07XX XXX XXX" :class="input" />
                                <button type="submit" :disabled="busy" :class="primary">Take it</button>
                            </form>
                            <p v-if="change" class="text-emerald-300">Change: {{ ksh(change) }}</p>
                        </template>
                        <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                        <button type="button" @click="newIntake" :class="quiet">Another bespoke order</button>
                    </div>
                </section>

                <!-- DRAWER -->
                <div v-else-if="tab === 'till'" class="grid gap-4 lg:grid-cols-2">
                    <section v-if="closed" aria-labelledby="z-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-2 lg:col-span-2">
                        <h2 id="z-title" class="text-white font-headline text-lg">{{ closed.register }} closed · Z report</h2>
                        <p class="text-sm">Expected {{ ksh(closed.expected_cash) }} · counted {{ ksh(closed.counted_cash) }} ·
                            <span :class="closed.variance === 0 ? 'text-emerald-300' : 'text-amber-300'">{{ closed.variance === 0 ? 'balanced' : (closed.variance < 0 ? ksh(-closed.variance) + ' short' : ksh(closed.variance) + ' over') }}</span></p>
                        <p class="text-sm text-slate-400">{{ closed.sales_count }} sale(s) paid, {{ closed.voids }} void(s), discounts {{ ksh(closed.discounts) }}</p>
                        <p class="text-sm text-slate-400">Taken: {{ Object.entries(closed.sales_by_method).map(([m, v]) => `${methodLabel[m] || m} ${ksh(v)}`).join(' · ') || 'nothing' }}<template v-if="Object.keys(closed.refunds_by_method || {}).length"> · refunded {{ Object.entries(closed.refunds_by_method).map(([m, v]) => `${methodLabel[m] || m} ${ksh(v)}`).join(' · ') }}</template></p>
                        <div v-if="closed.unfinished?.length" class="rounded-xl bg-amber-500/10 ring-1 ring-amber-400/30 p-3 text-sm text-amber-200">
                            Still owed, part paid: {{ closed.unfinished.map(u => `${u.reference} (${ksh(u.balance_due)})`).join(', ') }}. Finish or refund them from Orders or the Studio.
                        </div>
                        <button type="button" @click="closed = null" :class="quiet">Done</button>
                    </section>
                    <section v-if="!till && !closed" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-3 lg:col-span-2" aria-labelledby="open2-title">
                        <h2 id="open2-title" class="text-white font-headline text-lg">{{ register }} is closed</h2>
                        <form @submit.prevent="openTill" class="grid gap-2 sm:grid-cols-[1fr_auto]">
                            <input v-model="openFloat" type="number" min="0" required aria-label="Opening float" placeholder="Opening float (KSh)" :class="input" />
                            <button type="submit" :disabled="busy" :class="primary">Open the drawer</button>
                        </form>
                    </section>
                    <template v-if="till">
                        <section aria-labelledby="now-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-2">
                            <h2 id="now-title" class="text-white font-headline text-lg">In the drawer now</h2>
                            <template v-if="report">
                                <p class="text-3xl text-white tabular-nums">{{ ksh(report.expected_cash) }}</p>
                                <dl class="text-sm grid grid-cols-2 gap-y-1">
                                    <dt class="text-slate-400">Float</dt><dd class="text-right tabular-nums">{{ ksh(report.opening_float) }}</dd>
                                    <dt class="text-slate-400">Cash sales</dt><dd class="text-right tabular-nums">{{ ksh(report.cash.sale) }}</dd>
                                    <dt class="text-slate-400">Cash refunds</dt><dd class="text-right tabular-nums">{{ ksh(report.cash.refund) }}</dd>
                                    <dt class="text-slate-400">Paid out</dt><dd class="text-right tabular-nums">{{ ksh(report.cash.payout) }}</dd>
                                    <dt class="text-slate-400">To the bank</dt><dd class="text-right tabular-nums">{{ ksh(report.cash.pickup) }}</dd>
                                </dl>
                                <p class="text-xs text-slate-400">{{ report.sales_count }} sale(s) paid · opened by {{ report.opened_by }} {{ report.opened_at }}</p>
                                <p v-if="report.unfinished?.length" class="text-xs text-amber-300">Not finished: {{ report.unfinished.map(u => `${u.reference} (${ksh(u.balance_due)} owed)`).join(', ') }}. Sales never paid are voided when you close.</p>
                            </template>
                        </section>
                        <section aria-labelledby="out-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-2">
                            <h2 id="out-title" class="text-white font-headline text-lg">Take cash out</h2>
                            <form @submit.prevent="takeOut" class="grid gap-2 sm:grid-cols-2">
                                <select v-model="payout.type" aria-label="Kind" :class="input"><option value="payout">An expense</option><option value="pickup">To the bank</option></select>
                                <input v-model="payout.amount" type="number" min="1" required aria-label="Amount" placeholder="KSh" :class="input" />
                                <input v-model="payout.reason" required maxlength="255" aria-label="Reason" placeholder="What for" :class="input" class="sm:col-span-2" />
                                <input v-model="payout.pin" type="password" inputmode="numeric" required aria-label="Manager PIN" placeholder="Manager PIN" :class="input" />
                                <button type="submit" :disabled="busy" :class="quiet">Take out</button>
                            </form>
                        </section>
                        <section aria-labelledby="close-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-2 lg:col-span-2">
                            <h2 id="close-title" class="text-white font-headline text-lg">Close {{ register }}</h2>
                            <p class="text-sm text-slate-400">Count the drawer without looking at the expected figure. A difference over {{ ksh(policy.variance_alert) }} needs a reason and a manager.</p>
                            <form @submit.prevent="closeTill" class="grid gap-2 sm:grid-cols-4">
                                <input v-model="closing.counted" type="number" min="0" required aria-label="Cash counted" placeholder="Cash counted" :class="input" />
                                <input v-model="closing.reason" maxlength="255" aria-label="Reason for any difference" placeholder="Reason for any difference" :class="input" />
                                <input v-model="closing.pin" type="password" inputmode="numeric" aria-label="Manager PIN" placeholder="Manager PIN (if asked)" :class="input" />
                                <button type="submit" :disabled="busy" :class="primary">Close and print Z</button>
                            </form>
                        </section>
                    </template>
                    <p v-if="firstError" role="alert" class="text-sm text-rose-300 lg:col-span-2">{{ firstError }}</p>
                </div>
            </main>

            <!-- Size sheet -->
            <div v-if="sizing" class="fixed inset-0 z-30 bg-black/60 flex items-end sm:items-center justify-center p-4" @click.self="sizing = null">
                <div role="dialog" aria-modal="true" aria-labelledby="size-title" class="w-full max-w-md rounded-2xl bg-slate-900 ring-1 ring-white/10 p-5 space-y-4">
                    <h2 id="size-title" class="text-white font-headline text-lg">{{ sizing.name }}</h2>
                    <div v-for="part in partsOf(sizing)" :key="part">
                        <p class="text-sm text-slate-400 mb-2">{{ partLabel[part] }}</p>
                        <div class="flex flex-wrap gap-2" role="radiogroup" :aria-label="partLabel[part]">
                            <button v-for="s in sizing.sizes.filter(x => x.part === part)" :key="s.id" type="button" role="radio" :aria-checked="chosen[part]?.id === s.id"
                                :disabled="(s.available ?? 1) < 1" @click="chosen[part] = s"
                                class="min-w-14 rounded-xl px-3 py-2 text-sm disabled:opacity-30 disabled:line-through" :class="chosen[part]?.id === s.id ? 'bg-[#57d5fc] text-slate-900 font-semibold' : 'bg-white/5 text-white'">
                                {{ s.size }}<span class="block text-[10px] opacity-70">{{ s.available ?? '' }} left</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" @click="confirmSizes" :disabled="!sizingReady" :class="primary" class="flex-1">Add to sale</button>
                        <button type="button" @click="sizing = null" :class="quiet">Cancel</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
