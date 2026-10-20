<!--
  StudioDashboard — admin home. Thin shell: owns all forms/handlers/modals and
  routes the active section to a presentational panel. Chrome lives in AdminShell.
-->
<script setup>
import { studioSection } from '@/Support/studioSections';
import { orderImages } from '@/Support/orderImages';
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';
import { paymentStatus } from '@/Support/orderStatus';
import AdminShell from '@/Layouts/AdminShell.vue';
import Modal from '@/Components/Modal.vue';
import OverviewPanel from './Partials/OverviewPanel.vue';
import OrdersPanel from './Partials/OrdersPanel.vue';
import ProductsPanel from './Partials/ProductsPanel.vue';
import GalleryPanel from './Partials/GalleryPanel.vue';
import SubscribersPanel from './Partials/SubscribersPanel.vue';
import SettingsPanel from './Partials/SettingsPanel.vue';
import CustomOrderOptionsPanel from './Partials/CustomOrderOptionsPanel.vue';
import PaymentsPanel from './Partials/PaymentsPanel.vue';
import OrderPolicyPanel from './Partials/OrderPolicyPanel.vue';
import DropsPanel from './Partials/DropsPanel.vue';
import RefundsPanel from './Partials/RefundsPanel.vue';
import PaymentAttention from './Partials/PaymentAttention.vue';

const props = defineProps({
    workflow: { type: Object, default: () => ({}) },
    generated_at: { type: String, default: '' },
    stats: { type: Object, default: () => ({}) },
    attention: { type: Object, default: () => ({ awaiting: [], low_stock: [] }) },
    // A page of orders ({ data, page, last_page, total, archived }), plus the
    // order currently open (?order=ID), wherever it sits in the list.
    orders: { type: Object, default: () => ({ data: [], page: 1, last_page: 1, total: 0, archived: false }) },
    focus_order: { type: Object, default: null },
    products: { type: Array, default: () => [] },
    archived_products: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    gallery: { type: Array, default: () => [] },
    archived_gallery: { type: Array, default: () => [] },
    leads: { type: Array, default: () => [] },
    custom_order_options: { type: Object, default: () => ({ garments: [], fabrics: [], colours: [] }) },
    order_policy: { type: Object, default: () => ({ deposit_percent: 50, pay_link_days: 14, deposit_policy: '' }) },
    payment_settings: { type: Object, default: () => ({ mpesa_enabled: true, whatsapp_enabled: true, instructions: '', whatsapp_hold_hours: 48, hold_options: [12, 24, 48, 72], mpesa_configured: false, mpesa_env: 'sandbox', prompts_in_progress: 0 }) },
    drops: { type: Array, default: () => [] },
    refunds: { type: Array, default: () => [] },
    active_tab: { type: String, default: 'home' },
});

const page = usePage();
const { formatAmount } = useCurrency();

/* ───────── Navigation + lazy per-section loading ───────── */
const currentTab = ref(props.active_tab);
function goTab(id) { currentTab.value = id; }
function openQueue(queue) {
    router.get(route('studio.index'), { tab: 'orders', o_queue: queue, o_sort: 'oldest' }, { preserveScroll: false });
}
function refreshOverview() {
    router.reload({ only: ['stats', 'workflow', 'attention', 'generated_at'] });
}
watch(currentTab, () => { selectedOrder.value = null; });

const title = computed(() => studioSection(currentTab.value).label);
const subtitle = computed(() => studioSection(currentTab.value).description);

const getObjectURL = (file) => {
    if (!file) return null;
    if (typeof file === 'string') return file;
    try { return URL.createObjectURL(file); } catch { return null; }
};

/* ───────── Orders ───────── */
const selectedOrder = ref(null);
const productionStages = ['pending', 'designing', 'sewing', 'completed', 'shipped'];
const orderForm = useForm({ id: null, status: '', internal_notes: '', price_quoted: 0, is_paid: false, override_reason: '', quote_reason: '' });
// Owner only: start work before the deposit, or ship with a balance owed, with a reason (audited).
const canOverrideFulfilment = computed(() => page.props.auth?.user?.abilities?.includes('fulfilment.override'));
const progressSnapForm = useForm({ image: null, description: '' });
const orderArchived = computed(() => !!selectedOrder.value?.deleted_at);

// The open order lives in the URL (?order=ID): a shareable link, and after any
// save the server returns that order fresh as focus_order.
function setUrlOrder(id) {
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('order', id); else url.searchParams.delete('order');
    window.history.replaceState(window.history.state, '', url);
}
function openOrder(orderOrId) {
    // An order object (from the list) or an id (Home shortcuts, links).
    const id = typeof orderOrId === 'object' ? orderOrId.id : Number(orderOrId);
    const known = typeof orderOrId === 'object' ? orderOrId
        : (props.orders.data || []).find(o => o.id === id) || (props.focus_order?.id === id ? props.focus_order : null);
    setUrlOrder(id);
    if (known) { bindOrder(known); return; }
    router.reload({ only: ['focus_order'], data: { order: id }, onSuccess: () => { if (props.focus_order) bindOrder(props.focus_order); } });
}
function bindOrder(order) {
    selectedOrder.value = order;
    copyFeedback.value = '';
    orderForm.id = order.id;
    orderForm.status = order.status;
    orderForm.internal_notes = order.internal_notes || '';
    orderForm.price_quoted = order.price_quoted || 0;
    orderForm.is_paid = !!order.is_paid;
    orderForm.clearErrors();
    progressSnapForm.reset();
}
function closeOrder() { selectedOrder.value = null; setUrlOrder(null); }
// Keep the open editor in sync after a save / payment record refreshes the props.
watch(() => [props.orders, props.focus_order], () => {
    if (!selectedOrder.value) return;
    const id = selectedOrder.value.id;
    const fresh = props.focus_order?.id === id ? props.focus_order : (props.orders.data || []).find(o => o.id === id);
    if (fresh) selectedOrder.value = fresh;
});
// Deep link: /studio?tab=orders&order=123 opens it straight away.
onMounted(() => { if (props.focus_order) bindOrder(props.focus_order); });

const isCommerceOrder = computed(() => ['standard', 'drop'].includes(selectedOrder.value?.type));
// A shop order sent on WhatsApp: paid outside M-Pesa STK, confirmed here by the studio.
const isWhatsAppShop = computed(() => selectedOrder.value?.type === 'standard' && selectedOrder.value?.payment_method === 'whatsapp');
const canRecordPayment = computed(() => {
    const o = selectedOrder.value;
    if (!o || o.is_paid || ['Cancelled', 'Expired'].includes(o.status)) return false;
    return !isCommerceOrder.value || isWhatsAppShop.value;
});
function cancelWhatsAppOrder() {
    if (!selectedOrder.value || !confirm('Cancel this unpaid WhatsApp order? Its pieces go back on sale.')) return;
    router.post(route('studio.order.cancel', selectedOrder.value.id), {}, { preserveScroll: true });
}
const orderKindLabel = computed(() => ({ standard: 'Shop order', drop: 'Drop reservation' })[selectedOrder.value?.type] || 'Custom order');
const customerPhone = computed(() => selectedOrder.value?.whatsapp_number || selectedOrder.value?.phone || '');
const customerWhatsApp = computed(() => {
    // Custom briefs keep the number as typed: 07…, 7…, +254 7… all become 2547….
    const raw = String(customerPhone.value).replace(/\D/g, '');
    const digits = raw.startsWith('0') ? '254' + raw.slice(1) : /^[17]\d{8}$/.test(raw) ? '254' + raw : raw;
    const store = page.props.store?.name || 'Serana Closet';
    return digits ? `https://wa.me/${digits}?text=${encodeURIComponent(`Hello ${selectedOrder.value?.full_name || ''}, this is ${store} about order ${selectedOrder.value?.cipher || ''}.`)}` : null;
});
const briefFields = computed(() => {
    const o = selectedOrder.value; if (!o) return [];
    const j = o.items_json || {};
    if (o.type === 'drop') return [['Drop', j.drop], ['Size', j.size], ['Height', j.height_cm && `${j.height_cm} cm`], ['Fit notes', j.fit_notes]].filter(f => f[1]);
    return [['Piece', o.type && o.type !== 'custom' ? o.type : null], ['Occasion', j.occasion], ['Needed by', j.deadline], ['Location', j.location],
        ['Fit', j.selected_fit], ['Fabric', o.fabric_preference], ['Colour', j.selected_color], ['Inspired by', j.source_product]].filter(f => f[1]);
});
const paymentForm = useForm({ amount: '', method: 'mpesa_manual', reference: '' });
// A bespoke order's deposit: the policy share unless owner/manager set it (with a reason).
const depositForm = useForm({ deposit: '', reason: '' });
const canSetDeposit = computed(() => !isCommerceOrder.value && page.props.auth?.user?.abilities?.includes('deposit.override'));
// The order's ledger (Phase C): owner may reverse a booking or confirm a flagged one genuine, with a reason.
const canReverse = computed(() => page.props.auth?.user?.abilities?.includes('ledger.reverse'));
function reviewBooking(row, action) {
    const question = action === 'reverse'
        ? `Reverse this KSh ${Number(row.amount).toLocaleString()} booking? It will stop counting as money received. Reason:`
        : 'Confirm this is a genuine, separate payment. Reason:';
    const reason = window.prompt(question);
    if (!reason) return;
    router.post(route(action === 'reverse' ? 'studio.payment.reverse' : 'studio.payment.confirm', row.id), { reason }, { preserveScroll: true });
}
// A late payment whose item sold out (Phase C): full refund, swap, or wait for the next batch, with a reason.
const resolveForm = useForm({ action: '', reason: '', from_product_id: null, to_product_id: '', size: '' });
const canResolve = computed(() => !!selectedOrder.value?.stock_problem && page.props.auth?.user?.abilities?.includes('money.record'));
const swapChoices = computed(() => (props.products || []).filter(p => p.in_stock && (p.available_stock === null || p.available_stock > 0)));
watch(selectedOrder, (o, prev) => { if (o?.id !== prev?.id) { resolveForm.reset(); resolveForm.clearErrors(); resolveForm.from_product_id = o?.stock_problem?.items?.[0]?.product_id ?? null; } });
function resolveOrder(action) {
    if (!selectedOrder.value) return;
    resolveForm.action = action;
    resolveForm.post(route('studio.order.resolve', selectedOrder.value.id), { preserveScroll: true, onSuccess: () => resolveForm.reset('reason', 'to_product_id', 'size') });
}
// Cancel a custom order (N8): before designing the deposit is refunded; from designing on it is kept.
const canCancelBespoke = computed(() => {
    const o = selectedOrder.value;
    return !!o && !isCommerceOrder.value && o.order_state !== 'cancelled' && o.fulfilment_status !== 'shipped'
        && page.props.auth?.user?.abilities?.includes('money.record');
});
function cancelBespoke() {
    const o = selectedOrder.value;
    const paid = Number(o.amount_paid || 0);
    const started = ['designing', 'sewing', 'completed'].includes(o.fulfilment_status);
    const kept = started ? Math.min(Number(o.deposit_required || 0), paid) : 0;
    const back = paid - kept;
    const outcome = paid === 0 ? 'Nothing has been paid, so nothing is refunded.'
        : started ? `Work has started, so the deposit of KSh ${kept.toLocaleString()} is kept${back > 0 ? ` and KSh ${back.toLocaleString()} is owed back` : ''}.`
        : `Work hasn't started, so all KSh ${paid.toLocaleString()} paid is owed back.`;
    const reason = window.prompt(`Cancel order ${o.cipher}? ${outcome} The customer is emailed.\n\nReason:`);
    if (!reason) return;
    router.post(route('studio.order.cancel_bespoke', o.id), { reason }, { preserveScroll: true });
}
// The customer's signed pay link (design B): copy it, or send it prefilled on WhatsApp.
const payLinkCopied = ref(false);
async function copyPayLink() {
    try { await navigator.clipboard.writeText(selectedOrder.value.pay_link); payLinkCopied.value = true; setTimeout(() => { payLinkCopied.value = false; }, 2500); } catch { window.prompt('Copy this pay link:', selectedOrder.value.pay_link); }
}
function setDeposit() {
    depositForm.transform(d => ({ ...d, deposit: d.deposit === '' ? null : d.deposit }))
        .put(route('studio.order.deposit', selectedOrder.value.id), { preserveScroll: true, onSuccess: () => depositForm.reset() });
}
watch(selectedOrder, (o, prev) => { if (o?.id !== prev?.id) { paymentForm.reset(); paymentForm.clearErrors(); paymentForm.amount = o?.balance_due > 0 ? o.balance_due : (o?.price_quoted ? Math.round(o.price_quoted) : ''); } }); // the balance, now that part-payments exist
function recordPayment() {
    if (!selectedOrder.value) return;
    paymentForm.post(route('studio.order.record_payment', selectedOrder.value.id), { preserveScroll: true });
}
function saveOrder() {
    // Phase C: lowering a quote below what's paid owes the difference back; warn and ask why first.
    const o = selectedOrder.value;
    const quote = Math.round(Number(orderForm.price_quoted || 0));
    orderForm.quote_reason = '';
    if (o && !isCommerceOrder.value && quote !== Number(o.chargeable_total) && quote < Number(o.amount_paid || 0)) {
        const owed = Number(o.amount_paid) - quote;
        const reason = window.prompt(`KSh ${Number(o.amount_paid).toLocaleString()} is already paid. Lowering the quote to KSh ${quote.toLocaleString()} means KSh ${owed.toLocaleString()} is owed back to the customer (it goes to Refunds, and the piece can't ship until it's refunded).\n\nReason for lowering the quote:`);
        if (!reason) return;
        orderForm.quote_reason = reason;
    }
    orderForm.put(route('studio.update', orderForm.id), { preserveScroll: true });
}
function archiveOrder(id) {
    if (confirm('Move this order to the archive?')) {
        router.delete(route('studio.order.destroy', id), { preserveScroll: true, onSuccess: closeOrder });
    }
}
function restoreOrder(id) {
    router.post(route('studio.order.restore', id), {}, { preserveScroll: true, onSuccess: closeOrder });
}
function forceDeleteOrder(id) {
    if (confirm('Permanently delete this order? This cannot be undone.')) {
        router.delete(route('studio.order.force_delete', id), { preserveScroll: true, onSuccess: closeOrder });
    }
}
function submitProgressSnap() {
    if (!orderForm.id || !progressSnapForm.image) return;
    progressSnapForm.post(route('studio.progress_snap', orderForm.id), {
        preserveScroll: true, forceFormData: true,
        onSuccess: () => progressSnapForm.reset(),
    });
}
// Copy-ready text for packing/dispatch (shop) or the workroom (bespoke / drop).
const copyFeedback = ref('');
async function copyBrief() {
    const o = selectedOrder.value;
    if (!o) return;
    const lines = [`SERANA ${orderKindLabel.value.toUpperCase()} ${o.cipher || '#' + o.id}`, `Client: ${o.full_name || '—'} · ${customerPhone.value || '—'}`];
    if (o.type === 'standard') {
        (o.items_with_urls || []).forEach(i => lines.push(`• ${i.name}${i.size_label ? ' — ' + i.size_label : ''} × ${i.quantity}`));
        lines.push(`Deliver to: ${[o.address, o.city].filter(Boolean).join(', ') || '—'}`);
    } else {
        briefFields.value.forEach(([k, v]) => lines.push(`${k}: ${v}`));
        if (o.items_json?.notes) lines.push(`Notes: ${o.items_json.notes}`);
    }
    const m = [['Bust', o.bust_cm], ['Waist', o.waist_cm], ['Hips', o.hips_cm], ['Length', o.length_cm], ['Height', o.height_cm || o.items_json?.height_cm]].filter(x => x[1]);
    if (m.length) lines.push('Measurements (cm): ' + m.map(([k, v]) => `${k} ${v}`).join(' · '));
    lines.push(`Stage: ${o.status || '—'} · Payment: ${paymentStatus(o).label}`, `Total: ${formatAmount(o.price_quoted || 0, page.props)}`);
    try {
        await navigator.clipboard.writeText(lines.join('\n'));
        copyFeedback.value = 'Brief copied to clipboard.';
    } catch { copyFeedback.value = 'Copy unavailable. Select and copy the order details manually.'; }
}

/* ───────── Products ───────── */
const showProductModal = ref(false);
const isEditingProduct = ref(false);
const productPreview = ref(null);
const secondaryPreview1 = ref(null);
const secondaryPreview2 = ref(null);
const productForm = useForm({
    id: null, name: '', description: '', price: 0, category_id: '',
    image: null, secondary_image_1: null, secondary_image_2: null,
    in_stock: true, is_customizable: false, batch_limit: null, garment_type: 'set',
});
function addProduct() {
    isEditingProduct.value = false;
    productForm.reset();
    productForm.category_id = props.categories[0]?.id || '';
    productPreview.value = secondaryPreview1.value = secondaryPreview2.value = null;
    showProductModal.value = true;
}
function editProduct(p) {
    isEditingProduct.value = true;
    productForm.id = p.id;
    productForm.name = p.name;
    productForm.description = p.description;
    productForm.price = p.price;
    productForm.category_id = p.category_id;
    productForm.garment_type = p.garment_type || 'set';
    productForm.in_stock = !!p.in_stock;
    productForm.is_customizable = !!p.is_customizable;
    productForm.batch_limit = p.batch_limit;
    productForm.image = productForm.secondary_image_1 = productForm.secondary_image_2 = null;
    productPreview.value = p.image_url;
    secondaryPreview1.value = p.secondary_image_urls?.[0] || null;
    secondaryPreview2.value = p.secondary_image_urls?.[1] || null;
    showProductModal.value = true;
}
function submitProduct() {
    const done = () => { showProductModal.value = false; isEditingProduct.value = false; productForm.reset(); productPreview.value = secondaryPreview1.value = secondaryPreview2.value = null; };
    if (isEditingProduct.value) {
        productForm.transform(d => ({ ...d, _method: 'PUT' })).post(route('admin.catalog.update_product', productForm.id), { onSuccess: done });
    } else {
        productForm.post(route('admin.catalog.store_product'), { onSuccess: done });
    }
}
function archiveProduct(id) { if (confirm('Archive this product (hide from shop)?')) router.delete(route('admin.catalog.destroy_product', id), { preserveScroll: true }); }
function restoreProduct(id) { router.post(route('admin.catalog.restore_product', id), {}, { preserveScroll: true }); }
function forceDeleteProduct(id) { if (confirm('Permanently delete this product? This cannot be undone.')) router.delete(route('admin.catalog.force_delete_product', id), { preserveScroll: true }); }

/* ───────── Categories ───────── */
const showCategoryModal = ref(false);
const isEditingCategory = ref(false);
const categoryForm = useForm({ id: null, name: '', description: '' });
function openCategories() { showCategoryModal.value = true; }
function newCategory() { isEditingCategory.value = false; categoryForm.reset(); }
function editCategory(c) { isEditingCategory.value = true; categoryForm.id = c.id; categoryForm.name = c.name; categoryForm.description = c.description; }
function submitCategory() {
    const done = () => { isEditingCategory.value = false; categoryForm.reset(); };
    if (isEditingCategory.value) {
        categoryForm.transform(d => ({ ...d, _method: 'PUT' })).post(route('admin.catalog.update_category', categoryForm.id), { preserveScroll: true, onSuccess: done });
    } else {
        categoryForm.post(route('admin.catalog.store_category'), { preserveScroll: true, onSuccess: done });
    }
}
function deleteCategory(id) { if (confirm('Remove this category? Reassign its products first.')) router.delete(route('admin.catalog.destroy_category', id), { preserveScroll: true }); }
function updateCategoryBanner(cat, file) {
    if (!file) return;
    router.post(route('admin.catalog.update_category', cat.id),
        { _method: 'PUT', banner: file, name: cat.name, description: cat.description },
        { forceFormData: true, preserveScroll: true });
}
function removeCategoryBanner(cat) {
    if (confirm(`Remove the banner image for "${cat.name}"?`)) {
        router.post(route('admin.catalog.update_category', cat.id),
            { _method: 'PUT', remove_banner: true, name: cat.name, description: cat.description },
            { preserveScroll: true });
    }
}

/* ───────── Gallery ───────── */
const showGalleryModal = ref(false);
const isEditingGallery = ref(false);
const galleryPreview = ref(null);
const galleryForm = useForm({ id: null, image: null, client_name: '', garment_name: '', testimonial: '' });
function addGallery() { isEditingGallery.value = false; galleryForm.reset(); galleryPreview.value = null; showGalleryModal.value = true; }
function editGallery(g) {
    isEditingGallery.value = true;
    galleryForm.id = g.id; galleryForm.client_name = g.client_name; galleryForm.garment_name = g.garment_name; galleryForm.testimonial = g.testimonial; galleryForm.image = null;
    galleryPreview.value = g.image_url;
    showGalleryModal.value = true;
}
function submitGallery() {
    const done = () => { showGalleryModal.value = false; isEditingGallery.value = false; galleryForm.reset(); galleryPreview.value = null; };
    if (isEditingGallery.value) {
        galleryForm.transform(d => ({ ...d, _method: 'PUT' })).post(route('admin.gallery.update', galleryForm.id), { onSuccess: done });
    } else {
        galleryForm.post(route('admin.gallery.store'), { onSuccess: done });
    }
}
function archiveGallery(id) { if (confirm('Archive this photo?')) router.delete(route('admin.gallery.destroy', id), { preserveScroll: true }); }
function restoreGallery(id) { router.post(route('admin.gallery.restore', id), {}, { preserveScroll: true }); }
function forceDeleteGallery(id) { if (confirm('Permanently delete this photo?')) router.delete(route('admin.gallery.force_delete', id), { preserveScroll: true }); }

/* ───────── Drops ───────── */
const showDropModal = ref(false);
const isEditingDrop = ref(false);
const dropPreview = ref(null);
const dropForm = useForm({
    id: null, title: '', description: '', price: 0, target_quantity: 10,
    ends_at: '', status: 'draft', sizes: '', image: null,
});
// Offered sizes are edited as a comma list ("S, M, L") and sent as an array.
const parseSizes = (str) => (str || '').split(',').map(s => s.trim()).filter(Boolean);
function addDrop() {
    isEditingDrop.value = false;
    dropForm.reset();
    dropPreview.value = null;
    showDropModal.value = true;
}
function editDrop(d) {
    isEditingDrop.value = true;
    dropForm.id = d.id;
    dropForm.title = d.title;
    dropForm.description = d.description;
    dropForm.price = d.price;
    dropForm.target_quantity = d.target_quantity;
    // datetime-local wants "YYYY-MM-DDTHH:mm"
    dropForm.ends_at = d.ends_at ? String(d.ends_at).slice(0, 16).replace(' ', 'T') : '';
    dropForm.status = d.status;
    dropForm.sizes = (d.sizes || []).join(', ');
    dropForm.image = null;
    dropPreview.value = d.image_url;
    showDropModal.value = true;
}
function submitDrop() {
    const done = () => { showDropModal.value = false; isEditingDrop.value = false; dropForm.reset(); dropPreview.value = null; };
    if (isEditingDrop.value) {
        dropForm.transform(d => ({ ...d, sizes: parseSizes(d.sizes), _method: 'PUT' })).post(route('admin.drops.update', dropForm.id), { onSuccess: done });
    } else {
        dropForm.transform(d => ({ ...d, sizes: parseSizes(d.sizes) })).post(route('admin.drops.store'), { onSuccess: done });
    }
}
function archiveDrop(id) { if (confirm('Archive this drop?')) router.delete(route('admin.drops.destroy', id), { preserveScroll: true }); }

/* ───────── Settings ───────── */
const siteInfoForm = useForm({
    settings: {
        site_name: page.props.theme_settings?.site_name ?? '',
        site_tagline: page.props.theme_settings?.site_tagline ?? '',
        whatsapp_number: page.props.theme_settings?.whatsapp_number ?? page.props.whatsapp_number ?? '',
        focal_1_label: page.props.theme_settings?.focal_1_label ?? '',
        focal_2_label: page.props.theme_settings?.focal_2_label ?? '',
        focal_3_label: page.props.theme_settings?.focal_3_label ?? '',
        contact_email: page.props.theme_settings?.contact_email ?? '',
        contact_address: page.props.theme_settings?.contact_address ?? '',
        instagram_url: page.props.theme_settings?.instagram_url ?? '',
        tiktok_url: page.props.theme_settings?.tiktok_url ?? '',
        facebook_url: page.props.theme_settings?.facebook_url ?? '',
        x_url: page.props.theme_settings?.x_url ?? '',
        care_phone: page.props.theme_settings?.care_phone ?? '',
        notification_email: page.props.theme_settings?.notification_email ?? '',
    },
});
function saveSiteInfo() { siteInfoForm.post(route('admin.theme.bulk_update'), { preserveScroll: true }); }
function uploadAsset(key, file) {
    router.post(route('admin.theme.update'), { key, value: file }, { preserveScroll: true, forceFormData: true });
}
function removeAsset(key) {
    if (confirm('Remove this image and revert to the default?')) router.delete(route('admin.theme.destroy', key), { preserveScroll: true });
}
</script>

<template>
    <Head title="Studio · Admin" />

    <AdminShell :current="currentTab" @update:current="goTab" :title="title" :subtitle="subtitle">
        <template #actions>
            <div v-if="currentTab === 'products'" class="flex gap-2">
                <button @click="openCategories" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-white/5 text-slate-200 hover:bg-white/10">Categories</button>
                <button @click="addProduct" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-[#57d5fc] text-slate-900 hover:brightness-105">Add product</button>
            </div>
            <button v-else-if="currentTab === 'showcase'" @click="addGallery" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-[#57d5fc] text-slate-900 hover:brightness-105">Add photo</button>
            <button v-else-if="currentTab === 'drops'" @click="addDrop" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-[#57d5fc] text-slate-900 hover:brightness-105">New campaign</button>
        </template>

        <PaymentAttention v-if="currentTab === 'home'" :items="attention.payments || []" :count="attention.payments_count || 0" @openOrder="openOrder" @go="goTab" />
        <OverviewPanel v-if="currentTab === 'home'" :stats="stats" :attention="attention" :workflow="workflow" :generated-at="generated_at" @queue="openQueue" @refresh="refreshOverview" @create="kind => kind === 'product' ? addProduct() : addDrop()" @go="goTab" @openOrder="openOrder" />
        <OrdersPanel v-else-if="currentTab === 'orders'" :orders="orders" :selectedId="selectedOrder?.id" @select="openOrder" />
        <ProductsPanel v-else-if="currentTab === 'products'" :products="products" :archived="archived_products" @edit="editProduct" @delete="archiveProduct" @restore="restoreProduct" @forceDelete="forceDeleteProduct" />
        <DropsPanel v-else-if="currentTab === 'drops'" :drops="drops" @edit="editDrop" @archive="archiveDrop" />
        <GalleryPanel v-else-if="currentTab === 'showcase'" :gallery="gallery" :archived="archived_gallery" @edit="editGallery" @delete="archiveGallery" @restore="restoreGallery" @forceDelete="forceDeleteGallery" />
        <SubscribersPanel v-else-if="currentTab === 'subscribers'" :leads="leads" />
        <RefundsPanel v-else-if="currentTab === 'refunds'" :refunds="refunds" @openOrder="openOrder" />
        <template v-else-if="currentTab === 'settings'">
            <PaymentsPanel v-if="$page.props.auth?.user?.abilities?.includes('settings.payments')" :settings="payment_settings" />
            <OrderPolicyPanel v-if="$page.props.auth?.user?.abilities?.includes('settings.policies')" :policy="order_policy" />
            <SettingsPanel :form="siteInfoForm" :categories="categories"
                @save="saveSiteInfo" @uploadAsset="uploadAsset" @removeAsset="removeAsset"
                @uploadCategoryBanner="updateCategoryBanner" @removeCategoryBanner="removeCategoryBanner" />
            <CustomOrderOptionsPanel :options="custom_order_options" />
        </template>
    </AdminShell>

    <!-- ───────── Order detail ───────── -->
    <Modal :show="!!selectedOrder" maxWidth="2xl" @close="closeOrder">
        <div v-if="selectedOrder" class="bg-slate-900 text-slate-200 p-4 sm:p-6 max-h-[88dvh] sm:max-h-[85vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div>
                    <h3 class="text-lg sm:text-xl font-headline text-white">{{ selectedOrder.full_name || 'Customer' }}</h3>
                    <p class="text-slate-500 text-xs font-mono">{{ selectedOrder.reference || selectedOrder.cipher || ('#' + selectedOrder.id) }}</p>
                </div>
                <button @click="closeOrder" class="material-symbols-outlined text-slate-400 hover:text-white">close</button>
            </div>

            <div class="mb-4 rounded-xl border border-white/10 p-3 text-sm"><p class="font-semibold text-white">Decision before action</p><p class="mt-1 text-slate-300">{{ selectedOrder.is_paid ? (selectedOrder.status === 'completed' ? 'Ready for dispatch: verify items, sizes and delivery details, then update the stage after handover.' : selectedOrder.status === 'shipped' ? 'Dispatched: review the saved details before contacting the customer.' : 'Payment confirmed: review the brief or packing details and update the stage as work advances.') : 'Payment not confirmed: review the quote or payment evidence before committing fulfilment. A stage change does not confirm payment.' }}</p><p class="mt-2 text-xs text-slate-400">Saved order updated: {{ selectedOrder.updated_at ? new Date(selectedOrder.updated_at).toLocaleString('en-GB', { timeZone: 'Africa/Nairobi', dateStyle: 'medium', timeStyle: 'short' }) + ' (Nairobi)' : 'Unknown' }}</p></div>
            <!-- Who it is and how to reach them -->
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-[#57d5fc]">{{ orderKindLabel }}</p>
            <div class="grid sm:grid-cols-2 gap-3 mb-4 text-sm">
                <div class="bg-slate-950/60 rounded-xl px-4 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0"><span class="text-slate-500 text-xs block">Phone / WhatsApp</span><span class="text-white">{{ customerPhone || '—' }}</span></div>
                    <a v-if="customerWhatsApp" :href="customerWhatsApp" target="_blank" rel="noopener" class="shrink-0 rounded-lg bg-emerald-500/15 px-3 py-2 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25">Message</a>
                </div>
                <div v-if="selectedOrder.email" class="bg-slate-950/60 rounded-xl px-4 py-3"><span class="text-slate-500 text-xs block">Email</span><a :href="`mailto:${selectedOrder.email}`" class="text-white hover:underline break-all">{{ selectedOrder.email }}</a></div>
            </div>

            <!-- Shop order: what to pack and where it goes -->
            <div v-if="selectedOrder.type === 'standard'" class="mb-5 space-y-3 text-sm">
                <div class="bg-slate-950/60 rounded-xl p-4">
                    <span class="text-slate-500 text-xs block mb-2">Items to pack</span>
                    <ul class="space-y-2">
                        <li v-for="(item, i) in selectedOrder.items_with_urls || []" :key="i" class="flex items-start justify-between gap-3">
                            <span><span class="text-white">{{ item.name }}</span><span v-if="item.size_label" class="block text-xs text-slate-400">{{ item.size_label }}</span></span>
                            <span class="whitespace-nowrap text-slate-300">× {{ item.quantity }} · {{ formatAmount((item.price || 0) * (item.quantity || 1), page.props) }}</span>
                        </li>
                    </ul>
                </div>
                <div class="bg-slate-950/60 rounded-xl p-4">
                    <span class="text-slate-500 text-xs block mb-1">Deliver to</span>
                    <span class="text-white">{{ selectedOrder.address || '—' }}</span>
                    <span v-if="selectedOrder.city" class="block text-slate-300">{{ selectedOrder.city }}</span>
                </div>
            </div>

            <!-- Custom order / Drop reservation details -->
            <div v-else class="mb-5 text-sm">
                <div v-if="briefFields.length" class="grid sm:grid-cols-2 gap-3">
                    <div v-for="[label, value] in briefFields" :key="label" class="bg-slate-950/60 rounded-xl px-4 py-3"><span class="text-slate-500 text-xs block">{{ label }}</span><span class="text-white capitalize">{{ value }}</span></div>
                </div>
                <div v-if="selectedOrder.items_json?.notes" class="mt-3 bg-slate-950/60 rounded-xl px-4 py-3"><span class="text-slate-500 text-xs block">Client notes</span><p class="text-white whitespace-pre-line">{{ selectedOrder.items_json.notes }}</p></div>
            </div>

            <!-- Measurements -->
            <div v-if="selectedOrder.bust_cm || selectedOrder.waist_cm || selectedOrder.hips_cm || selectedOrder.height_cm" class="grid grid-cols-4 gap-2 mb-5 text-center">
                <div v-for="m in [['Bust', selectedOrder.bust_cm], ['Waist', selectedOrder.waist_cm], ['Hips', selectedOrder.hips_cm], ['Height', selectedOrder.height_cm]]" :key="m[0]" class="bg-slate-950/60 rounded-xl py-3">
                    <span class="block text-slate-500 text-[10px] uppercase">{{ m[0] }}</span>
                    <span class="text-white">{{ m[1] || '—' }}</span>
                </div>
            </div>

            <!-- Images -->
            <div v-if="orderImages(selectedOrder).length" class="flex gap-2 overflow-x-auto mb-5 no-scrollbar">
                <img v-for="(img, i) in orderImages(selectedOrder)" :key="i" :src="img" class="h-24 w-24 object-cover rounded-lg shrink-0" />
            </div>

            <!-- Editable fields -->
            <div class="space-y-4">
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Production stage</span>
                        <p v-if="selectedOrder.order_state === 'cancelled'" class="rounded-xl bg-slate-950/60 px-4 py-3 text-white">Cancelled at: <span class="capitalize">{{ selectedOrder.fulfilment_status }}</span></p>
                        <select v-else :disabled="['standard', 'drop'].includes(selectedOrder.type) && !selectedOrder.is_paid" v-model="orderForm.status" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white capitalize focus:ring-1 focus:ring-[#57d5fc] outline-none">
                            <option v-for="s in productionStages" :key="s" :value="s" class="capitalize">{{ s }}</option>
                            <option v-if="!productionStages.includes(orderForm.status)" :value="orderForm.status">{{ orderForm.status }}</option>
                        </select>
                    </label>
                    <label v-if="canOverrideFulfilment && !isCommerceOrder" class="block sm:col-span-2">
                        <span class="block text-sm text-slate-300 mb-2">Owner override reason <span class="text-slate-500">(only if starting work before the deposit, or shipping with a balance)</span></span>
                        <input v-model="orderForm.override_reason" maxlength="255" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    </label>
                    <div v-if="isCommerceOrder" class="block">
                        <span class="block text-sm text-slate-300 mb-2">Total</span>
                        <p class="rounded-xl bg-slate-950/60 px-4 py-3 text-white">{{ formatAmount(selectedOrder.price_quoted || 0, page.props) }} <span class="text-xs text-slate-500">· locked {{ selectedOrder.is_paid ? 'after payment' : 'from checkout' }}</span></p>
                    </div>
                    <label v-else class="block">
                        <span class="block text-sm text-slate-300 mb-2">Quote (KES — Kenyan shillings)</span>
                        <input v-model="orderForm.price_quoted" type="number" min="0" step="1" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    </label>
                </div>
                <div class="rounded-xl bg-slate-950/60 p-4 text-sm">
                    <p class="text-slate-300">Payment: <strong :class="selectedOrder.is_paid ? 'text-emerald-300' : 'text-amber-300'">{{ paymentStatus(selectedOrder).label }}</strong></p>
                    <p v-if="!isCommerceOrder && selectedOrder.chargeable_total > 0" class="mt-1 text-xs text-slate-300">
                        Deposit {{ formatAmount(selectedOrder.deposit_required, page.props) }} · Paid {{ formatAmount(selectedOrder.amount_paid, page.props) }} · Balance {{ formatAmount(Math.max(0, selectedOrder.balance_due), page.props) }}
                    </p>
                    <p v-if="isCommerceOrder && (selectedOrder.amount_paid > 0 || selectedOrder.balance_due < 0)" class="mt-1 text-xs text-slate-300">
                        Total {{ formatAmount(selectedOrder.chargeable_total, page.props) }} · Paid {{ formatAmount(selectedOrder.amount_paid, page.props) }} ·
                        <template v-if="selectedOrder.balance_due < 0"><span class="text-amber-400">Owed back {{ formatAmount(-selectedOrder.balance_due, page.props) }}</span></template>
                        <template v-else>Balance {{ formatAmount(selectedOrder.balance_due, page.props) }}</template>
                    </p>
                    <p v-if="selectedOrder.resolution && selectedOrder.resolution !== 'none' && selectedOrder.resolution_reason" role="status" class="mt-2 rounded-lg bg-amber-500/10 ring-1 ring-amber-400/30 px-3 py-2 text-xs text-amber-400">{{ selectedOrder.resolution_reason }}</p>
                    <form v-if="canResolve" @submit.prevent class="mt-3 rounded-xl ring-1 ring-white/10 p-3 space-y-2" aria-label="Decide what happens to this order">
                        <p class="text-xs text-slate-300">
                            <template v-if="selectedOrder.stock_problem.waiting">Waiting for the next batch. When it is back, swap the customer onto it, or refund in full.</template>
                            <template v-else>Sold out: {{ selectedOrder.stock_problem.items.map(i => i.name).join(', ') }}. Agree with the customer, then record the decision.</template>
                        </p>
                        <select v-if="selectedOrder.stock_problem.items.length > 1" v-model="resolveForm.from_product_id" aria-label="Item that sold out" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                            <option v-for="i in selectedOrder.stock_problem.items" :key="i.product_id" :value="i.product_id">{{ i.name }} × {{ i.quantity }}</option>
                        </select>
                        <div class="grid gap-2 sm:grid-cols-[1.4fr_1fr]">
                            <select v-model="resolveForm.to_product_id" aria-label="Swap to" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm">
                                <option value="">Swap to… (in stock)</option>
                                <option v-for="p in swapChoices" :key="p.id" :value="p.id">{{ p.name }} · {{ formatAmount(p.price, page.props) }}<template v-if="p.available_stock !== null"> · {{ p.available_stock }} left</template></option>
                            </select>
                            <input v-model="resolveForm.size" maxlength="60" aria-label="Size for the swap" placeholder="Size (optional)" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm" />
                        </div>
                        <input v-model="resolveForm.reason" maxlength="255" aria-label="Reason for this decision" placeholder="Reason (what the customer agreed)" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm" />
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="resolveOrder('swap')" :disabled="resolveForm.processing || !resolveForm.reason || !resolveForm.to_product_id" class="rounded-xl bg-[#57d5fc] px-4 py-2 text-sm font-semibold text-slate-900 disabled:opacity-40">Swap</button>
                            <button v-if="!selectedOrder.stock_problem.waiting" type="button" @click="resolveOrder('wait')" :disabled="resolveForm.processing || !resolveForm.reason" class="rounded-xl bg-white/5 px-4 py-2 text-sm text-slate-200 disabled:opacity-40">Wait for next batch</button>
                            <button type="button" @click="resolveOrder('refund')" :disabled="resolveForm.processing || !resolveForm.reason" class="rounded-xl bg-rose-500/15 px-4 py-2 text-sm font-semibold text-rose-300 disabled:opacity-40">Full refund</button>
                        </div>
                        <p v-for="(message, field) in resolveForm.errors" :key="'r' + field" role="alert" class="text-sm text-rose-300">{{ message }}</p>
                    </form>
                    <form v-if="canSetDeposit && selectedOrder.chargeable_total > 0" @submit.prevent="setDeposit" class="mt-2 grid gap-2 sm:grid-cols-[1fr_1.6fr_auto]">
                        <input v-model="depositForm.deposit" type="number" min="0" step="1" aria-label="Deposit for this order in KSh" placeholder="Deposit (KSh)" title="Leave blank to use the policy share" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2 text-white text-sm focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                        <input v-model="depositForm.reason" aria-label="Why this deposit differs" placeholder="Reason" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2 text-white text-sm focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                        <button type="submit" :disabled="depositForm.processing || !depositForm.reason" class="rounded-xl bg-white/5 px-4 py-2 text-sm text-slate-200 disabled:opacity-40">Set deposit</button>
                    </form>
                    <p v-for="(message, field) in depositForm.errors" :key="'d' + field" role="alert" class="mt-2 text-sm text-rose-300">{{ message }}</p>
                    <ul v-if="selectedOrder.ledger_rows?.length" class="mt-3 space-y-1 text-xs text-slate-300" aria-label="Payments on this order">
                        <li v-for="row in selectedOrder.ledger_rows" :key="row.id" class="flex flex-wrap items-center gap-2">
                            <span :class="row.reversed ? 'line-through text-slate-500' : ''">{{ row.type === 'payment' ? 'Payment' : row.type === 'refund' ? 'Refund' : 'Reversal' }} {{ formatAmount(row.amount, page.props) }} · {{ row.method }}<template v-if="row.reference"> · {{ row.reference }}</template> · {{ row.at }}</span>
                            <span v-if="row.flagged" class="text-rose-300">Code also booked on another order</span>
                            <span v-if="row.unexpected" class="text-amber-400">Differs from its prompt</span>
                            <span v-if="row.genuine" class="text-slate-400">Confirmed genuine</span>
                            <template v-if="canReverse && row.type === 'payment' && !row.reversed">
                                <button type="button" @click="reviewBooking(row, 'reverse')" class="underline hover:text-rose-300">Reverse</button>
                                <button v-if="row.flagged || row.unexpected" type="button" @click="reviewBooking(row, 'confirm')" class="underline">Confirm genuine</button>
                            </template>
                        </li>
                    </ul>
                    <div v-if="selectedOrder.pay_link && selectedOrder.balance_due > 0" class="mt-3 flex flex-wrap gap-2">
                        <button type="button" @click="copyPayLink" class="rounded-xl bg-white/5 px-4 py-2 text-sm text-slate-200 hover:bg-white/10">{{ payLinkCopied ? 'Pay link copied' : 'Copy pay link' }}</button>
                        <a v-if="selectedOrder.pay_whatsapp_url" :href="selectedOrder.pay_whatsapp_url" target="_blank" rel="noopener" class="rounded-xl bg-emerald-500/20 px-4 py-2 text-sm font-semibold text-emerald-400">Send quote &amp; pay link on WhatsApp</a>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        <template v-if="isWhatsAppShop && !selectedOrder.is_paid">WhatsApp order. Once you’ve received {{ formatAmount(selectedOrder.price_quoted, page.props) }} (Send Money, Pochi, cash…), confirm it here: the customer gets their receipt and the stock is committed.</template>
                        <template v-else-if="isWhatsAppShop">Confirmed by the studio<span v-if="selectedOrder.mpesa_receipt_number"> · ref {{ selectedOrder.mpesa_receipt_number }}</span>.</template>
                        <template v-else-if="isCommerceOrder">Confirmed automatically by M-Pesa. It can't be changed here.</template>
                        <template v-else-if="selectedOrder.is_paid">Recorded by the studio<span v-if="selectedOrder.mpesa_receipt_number"> · ref {{ selectedOrder.mpesa_receipt_number }}</span>.</template>
                        <template v-else>Custom order payments are arranged on WhatsApp. Record the payment here once you receive it.</template>
                    </p>
                    <form v-if="canRecordPayment" @submit.prevent="recordPayment" class="mt-3 grid gap-2 sm:grid-cols-[1fr_auto_1.4fr_auto]">
                        <input v-model="paymentForm.amount" type="number" min="1" step="1" aria-label="Amount received in KSh" placeholder="Amount (KSh)" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                        <select v-model="paymentForm.method" aria-label="How it was paid" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none">
                            <option value="mpesa_manual">M-Pesa</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank</option>
                        </select>
                        <input v-model="paymentForm.reference" aria-label="M-Pesa code or payment note" placeholder="M-Pesa code or note" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                        <button type="submit" :disabled="paymentForm.processing || !paymentForm.amount || !paymentForm.reference" class="rounded-xl bg-emerald-500/20 px-4 py-2.5 text-sm font-semibold text-emerald-400 disabled:opacity-40">{{ paymentForm.processing ? 'Saving…' : isWhatsAppShop ? 'Confirm payment' : 'Record payment' }}</button>
                    </form>
                    <button v-if="isWhatsAppShop && !selectedOrder.is_paid && selectedOrder.status === 'Pending Payment'" type="button" @click="cancelWhatsAppOrder" class="mt-3 text-xs text-slate-400 underline hover:text-rose-300">Customer didn’t pay? Cancel order and release stock</button>
                    <p v-for="(message, field) in paymentForm.errors" :key="field" role="alert" class="mt-2 text-sm text-rose-300">{{ message }}</p>
                </div>
                <p v-for="(message, field) in orderForm.errors" :key="field" role="alert" class="text-sm text-rose-300">{{ message }}</p>

                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Private decision &amp; handover notes</span>
                    <p class="text-xs text-slate-400 mb-2">Record the decision, reason, responsible person and agreed next step. These notes are internal; saving replaces the current text.</p>
                    <textarea v-model="orderForm.internal_notes" rows="3" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none"></textarea>
                </label>

                <!-- Progress photo -->
                <div class="bg-slate-950/60 rounded-xl p-4">
                    <span class="block text-sm text-slate-300 mb-2">Add a progress photo</span>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="file" accept="image/*" @input="progressSnapForm.image = $event.target.files[0]" class="text-xs text-slate-400 flex-1" />
                        <button @click="submitProgressSnap" :disabled="!progressSnapForm.image || progressSnapForm.processing" class="px-4 py-2 rounded-lg text-xs font-semibold bg-white/10 text-white disabled:opacity-40">Upload</button>
                    </div>
                </div>
            </div>

            <p v-if="copyFeedback" role="status" class="mt-3 text-sm text-slate-300">{{ copyFeedback }}</p>
            <!-- Actions -->
            <div class="mt-6 flex flex-wrap gap-2">
                <button @click="saveOrder" :disabled="orderForm.processing" class="px-6 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm disabled:opacity-40">{{ orderForm.processing ? 'Saving…' : 'Save order changes' }}</button>
                <button @click="copyBrief" class="px-4 py-3 rounded-xl bg-white/5 text-slate-200 text-sm hover:bg-white/10">Copy brief</button>
                <button v-if="canCancelBespoke" type="button" @click="cancelBespoke" class="px-4 py-3 rounded-xl bg-rose-500/10 text-rose-300 text-sm hover:bg-rose-500/20">Cancel order</button>
                <template v-if="!orderArchived">
                    <button @click="archiveOrder(selectedOrder.id)" class="px-4 py-3 rounded-xl bg-white/5 text-slate-400 text-sm hover:text-rose-400 ml-auto">Archive</button>
                </template>
                <template v-else>
                    <button @click="restoreOrder(selectedOrder.id)" class="px-4 py-3 rounded-xl bg-[#57d5fc]/15 text-[#57d5fc] text-sm ml-auto">Restore</button>
                    <button @click="forceDeleteOrder(selectedOrder.id)" class="px-4 py-3 rounded-xl bg-white/5 text-slate-400 text-sm hover:text-rose-400">Delete</button>
                </template>
            </div>
        </div>
    </Modal>

    <!-- ───────── Product form ───────── -->
    <Modal :show="showProductModal" maxWidth="2xl" @close="showProductModal = false">
        <div class="bg-slate-900 text-slate-200 p-4 sm:p-6 max-h-[88dvh] sm:max-h-[85vh] overflow-y-auto">
            <h3 class="text-lg sm:text-xl font-headline text-white mb-5">{{ isEditingProduct ? 'Edit product' : 'Add product' }}</h3>
            <div class="space-y-4">
                <label class="block mb-4">
                    <span class="block text-sm text-slate-300 mb-2">Garment Type (required)</span>
                    <select v-model="productForm.garment_type" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white">
                        <option value="set">Hoodie & Jogger Set — two sizes</option>
                        <option value="hoodie">Hoodie — top size</option>
                        <option value="jogger">Jogger — bottom size</option>
                    </select>
                </label>
                <p v-for="(message, field) in productForm.errors" :key="field" role="alert" class="text-sm text-rose-300 mb-3">{{ message }}</p>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Name</span>
                    <input v-model="productForm.name" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    <span v-if="productForm.errors.name" class="text-rose-400 text-xs">{{ productForm.errors.name }}</span>
                </label>
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Price (KES — Kenyan shillings)</span>
                        <input v-model="productForm.price" type="number" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    </label>
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Category</span>
                        <select v-model="productForm.category_id" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none">
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </label>
                </div>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Description</span>
                    <textarea v-model="productForm.description" rows="3" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none"></textarea>
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <div v-for="(slot, idx) in [['image', productPreview], ['secondary_image_1', secondaryPreview1], ['secondary_image_2', secondaryPreview2]]" :key="idx">
                        <span class="block text-xs text-slate-400 mb-1">{{ idx === 0 ? 'Main photo' : 'Extra ' + idx }}</span>
                        <label class="block aspect-square rounded-xl bg-slate-950 border border-dashed border-white/10 overflow-hidden cursor-pointer grid place-items-center">
                            <img v-if="slot[1]" :src="getObjectURL(slot[1])" class="w-full h-full object-cover" />
                            <span v-else class="material-symbols-outlined text-slate-700">add_photo_alternate</span>
                            <input type="file" accept="image/*" class="hidden"
                                @input="(e) => { const f = e.target.files[0]; if(!f) return; productForm[slot[0]] = f; if(idx===0) productPreview = f; else if(idx===1) secondaryPreview1 = f; else secondaryPreview2 = f; }" />
                        </label>
                    </div>
                </div>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input v-model="productForm.in_stock" type="checkbox" class="w-4 h-4 accent-[#57d5fc]" /> In stock</label>
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input v-model="productForm.is_customizable" type="checkbox" class="w-4 h-4 accent-[#57d5fc]" /> Offer a custom version <span class="text-slate-500">("Custom option" badge + made-to-measure link)</span></label>
                </div>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Batch limit (optional)</span>
                    <input v-model="productForm.batch_limit" type="number" placeholder="Leave blank for unlimited" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                </label>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button @click="showProductModal = false" class="px-5 py-3 rounded-xl bg-white/5 text-slate-300 text-sm">Cancel</button>
                <button @click="submitProduct" :disabled="productForm.processing" class="px-6 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm disabled:opacity-40">{{ productForm.processing ? 'Saving…' : 'Save product' }}</button>
            </div>
        </div>
    </Modal>

    <!-- ───────── Gallery form ───────── -->
    <Modal :show="showGalleryModal" maxWidth="lg" @close="showGalleryModal = false">
        <div class="bg-slate-900 text-slate-200 p-4 sm:p-6">
            <h3 class="text-lg sm:text-xl font-headline text-white mb-5">{{ isEditingGallery ? 'Edit photo' : 'Add photo' }}</h3>
            <div class="space-y-4">
                <p v-for="(message, field) in galleryForm.errors" :key="field" role="alert" class="text-sm text-rose-300 mb-3">{{ message }}</p>
                <p v-for="(message, field) in categoryForm.errors" :key="field" role="alert" class="text-sm text-rose-300 mb-3">{{ message }}</p>
                <label class="block aspect-[4/3] rounded-xl bg-slate-950 border border-dashed border-white/10 overflow-hidden cursor-pointer grid place-items-center">
                    <img v-if="galleryPreview" :src="getObjectURL(galleryPreview)" class="w-full h-full object-cover" />
                    <span v-else class="material-symbols-outlined text-slate-700 text-3xl">add_photo_alternate</span>
                    <input type="file" accept="image/*" class="hidden" @input="(e) => { const f = e.target.files[0]; if(f){ galleryForm.image = f; galleryPreview = f; } }" />
                </label>
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block text-sm text-slate-300"><span class="block mb-2">Client name (required)</span><input v-model="galleryForm.client_name" placeholder="Client name" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" /></label>
                    <label class="block text-sm text-slate-300"><span class="block mb-2">Garment name (required)</span><input v-model="galleryForm.garment_name" placeholder="Garment name" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" /></label>
                </div>
                <label class="block text-sm text-slate-300"><span class="block mb-2">Customer testimonial (optional)</span><textarea v-model="galleryForm.testimonial" rows="2" placeholder="Testimonial (optional)" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none"></textarea></label>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button @click="showGalleryModal = false" class="px-5 py-3 rounded-xl bg-white/5 text-slate-300 text-sm">Cancel</button>
                <button @click="submitGallery" :disabled="galleryForm.processing" class="px-6 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm disabled:opacity-40">{{ galleryForm.processing ? 'Saving…' : 'Save photo' }}</button>
            </div>
        </div>
    </Modal>

    <!-- ───────── Categories ───────── -->
    <Modal :show="showCategoryModal" maxWidth="lg" @close="showCategoryModal = false">
        <div class="bg-slate-900 text-slate-200 p-4 sm:p-6">
            <h3 class="text-lg sm:text-xl font-headline text-white mb-5">Categories</h3>
            <div class="space-y-2 mb-5 max-h-52 overflow-y-auto">
                <div v-for="c in categories" :key="c.id" class="flex items-center justify-between bg-slate-950/60 rounded-xl px-4 py-3">
                    <span class="text-sm text-white">{{ c.name }}</span>
                    <span class="flex gap-2">
                        <button @click="editCategory(c)" class="text-slate-400 hover:text-white"><span class="material-symbols-outlined text-[18px]">edit</span></button>
                        <button @click="deleteCategory(c.id)" class="text-slate-400 hover:text-rose-400"><span class="material-symbols-outlined text-[18px]">delete</span></button>
                    </span>
                </div>
                <p v-if="categories.length === 0" class="text-slate-500 text-sm text-center py-4">No categories yet.</p>
            </div>
            <div class="border-t border-white/10 pt-4 space-y-3">
                <p class="text-sm text-slate-300">{{ isEditingCategory ? 'Edit category' : 'New category' }}</p>
                <label class="block text-sm text-slate-300"><span class="block mb-2">Category name</span><input v-model="categoryForm.name" placeholder="Name" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" /></label>
                <label class="block text-sm text-slate-300"><span class="block mb-2">Category description (optional)</span><input v-model="categoryForm.description" placeholder="Short description (optional)" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" /></label>
                <div class="flex gap-2 justify-end">
                    <button v-if="isEditingCategory" @click="newCategory" class="px-4 py-2.5 rounded-xl bg-white/5 text-slate-300 text-sm">New</button>
                    <button @click="submitCategory" :disabled="categoryForm.processing" class="px-5 py-2.5 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm disabled:opacity-40">{{ isEditingCategory ? 'Update' : 'Add' }}</button>
                </div>
            </div>
        </div>
    </Modal>

    <!-- ───────── Drop form ───────── -->
    <Modal :show="showDropModal" maxWidth="2xl" @close="showDropModal = false">
        <div class="bg-slate-900 text-slate-200 p-4 sm:p-6 max-h-[88dvh] sm:max-h-[85vh] overflow-y-auto">
            <h3 class="text-lg sm:text-xl font-headline text-white mb-1">{{ isEditingDrop ? 'Edit pre-order campaign' : 'New pre-order campaign' }}</h3>
            <p class="text-slate-500 text-sm mb-5">Customers reserve and pay upfront; you produce once it hits the goal.</p>
            <div class="space-y-4">
                <p v-for="(message, field) in dropForm.errors" :key="field" role="alert" class="text-sm text-rose-300 mb-3">{{ message }}</p>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Title</span>
                    <input v-model="dropForm.title" placeholder="e.g. Midnight Trench" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    <span v-if="dropForm.errors.title" class="text-rose-400 text-xs">{{ dropForm.errors.title }}</span>
                </label>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Description</span>
                    <textarea v-model="dropForm.description" rows="3" placeholder="Describe the garment, fabric, fit and what the customer will receive." class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none"></textarea>
                </label>
                <div class="grid sm:grid-cols-3 gap-4">
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Price (KES — Kenyan shillings)</span>
                        <input v-model="dropForm.price" type="number" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    </label>
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Target pieces</span>
                        <input v-model="dropForm.target_quantity" type="number" min="1" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    </label>
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Status</span>
                        <select v-model="dropForm.status" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none">
                            <option value="draft">Draft (hidden)</option>
                            <option value="live">Live (taking reservations)</option>
                            <option value="greenlit">In production</option>
                            <option value="fulfilled">Fulfilled (shipped)</option>
                            <option value="closed">Closed</option>
                        </select>
                    </label>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Reservation deadline (optional)</span>
                        <input v-model="dropForm.ends_at" type="datetime-local" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    </label>
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Sizes offered (optional)</span>
                        <input v-model="dropForm.sizes" placeholder="e.g. XS, S, M, L, XL" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                        <span class="block text-xs text-slate-600 mt-1.5">Comma-separated. Leave blank to collect measurements only.</span>
                    </label>
                </div>
                <div>
                    <span class="block text-sm text-slate-300 mb-2">Concept image</span>
                    <label class="block aspect-video rounded-xl bg-slate-950 border border-dashed border-white/10 overflow-hidden cursor-pointer grid place-items-center max-w-sm">
                        <img v-if="dropPreview" :src="getObjectURL(dropPreview)" class="w-full h-full object-cover" />
                        <span v-else class="material-symbols-outlined text-slate-700 text-3xl">add_photo_alternate</span>
                        <input type="file" accept="image/*" class="hidden" @input="(e) => { const f = e.target.files[0]; if(f){ dropForm.image = f; dropPreview = f; } }" />
                    </label>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button @click="showDropModal = false" class="px-5 py-3 rounded-xl bg-white/5 text-slate-300 text-sm">Cancel</button>
                <button @click="submitDrop" :disabled="dropForm.processing" class="px-6 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm disabled:opacity-40">{{ dropForm.processing ? 'Saving…' : 'Save campaign' }}</button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.font-headline { font-family: 'Noto Serif', serif; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300; vertical-align: middle; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
