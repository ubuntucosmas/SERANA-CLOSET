<!--
  StudioDashboard — admin home. Thin shell: owns all forms/handlers/modals and
  routes the active section to a presentational panel. Chrome lives in AdminShell.
-->
<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';
import AdminShell from '@/Layouts/AdminShell.vue';
import Modal from '@/Components/Modal.vue';
import OverviewPanel from './Partials/OverviewPanel.vue';
import OrdersPanel from './Partials/OrdersPanel.vue';
import ProductsPanel from './Partials/ProductsPanel.vue';
import GalleryPanel from './Partials/GalleryPanel.vue';
import SubscribersPanel from './Partials/SubscribersPanel.vue';
import SettingsPanel from './Partials/SettingsPanel.vue';
import DropsPanel from './Partials/DropsPanel.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    attention: { type: Object, default: () => ({ awaiting: [], low_stock: [] }) },
    orders: { type: Array, default: () => [] },
    archived_orders: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    archived_products: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    gallery: { type: Array, default: () => [] },
    archived_gallery: { type: Array, default: () => [] },
    leads: { type: Array, default: () => [] },
    drops: { type: Array, default: () => [] },
    active_tab: { type: String, default: 'home' },
});

const page = usePage();
const { formatAmount } = useCurrency();

/* ───────── Navigation + lazy per-section loading ───────── */
const currentTab = ref(props.active_tab);
function goTab(id) { currentTab.value = id; }
watch(currentTab, () => { selectedOrder.value = null; });

const HEAD = {
    home: ['Home', 'Your studio at a glance.'],
    orders: ['Orders', 'Track payment and production for every order.'],
    products: ['Products', "Manage what's for sale in your shop."],
    drops: ['Drops', 'Sell-then-make pre-orders — produced only when enough are reserved.'],
    showcase: ['Showcase', 'The photo gallery shown to customers.'],
    subscribers: ['Subscribers', 'People on your newsletter list.'],
    settings: ['Settings', "Your store's name, contacts and images."],
};
const title = computed(() => HEAD[currentTab.value]?.[0] || '');
const subtitle = computed(() => HEAD[currentTab.value]?.[1] || '');

const getObjectURL = (file) => {
    if (!file) return null;
    if (typeof file === 'string') return file;
    try { return URL.createObjectURL(file); } catch { return null; }
};

/* ───────── Orders ───────── */
const selectedOrder = ref(null);
const productionStages = ['pending', 'designing', 'sewing', 'completed', 'shipped'];
const orderForm = useForm({ id: null, status: '', internal_notes: '', price_quoted: 0, is_paid: false });
const progressSnapForm = useForm({ image: null, description: '' });
const orderArchived = computed(() => props.archived_orders.some(o => o.id === selectedOrder.value?.id));

function openOrder(orderOrId) {
    // Accept an order object (from the list) or an id (from the Home shortcuts).
    const order = typeof orderOrId === 'object' ? orderOrId
        : [...props.orders, ...props.archived_orders].find(o => o.id === orderOrId);
    if (order) bindOrder(order);
}
function bindOrder(order) {
    selectedOrder.value = order;
    orderForm.id = order.id;
    orderForm.status = order.status;
    orderForm.internal_notes = order.internal_notes || '';
    orderForm.price_quoted = order.price_quoted || 0;
    orderForm.is_paid = !!order.is_paid;
    progressSnapForm.reset();
}
function closeOrder() { selectedOrder.value = null; }
function saveOrder() {
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
function orderImages(order) {
    if (!order) return [];
    let imgs = [];
    if (order.type === 'standard') imgs = (order.items_with_urls || []).map(i => i.image_url).filter(Boolean);
    else imgs = order.inspiration_urls || [];
    if (order.progress_snaps?.length) imgs.push(...order.progress_snaps.map(s => s.image_url).filter(Boolean));
    return [...new Set(imgs)];
}
function copyBrief() {
    const o = selectedOrder.value;
    if (!o) return;
    const brief = `SERANA ORDER #${String(o.id).padStart(4, '0')}\n` +
        `Client: ${o.full_name || '—'}\nItem: ${o.garment_name || o.outfit_type || 'Custom piece'}\n` +
        `Stage: ${o.status || '—'}\n\nMeasurements (cm):\n` +
        `• Bust: ${o.bust_cm || '--'}  • Waist: ${o.waist_cm || '--'}  • Hips: ${o.hips_cm || '--'}  • Height: ${o.height_cm || '--'}\n\n` +
        `Total: ${formatAmount(o.price_quoted || o.total || 0, page.props)}`;
    navigator.clipboard?.writeText(brief);
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
    in_stock: true, is_customizable: false, batch_limit: null,
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
        studio_capacity: page.props.theme_settings?.studio_capacity ?? '',
        focal_1_label: page.props.theme_settings?.focal_1_label ?? '',
        focal_2_label: page.props.theme_settings?.focal_2_label ?? '',
        focal_3_label: page.props.theme_settings?.focal_3_label ?? '',
        contact_email: page.props.theme_settings?.contact_email ?? '',
        contact_address: page.props.theme_settings?.contact_address ?? '',
        instagram_url: page.props.theme_settings?.instagram_url ?? '',
        tiktok_url: page.props.theme_settings?.tiktok_url ?? '',
        facebook_url: page.props.theme_settings?.facebook_url ?? '',
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
            <button v-else-if="currentTab === 'drops'" @click="addDrop" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-[#57d5fc] text-slate-900 hover:brightness-105">New drop</button>
        </template>

        <OverviewPanel v-if="currentTab === 'home'" :stats="stats" :attention="attention" @go="goTab" @openOrder="openOrder" />
        <OrdersPanel v-else-if="currentTab === 'orders'" :orders="orders" :archived="archived_orders" :selectedId="selectedOrder?.id" @select="openOrder" />
        <ProductsPanel v-else-if="currentTab === 'products'" :products="products" :archived="archived_products" @edit="editProduct" @delete="archiveProduct" @restore="restoreProduct" @forceDelete="forceDeleteProduct" />
        <DropsPanel v-else-if="currentTab === 'drops'" :drops="drops" @edit="editDrop" @archive="archiveDrop" />
        <GalleryPanel v-else-if="currentTab === 'showcase'" :gallery="gallery" :archived="archived_gallery" @edit="editGallery" @delete="archiveGallery" @restore="restoreGallery" @forceDelete="forceDeleteGallery" />
        <SubscribersPanel v-else-if="currentTab === 'subscribers'" :leads="leads" />
        <SettingsPanel v-else-if="currentTab === 'settings'" :form="siteInfoForm" :categories="categories"
            @save="saveSiteInfo" @uploadAsset="uploadAsset" @removeAsset="removeAsset"
            @uploadCategoryBanner="updateCategoryBanner" @removeCategoryBanner="removeCategoryBanner" />
    </AdminShell>

    <!-- ───────── Order detail ───────── -->
    <Modal :show="!!selectedOrder" maxWidth="2xl" @close="closeOrder">
        <div v-if="selectedOrder" class="bg-slate-900 text-slate-200 p-6 max-h-[85vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-4 mb-5">
                <div>
                    <h3 class="text-xl font-headline text-white">{{ selectedOrder.full_name || 'Customer' }}</h3>
                    <p class="text-slate-500 text-xs font-mono">{{ selectedOrder.reference || selectedOrder.cipher || ('#' + selectedOrder.id) }}</p>
                </div>
                <button @click="closeOrder" class="material-symbols-outlined text-slate-400 hover:text-white">close</button>
            </div>

            <!-- Contact + item -->
            <div class="grid sm:grid-cols-2 gap-3 mb-5 text-sm">
                <div v-if="selectedOrder.phone" class="bg-slate-950/60 rounded-xl px-4 py-3"><span class="text-slate-500 text-xs block">Phone</span>{{ selectedOrder.phone }}</div>
                <div v-if="selectedOrder.email" class="bg-slate-950/60 rounded-xl px-4 py-3"><span class="text-slate-500 text-xs block">Email</span>{{ selectedOrder.email }}</div>
                <div class="bg-slate-950/60 rounded-xl px-4 py-3" :class="selectedOrder.items_json?.size ? '' : 'sm:col-span-2'"><span class="text-slate-500 text-xs block">Item</span>{{ selectedOrder.garment_name || selectedOrder.outfit_type || 'Custom piece' }}</div>
                <div v-if="selectedOrder.items_json?.size" class="bg-slate-950/60 rounded-xl px-4 py-3"><span class="text-slate-500 text-xs block">Size</span>{{ selectedOrder.items_json.size }}</div>
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
                        <select v-model="orderForm.status" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white capitalize focus:ring-1 focus:ring-[#57d5fc] outline-none">
                            <option v-for="s in productionStages" :key="s" :value="s" class="capitalize">{{ s }}</option>
                            <option v-if="!productionStages.includes(orderForm.status)" :value="orderForm.status">{{ orderForm.status }}</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Price (KSh)</span>
                        <input v-model="orderForm.price_quoted" type="number" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    </label>
                </div>
                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="orderForm.is_paid" type="checkbox" class="w-4 h-4 accent-[#57d5fc]" /> Marked as paid
                </label>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Private notes</span>
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

            <!-- Actions -->
            <div class="mt-6 flex flex-wrap gap-2">
                <button @click="saveOrder" :disabled="orderForm.processing" class="px-6 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm disabled:opacity-40">{{ orderForm.processing ? 'Saving…' : 'Save' }}</button>
                <button @click="copyBrief" class="px-4 py-3 rounded-xl bg-white/5 text-slate-200 text-sm hover:bg-white/10">Copy brief</button>
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
        <div class="bg-slate-900 text-slate-200 p-6 max-h-[85vh] overflow-y-auto">
            <h3 class="text-xl font-headline text-white mb-5">{{ isEditingProduct ? 'Edit product' : 'Add product' }}</h3>
            <div class="space-y-4">
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Name</span>
                    <input v-model="productForm.name" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    <span v-if="productForm.errors.name" class="text-rose-400 text-xs">{{ productForm.errors.name }}</span>
                </label>
                <div class="grid sm:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Price (KSh)</span>
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
                    <label class="flex items-center gap-2 text-sm text-slate-300"><input v-model="productForm.is_customizable" type="checkbox" class="w-4 h-4 accent-[#57d5fc]" /> Customizable</label>
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
        <div class="bg-slate-900 text-slate-200 p-6">
            <h3 class="text-xl font-headline text-white mb-5">{{ isEditingGallery ? 'Edit photo' : 'Add photo' }}</h3>
            <div class="space-y-4">
                <label class="block aspect-[4/3] rounded-xl bg-slate-950 border border-dashed border-white/10 overflow-hidden cursor-pointer grid place-items-center">
                    <img v-if="galleryPreview" :src="getObjectURL(galleryPreview)" class="w-full h-full object-cover" />
                    <span v-else class="material-symbols-outlined text-slate-700 text-3xl">add_photo_alternate</span>
                    <input type="file" accept="image/*" class="hidden" @input="(e) => { const f = e.target.files[0]; if(f){ galleryForm.image = f; galleryPreview = f; } }" />
                </label>
                <div class="grid sm:grid-cols-2 gap-4">
                    <input v-model="galleryForm.client_name" placeholder="Client name" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    <input v-model="galleryForm.garment_name" placeholder="Garment name" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                </div>
                <textarea v-model="galleryForm.testimonial" rows="2" placeholder="Testimonial (optional)" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none"></textarea>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <button @click="showGalleryModal = false" class="px-5 py-3 rounded-xl bg-white/5 text-slate-300 text-sm">Cancel</button>
                <button @click="submitGallery" :disabled="galleryForm.processing" class="px-6 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm disabled:opacity-40">{{ galleryForm.processing ? 'Saving…' : 'Save photo' }}</button>
            </div>
        </div>
    </Modal>

    <!-- ───────── Categories ───────── -->
    <Modal :show="showCategoryModal" maxWidth="lg" @close="showCategoryModal = false">
        <div class="bg-slate-900 text-slate-200 p-6">
            <h3 class="text-xl font-headline text-white mb-5">Categories</h3>
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
                <input v-model="categoryForm.name" placeholder="Name" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                <input v-model="categoryForm.description" placeholder="Short description (optional)" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                <div class="flex gap-2 justify-end">
                    <button v-if="isEditingCategory" @click="newCategory" class="px-4 py-2.5 rounded-xl bg-white/5 text-slate-300 text-sm">New</button>
                    <button @click="submitCategory" :disabled="categoryForm.processing" class="px-5 py-2.5 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm disabled:opacity-40">{{ isEditingCategory ? 'Update' : 'Add' }}</button>
                </div>
            </div>
        </div>
    </Modal>

    <!-- ───────── Drop form ───────── -->
    <Modal :show="showDropModal" maxWidth="2xl" @close="showDropModal = false">
        <div class="bg-slate-900 text-slate-200 p-6 max-h-[85vh] overflow-y-auto">
            <h3 class="text-xl font-headline text-white mb-1">{{ isEditingDrop ? 'Edit drop' : 'New drop' }}</h3>
            <p class="text-slate-500 text-sm mb-5">Customers reserve and pay upfront; you produce once it hits the goal.</p>
            <div class="space-y-4">
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Title</span>
                    <input v-model="dropForm.title" placeholder="e.g. Midnight Trench" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    <span v-if="dropForm.errors.title" class="text-rose-400 text-xs">{{ dropForm.errors.title }}</span>
                </label>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Description</span>
                    <textarea v-model="dropForm.description" rows="3" placeholder="What is it, the fabric, the vibe…" class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none"></textarea>
                </label>
                <div class="grid sm:grid-cols-3 gap-4">
                    <label class="block">
                        <span class="block text-sm text-slate-300 mb-2">Price (KSh)</span>
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
                            <option value="greenlit">Greenlit (in production)</option>
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
                <button @click="submitDrop" :disabled="dropForm.processing" class="px-6 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm disabled:opacity-40">{{ dropForm.processing ? 'Saving…' : 'Save drop' }}</button>
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
