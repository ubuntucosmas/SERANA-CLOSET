<!-- The till (Phase E, design §15.3–15.5). The tablet stays signed in as a
     staff account; each person unlocks it with their PIN and every action is
     recorded as them. It locks itself after the idle minutes. Sell, take
     money, hand over orders, take bespoke orders, returns, and close the
     drawer — all against the till endpoints, which hold every rule. -->
<script setup>
import { registerLabel } from '@/Support/register';
import { computed, onBeforeUnmount, onMounted, nextTick, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';
import { canAddPosLine } from '@/Support/posStock';
import Modal from '@/Components/Modal.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import WorkflowContext from '@/Components/WorkflowContext.vue';

const props = defineProps({
    operator: { type: Object, default: null },
    shop: { type: String, default: null },
    tills: { type: Array, default: () => [] },
    policy: { type: Object, required: true },
    garments: { type: Array, default: () => [] },
});

const page = usePage();
const sessionInterceptor = axios.interceptors.response.use(response => response, error => {
    if (error.response?.status === 401) window.location.assign(route('login'));
    return Promise.reject(error);
});
onBeforeUnmount(() => axios.interceptors.response.eject(sessionInterceptor));
const canUseStudio = computed(() => page.props.auth?.user?.abilities?.includes('studio.access'));
const { formatAmount } = useCurrency();
const ksh = value => formatAmount(value, page.props);
const operator = ref(props.operator);
const tills = ref(props.tills);
const register = ref(props.tills[0]?.register || 'Till 1');
const till = computed(() => tills.value.find(t => t.register === register.value) || null);
// One shop, one till: the picker shows only when more than one register is open (audit P2-7).
const registers = computed(() => [...new Set(['Till 1', ...tills.value.map(t => t.register)])]);
const tab = ref('sell');
const tabs = [
    { key: 'sell', label: 'New sale', icon: 'point_of_sale' },
    { key: 'orders', label: 'Order pickup', icon: 'inventory_2' },
    { key: 'sales', label: 'Session sales', icon: 'receipt_long' },
    { key: 'bespoke', label: 'Custom orders', icon: 'design_services' },
    { key: 'till', label: 'Register', icon: 'payments' },
];

// ---- Talking to the till: every call says which register; a 423 means the tablet locked itself. ----
const errors = ref({});
const busy = ref(false);
async function call(method, url, data = {}, opts = {}) {
    if (busy.value) throw Object.assign(new Error('An operation is already in progress.'), { operationPending: true });
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
    if (e?.operationPending) return;
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
        if (till.value && !results.value.length) { search(false); focusSearch(); }
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
onMounted(() => {
    touch(); ['pointerdown', 'keydown'].forEach(e => window.addEventListener(e, touch));
    // The pieces show straight away; typing narrows them (audit P2-9).
    if (operator.value && till.value) { search(false); focusSearch(); }
});
onBeforeUnmount(() => { clearTimeout(idleTimer); clearInterval(promptPoll); clearTimeout(searchTimer); searchSequence++; ['pointerdown', 'keydown'].forEach(e => window.removeEventListener(e, touch)); });

// ---- Open Register ----
const openFloat = ref('');
async function openTill() {
    try {
        const data = await call('post', route('pos.till.open'), { float: Number(openFloat.value || 0) });
        tills.value = [...tills.value.filter(t => t.register !== register.value), data.till];
        openFloat.value = '';
        search(false); focusSearch();
    } catch (e) { fail(e); }
}

// ---- Sell: find, pick sizes, ring up ----
const productSearch = ref(null);
async function focusSearch() { await nextTick(); productSearch.value?.focus(); }
const query = ref('');
const results = ref([]);
const categories = ref([]);
const selectedCategory = ref('');
let searchTimer = null;
let searchSequence = 0;
const searching = ref(false);
const searchError = ref('');
async function search(scan = false) {
    const sequence = ++searchSequence;
    searching.value = true;
    searchError.value = '';
    try {
        const { data } = await axios.get(route('pos.catalogue'), { params: { register: register.value, q: query.value.trim(), category_id: selectedCategory.value || undefined } });
        if (sequence !== searchSequence) return;
        results.value = data.products;
        categories.value = data.categories || [];
        // A scanned barcode or SKU picks its size straight onto the sale.
        if (scan && data.exact) {
            const product = data.products.find(p => p.id === data.exact.product_id);
            const size = product?.sizes.find(s => s.id === data.exact.variant_id);
            if (product && size && product.garment_type !== 'set') { addLine(product, { [size.part]: size }); query.value = ''; }
            else if (product) openSizes(product);
        }
    } catch (e) {
        if (sequence === searchSequence) {
            searchError.value = 'Could not load products. Check the connection and retry.';
            if (e.response?.status === 423) operator.value = null;
        }
    } finally { if (sequence === searchSequence) searching.value = false; }
}
watch([query, selectedCategory], () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => search(false), 250); });

const sizing = ref(null); // the product whose size sheet is open
const chosen = reactive({});
const partsOf = (p) => [...new Set(p.sizes.map(s => s.part))];
const partLabel = { single: 'Size', top: 'Top', bottom: 'Bottom' };
const productAvailable = product => product.sizes.length > 0 && partsOf(product).every(part => product.sizes.some(size => size.part === part && (size.available ?? 1) > 0));
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
    if (savedDraft.value) { draftNotice.value = 'Restore or discard your saved bag first.'; return; }
    const sizes = Object.values(byPart);
    const key = product.id + ':' + sizes.map(s => s.id).sort().join('-');
    const candidate = { variant_ids: sizes.map(s => s.id), variant_limits: Object.fromEntries(sizes.map(s => [s.id, s.available ?? Infinity])) };
    if (!canAddPosLine(cart.value, candidate)) { errors.value = { _: ['Not enough stock in these sizes. Reduce the quantities already in the bag.'] }; return; }
    const existing = cart.value.find(l => l.key === key);
    if (existing) { existing.quantity++; return; }
    const options = {};
    for (const [part, size] of Object.entries(byPart)) options[part === 'bottom' ? 'bottom_size' : 'top_size'] = size.size;
    cart.value.push({ key, id: product.id, name: product.name, price: product.price, quantity: 1, options, ...candidate,
        label: sizes.map(s => (s.part === 'single' ? '' : partLabel[s.part] + ' ') + s.size).join(' · '),
        available: Math.min(...sizes.map(s => s.available ?? Infinity)) });
}
function incrementLine(line) { if (canAddPosLine(cart.value, line)) line.quantity++; }
const subtotal = computed(() => cart.value.reduce((n, l) => n + l.price * l.quantity, 0));
const customer = reactive({ name: '', phone: '', email: '' });
const discount = reactive({ amount: '', reason: '', pin: '' });
const discountLimit = computed(() => Math.min(Math.floor(subtotal.value * props.policy.discount_percent / 100), props.policy.discount_cap));
const needsDiscountPin = computed(() => Number(discount.amount || 0) > discountLimit.value && !operator.value?.can_approve);

const sale = ref(null);
// A draft is confined to this tab and this operator/register. Never store PINs.
const savedDraft = ref(null);
const draftNotice = ref('');
const draftKey = computed(() => operator.value ? `serana-pos-draft:${operator.value.id}:${register.value}` : null);
function readDraft() {
    savedDraft.value = null;
    if (!draftKey.value) return;
    try {
        const draft = JSON.parse(sessionStorage.getItem(draftKey.value) || 'null');
        if (draft?.cart?.length) savedDraft.value = draft;
    } catch { draftNotice.value = 'Draft recovery is unavailable in this browser. Keep this tab open until the sale is created.'; }
}
function discardDraft() {
    if (draftKey.value) { try { sessionStorage.removeItem(draftKey.value); } catch {} }
    savedDraft.value = null;
}
function restoreDraft() {
    if (!savedDraft.value) return;
    cart.value = savedDraft.value.cart.map(line => ({ ...line,
        available: line.available ?? Infinity,
        variant_limits: Object.fromEntries(Object.entries(line.variant_limits || {}).map(([id, limit]) => [id, limit ?? Infinity])),
    }));
    Object.assign(customer, savedDraft.value.customer);
    discount.amount = savedDraft.value.discount?.amount || '';
    discount.reason = savedDraft.value.discount?.reason || '';
    savedDraft.value = null;
    draftNotice.value = 'Draft restored. Stock and prices will be confirmed when you take payment.';
}
function persistDraft() {
    if (!draftKey.value || savedDraft.value) return;
    try {
        if (sale.value || !cart.value.length) sessionStorage.removeItem(draftKey.value);
        else sessionStorage.setItem(draftKey.value, JSON.stringify({ cart: cart.value, customer: { ...customer }, discount: { amount: discount.amount, reason: discount.reason }, at: new Date().toISOString() }));
    } catch { draftNotice.value = 'This browser could not save your draft. Keep this tab open until the sale is created.'; }
}
watch([cart, customer, () => discount.amount, () => discount.reason, sale], persistDraft, { deep: true, flush: 'sync' });
watch(draftKey, (key, previous) => {
    savedDraft.value = { switching: true };
    if (key !== previous) {
        cart.value = []; sale.value = null;
        Object.assign(customer, { name: '', phone: '', email: '' });
        Object.assign(discount, { amount: '', reason: '', pin: '' });
        readDraft();
    }
});
onMounted(readDraft);

async function ringUp() {
    try {
        const data = await call('post', route('pos.sales.store'), {
            items: cart.value.map(l => ({ id: l.id, quantity: l.quantity, options: l.options, variant_ids: l.variant_ids })),
            customer: customer.name || customer.phone || customer.email ? { name: customer.name || null, phone: customer.phone || null, email: customer.email || null } : null,
            discount: Number(discount.amount || 0) || null, discount_reason: discount.reason || null, approval_pin: discount.pin || null,
        });
        sale.value = data.sale;
        change.value = null;
        startTender('sale');
    } catch (e) { fail(e); }
}
function newSale() {
    discardDraft(); draftNotice.value = '';
    paymentMessage.value = ''; paymentCheckedAt.value = '';
    sale.value = null; cart.value = []; query.value = ''; results.value = [];
    Object.assign(customer, { name: '', phone: '', email: '' }); Object.assign(discount, { amount: '', reason: '', pin: '' });
    errors.value = {}; change.value = null; clearInterval(promptPoll);
    search(false); focusSearch();
}

// ---- Take money (a sale, a collection's balance, a bespoke deposit) ----
const tender = reactive({ method: 'cash', amount: '', tendered: '', reference: '', phone: '', credit_ref: '' });
const change = ref(null);
const cashPreview = computed(() => Math.max(0, Number(tender.tendered || 0) - Number(tender.amount || 0)));
watch(tab, value => { if (value === 'sell') focusSearch(); });
let promptPoll = null;
const paymentCheckedAt = ref('');
const paymentMessage = ref('');
const checkingPayment = ref(false);
async function checkPayment(which = 'sale') {
    const order = targets[which].value;
    if (!order || !operator.value || checkingPayment.value) return;
    checkingPayment.value = true;
    try {
        const { data } = await axios.get(route('pos.sales.show', order.id));
        await refreshers[which](data.sale);
        paymentCheckedAt.value = new Date().toLocaleTimeString();
        paymentMessage.value = data.sale.prompt?.status === 'pending' ? 'Payment is still pending. Check again before requesting another payment.' : '';
    } catch (e) { if (e.response?.status === 423) operator.value = null; paymentMessage.value = 'Could not check payment. Check the connection and retry before taking more money.'; }
    finally { checkingPayment.value = false; }
}
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
    if (order?.prompt?.status === 'pending') return checkPayment(which);
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
            paymentCheckedAt.value = new Date().toLocaleTimeString();
            if (data.sale.paid || data.sale.balance_due <= 0 || (data.sale.prompt && data.sale.prompt.status !== 'pending') || tries > 40) { clearInterval(promptPoll); if (tries > 40) paymentMessage.value = 'Automatic checks paused. Check payment status before taking more money.'; }
        } catch { clearInterval(promptPoll); paymentMessage.value = 'Connection interrupted. Check payment status before taking more money.'; }
    }, 3000);
}
// The customer changes their mind after "Take payment": void the untouched sale and put the same pieces back in the bag.
async function changeItems() {
    const s = sale.value;
    try {
        await call('post', route('pos.sales.void', s.id), { reason: 'Changed the pieces before paying' });
        if (!cart.value.length) {
            cart.value = (s.items || []).map(i => ({ key: i.id + ':' + (i.variant_ids || []).join('-'), id: i.id, name: i.name, price: i.price, quantity: i.quantity,
                options: i.options, variant_ids: i.variant_ids, label: Object.values(i.options || {}).filter(Boolean).join(' · '), available: Infinity }));
        }
        sale.value = null;
        change.value = null;
    } catch (e) { fail(e); }
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

watch(operator, (person) => {
    if (!person) clearInterval(promptPoll);
    else if (sale.value?.prompt?.status === 'pending') { checkPayment('sale'); watchPrompt(sale.value.id, refreshers.sale); }
});

// ---- Today's sales on this drawer: nothing is lost to a reload (audit P1-1) ----
const todays = ref(null);
async function loadToday() { try { todays.value = (await call('get', route('pos.sales.index'))).sales; } catch (e) { fail(e); } }
watch([tab, operator], () => { if (tab.value === 'sales' && till.value && operator.value) loadToday(); });
async function resume(id) {
    paymentMessage.value = ''; paymentCheckedAt.value = '';
    try {
        sale.value = (await call('get', route('pos.sales.show', id))).sale;
        cart.value = [];
        change.value = null;
        startTender('sale');
        tab.value = 'sell';
        if (sale.value.prompt?.status === 'pending') watchPrompt(sale.value.id, refreshers.sale);
    } catch (e) { fail(e); }
}

// ---- Orders desk: collect, returns ----
const desk = reactive({ ref: '', phone: '', note: '' });
const collectOrder = ref(null);
async function findForCollection() {
    try {
        collectOrder.value = (await call('get', route('pos.collect.find'), { ref: desk.ref.trim(), phone: desk.phone })).order;
        change.value = null;
        startTender('collect');
        rememberDeskOrder(collectOrder.value.id);
        if (collectOrder.value.prompt?.status === 'pending') watchPrompt(collectOrder.value.id, refreshCollect);
    } catch { collectOrder.value = null; }
}
async function refreshCollect() {
    if (!collectOrder.value) return;
    const id = collectOrder.value.id;
    const data = await call('get', route('pos.orders.show', id));
    if (collectOrder.value?.id === id) {
        collectOrder.value = data.order;
        updateFoundOrder(data.order);
    }
}
const orderQuery = ref('');
const foundOrders = ref(null);
const hasMoreOrders = ref(false);
function updateFoundOrder(order) {
    if (foundOrders.value) foundOrders.value = foundOrders.value.map(item => item.id === order.id ? { ...item, balance_due: order.balance_due, state: order.state, fulfilment: order.fulfilment } : item);
}
async function searchOrders() {
    try {
        const data = await call('get', route('pos.orders.search'), { q: orderQuery.value.trim() });
        foundOrders.value = data.orders; hasMoreOrders.value = data.has_more;
    } catch (e) { foundOrders.value = null; fail(e); }
}
function rememberDeskOrder(id) {
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('order', id); else url.searchParams.delete('order');
    window.history.replaceState(window.history.state, '', url);
}
async function continueOrder(id) {
    clearInterval(promptPoll);
    paymentMessage.value = ''; paymentCheckedAt.value = ''; desk.phone = ''; desk.note = '';
    try {
        collectOrder.value = (await call('get', route('pos.orders.show', id))).order;
        desk.ref = collectOrder.value.reference;
        tab.value = 'orders';
        rememberDeskOrder(id);
        startTender('collect');
        if (collectOrder.value.prompt?.status === 'pending') watchPrompt(id, refreshCollect);
    } catch (e) { fail(e); }
}
function restoreDeskOrder() {
    const id = new URL(window.location.href).searchParams.get('order');
    if (operator.value && /^\d+$/.test(id || '')) continueOrder(Number(id));
}
onMounted(restoreDeskOrder);
watch(operator, person => { if (person) restoreDeskOrder(); });
function useDeposit() {
    tender.amount = Math.min(collectOrder.value.deposit_due, collectOrder.value.balance_due);
    tender.tendered = tender.amount;
}
const collected = ref(null);
async function markCollected() {
    try {
        const data = await call('post', route('pos.collect.store', collectOrder.value.id), { phone: desk.phone, note: desk.note || null });
        collected.value = data.order.reference;
        updateFoundOrder(data.order);
        rememberDeskOrder(null);
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
        rememberDeskOrder(intakeOrder.value.id);
    } catch (e) { fail(e); }
}
async function refreshIntake() {
    if (!intakeOrder.value) return;
    intakeOrder.value = (await call('get', route('pos.orders.show', intakeOrder.value.id))).order;
}
function newIntake() { rememberDeskOrder(null); Object.assign(intake, blank()); intakeImages.value = []; intakeOrder.value = null; errors.value = {}; }

// ---- The drawer: take cash out, report, close ----
const report = ref(null);
async function loadReport() { try { report.value = (await call('get', route('pos.till.report'))).report; } catch { report.value = null; } }
watch([tab, register, operator], () => { if (tab.value === 'till' && till.value && operator.value) loadReport(); });
const payout = reactive({ type: 'payout', amount: '', reason: '', pin: '' });
async function takeOut() {
    if (busy.value || !confirm(`Remove ${ksh(payout.amount)} cash? Reason: ${payout.reason}`)) return;
    try {
        await call('post', route('pos.till.payout'), { type: payout.type, amount: Number(payout.amount || 0), reason: payout.reason, approval_pin: payout.pin });
        Object.assign(payout, { type: 'payout', amount: '', reason: '', pin: '' });
        loadReport();
    } catch (e) { fail(e); }
}
const closing = reactive({ counted: '', reason: '', pin: '' });
const closed = ref(null);
const cashDifference = computed(() => closing.counted === '' || !report.value ? null : Number(closing.counted) - Number(report.value.expected_cash));
async function closeTill() {
    if (busy.value) return;
    if (cashDifference.value && !closing.reason.trim()) { errors.value = { variance_reason: ['Explain the cash drawer difference before closing.'] }; return; }
    if (!confirm(`Close ${registerLabel(register.value)} with ${ksh(closing.counted)} counted?`)) return;
    try {
        closed.value = (await call('post', route('pos.till.close'), { counted: Number(closing.counted || 0), variance_reason: closing.reason || null, approval_pin: closing.pin || null })).report;
        tills.value = tills.value.filter(t => t.register !== register.value);
        Object.assign(closing, { counted: '', reason: '', pin: '' });
        report.value = null;
    } catch (e) { fail(e); }
}
function printZ() { window.print(); }
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

const posGuidance = computed(() => ({
    sell: { task: 'Sell the selected pieces and confirm payment', reason: 'Sizes, quantities and payment must match the actual goods handed over.', when: 'When a customer buys at the counter.', confirmation: 'The server shows Paid and offers a receipt; cash change is shown separately.' },
    sales: { task: 'Continue or reprint a sale from this drawer', reason: 'Recover a server-created sale without charging or entering it again.', when: 'After interruption, or when a customer needs their receipt.', confirmation: 'The reopened sale shows the current server balance, payment state and reference.' },
    orders: { task: 'Find an order, continue payment or verify collection', reason: 'Use the existing order so stock, deposits and balances stay on one record.', when: 'After an interrupted payment or when the customer arrives to collect.', confirmation: 'Payment updates the live balance. Handover separately shows the reference as collected after phone verification.' },
    bespoke: { task: 'Record a custom brief, quote and optional deposit', reason: 'Keep the customer requirements and money on one recoverable order.', when: 'When taking a new custom order in person.', confirmation: 'An order reference is created; a payment is complete only when the server updates paid and balance.' },
    till: { task: 'Count and reconcile the cash drawer', reason: 'Explain every payout and any difference between expected and counted cash.', when: 'Open before trading; close after counting at the end of the session.', confirmation: 'A closing report records the count, difference, reason and approval.' },
}[tab.value]));

const deskHeader = ref(null);
const deskContext = ref(null);
const deskHeaderHeight = ref(112);
const deskContextHeight = ref(80);
const layoutObservers = new Map();
for (const [element, height] of [[deskHeader, deskHeaderHeight], [deskContext, deskContextHeight]]) {
    watch(element, node => {
        layoutObservers.get(element)?.disconnect();
        layoutObservers.delete(element);
        if (!node) return;
        const observer = new ResizeObserver(() => { height.value = Math.ceil(node.getBoundingClientRect().height); });
        observer.observe(node);
        layoutObservers.set(element, observer);
    }, { flush: 'post' });
}
onBeforeUnmount(() => layoutObservers.forEach(observer => observer.disconnect()));
const cartCount = computed(() => cart.value.reduce((count, line) => count + line.quantity, 0));
const workspace = computed(() => ({
    sell: sale.value ? { title: sale.value.paid ? 'Sale complete' : 'Take payment', description: 'Review the sale on the left. Confirm money received on the right.' } : { title: 'New sale', description: 'Find an item, select its size and review the current sale.' },
    orders: { title: 'Order pickup', description: 'Find the order, check payment and verify the customer before handover.' },
    sales: { title: 'Session sales', description: 'Continue an unfinished sale or retrieve a paid sale’s receipt.' },
    bespoke: { title: 'Custom orders', description: 'Capture the brief, measurements and quote in one workspace.' },
    till: { title: 'Register', description: 'Review cash movements, count the drawer and close the register.' },
}[tab.value]));

const input = 'w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-white text-sm placeholder-slate-600 focus:ring-1 focus:ring-[#57d5fc] outline-none';
const primary = 'rounded-xl bg-[#57d5fc] px-4 py-2.5 text-sm font-semibold text-slate-900 disabled:opacity-40';
const quiet = 'rounded-xl bg-white/5 px-4 py-2.5 text-sm text-slate-200 hover:bg-white/10 disabled:opacity-40';
</script>

<template>
    <Head title="Sales Desk" />
    <div class="sales-desk min-h-screen bg-slate-950 text-slate-200" :style="{ '--desk-header-height': deskHeaderHeight + 'px', '--desk-context-height': deskContextHeight + 'px' }">
        <!-- Locked: someone's PIN -->
        <div v-if="!operator" class="min-h-screen flex items-center justify-center p-4">
            <div class="absolute right-4 top-4 flex items-center gap-2"><a :href="route('home')" :class="quiet" class="inline-flex min-h-11 items-center gap-2"><span aria-hidden="true" class="material-symbols-outlined text-base">storefront</span>Website</a><ThemeToggle /></div>
            <form @submit.prevent="unlock" class="w-full max-w-xs space-y-5 text-center" aria-label="Unlock Sales Desk">
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
                    <button type="button" @click="pin = pin.slice(0, -1)" class="h-16 rounded-2xl text-slate-400 hover:bg-white/5" aria-label="Delete"><span aria-hidden="true" class="material-symbols-outlined">backspace</span></button>
                    <button type="button" @click="press('0')" class="h-16 rounded-2xl bg-white/5 text-2xl text-white hover:bg-white/10">0</button>
                    <button type="submit" :disabled="pin.length < 4 || busy" class="h-16 rounded-2xl bg-[#57d5fc] text-slate-900 font-semibold disabled:opacity-40" aria-label="Unlock"><span aria-hidden="true" class="material-symbols-outlined">arrow_forward</span></button>
                </div>
                <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
            </form>
        </div>

        <template v-else>
            <header ref="deskHeader" class="desk-header print:hidden">
                <div class="desk-frame desk-topbar">
                    <div class="desk-brand"><span aria-hidden="true" class="desk-brand-icon material-symbols-outlined">point_of_sale</span><div><h1>Sales Desk</h1><p>{{ shop || 'In-store Point of Sale' }}</p></div></div>
                    <div class="desk-session"><span class="desk-register-state" :class="till ? 'is-open' : 'is-closed'"><span aria-hidden="true" class="material-symbols-outlined">{{ till ? 'check_circle' : 'lock' }}</span>{{ registerLabel(register) }} · {{ till ? 'Open' : 'Closed' }}</span><span class="desk-operator">{{ operator.name }}<span v-if="till" class="desk-opened"> · since {{ till.opened_at }}</span></span></div>
                    <div class="desk-header-actions">
                        <ThemeToggle />
                        <a :href="route('home')" :class="quiet" class="gap-2"><span aria-hidden="true" class="material-symbols-outlined text-base">storefront</span>Website</a>
                        <label v-if="registers.length > 1" class="sr-only" for="register">Register</label>
                        <select v-if="registers.length > 1" id="register" v-model="register" class="w-32 !rounded-xl bg-slate-900 border border-white/10 px-3 py-2 text-sm text-white"><option v-for="r in registers" :key="r" :value="r">{{ registerLabel(r) }}</option></select>
                        <a v-if="canUseStudio" :href="route('studio.index')" :class="quiet">Studio</a>
                        <button type="button" @click="lock" :class="quiet" class="flex items-center gap-2"><span aria-hidden="true" class="material-symbols-outlined text-base">lock</span>Lock</button>
                    </div>
                </div>
                <nav class="desk-frame desk-navigation" aria-label="Sales Desk sections">
                    <button v-for="t in tabs" :key="t.key" type="button" @click="tab = t.key; errors = {}" :aria-current="tab === t.key ? 'page' : undefined" class="desk-nav-item" :class="{ 'is-current': tab === t.key }"><span aria-hidden="true" class="material-symbols-outlined">{{ t.icon }}</span>{{ t.label }}</button>
                </nav>
            </header>

            <main class="desk-frame desk-main">
                <div ref="deskContext" class="desk-context"><div><h2>{{ workspace.title }}</h2><p>{{ workspace.description }}</p></div><div class="desk-context-actions"><a v-if="tab === 'sell' && !sale" href="#desk-current-sale" class="desk-cart-jump">View sale · {{ cartCount }} items</a><details class="desk-guide"><summary>Workflow guide</summary><WorkflowContext class="desk-guide-body text-slate-300" :actor="`${operator.name} · ${operator.role || 'staff'}`" :task="posGuidance.task" :reason="posGuidance.reason" :when="posGuidance.when" :where="`${shop || 'Shop'} · ${registerLabel(register)}`" :confirmation="posGuidance.confirmation" /></details></div></div>

                <!-- Nothing happens until the drawer is open -->
                <section v-if="!till && tab !== 'till'" class="desk-open-register max-w-lg mx-auto mt-6 rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-5 space-y-3" aria-labelledby="open-title">
                    <h2 id="open-title" class="text-white font-headline text-lg">Open {{ registerLabel(register) }}</h2>
                    <p class="text-sm text-slate-400">Count the cash in the drawer before the first sale.</p>
                    <form @submit.prevent="openTill" class="space-y-3">
                        <label class="block text-sm">Cash drawer opening balance (KSh)<input v-model="openFloat" type="number" min="0" inputmode="numeric" required :class="input" class="mt-1" /></label>
                        <button type="submit" :disabled="busy" :class="primary" class="w-full">Open Register</button>
                        <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                    </form>
                </section>

                <!-- SELL -->
                <div v-else-if="tab === 'sell'" :class="sale ? 'desk-payment-workspace' : 'desk-sale-workspace'">
                    <section v-if="!sale" aria-labelledby="desk-finder-title" class="desk-panel desk-catalogue">
                        <div class="desk-finder-header"><div class="desk-panel-heading"><h3 id="desk-finder-title">Find items</h3><span aria-live="polite">{{ searching ? 'Searching…' : results.length + ' shown' }}</span></div>
                        <div class="relative">
                            <span aria-hidden="true" class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">barcode_scanner</span>
                            <input ref="productSearch" v-model="query" @keydown.enter.prevent="search(true)" type="search" aria-label="Scan or search" placeholder="Name, SKU, size or category · scan barcode…" :class="input" class="desk-search pl-10 py-3" autofocus />
                        </div>
                        <div v-if="categories.length" class="desk-categories" role="group" aria-label="Product categories"><button type="button" @click="selectedCategory = ''" :aria-pressed="selectedCategory === ''" :class="selectedCategory === '' ? primary : quiet">All</button><button v-for="category in categories" :key="category.id" type="button" @click="selectedCategory = category.id" :aria-pressed="selectedCategory === category.id" :class="selectedCategory === category.id ? primary : quiet">{{ category.name }}</button></div>
                        </div>
                        <div class="desk-results" tabindex="0" aria-label="Product results" :aria-busy="searching">
                        <ul class="desk-product-grid" aria-label="Pieces">
                            <li v-for="p in results" :key="p.id">
                                <button type="button" @click="openSizes(p)" :disabled="!productAvailable(p)" :aria-label="`Add ${p.name} · select size`" class="desk-product-card">
                                    <span class="desk-product-media"><span aria-hidden="true" class="material-symbols-outlined">checkroom</span><img v-if="p.image_url" :src="p.image_url" alt="" loading="lazy" @error="$event.target.remove()" /></span>
                                    <span class="desk-product-content">
                                        <span class="desk-product-name">{{ p.name }}</span>
                                        <span class="desk-product-price">{{ ksh(p.price) }}</span><span class="desk-product-stock">{{ productAvailable(p) ? 'Available' : 'Unavailable' }}</span>
                                        <span class="block mt-1 text-xs text-slate-400">{{ p.sizes.filter(size => (size.available ?? 1) > 0).map(size => (size.part === 'single' ? '' : partLabel[size.part] + ' ') + size.size).join(' · ') || 'No sizes available' }}</span>
                                        <span class="desk-add-label">{{ productAvailable(p) ? 'Add to sale' : 'Out of stock' }}<span aria-hidden="true" class="material-symbols-outlined">{{ productAvailable(p) ? 'add' : 'lock' }}</span></span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                        <p v-if="searching" role="status" class="text-sm text-slate-400">Loading products…</p><div v-if="searchError" role="alert" class="text-sm text-amber-300">{{ searchError }} <button type="button" @click="search(false)" :class="quiet">Retry</button></div>
                        <p v-if="!results.length && !searching && !searchError" class="desk-empty">{{ query ? 'No items match “' + query + '”. Try a name, SKU or size.' : 'No products are available in this view. Check the category or ask a manager to review stock.' }}</p>
                        </div>
                    </section>

                    <!-- Server-confirmed sale facts stay left; the next money action stays right. -->
                    <section v-else aria-labelledby="sale-title" class="desk-panel desk-sale-summary">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 id="sale-title" class="text-white font-headline text-lg">{{ sale.paid ? 'Sale complete' : 'Payment' }} · {{ sale.reference }}</h2>
                                <p class="text-xs text-slate-400">{{ sale.customer }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="sale.paid ? 'bg-emerald-500/15 text-emerald-300' : sale.state === 'cancelled' ? 'bg-rose-500/15 text-rose-300' : 'bg-amber-500/15 text-amber-300'">
                                {{ sale.paid ? 'Paid' : sale.state === 'cancelled' ? 'Voided' : ksh(sale.balance_due) + ' to pay' }}
                            </span>
                        </div>
                        <dl class="grid grid-cols-3 gap-3 rounded-xl bg-slate-950/60 p-3 text-sm"><div><dt class="text-slate-400">Order total</dt><dd class="font-semibold">{{ ksh(sale.total) }}</dd></div><div><dt class="text-slate-400">Received</dt><dd>{{ ksh(sale.paid_so_far) }}</dd></div><div><dt class="text-slate-400">Balance due</dt><dd class="text-xl font-semibold">{{ ksh(sale.balance_due) }}</dd></div></dl>
                        <ul class="text-sm divide-y divide-white/5">
                            <li v-for="(l, i) in sale.lines" :key="i" class="py-2 flex justify-between gap-3"><span>{{ l.quantity }} × {{ l.description }}</span><span class="tabular-nums" :class="l.amount < 0 ? 'text-emerald-300' : ''">{{ ksh(l.amount) }}</span></li>
                            <li v-for="(p, i) in sale.payments" :key="'p' + i" class="py-2 flex justify-between gap-3 text-slate-400"><span>{{ methodLabel[p.method] || p.method }}<template v-if="p.reference"> · {{ p.reference }}</template></span><span class="tabular-nums">{{ p.type === 'refund' ? '−' : '' }}{{ ksh(p.amount) }}</span></li>
                        </ul>

                        <div class="flex flex-wrap gap-2 pt-2 border-t border-white/10">
                            <button v-if="!sale.paid && sale.state === 'open' && !sale.paid_so_far" type="button" @click="changeItems" :disabled="busy" :class="quiet">Change items</button>
                            <button v-if="!sale.paid && sale.state === 'open' && !sale.paid_so_far" type="button" @click="voiding.open = !voiding.open" :class="quiet">Void</button>
                        </div>
                        <form v-if="voiding.open" @submit.prevent="voidSale" class="grid gap-2 sm:grid-cols-[1fr_8rem_auto]" aria-label="Void the sale">
                            <input v-model="voiding.reason" required maxlength="255" placeholder="Why it’s voided" aria-label="Reason" :class="input" />
                            <input v-model="voiding.pin" type="password" inputmode="numeric" placeholder="Manager PIN" aria-label="Manager PIN (another person’s sale)" :class="input" />
                            <button type="submit" :disabled="busy" class="rounded-xl bg-rose-500/80 px-4 py-2.5 text-sm text-white">Void sale</button>
                        </form>
                    </section>
                    <section v-if="sale && !sale.paid && sale.state === 'open'" class="desk-panel desk-tender" aria-labelledby="desk-payment-title">
                        <div class="desk-panel-heading"><h3 id="desk-payment-title">Confirm payment</h3><span>Step 2 of 2</span></div>
                        <div class="desk-amount-due"><span>Balance due</span><strong>{{ ksh(sale.balance_due) }}</strong><p>Confirm receipt before handing over the items.</p></div>
                        <template v-if="!sale.paid && sale.state === 'open'">
                            <div class="flex flex-wrap gap-2" role="group" aria-label="How they pay">
                                <button v-for="m in ['cash', 'mpesa_manual', 'mpesa_prompt', 'credit']" :key="m" type="button" :aria-pressed="tender.method === m" :disabled="busy || sale.prompt?.status === 'pending'" @click="tender.method = m"
                                    class="rounded-xl px-3 py-2 text-sm" :class="tender.method === m ? 'bg-[#57d5fc] text-slate-900 font-semibold' : 'bg-white/5 text-slate-300'">{{ methodLabel[m] }}</button>
                            </div>
                            <form @submit.prevent="takeMoney('sale')" class="grid gap-2 sm:grid-cols-2">
                                <label class="text-sm">Amount<input v-model="tender.amount" type="number" min="1" inputmode="numeric" required :class="input" class="mt-1" /></label>
                                <label v-if="tender.method === 'cash'" class="text-sm">Cash received<input v-model="tender.tendered" type="number" min="1" inputmode="numeric" required :class="input" class="mt-1" /></label>
                                <label v-if="tender.method === 'mpesa_manual'" class="text-sm">M-Pesa code<input v-model="tender.reference" required maxlength="100" autocapitalize="characters" :class="input" class="mt-1 uppercase" /></label>
                                <label v-if="tender.method === 'mpesa_prompt'" class="text-sm">Customer’s M-Pesa number<input v-model="tender.phone" type="tel" required :class="input" class="mt-1" placeholder="07XX XXX XXX" /></label>
                                <label v-if="tender.method === 'credit'" class="text-sm">Returned sale reference<input v-model="tender.credit_ref" required :class="input" class="mt-1 uppercase" placeholder="SRN-…" /></label>
                                <div v-if="tender.method === 'cash'" class="sm:col-span-2 flex gap-2">
                                    <button v-for="n in quick" :key="n" type="button" @click="tender.tendered = n" :class="quiet">{{ ksh(n) }}</button>
                                </div>
                                <p v-if="tender.method === 'cash'" class="sm:col-span-2 text-sm" aria-live="polite">Change preview: <strong>{{ ksh(cashPreview) }}</strong><span class="block text-xs text-slate-400">Confirmed change appears after payment is recorded.</span></p>
                                <button type="submit" :disabled="busy || checkingPayment || sale.prompt?.status === 'pending'" :class="primary" class="sm:col-span-2">{{ busy ? 'Recording payment…' : tender.method === 'mpesa_prompt' ? 'Send M-Pesa prompt' : tender.method === 'cash' ? 'Confirm cash payment' : 'Confirm ' + ksh(tender.amount) }}</button>
                            </form>
                            <div class="space-y-2 text-sm"><button type="button" @click="checkPayment()" :disabled="checkingPayment || busy" :class="quiet">{{ checkingPayment ? 'Checking…' : 'Check payment status' }}</button><p v-if="paymentCheckedAt" class="text-xs text-slate-400">Last checked {{ paymentCheckedAt }}</p><p v-if="paymentMessage" role="status" class="text-amber-300">{{ paymentMessage }}</p></div>
                        <p v-if="sale.prompt && sale.prompt.status === 'pending'" class="text-sm text-amber-300" role="status">Waiting for the customer to enter their M-Pesa PIN…</p>
                        </template>
                        <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                    </section>
                    <section v-else-if="sale" class="desk-panel desk-completion" aria-labelledby="desk-completion-title">
                        <span aria-hidden="true" class="material-symbols-outlined desk-completion-icon">{{ sale.paid ? 'check_circle' : 'close' }}</span>
                        <h3 id="desk-completion-title">{{ sale.paid ? 'Payment confirmed' : 'Sale voided' }}</h3><p>{{ sale.reference }}</p>
                        <strong v-if="sale.paid" class="desk-completion-total">{{ ksh(sale.total) }}</strong>
                        <p v-if="change" class="desk-confirmed-change" role="status">Change to give<strong>{{ ksh(change) }}</strong></p>
                        <button type="button" @click="newSale" :class="primary" class="w-full">New sale</button>
                        <a v-if="sale.paid" :href="receiptUrl(sale.id)" target="_blank" rel="noopener" :class="quiet" class="block text-center">View / print receipt</a>
                    </section>

                    <!-- Current sale: scroll the details, keep the total and next action visible. -->
                    <aside v-if="!sale" id="desk-current-sale" aria-labelledby="cart-title" class="desk-panel desk-cart">
                        <header class="desk-panel-heading"><h3 id="cart-title">Current sale</h3><span>{{ cartCount }} {{ cartCount === 1 ? 'item' : 'items' }}</span></header>
                        <div class="desk-cart-body">
                            <div v-if="!cart.length" class="desk-cart-empty"><span aria-hidden="true" class="material-symbols-outlined">shopping_bag</span><p>Your sale starts here</p><span>Choose an item and size from the catalogue.</span></div>
                            <ul class="desk-cart-lines">
                                <li v-for="(l, i) in cart" :key="l.key" class="desk-cart-line">
                                    <div class="desk-line-heading"><span>{{ l.name }}</span><strong>{{ ksh(l.price * l.quantity) }}</strong></div>
                                    <p>{{ l.label }} · {{ ksh(l.price) }} each</p>
                                    <div class="desk-line-controls"><span>Quantity</span><div><button type="button" @click="l.quantity > 1 ? l.quantity-- : cart.splice(i, 1)" :aria-label="`One fewer ${l.name}`">−</button><span>{{ l.quantity }}</span><button type="button" @click="incrementLine(l)" :disabled="!canAddPosLine(cart, l)" :aria-label="`One more ${l.name}`">+</button></div><button type="button" @click="cart.splice(i, 1)" :aria-label="`Remove ${l.name} from sale`" class="desk-remove-line">Remove</button></div>
                                </li>
                            </ul>
                            <div v-if="savedDraft?.cart?.length" class="rounded-xl bg-amber-500/10 p-3 text-sm space-y-2" role="status"><p>An unfinished sale is saved for you on {{ registerLabel(register) }}. Restore it or discard it before starting another sale.</p><button type="button" @click="restoreDraft" :class="primary">Restore saved bag</button><button type="button" @click="discardDraft" :class="quiet">Discard saved bag</button></div>
                            <p v-if="draftNotice" role="status" class="text-sm text-amber-300">{{ draftNotice }}</p>
                            <template v-if="cart.length">
                            <details class="text-sm">
                                <summary class="cursor-pointer text-slate-400">Customer (optional)</summary>
                                <div class="mt-2 grid gap-2">
                                    <input v-model="customer.name" maxlength="255" placeholder="Name" aria-label="Customer name" :class="input" />
                                    <input v-model="customer.phone" type="tel" placeholder="Phone (to find the sale later)" aria-label="Customer phone" :class="input" />
                                    <input v-model="customer.email" type="email" placeholder="Email (the receipt is emailed)" aria-label="Customer email" :class="input" />
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
                            <button type="button" @click="late.open = !late.open" class="text-left text-xs text-slate-400 underline" :aria-expanded="late.open" aria-controls="desk-paper-slip">Enter a paper slip from an outage instead (manager’s PIN)</button>
                            <form id="desk-paper-slip" v-if="late.open" @submit.prevent="enterLate" class="grid gap-2 rounded-xl bg-slate-950/60 p-3" aria-label="Late entry from a paper slip">
                                <p class="text-xs text-slate-400">These pieces, sold on paper while Sales Desk was unavailable. Stock moves now; the sale keeps the slip’s time.</p>
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
                        </div>
                        <footer class="desk-cart-footer"><div class="desk-cart-subtotal"><span>Subtotal</span><span>{{ ksh(subtotal) }}</span></div><div v-if="Number(discount.amount || 0)" class="desk-cart-subtotal"><span>Discount preview</span><span>−{{ ksh(Number(discount.amount)) }}</span></div><div class="desk-cart-total"><span>Total preview</span><strong>{{ ksh(subtotal - Number(discount.amount || 0)) }}</strong></div><p>Price and stock are confirmed in the next step.</p><p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p><button type="button" @click="ringUp" :disabled="busy || !cart.length || !!savedDraft?.cart?.length" :class="primary" class="w-full">{{ busy ? 'Confirming price & stock…' : 'Continue to payment' }}</button></footer>
                    </aside>
                </div>

                <!-- TODAY: this drawer's sales -->
                <section v-else-if="tab === 'sales'" aria-labelledby="today-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="today-title" class="text-white font-headline text-lg">Sales on this drawer · {{ registerLabel(register) }}</h2>
                        <button type="button" @click="loadToday" :disabled="busy" :class="quiet">Refresh</button>
                    </div>
                    <p v-if="todays && !todays.length" class="text-sm text-slate-400">No sales yet on this drawer.</p>
                    <ul v-if="todays" class="divide-y divide-white/5">
                        <li v-for="t in todays" :key="t.id" class="py-3 flex flex-wrap items-center gap-3 justify-between">
                            <span class="min-w-0">
                                <span class="block text-white text-sm">{{ t.time }} · {{ t.reference }} · {{ ksh(t.total) }}</span>
                                <span class="block text-xs text-slate-400">{{ t.customer }} · {{ t.seller }}<template v-if="t.slip_number"> · paper slip {{ t.slip_number }}</template></span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="t.paid ? 'bg-emerald-500/15 text-emerald-300' : t.state === 'cancelled' ? 'bg-rose-500/15 text-rose-300' : 'bg-amber-500/15 text-amber-300'">
                                    {{ t.paid ? 'Paid' : t.state === 'cancelled' ? 'Voided' : ksh(t.balance_due) + ' to pay' }}
                                </span>
                                <button v-if="!t.paid && t.state === 'open'" type="button" @click="resume(t.id)" :class="primary">Take payment</button>
                                <a v-if="t.paid" :href="receiptUrl(t.id)" target="_blank" rel="noopener" :class="quiet">Receipt</a>
                            </span>
                        </li>
                    </ul>
                    <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                </section>

                <!-- ORDERS: collect and returns -->
                <div v-else-if="tab === 'orders'" class="desk-operations-grid">
                    <div class="desk-work-column">
                    <section class="desk-panel p-5 space-y-3" aria-labelledby="find-order-title">
                        <h2 id="find-order-title" class="text-white font-headline text-lg">Find and continue an order</h2>
                        <form @submit.prevent="searchOrders" class="desk-search-form"><label class="flex-1 text-sm">Reference, customer name or full phone number<input v-model="orderQuery" minlength="3" required maxlength="120" :class="input" /></label><button type="submit" :disabled="busy" :class="primary">Find orders</button></form>
                        <p v-if="foundOrders && !foundOrders.length" role="status">No matching orders. Check the reference or customer details.</p>
                        <p v-if="hasMoreOrders" class="text-xs text-amber-300">More than 20 matches. Add more of the name or use the exact reference.</p>
                        <ul class="divide-y divide-white/10"><li v-for="found in foundOrders || []" :key="found.id" class="py-2 flex flex-wrap justify-between gap-2 text-sm"><span>{{ found.reference }} · {{ found.customer }}<span class="block text-xs text-slate-400">{{ found.placed_at }} · {{ found.kind }} · {{ found.state }} · {{ found.fulfilment }} · {{ ksh(found.balance_due) }} due</span></span><button type="button" @click="continueOrder(found.id)" :disabled="busy" :class="quiet">Open order</button></li></ul>
                        <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                    </section>
                    <section aria-labelledby="collect-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-3">
                        <h2 id="collect-title" class="text-white font-headline text-lg">Payment & collection</h2>
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
                            <p v-if="collectOrder.total <= 0" class="text-sm text-amber-300">This order needs a quote before payment.<a v-if="canUseStudio" :href="route('studio.index', { tab: 'orders', order: collectOrder.id })" class="ml-2 underline">Open this order in Studio</a><span v-else> Ask the owner or manager to quote it.</span></p>
                            <p class="text-xs text-slate-400">Payment: {{ collectOrder.payment_status }} · Work: {{ collectOrder.fulfilment }} · Order: {{ collectOrder.state }}</p>
                            <p v-if="collectOrder.handover" class="text-sm">{{ collectOrder.handover.label }} · {{ collectOrder.handover.location }} · {{ collectOrder.handover.next }}</p>
                            <button type="button" @click="checkPayment('collect')" :disabled="checkingPayment || busy" :class="quiet">Check payment status</button>
                            <p v-if="collectOrder.prompt?.status === 'pending'" role="status" class="text-amber-300 text-sm">M-Pesa is pending. Check the result before taking another payment.</p>
                            <p v-if="paymentMessage" role="status" class="text-amber-300 text-sm">{{ paymentMessage }}</p>
                            <p v-if="paymentCheckedAt" class="text-xs text-slate-400">Last checked {{ paymentCheckedAt }}</p>
                            <a v-if="collectOrder.receipt_url" :href="collectOrder.receipt_url" :class="quiet">Download receipt</a>
                            <ul class="text-sm text-slate-300"><li v-for="(l, i) in collectOrder.lines" :key="i">{{ l.quantity }} × {{ l.description }}</li></ul>
                            <template v-if="collectOrder.balance_due > 0 && collectOrder.state === 'open'">
                                <p class="text-amber-300 text-sm">Balance due {{ ksh(collectOrder.balance_due) }}. Record only money actually received.</p>
                                <button v-if="collectOrder.deposit_due > 0 && collectOrder.deposit_due < collectOrder.balance_due" type="button" @click="useDeposit" :class="quiet">Use remaining deposit · {{ ksh(collectOrder.deposit_due) }}</button>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="m in ['cash', 'mpesa_manual', 'mpesa_prompt']" :key="m" type="button" @click="tender.method = m"
                                        class="rounded-xl px-3 py-2 text-sm" :class="tender.method === m ? 'bg-[#57d5fc] text-slate-900 font-semibold' : 'bg-white/5 text-slate-300'">{{ methodLabel[m] }}</button>
                                </div>
                                <form @submit.prevent="takeMoney('collect')" class="grid gap-2 sm:grid-cols-2">
                                    <input v-model="tender.amount" type="number" min="1" required aria-label="Amount" :class="input" />
                                    <input v-if="tender.method === 'cash'" v-model="tender.tendered" type="number" min="1" required aria-label="Cash handed over" placeholder="Cash handed over" :class="input" />
                                    <input v-if="tender.method === 'mpesa_manual'" v-model="tender.reference" required aria-label="M-Pesa code" placeholder="M-Pesa code" :class="input" class="uppercase" />
                                    <input v-if="tender.method === 'mpesa_prompt'" v-model="tender.phone" type="tel" required aria-label="M-Pesa number" placeholder="07XX XXX XXX" :class="input" />
                                    <button type="submit" :disabled="busy || checkingPayment || collectOrder.prompt?.status === 'pending'" :class="primary" class="sm:col-span-2">Record payment</button>
                                </form>
                                <p v-if="change" class="text-emerald-300">Change: {{ ksh(change) }}</p>
                            </template>
                            <template v-else-if="collectOrder.can_collect">
                                <label class="block text-sm">Verify the phone number on the order<input v-model="desk.phone" type="tel" required :class="input" /></label>
                                <input v-model="desk.note" maxlength="255" placeholder="Note (e.g. collected by her sister)" aria-label="Note" :class="input" />
                                <button type="button" @click="markCollected" :disabled="busy || !desk.phone.trim()" :class="primary" class="w-full">{{ busy ? 'Recording handover…' : 'Hand over order' }}</button>
                            </template>
                            <p v-else class="text-sm text-amber-300">{{ collectOrder.collect_blocker }}</p>
                        </div>
                        <p v-if="firstError && (collectOrder || !returnOrder)" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                    </section>

                    </div>
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
                            <p class="text-white">{{ returnOrder.reference }} · {{ returnOrder.customer }} · paid {{ ksh(returnOrder.paid) }}
                                <a v-if="returnOrder.channel === 'counter'" :href="receiptUrl(returnOrder.id)" target="_blank" rel="noopener" class="ml-2 text-sm text-[#57d5fc] underline">Receipt</a></p>
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
                <section v-else-if="tab === 'bespoke'" aria-labelledby="intake-title" class="desk-panel desk-intake space-y-4">
                    <h2 id="intake-title" class="text-white font-headline text-lg">Bespoke order</h2>
                    <form v-if="!intakeOrder" @submit.prevent="submitIntake" class="desk-intake-form grid gap-3 sm:grid-cols-2">
                        <label class="text-sm">Name<input v-model="intake.full_name" required maxlength="255" :class="input" class="mt-1" /></label>
                        <label class="text-sm">Phone<input v-model="intake.phone" type="tel" required :class="input" class="mt-1" placeholder="07XX XXX XXX" /></label>
                        <label class="text-sm">Email (optional)<input v-model="intake.email" type="email" :class="input" class="mt-1" /></label>
                        <label class="text-sm">Garment<input v-model="intake.garment" list="garments" maxlength="100" :class="input" class="mt-1" /><datalist id="garments"><option v-for="g in garments" :key="g" :value="g" /></datalist></label>
                        <label class="text-sm">Occasion<input v-model="intake.occasion" maxlength="100" :class="input" class="mt-1" /></label>
                        <label class="text-sm">Needed by<input v-model="intake.deadline" type="date" :class="input" class="mt-1" /></label>
                        <label class="text-sm">Fabric<input v-model="intake.fabric_preference" maxlength="255" :class="input" class="mt-1" /></label>
                        <label class="text-sm">Colour<input v-model="intake.selected_color" maxlength="100" :class="input" class="mt-1" /></label>
                        <fieldset class="desk-intake-full grid grid-cols-3 sm:grid-cols-5 gap-2">
                            <legend class="text-sm mb-1">Measurements (cm)</legend>
                            <label v-for="m in ['bust', 'waist', 'hips', 'length', 'height']" :key="m" class="text-xs text-slate-400 capitalize">{{ m }}<input v-model="intake[m + '_cm']" type="number" step="0.5" min="10" max="250" :class="input" class="mt-1" /></label>
                        </fieldset>
                        <label class="text-sm desk-intake-full">Notes<textarea v-model="intake.notes" rows="3" maxlength="5000" :class="input" class="mt-1"></textarea></label>
                        <label class="text-sm">Reference photos (up to 6)<input type="file" multiple accept="image/*,application/pdf" @change="intakeImages = [...$event.target.files].slice(0, 6)" class="mt-1 block text-sm text-slate-400" /></label>
                        <label class="text-sm">Quote now (KSh, optional)<input v-model="intake.quote" type="number" min="1" inputmode="numeric" :class="input" class="mt-1" /></label>
                        <p v-if="firstError" role="alert" class="desk-intake-full text-sm text-rose-300">{{ firstError }}</p>
                        <button type="submit" :disabled="busy" :class="primary" class="desk-intake-full">Create the order</button>
                    </form>
                    <div v-else class="space-y-3">
                        <p class="text-white">{{ intakeOrder.reference }} · {{ intakeOrder.customer }}<template v-if="intakeOrder.garment"> · {{ intakeOrder.garment }}</template></p>
                        <p v-if="!intakeOrder.total" class="text-sm text-slate-400">No quote yet: the Studio quotes it, and the customer pays the deposit from their link or here.</p>
                        <template v-else>
                            <p class="text-sm">Quote {{ ksh(intakeOrder.total) }} · deposit {{ ksh(intakeOrder.deposit_required) }} · paid {{ ksh(intakeOrder.paid) }}</p>
                            <button type="button" @click="checkPayment('intake')" :disabled="checkingPayment || busy" :class="quiet">Check payment status</button><p v-if="intakeOrder.prompt?.status === 'pending'" role="status" class="text-amber-300 text-sm">M-Pesa pending. Check before taking another payment.</p><p v-if="paymentMessage" role="status" class="text-amber-300 text-sm">{{ paymentMessage }}</p>
                            <form v-if="intakeOrder.balance_due > 0" @submit.prevent="takeMoney('intake')" class="grid gap-2 sm:grid-cols-[auto_1fr_1fr_auto] items-center">
                                <select v-model="tender.method" aria-label="How they pay" :class="input"><option value="cash">Cash</option><option value="mpesa_manual">M-Pesa code</option><option value="mpesa_prompt">M-Pesa prompt</option></select>
                                <input v-model="tender.amount" type="number" min="1" required aria-label="Amount" :class="input" />
                                <input v-if="tender.method === 'cash'" v-model="tender.tendered" type="number" min="1" required aria-label="Cash handed over" placeholder="Handed over" :class="input" />
                                <input v-else-if="tender.method === 'mpesa_manual'" v-model="tender.reference" required aria-label="M-Pesa code" placeholder="M-Pesa code" :class="input" class="uppercase" />
                                <input v-else v-model="tender.phone" type="tel" required aria-label="M-Pesa number" placeholder="07XX XXX XXX" :class="input" />
                                <button type="submit" :disabled="busy || checkingPayment || intakeOrder.prompt?.status === 'pending'" :class="primary">Record payment</button>
                            </form>
                            <p v-if="change" class="text-emerald-300">Change: {{ ksh(change) }}</p>
                        </template>
                        <p v-if="firstError" role="alert" class="text-sm text-rose-300">{{ firstError }}</p>
                        <button type="button" @click="newIntake" :class="quiet">Another bespoke order</button>
                    </div>
                </section>

                <!-- DRAWER -->
                <div v-else-if="tab === 'till'" class="desk-register-grid">
                    <section v-if="closed" aria-labelledby="z-title" class="z-report rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-2 lg:col-span-2">
                        <h2 id="z-title" class="text-white font-headline text-lg">{{ registerLabel(closed.register) }} closed · Register summary</h2>
                        <p class="text-sm">Expected {{ ksh(closed.expected_cash) }} · counted {{ ksh(closed.counted_cash) }} ·
                            <span :class="closed.variance === 0 ? 'text-emerald-300' : 'text-amber-300'">{{ closed.variance === 0 ? 'balanced' : (closed.variance < 0 ? ksh(-closed.variance) + ' short' : ksh(closed.variance) + ' over') }}</span></p>
                        <p class="text-sm text-slate-400">{{ closed.sales_count }} sale(s) paid, {{ closed.voids }} void(s), discounts {{ ksh(closed.discounts) }}</p>
                        <p class="text-sm text-slate-400">Taken: {{ Object.entries(closed.sales_by_method).map(([m, v]) => `${methodLabel[m] || m} ${ksh(v)}`).join(' · ') || 'nothing' }}<template v-if="Object.keys(closed.refunds_by_method || {}).length"> · refunded {{ Object.entries(closed.refunds_by_method).map(([m, v]) => `${methodLabel[m] || m} ${ksh(v)}`).join(' · ') }}</template></p>
                        <div v-if="closed.unfinished?.length" class="rounded-xl bg-amber-500/10 ring-1 ring-amber-400/30 p-3 text-sm text-amber-200">
                            Still owed, part paid: {{ closed.unfinished.map(u => `${u.reference} (${ksh(u.balance_due)})`).join(', ') }}. Finish or refund them from Orders or the Studio.
                        </div>
                        <div class="flex gap-2 print:hidden">
                            <button type="button" @click="printZ" :class="primary">Print register summary</button>
                            <button type="button" @click="closed = null" :class="quiet">Done</button>
                        </div>
                    </section>
                    <section v-if="!till && !closed" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-3 lg:col-span-2" aria-labelledby="open2-title">
                        <h2 id="open2-title" class="text-white font-headline text-lg">{{ registerLabel(register) }} is closed</h2>
                        <form @submit.prevent="openTill" class="grid gap-2 sm:grid-cols-[1fr_auto]">
                            <input v-model="openFloat" type="number" min="0" required aria-label="Opening float" placeholder="Cash drawer opening balance (KSh)" :class="input" />
                            <button type="submit" :disabled="busy" :class="primary">Open Register</button>
                        </form>
                    </section>
                    <template v-if="till">
                        <section aria-labelledby="now-title" class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 space-y-2">
                            <h2 id="now-title" class="text-white font-headline text-lg">In the drawer now</h2>
                            <template v-if="report">
                                <p class="text-3xl text-white tabular-nums">{{ ksh(report.expected_cash) }}</p>
                                <dl class="text-sm grid grid-cols-2 gap-y-1">
                                    <dt class="text-slate-400">Opening cash</dt><dd class="text-right tabular-nums">{{ ksh(report.opening_float) }}</dd>
                                    <dt class="text-slate-400">Cash sales</dt><dd class="text-right tabular-nums">{{ ksh(report.cash.sale) }}</dd>
                                    <dt class="text-slate-400">Cash refunds</dt><dd class="text-right tabular-nums">{{ ksh(report.cash.refund) }}</dd>
                                    <dt class="text-slate-400">Cash removed · expenses</dt><dd class="text-right tabular-nums">{{ ksh(report.cash.payout) }}</dd>
                                    <dt class="text-slate-400">Cash removed · bank</dt><dd class="text-right tabular-nums">{{ ksh(report.cash.pickup) }}</dd>
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
                        <section aria-labelledby="close-title" class="desk-panel desk-register-close p-5 space-y-3">
                            <h2 id="close-title" class="text-white font-headline text-lg">Close {{ registerLabel(register) }}</h2>
                            <p class="text-sm text-slate-400">Compare the counted cash with expected cash. Explain any difference. A difference over {{ ksh(policy.variance_alert) }} needs a reason and a manager.</p>
                            <form @submit.prevent="closeTill" class="desk-close-form">
                                <label class="text-sm text-slate-300">Counted cash<input v-model="closing.counted" type="number" min="0" required aria-label="Cash counted" :class="input" class="mt-1" /></label>
                                <p v-if="cashDifference !== null" class="desk-cash-comparison text-sm" role="status">Expected cash: {{ ksh(report.expected_cash) }} · Counted cash: {{ ksh(closing.counted) }} · Difference: <strong>{{ ksh(Math.abs(cashDifference)) }} {{ cashDifference < 0 ? 'short' : cashDifference > 0 ? 'over' : '— balanced' }}</strong></p>
                                <label class="text-sm text-slate-300">Reason for any difference<input :required="!!cashDifference" v-model="closing.reason" maxlength="255" aria-label="Reason for any difference" :class="input" class="mt-1" /></label>
                                <label class="text-sm text-slate-300">Manager PIN (when required)<input v-model="closing.pin" type="password" inputmode="numeric" aria-label="Manager PIN" :class="input" class="mt-1" /></label>
                                <button type="submit" :disabled="busy || !report" :class="primary">{{ busy ? 'Closing register…' : 'Close Register' }}</button>
                            </form>
                        </section>
                    </template>
                    <p v-if="firstError" role="alert" class="text-sm text-rose-300 lg:col-span-2">{{ firstError }}</p>
                </div>
            </main>

            <!-- Size sheet -->
            <Modal :show="!!sizing" max-width="md" labelled-by="size-title" @close="sizing = null">
                <div v-if="sizing" class="w-full max-w-md rounded-2xl bg-slate-900 ring-1 ring-white/10 p-5 space-y-4">
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
            </Modal>
        </template>
    </div>
</template>

<style scoped>
.sales-desk { --desk-gutter: clamp(16px, 1.8vw, 36px); --desk-border: #283549; --desk-panel: #101a2a; --desk-header: #080f1c; --desk-heading: #f8fafc; --desk-text: #e2e8f0; --desk-muted: #a3b2c7; --desk-border-strong: #3d4c60; --desk-accent: #8de7ff; --desk-raised: #152237; --desk-input: #0a1322; --desk-tile: #131f31; --desk-media: #1b293d; --desk-success: #a7f3d0; --desk-inset: #0b1525; color-scheme: dark; font-variant-numeric: tabular-nums; }
.desk-frame { width: 100%; min-width: 0; padding-inline: var(--desk-gutter); }
.desk-header { position: sticky; top: 0; z-index: 30; background: var(--desk-header); border-bottom: 1px solid var(--desk-border); }
.desk-topbar { min-height: 76px; display: flex; align-items: center; gap: 28px; padding-block: 12px; }
.desk-brand { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.desk-brand-icon { display: grid; place-items: center; width: 40px; height: 40px; border: 1px solid #57d5fc40; border-radius: 10px; color: #57d5fc; background: #57d5fc0a; }
.desk-brand h1 { margin: 0; font-size: 18px; line-height: 1.3; font-weight: 650; color: var(--desk-heading); }
.desk-brand p { margin-top: 3px; font-size: 12px; color: var(--desk-muted); }
.desk-session { min-width: 0; display: flex; align-items: center; flex-wrap: wrap; gap: 8px 16px; }
.desk-register-state { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border: 1px solid var(--desk-border-strong); border-radius: 7px; font-size: 12px; white-space: nowrap; color: var(--desk-text); }
.desk-register-state .material-symbols-outlined { font-size: 16px; }
.desk-register-state.is-open { color: var(--desk-success); border-color: #10b98155; background: #10b9810a; }
.desk-operator { font-size: 13px; color: var(--desk-text); overflow-wrap: anywhere; }
.desk-opened { color: var(--desk-muted); font-size: 12px; }
.desk-header-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.desk-header-actions :is(button, a) { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; }
.desk-navigation { display: flex; align-items: stretch; gap: 8px; overflow-x: auto; }
.desk-nav-item { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 10px 16px; border-bottom: 3px solid transparent; color: var(--desk-muted); white-space: nowrap; font-size: 13px; font-weight: 500; }
.desk-nav-item .material-symbols-outlined { font-size: 19px; }
.desk-nav-item:hover { color: var(--desk-heading); background: #ffffff04; }
.desk-nav-item.is-current { color: var(--desk-accent); border-bottom-color: #57d5fc; background: #57d5fc08; }
.desk-main { padding-block: 20px; }
.desk-context { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.desk-context h2 { font-size: 22px; line-height: 1.3; font-weight: 600; color: var(--desk-heading); }
.desk-context p { margin-top: 5px; font-size: 13px; color: var(--desk-muted); }
.desk-context-actions { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.desk-guide { position: relative; font-size: 12px; color: var(--desk-muted); }
.desk-guide summary { padding: 12px 0; cursor: pointer; }
.desk-guide-body { position: absolute; right: 0; top: 100%; width: min(540px, 80vw); padding: 16px; background: var(--desk-raised); border: 1px solid var(--desk-border-strong); box-shadow: 0 16px 32px #0005; z-index: 35; }
.desk-cart-jump { display: none; }
.desk-panel { min-width: 0; border: 1px solid var(--desk-border); border-radius: 14px; background: var(--desk-panel); padding: 20px; }
.desk-panel-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.desk-panel-heading h3 { font-size: 16px; font-weight: 600; color: var(--desk-heading); }
.desk-panel-heading > span { font-size: 12px; color: var(--desk-muted); }
.sales-desk :is(input:not([type="checkbox"]), select, textarea) { min-width: 0; }
.desk-panel :is(input, select, textarea) { min-height: 44px; border-radius: 9px; border-color: var(--desk-border-strong); background-color: var(--desk-input); }
.desk-sale-workspace, .desk-payment-workspace { display: grid; grid-template-columns: minmax(0, 1fr) clamp(352px, 28vw, 480px); gap: 20px; align-items: start; }
.desk-sale-workspace { height: calc(100dvh - var(--desk-header-height) - var(--desk-context-height) - 60px); min-height: 480px; align-items: stretch; }
.desk-catalogue { display: flex; flex-direction: column; padding: 0; min-height: 0; overflow: hidden; }
.desk-finder-header { padding: 20px; display: grid; gap: 16px; flex-shrink: 0; border-bottom: 1px solid var(--desk-border); }
.desk-search { min-height: 48px !important; }
.desk-categories { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 2px; }
.desk-categories button { flex-shrink: 0; min-height: 40px; padding: 8px 14px; font-size: 12px; border-radius: 8px; }
.desk-results { min-height: 0; overflow: auto; flex: 1; padding: 16px; scrollbar-gutter: stable; }
.desk-results:focus-visible { outline: 2px solid #57d5fc; outline-offset: -3px; }
.desk-product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; align-content: start; }
.desk-product-card { display: flex; flex-direction: column; width: 100%; height: 100%; text-align: left; border: 1px solid var(--desk-border); border-radius: 10px; background: var(--desk-tile); overflow: hidden; }
.desk-product-card:hover:enabled { border-color: #57d5fc; }
.desk-product-card:disabled { opacity: .55; cursor: not-allowed; }
.desk-product-media { position: relative; display: grid; place-items: center; width: 100%; aspect-ratio: 5 / 4; background: var(--desk-media); overflow: hidden; }
.desk-product-media > span { font-size: 40px; color: var(--desk-muted); }
.desk-product-media img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.desk-product-content { display: flex; flex: 1; flex-direction: column; gap: 6px; padding: 12px; width: 100%; min-width: 0; }
.desk-product-name { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 13px; line-height: 1.5; min-height: 39px; font-weight: 500; color: var(--desk-text); overflow-wrap: anywhere; }
.desk-product-price { font-size: 17px; font-weight: 600; color: var(--desk-heading); }
.desk-product-stock { font-size: 11px; color: var(--desk-success); }
.desk-product-content > .block { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-size: 11px; line-height: 1.5; min-height: 33px; }
.desk-add-label { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-top: auto; padding-top: 10px; border-top: 1px solid var(--desk-border); font-size: 12px; font-weight: 600; color: var(--desk-accent); }
.desk-add-label .material-symbols-outlined { font-size: 18px; }
.desk-empty { padding: 40px 12px; text-align: center; font-size: 14px; color: var(--desk-muted); }
.desk-cart { display: flex; flex-direction: column; padding: 0; min-height: 0; overflow: hidden; }
.desk-cart > .desk-panel-heading { padding: 20px; border-bottom: 1px solid var(--desk-border); flex-shrink: 0; }
.desk-cart-body { flex: 1; min-height: 0; padding: 20px; overflow: auto; scrollbar-gutter: stable; }
.desk-cart-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 10px; min-height: 210px; color: var(--desk-muted); }
.desk-cart-empty .material-symbols-outlined { font-size: 36px; }
.desk-cart-empty p { font-size: 15px; font-weight: 500; color: var(--desk-text); }
.desk-cart-empty > span:last-child { font-size: 12px; max-width: 230px; line-height: 1.6; }
.desk-cart-lines { margin-bottom: 12px; }
.desk-cart-line { padding-block: 14px; border-bottom: 1px solid var(--desk-border); }
.desk-cart-line:first-child { padding-top: 0; }
.desk-line-heading { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: start; font-size: 13px; color: var(--desk-heading); }
.desk-line-heading > span { overflow-wrap: anywhere; }
.desk-line-heading strong { font-weight: 600; }
.desk-cart-line > p { margin-top: 5px; color: var(--desk-muted); font-size: 12px; }
.desk-line-controls { display: flex; align-items: center; gap: 10px; margin-top: 12px; font-size: 12px; color: var(--desk-muted); }
.desk-line-controls > span { flex: 1; }
.desk-line-controls > div { display: flex; align-items: center; gap: 4px; border: 1px solid var(--desk-border-strong); border-radius: 8px; }
.desk-line-controls > div button { width: 40px; height: 40px; color: var(--desk-text); font-size: 18px; }
.desk-line-controls > div button:disabled { opacity: .3; }
.desk-line-controls > div span { width: 22px; text-align: center; color: var(--desk-text); }
.desk-remove-line { min-height: 40px; color: var(--desk-muted); text-decoration: underline; text-underline-offset: 3px; }
.desk-cart-body details { border-bottom: 1px solid var(--desk-border); padding-block: 12px; }
.desk-cart-body details summary { min-height: 28px; }
.desk-cart-body > template + p { margin-top: 12px; }
.desk-cart-footer { flex-shrink: 0; padding: 20px; border-top: 1px solid var(--desk-border-strong); background: var(--desk-raised); }
.desk-cart-subtotal, .desk-cart-total { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.desk-cart-subtotal { font-size: 12px; color: var(--desk-muted); margin-bottom: 8px; }
.desk-cart-total { padding-top: 6px; color: var(--desk-heading); font-size: 14px; }
.desk-cart-total strong { font-size: clamp(22px, 2vw, 28px); font-weight: 650; }
.desk-cart-footer > p { margin-block: 8px 14px; font-size: 11px; color: var(--desk-muted); }
.desk-cart-footer > button { min-height: 48px; }
.desk-sale-summary { display: grid; gap: 20px; }
.desk-sale-summary li { align-items: start; padding-block: 14px; }
.desk-sale-summary li > span:first-child { min-width: 0; overflow-wrap: anywhere; }
.desk-sale-summary li > span:last-child { flex-shrink: 0; }
.desk-tender { display: flex; flex-direction: column; gap: 18px; }
.desk-tender form > button[type="submit"] { min-height: 48px; }
.desk-amount-due { padding: 18px; border: 1px solid var(--desk-border-strong); border-radius: 10px; background: var(--desk-inset); }
.desk-amount-due > span { font-size: 13px; color: var(--desk-muted); }
.desk-amount-due strong { display: block; margin-top: 4px; font-size: 34px; line-height: 1.3; color: var(--desk-heading); font-weight: 650; }
.desk-amount-due p { margin-top: 6px; font-size: 12px; color: var(--desk-muted); }
.desk-completion { display: grid; gap: 16px; text-align: center; }
.desk-completion-icon { font-size: 48px; color: var(--desk-success); }
.desk-completion h3 { font-size: 20px; font-weight: 600; color: var(--desk-heading); }
.desk-completion > p { font-size: 13px; color: var(--desk-muted); }
.desk-completion-total { font-size: 32px; font-weight: 650; color: var(--desk-heading); }
.desk-confirmed-change { border: 1px solid #10b98155; border-radius: 10px; padding: 16px; background: #10b9810a; color: var(--desk-success) !important; }
.desk-confirmed-change strong { display: block; margin-top: 4px; font-size: 26px; }
.desk-completion > :is(button, a) { min-height: 48px; }
.desk-operations-grid, .desk-register-grid { display: grid; gap: 20px; align-items: start; }
.desk-work-column { min-width: 0; display: grid; gap: 20px; }
.desk-operations-grid > section { min-width: 0; }
.desk-search-form { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: 12px; }
.desk-search-form input { margin-top: 8px; }
.desk-register-grid > section { min-width: 0; }
.desk-register-grid > .lg\:col-span-2 { grid-column: 1 / -1; }
.desk-close-form { display: grid; gap: 14px; }
.desk-cash-comparison { padding: 12px; background: var(--desk-inset); border: 1px solid var(--desk-border-strong); border-radius: 8px; color: var(--desk-text); line-height: 1.8; }
.desk-intake { padding: 24px; }
.desk-intake-full { grid-column: 1 / -1; }
@media (min-width: 1200px) { .desk-intake-form { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (min-width: 1100px) { .desk-operations-grid { grid-template-columns: minmax(0, 1.6fr) minmax(340px, 1fr); } }
@media (min-width: 1280px) { .desk-register-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.15fr); } }
@media (min-width: 768px) and (max-width: 1279px) {
    .desk-sale-workspace, .desk-payment-workspace { grid-template-columns: minmax(0, 1fr) 304px; gap: 16px; }
    .desk-register-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .desk-register-close { grid-column: 1 / -1; }
    .desk-product-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
    .desk-opened { display: block; margin-top: 3px; }
    .desk-topbar { gap: 16px; }
    .desk-finder-header, .desk-cart-body, .desk-cart-footer, .desk-cart > .desk-panel-heading { padding: 16px; }
}
@media (max-width: 767px) {
    .desk-topbar { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; }
    .desk-brand { gap: 8px; min-width: 0; }
    .desk-brand-icon { width: 32px; height: 36px; }
    .desk-brand h1 { font-size: 16px; }
    .desk-brand p { font-size: 11px; overflow-wrap: anywhere; }
    .desk-header-actions { grid-column: 1 / -1; grid-row: 3; margin-left: 0; gap: 6px; flex-wrap: wrap; }
    .desk-header-actions :is(button, a) { padding-inline: 10px; font-size: 12px; }
    .desk-session { grid-column: 1 / -1; gap: 8px 12px; }
    .desk-operator { font-size: 12px; }
    .desk-opened { display: none; }
    .desk-navigation { gap: 0; }
    .desk-nav-item { padding-inline: 12px; min-height: 46px; font-size: 12px; }
    .desk-main { padding-block: 16px; }
    .desk-context { flex-direction: column; align-items: stretch; gap: 8px; margin-bottom: 16px; }
    .desk-context h2 { font-size: 20px; }
    .desk-context-actions { justify-content: space-between; }
    .desk-cart-jump { display: inline-flex; align-items: center; min-height: 40px; font-size: 12px; color: var(--desk-accent); }
    .desk-guide-body { width: calc(100vw - 32px); }
    .desk-sale-workspace, .desk-payment-workspace { grid-template-columns: minmax(0, 1fr); height: auto; min-height: 0; gap: 16px; }
    .desk-catalogue .desk-results { max-height: 55dvh; flex: auto; }
    .desk-product-grid { grid-template-columns: repeat(auto-fill, minmax(135px, 1fr)); }
    .desk-finder-header, .desk-cart-body, .desk-cart-footer, .desk-cart > .desk-panel-heading { padding: 16px; }
    .desk-cart { scroll-margin-top: calc(var(--desk-header-height) + 12px); }
    .desk-cart-body { overflow: visible; }
    .desk-cart-empty { min-height: 150px; }
    .desk-search-form { grid-template-columns: minmax(0, 1fr); }
    .desk-panel { padding: 16px; }
    .desk-catalogue, .desk-cart { padding: 0; }
}
@media (min-width: 768px) and (max-height: 700px) {
    .desk-sale-workspace { height: auto; min-height: 0; }
    .desk-catalogue .desk-results { max-height: 70dvh; }
    .desk-cart-body { max-height: 50dvh; }
}
@media (prefers-reduced-motion: reduce) { .sales-desk { scroll-behavior: auto; } }
</style>


<style>
/* POS tokens and legacy utility colours follow the shared saved theme. */
html:not(.dark) .sales-desk {
    --desk-border: #ccd6e1; --desk-border-strong: #b5c3d2; --desk-panel: #ffffff;
    --desk-header: #ffffff; --desk-heading: #142337; --desk-text: #26384e;
    --desk-muted: #52647a; --desk-accent: #07617f; --desk-raised: #edf4f9;
    --desk-input: #ffffff; --desk-tile: #ffffff; --desk-media: #e7eef5;
    --desk-success: #116344; --desk-inset: #f2f6fa;
    color-scheme: light; background-color: #f2f5f9; color: var(--desk-text);
}
html:not(.dark) .sales-desk :is(.text-slate-200, .text-slate-300) { color: var(--desk-text); }
html:not(.dark) .sales-desk :is(.text-slate-400, .text-slate-500) { color: var(--desk-muted); }
html:not(.dark) .sales-desk .text-white { color: var(--desk-heading); }
html:not(.dark) .sales-desk :is(.bg-slate-900, .bg-slate-900\/60) { background-color: var(--desk-panel); }
html:not(.dark) .sales-desk :is(.bg-slate-950, .bg-slate-950\/60) { background-color: var(--desk-inset); }
html:not(.dark) .sales-desk :is(input, select, textarea) { color: var(--desk-heading); border-color: var(--desk-border-strong); }
html:not(.dark) .sales-desk input::placeholder { color: #64748b; }
html:not(.dark) .sales-desk .text-emerald-300 { color: #116344; }
html:not(.dark) .sales-desk :is(.text-amber-200, .text-amber-300) { color: #854400; }
html:not(.dark) .sales-desk .text-rose-300 { color: #b42340; }
html:not(.dark) .sales-desk .desk-register-state.is-open { border-color: #91c5af; background: #edf8f2; }
html:not(.dark) .sales-desk .desk-confirmed-change { border-color: #91c5af; background: #edf8f2; }
html:not(.dark) .sales-desk .desk-brand-icon { color: var(--desk-accent); border-color: #9cc9da; }
html:not(.dark) .sales-desk .desk-guide-body { background: var(--desk-panel); }

/* The Register summary prints black on white; everything else on the till stays off the paper. */
@media print {
    body * { visibility: hidden; }
    .z-report, .z-report * { visibility: visible; color: #000 !important; background: #fff !important; box-shadow: none !important; }
    .z-report { position: absolute; inset: 0 auto auto 0; width: 80mm; }
}
</style>
