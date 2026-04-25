<!-- 
  StudioDashboard.vue - The "Order Registry" 
  
  Architectural Role: 
  The primary administrative hub for Serana Studio. It manages incoming custom orders,
  production progress, and brand asset management.
  
  Key Features:
  1. High-Density Tabular Registry: Desktop view optimized for rapid data auditing.
  2. Mobile Command Center: Sidebar transforms into a drawer; layout becomes vertical.
  3. Artisan Snapshot: Rapid data portability for WhatsApp production sharing.
  4. Bento-to-Sheet Transition: Order details open as side panels (desktop) or bottom-sheets (mobile).
-->
<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { useCurrency } from '../../Composables/useCurrency';

const props = defineProps({
	orders: Array,
	products: { type: Array, default: () => [] },
    archived_products: { type: Array, default: () => [] },
	categories: { type: Array, default: () => [] },
	gallery: { type: Array, default: () => [] },
    archived_gallery: { type: Array, default: () => [] },
	stats: { type: Object, default: () => ({}) },
	leads: { type: Array, default: () => [] },
    archived_orders: { type: Array, default: () => [] },
	active_tab: { type: String, default: 'overview' }
});

const page = usePage();
const { formatAmount } = useCurrency();
const currentTab = ref(props.active_tab);
const showArchivedProducts = ref(false);
const showArchivedGallery = ref(false);
const showArchivedOrders = ref(false);
const selectedOrder = ref(null);

const filteredOrders = computed(() => {
    const pool = showArchivedOrders.value ? props.archived_orders : props.orders;
	return pool.filter(o => currentTab.value === 'overview' ? o.type === 'standard' : o.type !== 'standard');
});

const productPreview = ref(null);
const secondaryPreview1 = ref(null);
const secondaryPreview2 = ref(null);

function editProduct(product) {
    isEditingProduct.value = true;
    productForm.id = product.id;
    productForm.name = product.name;
    productForm.description = product.description;
    productForm.price = product.price;
    productForm.category_id = product.category_id;
    productForm.in_stock = product.in_stock;
    productForm.is_customizable = product.is_customizable;
    productForm.batch_limit = product.batch_limit;
    productForm.image = null; 
    productForm.secondary_image_1 = null;
    productForm.secondary_image_1 = null;
    productForm.secondary_image_2 = null;
    
    productForm.specifications = product.specifications || {
        batch_name: 'Active Batch #01',
        fabric_weight: '450GSM Heavyweight Cotton Fleece',
        weight_index: 85,
        tech_specs: ['', '', '', '']
    };
    
    productPreview.value = product.image_url;
    secondaryPreview1.value = product.secondary_image_urls?.[0] || null;
    secondaryPreview2.value = product.secondary_image_urls?.[1] || null;
    
    showAddProductModal.value = true;
}

const galleryPreview = ref(null);
function editGallery(item) {
    isEditingGallery.value = true;
    galleryForm.id = item.id;
    galleryForm.client_name = item.client_name;
    galleryForm.garment_name = item.garment_name;
    galleryForm.testimonial = item.testimonial;
    galleryForm.image = null;
    galleryPreview.value = item.image_url;
    showAddGalleryModal.value = true;
}

const productForm = useForm({
    id: null,
	name: '',
	description: '',
	price: 0,
	category_id: props.categories[0]?.id || '',
	image: null,
	secondary_image_1: null,
	secondary_image_2: null,
	in_stock: true,
	is_customizable: false,
	batch_limit: null,
    specifications: {
        batch_name: 'Active Batch #01',
        fabric_weight: '450GSM Heavyweight Cotton Fleece',
        weight_index: 85,
        tech_specs: ['', '', '', '']
    }
});

const isEditingProduct = ref(false);
const showAddProductModal = ref(false);

const form = useForm({
	id: null,
	status: '',
	internal_notes: '',
	price_quoted: 0,
	is_paid: false
});

const galleryForm = useForm({
    id: null,
	image: null,
	client_name: '',
	garment_name: '',
	testimonial: ''
});

const isEditingGallery = ref(false);
const showAddGalleryModal = ref(false);

const categoryForm = useForm({
    id: null,
    name: '',
    description: ''
});

const isEditingCategory = ref(false);
const showAddCategoryModal = ref(false);

const themeForm = useForm({
    key: '',
    value: null
});

const siteInfoForm = useForm({
    settings: {
        site_name: '',
        site_tagline: '',
        whatsapp_number: '',
    }
});

function initSiteInfo() {
    const ts = usePage().props.theme_settings ?? {};
    siteInfoForm.settings.site_name      = ts.site_name ?? '';
    siteInfoForm.settings.site_tagline   = ts.site_tagline ?? '';
    siteInfoForm.settings.whatsapp_number = ts.whatsapp_number ?? usePage().props.whatsapp_number ?? '';
    siteInfoForm.settings.focal_1_label = ts.focal_1_label ?? '';
    siteInfoForm.settings.focal_2_label = ts.focal_2_label ?? '';
    siteInfoForm.settings.focal_3_label = ts.focal_3_label ?? '';
}
initSiteInfo();

function saveSiteInfo() {
    siteInfoForm.post(route('admin.theme.bulk_update'), {
        preserveScroll: true,
    });
}

function updateThemeAsset(key, val) {
    themeForm.key = key;
    themeForm.value = val;
    themeForm.post(route('admin.theme.update'), {
        preserveScroll: true,
        forceFormData: true
    });
}

const progressSnapForm = useForm({
    image: null,
    description: ''
});


function deleteThemeAsset(key) {
    if (confirm(`Reset ${key} to default?`)) {
        router.delete(route('admin.theme.destroy', key), {
            preserveScroll: true
        });
    }
}

const statuses = ['pending', 'designing', 'sewing', 'completed', 'shipped'];

const menuOpen = ref(false); // Mobile menu toggle
const showBriefNotification = ref(false);

function copyOrderBrief() {
    if (!selectedOrder.value) return;
    
    const o = selectedOrder.value;
    const brief = `
◆ SERANA ORDER BRIEF #${String(o.id).padStart(4, '0')}
--------------------------
Client: ${o.full_name}
Item: ${o.garment_name || o.outfit_type || 'Custom Piece'}
Status: ${o.status || 'Active'}

MEASUREMENTS (CM):
• Bust: ${o.bust_cm || '--'}
• Waist: ${o.waist_cm || '--'}
• Hips: ${o.hips_cm || '--'}
• Height: ${o.height_cm || '--'}

TOTAL: ${formatAmount(o.total || 0, page.props)}
--------------------------
`.trim();

    navigator.clipboard.writeText(brief).then(() => {
        showBriefNotification.value = true;
        setTimeout(() => showBriefNotification.value = false, 3000);
    });
}

/**
 * selectOrder(order)
 * 
 * Handles the focused selection of an order for the detail view.
 * Note: On mobile viewports (<1024px), this triggers an auto-scroll
 * to the details section to ensure visual continuity.
 */
function selectOrder(order) {
    if (selectedOrder.value?.id === order.id) {
        selectedOrder.value = null;
        form.id = null;
        return;
    }
    selectedOrder.value = order;
    form.id = order.id;
    form.status = order.status;
    form.internal_notes = order.internal_notes || '';
    form.price_quoted = order.price_quoted || 0;
    form.is_paid = order.is_paid || false;
    
    // Auto-scroll to details on mobile when selecting an order
    if (window.innerWidth < 1024) {
        setTimeout(() => {
            const el = document.getElementById('order-sheet-mobile');
            el?.scrollIntoView({ behavior: 'smooth' });
        }, 100);
    }
}

function updateOrder() {
    console.log('[Serana] Documenting order update...');
	form.put(route('studio.update', form.id), {
        onSuccess: () => {
            console.log('[Serana] Order updated successfully.');
        },
        onError: (err) => {
            console.error('[Serana] Order update failed:', err);
        }
	});
}

function deleteOrder(id) {
    if (confirm('Move this order to the archive? This will remove it from the active registry.')) {
        router.delete(route('studio.order.destroy', id), {
            preserveScroll: true,
            onSuccess: () => selectedOrder.value = null
        });
    }
}

function restoreOrder(id) {
    if (confirm('Restore this order to the active registry?')) {
        router.post(route('studio.order.restore', id), {}, {
            preserveScroll: true,
            onSuccess: () => selectedOrder.value = null
        });
    }
}

function forceDeleteOrder(id) {
    if (confirm('PERMANENTLY PURGE this order and all its visual assets? This cannot be undone.')) {
        router.delete(route('studio.order.force_delete', id), {
            preserveScroll: true,
            onSuccess: () => selectedOrder.value = null
        });
    }
}

function submitProgressSnap() {
    if (!form.id || !progressSnapForm.image) return;
    
    progressSnapForm.post(route('studio.progress_snap', form.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            progressSnapForm.image = null;
            // The record is updated via inertia, so computed refs will handle the rest
            console.log('[Serana] Progress snap archived.');
        }
    });
}

function deleteProduct(id) {
	if (confirm('Move this piece to the archive (Unpublish)?')) {
		router.delete(route('admin.catalog.destroy_product', id), {
            preserveScroll: true
        });
	}
}

function restoreProduct(id) {
    if (confirm('Restore this piece to the shop collection (Publish)?')) {
        router.post(route('admin.catalog.restore_product', id), {}, {
            preserveScroll: true
        });
    }
}

function forceDeleteProduct(id) {
    if (confirm('PERMANENTLY PURGE this product and all its assets? This cannot be undone.')) {
        router.delete(route('admin.catalog.force_delete_product', id), {
            preserveScroll: true
        });
    }
}

function submitProduct() {
    if (isEditingProduct.value) {
        productForm.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('admin.catalog.update_product', productForm.id), {
            onSuccess: () => {
                showAddProductModal.value = false;
                isEditingProduct.value = false;
                productForm.id = null;
                productPreview.value = null;
                secondaryPreview1.value = null;
                secondaryPreview2.value = null;
                productForm.reset();
            }
        });
    } else {
        productForm.post(route('admin.catalog.store_product'), {
            onSuccess: () => {
                showAddProductModal.value = false;
                secondaryPreview1.value = null;
                secondaryPreview2.value = null;
                productForm.reset();
            }
        });
    }
}

function submitGallery() {
    if (isEditingGallery.value) {
        galleryForm.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('admin.gallery.update', galleryForm.id), {
            onSuccess: () => {
                showAddGalleryModal.value = false;
                isEditingGallery.value = false;
                galleryForm.id = null;
                galleryPreview.value = null;
                galleryForm.reset();
            }
        });
    } else {
        galleryForm.post(route('admin.gallery.store'), {
            onSuccess: () => {
                showAddGalleryModal.value = false;
                galleryForm.reset();
            }
        });
    }
}

function submitCategory() {
    if (isEditingCategory.value) {
        categoryForm.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('admin.catalog.update_category', categoryForm.id), {
            onSuccess: () => {
                showAddCategoryModal.value = false;
                isEditingCategory.value = false;
                categoryForm.reset();
            }
        });
    } else {
        categoryForm.post(route('admin.catalog.store_category'), {
            onSuccess: () => {
                showAddCategoryModal.value = false;
                categoryForm.reset();
            }
        });
    }
}

function editCategory(cat) {
    isEditingCategory.value = true;
    categoryForm.id = cat.id;
    categoryForm.name = cat.name;
    categoryForm.description = cat.description;
    showAddCategoryModal.value = true;
}

function deleteCategory(id) {
    if (confirm('Permanently remove this collection segment? All associated pieces must be reassigned first.')) {
        router.delete(route('admin.catalog.destroy_category', id), {
            preserveScroll: true
        });
    }
}

function updateCategoryBanner(category, file) {
    if (!file) return;
    
    // We use a manual router.post here to support file uploads with _method: PUT shortcut if needed
    // but our CatalogController@updateCategory is already set up to handle this
    router.post(route('admin.catalog.update_category', category.id), {
        _method: 'PUT',
        banner: file,
        name: category.name, // Keep existing name
        description: category.description // Keep existing description
    }, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            console.log(`[Serana] Banner updated for segment: ${category.name}`);
        }
    });
}

function deleteCategoryBanner(category) {
    if (confirm(`Are you sure you want to remove the banner for "${category.name}"? This segment will revert to the brand default.`)) {
        router.post(route('admin.catalog.update_category', category.id), {
            _method: 'PUT',
            remove_banner: true,
            name: category.name,
            description: category.description
        }, {
            preserveScroll: true,
            onSuccess: () => {
                console.log(`[Serana] Banner reset for segment: ${category.name}`);
            }
        });
    }
}

function deleteGallery(id) {
	if (confirm('Remove this showcase from the Circle (Unpublish)?')) {
	    router.delete(route('admin.gallery.destroy', id), {
            preserveScroll: true
        });
	}
}

function restoreGallery(id) {
    if (confirm('Restore this showcase to the Circle (Publish)?')) {
        router.post(route('admin.gallery.restore', id), {}, {
            preserveScroll: true
        });
    }
}

function forceDeleteGallery(id) {
    if (confirm('PERMANENTLY PURGE this showcase asset? This cannot be undone.')) {
        router.delete(route('admin.gallery.force_delete', id), {
            preserveScroll: true
        });
    }
}

const getObjectURL = (file) => {
    if (!file) return null;
    if (typeof file === 'string') return file;
    try {
        return window.URL.createObjectURL(file);
    } catch (e) {
        console.error('[Serana] Failed to create object URL:', e);
        return null;
    }
};

function getOrderThumbnails(order) {
	if (!order) return [];
	if (order.type === 'standard') {
		if (order.items_with_urls && order.items_with_urls.length > 0) {
			return order.items_with_urls.map(item => item.image_url).filter(Boolean);
		}
	} else {
		if (order.inspiration_urls && order.inspiration_urls.length > 0) {
			return order.inspiration_urls;
		}
	}
	return [];
}

const hoveredOrder = ref(null);

function getAllOrderImages(order) {
    if (!order) return [];
    let images = [];
    
    // Core references
    const thumbs = getOrderThumbnails(order);
    if (thumbs.length) images.push(...thumbs);
    
    // Progress snapshots
    if (order.progress_snaps && order.progress_snaps.length > 0) {
        images.push(...order.progress_snaps.map(s => s.image_url).filter(Boolean));
    }
    
    return [...new Set(images)]; // Distinct high-fidelity assets
}
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,300;0,400;0,700;1,300;1,400&family=Inter:wght@200;400;600;800&display=swap');

.serif-text { font-family: "Noto Serif", serif; }
.font-headline { font-family: 'Noto Serif', serif; }
.font-body { font-family: 'Inter', sans-serif; }

.bg-atelier-bg { background-color: #020617; }
.bg-atelier-surface { background-color: #0f172a; }
.text-atelier-primary { color: #3b82f6; }
.text-atelier-secondary { color: #94a3b8; }

.glass-panel {
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(24px);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.gradient-bg {
    background: radial-gradient(circle at top right, #0f172a, #020617);
}

.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
    vertical-align: middle;
}

.animate-in {
    animation: slideUpFade 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes slideUpFade {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.status-pulse {
    position: relative;
}

.status-pulse::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: inherit;
    border: 2px solid currentColor;
    opacity: 0;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(0.95); opacity: 0.5; }
    100% { transform: scale(1.5); opacity: 0; }
}

.luxury-shadow {
    box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
}
</style>

<template>
    <Head title="Serana Studio | Admin" />

    <div class="flex h-screen bg-[#020617] text-slate-200 font-body overflow-hidden selection:bg-blue-900 selection:text-white relative">
        
        <!-- SideNavBar -->
        <aside :class="[
            'h-screen w-64 fixed left-0 top-0 bg-slate-950 flex flex-col py-8 px-6 z-50 transition-transform duration-500 border-r border-white/5',
            menuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">
            <div class="mb-12">
                <h2 class="font-headline text-2xl text-white leading-tight italic">Studio</h2>
                <p class="font-body tracking-wider uppercase text-[10px] text-slate-500 mt-1">Admin Dashboard</p>
            </div>
            
            <nav class="flex-1 space-y-2">
                <button v-for="tab in [
                    { id: 'overview', label: 'Orders', icon: 'shopping_bag' },
                    { id: 'catalog', label: 'Inventory', icon: 'inventory_2' },
                    { id: 'gallery', label: 'Showcase', icon: 'auto_awesome_motion' },
                    { id: 'leads', label: 'Subscribers', icon: 'insights' },
                    { id: 'brand', label: 'Identity', icon: 'collections' },
                    { id: 'settings', label: 'Settings', icon: 'settings' }
                ]" :key="tab.id"
                @click="currentTab = tab.id; menuOpen = false"
                :class="[
                    'group flex items-center gap-3 px-4 py-3 w-full transition-all duration-300 rounded-lg',
                    currentTab === tab.id 
                        ? 'text-white font-bold bg-blue-600/10 border border-blue-500/20' 
                        : 'text-slate-500 hover:translate-x-1 hover:text-white'
                ]">
                    <span class="material-symbols-outlined text-[20px]" :class="currentTab === tab.id ? 'text-blue-400' : ''">{{ tab.icon }}</span>
                    <span class="font-body tracking-wider uppercase text-[10px]">{{ tab.label }}</span>
                </button>

                <a href="#" class="group flex items-center gap-3 px-4 py-3 text-slate-500 hover:translate-x-1 hover:text-white transition-all duration-200">
                    <span class="material-symbols-outlined text-[20px]">analytics</span>
                    <span class="font-body tracking-wider uppercase text-[10px]">Analytics</span>
                </a>
                <a href="#" class="group flex items-center gap-3 px-4 py-3 text-slate-500 hover:translate-x-1 hover:text-white transition-all duration-200">
                    <span class="material-symbols-outlined text-[20px]">event_note</span>
                    <span class="font-body tracking-wider uppercase text-[10px]">Schedule</span>
                </a>
            </nav>

            <div class="mt-auto space-y-6">
                <button @click="currentTab === 'catalog' ? showAddProductModal = true : (currentTab === 'gallery' ? showAddGalleryModal = true : null)" 
                        class="w-full py-4 bg-blue-600 text-white font-headline text-sm rounded-lg hover:bg-blue-500 transition-all active:scale-95 shadow-lg shadow-blue-900/20">
                    {{ currentTab === 'catalog' ? 'New Product' : (currentTab === 'gallery' ? 'Add Showcase' : 'Create New Line') }}
                </button>
                <div class="space-y-2">
                    <Link class="group flex items-center gap-3 px-4 py-2 text-slate-500 hover:text-blue-400 transition-colors" :href="route('home')">
                        <span class="material-symbols-outlined text-[18px]">help_outline</span>
                        <span class="font-body tracking-wider uppercase text-[10px]">Back to Shop</span>
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="group flex items-center gap-3 px-4 py-2 text-slate-500 hover:text-red-400 transition-colors w-full text-left">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        <span class="font-body tracking-wider uppercase text-[10px]">Sign Out</span>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="ml-0 lg:ml-64 flex-1 flex flex-col min-h-screen overflow-y-auto no-scrollbar gradient-bg">
            
            <!-- TopAppBar -->
            <header class="w-full sticky top-0 z-40 bg-slate-950/80 backdrop-blur-3xl flex justify-between items-center px-8 py-5 border-b border-white/5">
                <div class="flex items-center gap-8">
                    <button @click="menuOpen = !menuOpen" class="lg:hidden material-symbols-outlined text-white">menu</button>
                    <Link :href="route('home')" class="flex items-center gap-4 group">
                        <h1 class="text-2xl font-headline italic text-white group-hover:text-blue-400 transition-colors">Serana Closet</h1>
                    </Link>
                    <div class="hidden md:flex relative w-72">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-lg">search</span>
                        <input class="w-full pl-10 pr-4 py-2 bg-slate-900 border border-white/5 text-sm font-body rounded-full text-white placeholder-slate-600 focus:ring-1 focus:ring-blue-500 transition-all" placeholder="Search archive..." type="text"/>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="flex gap-4">
                        <button class="material-symbols-outlined text-slate-400 hover:text-white transition-colors">notifications</button>
                        <button @click="currentTab = 'settings'" class="material-symbols-outlined text-slate-400 hover:text-white transition-colors">settings</button>
                    </div>
                    <div class="h-10 w-10 rounded-full bg-slate-900 overflow-hidden border border-white/10 ring-2 ring-blue-500/20">
                        <img alt="Manager" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB4Jge4oeulbricIpFRaOi6sWjXn0__QQLEnVspMapERjhco2dLuiQHyXEvPu-HKvoSBk6XCsJrCn9Z1L3kINWNn8gli7Y-IwpvcE6iiYjZxFyQZxx0YRAl0gZaQ6k5G2G2RoOjPiRI2oMM3ZDP1CQXmRYM2nPsoVBSYiQ2Rr79Nb9P19zPy05SUVxv8U1xm1d5SphCUKCMTfzL4cSR49gE8r-rLfYVXcUiS0oWxg9IJOVE-sIby2IzVld81OR5HopATKu7hiDCjcc"/>
                    </div>
                </div>
            </header>

            <!-- Canvas -->
            <section class="flex-1 p-8 lg:p-12 animate-in">
                <!-- Editorial Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-12 gap-6">
                    <div class="max-w-2xl">
                        <h2 class="text-5xl font-light tracking-tight text-white mb-4">
                            {{ currentTab === 'overview' ? 'Orders' : (currentTab === 'catalog' ? 'Inventory' : (currentTab === 'gallery' ? 'Showcase' : 'Identity')) }}
                        </h2>
                        <p class="text-slate-400 font-body leading-relaxed max-w-lg">
                            {{ 
                                currentTab === 'overview' ? 'Manage customer orders and track production status in real-time.' :
                                (currentTab === 'catalog' ? 'Curate and manage the boutique inventory archive.' :
                                (currentTab === 'gallery' ? 'Architect the brand showcase and artisanal gallery.' :
                                'Synchronize brand assets and identity parameters.'))
                            }}
                        </p>
                    </div>
                    <button v-if="currentTab === 'catalog' || currentTab === 'gallery'" 
                            @click="currentTab === 'catalog' ? showAddProductModal = true : showAddGalleryModal = true"
                            class="flex items-center gap-2 px-8 py-4 bg-blue-600 text-white rounded-sm font-body font-bold tracking-widest uppercase text-xs hover:bg-blue-500 transition-all shadow-xl shadow-blue-900/20">
                        <span class="material-symbols-outlined text-base">add</span>
                        {{ currentTab === 'catalog' ? 'New Product' : 'New Photo' }}
                    </button>
                </div>
            <!-- Bento Filter Section & Stock Health (Mapped to Atelier) -->
            <div v-if="currentTab === 'overview'" class="grid grid-cols-12 gap-6 mb-12">
                <div class="col-span-full xl:col-span-8 glass-panel p-8 rounded-sm flex flex-col md:flex-row items-center justify-between gap-6 luxury-shadow">
                    <div class="flex flex-col md:flex-row gap-8 w-full md:w-auto">
                        <div class="flex flex-col gap-2">
                            <span class="text-[10px] uppercase tracking-widest text-slate-500 font-bold">Total Orders</span>
                            <div class="flex items-baseline gap-2">
                                <span class="font-headline text-3xl text-white">{{ stats.total_orders }}</span>
                                <span class="text-[9px] text-slate-500 uppercase font-bold tracking-tighter">Active Tasks</span>
                            </div>
                        </div>
                        <div class="hidden md:block w-px h-10 bg-white/5"></div>
                        <div class="flex flex-col gap-2">
                            <span class="text-[10px] uppercase tracking-widest text-slate-500 font-bold">In Production</span>
                            <div class="flex items-baseline gap-2">
                                <span class="font-headline text-3xl text-blue-400">{{ orders.filter(o => o.status !== 'completed').length }}</span>
                                <span class="text-[9px] text-slate-500 uppercase font-bold tracking-tighter">Live State</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showArchivedOrders = !showArchivedOrders" 
                                :class="[
                                    'px-5 py-3 rounded-sm text-[10px] font-bold uppercase tracking-widest transition-all border',
                                    showArchivedOrders 
                                        ? 'bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-900/30' 
                                        : 'bg-slate-800 text-slate-400 border-white/5 hover:border-blue-500/40 hover:text-white'
                                ]">
                            {{ showArchivedOrders ? 'Active Orders' : 'View Archive' }}
                        </button>
                        <button class="p-3 bg-slate-800 text-slate-400 border border-white/5 hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-xl">filter_list</span>
                        </button>
                        <button class="p-3 bg-slate-800 text-slate-400 border border-white/5 hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-xl">sort</span>
                        </button>
                    </div>
                </div>
                <div class="col-span-full xl:col-span-4 bg-blue-600/10 border border-blue-500/20 p-8 rounded-sm flex flex-col justify-between luxury-shadow group overflow-hidden relative">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-500/10 rounded-full blur-2xl"></div>
                    <span class="text-[10px] uppercase tracking-widest text-blue-400 font-bold relative z-10">Artisan Revenue</span>
                    <div class="flex justify-between items-baseline mb-4 relative z-10">
                        <p class="text-4xl font-headline text-white">{{ formatAmount(stats.total_revenue, $page.props) }}</p>
                        <span class="text-blue-500/60 text-xs font-body italic">Cumulative</span>
                    </div>
                    <div class="w-full bg-white/5 h-1.5 rounded-full overflow-hidden relative z-10">
                        <div class="bg-blue-500 h-full w-[85%] transition-all duration-1000 shadow-[0_0_10px_#3b82f6]"></div>
                    </div>
                </div>
            </div>

            <!-- Inventory Ledger (Mapped to Order Registry) -->
            <!-- Registry Tab: Order Document Flow -->
            <div v-if="currentTab === 'overview'">
                <!-- Desktop Tabular Registry -->
                <div class="hidden lg:block">
                    <div class="space-y-1 overflow-x-auto no-scrollbar pb-10">
                        <!-- Heading Row -->
                        <div class="grid grid-cols-12 min-w-[1000px] px-8 py-6 bg-slate-900/50 rounded-sm mb-4 border border-white/5">
                            <div class="col-span-4 text-[10px] uppercase tracking-widest font-bold text-slate-500">Order Info</div>
                            <div class="col-span-2 text-[10px] uppercase tracking-widest font-bold text-slate-500">Order ID</div>
                            <div class="col-span-2 text-[10px] uppercase tracking-widest font-bold text-slate-500">Production</div>
                            <div class="col-span-2 text-[10px] uppercase tracking-widest font-bold text-slate-500">Status</div>
                            <div class="col-span-2 text-[10px] uppercase tracking-widest font-bold text-slate-500 text-right">Price</div>
                        </div>
                        
                        <!-- Ledger Rows -->
                        <div v-for="order in filteredOrders" :key="order.id" 
                             @click="selectOrder(order)"
                             @mouseenter="hoveredOrder = order"
                             @mouseleave="hoveredOrder = null"
                             class="grid grid-cols-12 min-w-[1000px] px-8 py-7 bg-slate-900/40 border border-white/5 hover:bg-slate-800/60 transition-all items-center group cursor-pointer mb-3 rounded-sm luxury-shadow"
                             :class="form.id === order.id ? 'border-blue-500/40 bg-blue-600/5 ring-1 ring-blue-500/20' : ''">
                            
                            <div class="col-span-4 flex items-center gap-6">
                                <div class="h-24 w-20 bg-slate-800 rounded-sm overflow-hidden flex-shrink-0 relative group-hover:scale-105 transition-transform duration-700 border border-white/5">
                                    <img :src="getOrderThumbnails(order)[0] || '/images/hero_editorial.png'" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" />
                                </div>
                                <div class="space-y-1">
                                    <p class="font-headline text-xl text-white group-hover:text-blue-400 transition-colors">{{ order.full_name }}</p>
                                    <p class="text-xs text-slate-500 font-body uppercase tracking-widest">{{ order.garment_name || 'Bespoke Item' }}</p>
                                </div>
                            </div>

                            <div class="col-span-2 font-body text-sm tracking-tight text-slate-400 group-hover:text-slate-200">#{{ String(order.id).padStart(5, '0') }}</div>
                            
                            <div class="col-span-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-headline text-xs text-blue-400 uppercase tracking-widest">{{ order.status }}</span>
                                </div>
                                <div class="w-20 h-1 bg-white/5 mt-3 relative overflow-hidden rounded-full">
                                    <div class="bg-blue-500 h-full transition-all duration-1000 shadow-[0_0_8px_#3b82f6]" 
                                         :style="{ width: order.status === 'completed' ? '100%' : (order.status === 'sewing' ? '75%' : (order.status === 'designing' ? '40%' : '15%')) }"></div>
                                </div>
                            </div>

                            <div class="col-span-2">
                                <span :class="[
                                    'px-4 py-1.5 rounded-sm text-[10px] uppercase font-bold tracking-widest border',
                                    order.is_paid 
                                        ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' 
                                        : 'bg-red-500/10 text-red-400 border-red-500/20'
                                ]">
                                    {{ order.is_paid ? 'PAID' : 'UNPAID' }}
                                </span>
                            </div>

                            <div class="col-span-2 text-right font-headline text-xl text-white group-hover:text-blue-400 transition-colors">
                                {{ formatAmount(order.price_quoted || 0, page.props) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Card Registry -->
                <div class="lg:hidden space-y-6 pb-20 mt-6">
                    <div v-for="order in filteredOrders" :key="order.id" 
                        @click="selectOrder(order)"
                        class="glass-panel p-6 border border-white/5 active:bg-slate-800 transition-all rounded-sm flex gap-6 luxury-shadow shadow-slate-950/40"
                        :class="form.id === order.id ? 'border-blue-500/40 ring-1 ring-blue-500/20' : ''">
                        
                        <div class="w-20 h-24 bg-slate-800 border border-white/5 flex-shrink-0 overflow-hidden rounded-sm">
                            <img :src="getOrderThumbnails(order)[0] || '/images/hero_editorial.png'" class="w-full h-full object-cover grayscale">
                        </div>

                        <div class="flex-grow flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start">
                                    <p class="text-[9px] text-blue-400 tracking-widest font-bold uppercase">#{{ String(order.id).padStart(4, '0') }}</p>
                                    <span :class="`text-[7px] font-black tracking-widest uppercase px-2 py-1 rounded-sm border ${order.status === 'completed' ? 'bg-blue-600 text-white border-blue-500' : 'bg-slate-800 text-slate-400 border-white/5'}`">
                                        {{ order.status }}
                                    </span>
                                </div>
                                <p class="font-headline text-xl text-white mt-1">{{ order.full_name }}</p>
                                <p class="font-body text-xs text-slate-500 uppercase tracking-tighter">{{ order.garment_name || 'Bespoke' }}</p>
                            </div>
                            <div class="flex justify-between items-end mt-2">
                                <p class="text-[10px] text-blue-400 font-black uppercase tracking-widest">{{ formatAmount(order.price_quoted || 0, page.props) }}</p>
                                <p class="text-[7px] text-slate-500 tracking-tighter uppercase font-bold">{{ new Date(order.created_at).toLocaleDateString() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Tab -->
            <div v-if="currentTab === 'catalog'">
                <div class="grid grid-cols-12 gap-8 mb-12">
                    <div class="col-span-full md:col-span-8 glass-panel p-8 rounded-sm flex items-center justify-between gap-6 luxury-shadow">
                        <div class="flex gap-8">
                            <div class="flex flex-col gap-2">
                            <span class="text-[10px] uppercase tracking-widest text-slate-500 font-bold">Category Scope</span>
                                <select class="bg-transparent border-none p-0 font-headline text-2xl focus:ring-0 cursor-pointer text-white">
                                    <option class="bg-slate-950">Full Collection</option>
                                    <option v-for="cat in categories" :key="cat.id" class="bg-slate-950">{{ cat.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex gap-2">
                           <button @click="showArchivedProducts = !showArchivedProducts" 
                                   class="px-6 py-3 rounded-sm bg-slate-800 border border-white/5 text-slate-400 text-[10px] font-bold uppercase tracking-widest hover:text-white transition-all">
                               {{ showArchivedProducts ? 'Live Collection' : 'View Archive' }}
                           </button>
                        </div>
                    </div>
                    <div class="col-span-full md:col-span-4 bg-emerald-500/10 border border-emerald-500/20 p-8 rounded-sm flex flex-col justify-between luxury-shadow relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-20 h-20 bg-emerald-500/10 rounded-full blur-2xl"></div>
                        <span class="text-[10px] uppercase tracking-widest text-emerald-400 font-bold relative z-10">System Integrity</span>
                        <div class="flex justify-between items-baseline relative z-10">
                            <p class="text-4xl font-headline text-white">98%</p>
                            <span class="text-emerald-500/60 text-[10px] font-bold uppercase tracking-widest">In Stock</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div v-for="product in (showArchivedProducts ? archived_products : products)" :key="product.id"
                         class="grid grid-cols-12 px-8 py-7 bg-slate-900/40 border border-white/5 hover:bg-slate-800/60 transition-all items-center group mb-4 rounded-sm luxury-shadow"
                         :class="product.in_stock ? '' : 'border-red-500/20 bg-red-600/5'">
                        <div class="col-span-5 flex items-center gap-8">
                            <div class="h-28 w-24 bg-slate-800 rounded-sm overflow-hidden flex-shrink-0 relative group-hover:scale-105 transition-all duration-700 border border-white/5">
                                <img :src="product.image_url" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" />
                            </div>
                            <div class="space-y-1">
                                <p class="font-headline text-2xl text-white mb-1 group-hover:text-blue-400 transition-colors">{{ product.name }}</p>
                                <p class="text-[10px] text-slate-500 font-body uppercase tracking-[0.2em]">{{ product.category?.name || 'Artisan Series' }}</p>
                            </div>
                        </div>
                        <div class="col-span-2 font-body text-xs tracking-[0.3em] text-slate-500 uppercase">SNC-{{ String(product.id).padStart(4, '0') }}</div>
                        <div class="col-span-2">
                            <div class="flex items-center gap-2">
                                <span :class="['font-headline text-sm tracking-widest uppercase', product.in_stock ? 'text-emerald-400' : 'text-red-400']">
                                    {{ product.in_stock ? 'IN STOCK' : 'SOLD OUT' }}
                                </span>
                            </div>
                        </div>
                        <div class="col-span-3 text-right">
                            <div class="flex flex-col items-end gap-6">
                                <p class="font-headline text-3xl text-white">{{ formatAmount(product.price, page.props) }}</p>
                                <div v-if="!showArchivedProducts" class="flex gap-5">
                                    <button @click="editProduct(product)" class="material-symbols-outlined text-slate-500 hover:text-blue-400 text-2xl transition-all">edit_note</button>
                                    <button @click="deleteProduct(product.id)" class="material-symbols-outlined text-slate-500 hover:text-red-400 text-2xl transition-all">archive</button>
                                </div>
                                <div v-else class="flex gap-5">
                                    <button @click="restoreProduct(product.id)" class="material-symbols-outlined text-slate-500 hover:text-blue-400 text-2xl transition-all">restore_from_trash</button>
                                    <button @click="forceDeleteProduct(product.id)" class="material-symbols-outlined text-slate-500 hover:text-red-400 text-2xl transition-all">delete_forever</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Showcase Tab -->
            <div v-if="currentTab === 'gallery'">
                <div class="grid grid-cols-12 gap-8 mb-12">
                    <div class="col-span-full md:col-span-12 glass-panel p-8 rounded-sm flex items-center justify-between luxury-shadow border border-white/5">
                        <div class="flex gap-12">
                            <button @click="showArchivedGallery = false" 
                                    class="text-[10px] uppercase tracking-[0.3em] font-bold transition-all relative py-3"
                                    :class="!showArchivedGallery ? 'text-white' : 'text-slate-500'">
                                Active Showcase
                                <span v-if="!showArchivedGallery" class="absolute bottom-0 left-0 w-full h-[1px] bg-blue-500"></span>
                            </button>
                            <button @click="showArchivedGallery = true" 
                                    class="text-[10px] uppercase tracking-[0.3em] font-bold transition-all relative py-3"
                                    :class="showArchivedGallery ? 'text-white' : 'text-slate-500'">
                                Archive Repository
                                <span v-if="showArchivedGallery" class="absolute bottom-0 left-0 w-full h-[1px] bg-blue-500"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!showArchivedGallery" class="grid grid-cols-1 md:grid-cols-2 gap-12 animate-in pb-20">
                    <div v-for="item in gallery" :key="item.id" class="bg-slate-900/60 p-4 rounded-sm border border-white/5 luxury-shadow shadow-slate-950/40 group relative">
                        <div class="aspect-[16/11] bg-slate-800 rounded-sm overflow-hidden mb-8 relative border border-white/5">
                            <img :src="item.image_url" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-1000" />
                            <div class="absolute top-6 left-6">
                                <span class="bg-blue-600/90 text-white px-5 py-2 rounded-sm text-[10px] font-bold uppercase tracking-widest">{{ item.garment_name }}</span>
                            </div>
                        </div>
                        <div class="px-6 mb-6">
                            <h3 class="font-headline text-3xl text-white italic mb-4">{{ item.client_name }}</h3>
                            <p class="text-sm text-slate-400 font-body leading-relaxed">"{{ item.testimonial }}"</p>
                        </div>
                        <div class="flex justify-end gap-8 p-6 border-t border-white/5">
                            <button @click="editGallery(item)" class="material-symbols-outlined text-slate-500 hover:text-blue-400 transition-all text-2xl">edit</button>
                            <button @click="deleteGallery(item.id)" class="material-symbols-outlined text-slate-500 hover:text-red-400 transition-all text-2xl">archive</button>
                        </div>
                    </div>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-12 animate-in pb-20">
                    <div v-for="item in archived_gallery" :key="item.id" class="bg-[#f6f3ee] p-3 rounded-xl luxury-shadow group opacity-60 hover:opacity-100 transition-opacity">
                         <div class="aspect-[16/11] bg-[#e5e2dd] rounded-lg overflow-hidden mb-6 relative grayscale">
                            <img :src="item.image_url" class="w-full h-full object-cover opacity-50" />
                            <div class="absolute inset-0 flex items-center justify-center bg-black/10 backdrop-blur-sm">
                                <span class="text-[10px] text-white font-bold uppercase tracking-[0.3em]">Archived Item</span>
                            </div>
                        </div>
                        <div class="flex justify-end gap-6 p-4 border-t border-[#f0ede8]">
                            <button @click="restoreGallery(item.id)" class="material-symbols-outlined text-[#5f5e5e] hover:text-[#390908] transition-colors">unarchive</button>
                            <button @click="forceDeleteGallery(item.id)" class="material-symbols-outlined text-[#5f5e5e] hover:text-[#ba1a1a] transition-colors">delete_forever</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscribers Panel -->
            <div v-if="currentTab === 'leads'" class="space-y-4 animate-in pb-20">
                <div v-for="lead in leads" :key="lead.id" class="bg-slate-900/60 p-8 rounded-sm border border-white/5 flex items-center justify-between group hover:bg-slate-800/80 transition-all luxury-shadow shadow-slate-950/40">
                    <div class="flex items-center gap-10">
                        <div class="w-14 h-14 bg-slate-800 border border-white/5 rounded-sm flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-400 text-2xl">mail</span>
                        </div>
                        <div>
                            <p class="font-headline text-2xl text-white group-hover:text-blue-400 transition-colors">{{ lead.email }}</p>
                            <p class="text-[10px] text-slate-500 font-bold tracking-[0.3em] uppercase mt-2">Discovery Sequence: Ref #{{ String(lead.id).padStart(4, '0') }}</p>
                        </div>
                    </div>
                    <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                        <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Active Asset</span>
                    </div>
                </div>
            </div>

            <!-- Identity Tab -->
            <div v-if="currentTab === 'brand'" class="space-y-12 pb-20 animate-in">

                <!-- Row 1: Site Info (Text Fields) -->
                <div class="glass-panel p-10 rounded-sm border border-white/5 luxury-shadow shadow-slate-950/40">
                    <div class="flex items-center justify-between mb-10">
                        <label class="text-[10px] uppercase tracking-[0.3em] text-slate-500 font-bold">Theme Settings</label>
                        <button @click="saveSiteInfo" :disabled="siteInfoForm.processing"
                                class="px-10 py-3 bg-blue-600 text-white text-[10px] font-bold uppercase tracking-widest rounded-sm hover:bg-blue-500 transition-all disabled:opacity-30 shadow-lg shadow-blue-900/30">
                            {{ siteInfoForm.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                        <div class="space-y-3">
                            <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em]">Platform ID</label>
                            <input v-model="siteInfoForm.settings.site_name"
                                   class="w-full bg-slate-950/50 border border-white/5 px-4 py-4 text-xl font-headline text-white focus:border-blue-500/50 outline-none transition-all placeholder:text-slate-800"
                                   placeholder="e.g. Serana Closet" />
                        </div>
                        <div class="space-y-3">
                            <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em]">Platform Tagline</label>
                            <input v-model="siteInfoForm.settings.site_tagline"
                                   class="w-full bg-slate-950/50 border border-white/5 px-4 py-4 text-xl font-headline text-white focus:border-blue-500/50 outline-none transition-all placeholder:text-slate-800"
                                   placeholder="e.g. Bespoke Made With Love" />
                        </div>
                        <div class="space-y-3">
                            <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em]">WhatsApp Number</label>
                            <input v-model="siteInfoForm.settings.whatsapp_number"
                                   class="w-full bg-slate-950/50 border border-white/5 px-4 py-4 text-xl font-headline text-white focus:border-blue-500/50 outline-none transition-all placeholder:text-slate-800"
                                   placeholder="e.g. 254712345678" />
                        </div>
                    </div>
                </div>

                <!-- Row 2: Logo + Favicon -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                    <!-- Main Logo -->
                    <div class="bg-slate-900/60 p-10 rounded-sm border border-white/5 luxury-shadow shadow-slate-950/40">
                        <div class="mb-8">
                            <label class="text-[11px] uppercase tracking-[0.3em] text-slate-500 font-bold block">Primary Signature</label>
                            <p class="text-[10px] text-slate-600 font-body mt-2 italic">Standardized brand mark for global header and navigational clusters.</p>
                        </div>
                        <div class="h-64 bg-slate-950 border border-dashed border-white/10 rounded-sm relative group flex items-center justify-center overflow-hidden transition-all hover:border-blue-500/30">
                            <img v-if="$page.props.theme_settings.site_logo" :src="$page.props.theme_settings.site_logo" class="max-h-32 object-contain grayscale group-hover:grayscale-0 transition-all duration-1000 drop-shadow-[0_0_20px_rgba(255,255,255,0.1)]">
                            <h1 v-else class="font-headline text-2xl text-slate-800 italic uppercase tracking-widest">LogoRegistry_Empty</h1>
                            <div class="absolute inset-0 bg-slate-950/90 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center gap-6 backdrop-blur-md">
                                <input type="file" id="logo-up" @input="updateThemeAsset('site_logo', $event.target.files[0])" class="hidden">
                                <label for="logo-up" class="px-8 py-3 bg-blue-600 text-white text-[10px] font-bold uppercase tracking-widest cursor-pointer rounded-sm hover:bg-blue-500 transition-all shadow-xl shadow-blue-900/40">Upload Asset</label>
                                <button v-if="$page.props.theme_settings.site_logo" @click="deleteThemeAsset('site_logo')" class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.2em] hover:text-red-400 transition-colors">Purge Asset</button>
                            </div>
                        </div>
                    </div>

                    <!-- Site Icon / Favicon -->
                     <div class="bg-slate-900/60 p-10 rounded-sm border border-white/5 luxury-shadow shadow-slate-950/40">
                        <div class="mb-8">
                            <label class="text-[11px] uppercase tracking-[0.3em] text-slate-500 font-bold block">Favicon Registry</label>
                            <p class="text-[10px] text-slate-600 font-body mt-2 italic">Compact mark for browser metadata and discovery shortcuts.</p>
                        </div>
                        <div class="h-64 bg-slate-950 border border-dashed border-white/10 rounded-sm relative group flex items-center justify-center overflow-hidden transition-all hover:border-blue-500/30">
                            <img v-if="$page.props.theme_settings.site_favicon" :src="$page.props.theme_settings.site_favicon" class="w-20 h-20 object-contain grayscale group-hover:grayscale-0 transition-all duration-1000 shadow-xl shadow-blue-900/10">
                            <span v-else class="material-symbols-outlined text-7xl text-slate-800">fingerprint</span>
                            <div class="absolute inset-0 bg-slate-950/90 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center gap-6 backdrop-blur-md">
                                <input type="file" id="fav-up" @input="updateThemeAsset('site_favicon', $event.target.files[0])" class="hidden">
                                <label for="fav-up" class="px-8 py-3 bg-blue-600 text-white text-[10px] font-bold uppercase tracking-widest cursor-pointer rounded-sm hover:bg-blue-500 transition-all shadow-xl shadow-blue-900/40">Upload Asset</label>
                                <button v-if="$page.props.theme_settings.site_favicon" @click="deleteThemeAsset('site_favicon')" class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.2em] hover:text-red-400 transition-colors">Purge Asset</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Main Stage Hero -->
                <div class="bg-slate-900/60 p-10 rounded-sm border border-white/5 luxury-shadow shadow-slate-950/40">
                    <div class="mb-8">
                        <label class="text-[11px] uppercase tracking-[0.3em] text-slate-500 font-bold block">Environmental Backdrop (Main Stage)</label>
                        <p class="text-[10px] text-slate-600 font-body mt-2 italic">The primary opening atmosphere for the Serana storefront. High-fidelity cinematic imagery required.</p>
                    </div>
                    <div class="aspect-[21/7] bg-slate-950 border border-dashed border-white/10 rounded-sm relative group overflow-hidden flex items-center justify-center transition-all hover:border-blue-500/30">
                        <img v-if="$page.props.theme_settings.hero_bg" :src="$page.props.theme_settings.hero_bg" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-[3000ms]">
                        <span v-else class="material-symbols-outlined text-7xl text-slate-800">panorama</span>
                        <div class="absolute inset-0 bg-slate-950/90 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center gap-6 backdrop-blur-md">
                            <input id="hero-bg-up" type="file" @input="updateThemeAsset('hero_bg', $event.target.files[0])" class="hidden">
                            <label for="hero-bg-up" class="px-10 py-3 bg-blue-600 text-white text-[10px] font-bold uppercase tracking-widest cursor-pointer rounded-sm shadow-xl shadow-blue-900/40">Upload Environmental Asset</label>
                            <button v-if="$page.props.theme_settings.hero_bg" @click="deleteThemeAsset('hero_bg')" class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.2em] hover:text-red-400 transition-colors">Purge Asset</button>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Page Banners (Category Backgrounds) -->
                <div class="bg-slate-900/60 p-10 rounded-sm border border-white/5 luxury-shadow shadow-slate-950/40">
                    <div class="mb-10">
                        <label class="text-[11px] uppercase tracking-[0.3em] text-slate-500 font-bold block">Discovery Banners (Collection Scopes)</label>
                        <p class="text-[10px] text-slate-600 font-body mt-2 italic">Contextual atmospheres displayed at the selection threshold of individual collections.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
                        <div v-for="cat in categories" :key="cat.id" class="space-y-4">
                            <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em]">{{ cat.name }} Scope</label>
                            <div class="aspect-[3/4] bg-slate-950 border border-white/10 rounded-sm relative group overflow-hidden flex items-center justify-center transition-all hover:border-blue-500/30">
                                <img v-if="cat.banner_url" :src="cat.banner_url" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-[3000ms]">
                                <span v-else class="material-symbols-outlined text-5xl text-slate-800">image</span>
                                <div class="absolute inset-0 bg-slate-950/90 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center gap-4 text-center backdrop-blur-md">
                                    <input :id="`banner-${cat.id}`" type="file" @input="updateCategoryBanner(cat, $event.target.files[0])" class="hidden">
                                    <label :for="`banner-${cat.id}`" class="px-6 py-2 bg-blue-600 text-white text-[10px] font-bold uppercase tracking-widest cursor-pointer rounded-sm shadow-xl shadow-blue-900/40">Update Asset</label>
                                    <button v-if="cat.banner_url" @click="deleteCategoryBanner(cat)" 
                                            class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.2em] hover:text-red-400 transition-colors">
                                        Purge
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 5: Editorial Curation & Floating Details -->
                <div class="glass-panel p-10 rounded-sm border border-white/5 luxury-shadow shadow-slate-950/40">
                    <div class="mb-10">
                        <label class="text-[11px] uppercase tracking-[0.3em] text-slate-500 font-bold block">Editorial Narrative Suite</label>
                        <p class="text-[10px] text-slate-600 font-body mt-2 italic">Manage the "Brand Story" focal points and the asymmetrical floating details that define the high-fidelity Home experience.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
                        <!-- Slot 0: Hero Detail Shot (Floating) -->
                        <div class="space-y-6">
                            <div class="aspect-[4/5] bg-slate-950 border border-white/5 rounded-sm relative group overflow-hidden flex items-center justify-center luxury-shadow shadow-slate-950/60 transition-all hover:border-blue-500/30">
                                <img v-if="$page.props.theme_settings.hero_detail_bg" :src="$page.props.theme_settings.hero_detail_bg" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-1000 shadow-[0_0_40px_rgba(30,41,59,0.5)]">
                                <div v-else class="text-center opacity-10">
                                    <span class="material-symbols-outlined text-7xl text-slate-500">texture</span>
                                </div>
                                <div class="absolute inset-0 bg-slate-950/90 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center gap-4 backdrop-blur-md">
                                    <input id="hero-detail-up" type="file" @input="updateThemeAsset('hero_detail_bg', $event.target.files[0])" class="hidden">
                                    <label for="hero-detail-up" class="px-6 py-2 bg-blue-600 text-white text-[9px] font-bold uppercase tracking-widest cursor-pointer rounded-sm shadow-xl shadow-blue-900/40">Upload Detail</label>
                                    <button v-if="$page.props.theme_settings.hero_detail_bg" @click="deleteThemeAsset('hero_detail_bg')" 
                                            class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.2em] hover:text-red-400 transition-colors">
                                        Purge Asset
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em]">Floating Focal #00</label>
                                <p class="text-[9px] text-blue-400/60 font-bold uppercase tracking-[0.3em]">Asymmetrical Detail Asset</p>
                            </div>
                        </div>

                        <!-- Slots 1-3: Focal Points -->
                        <div v-for="i in [1, 2, 3]" :key="i" class="space-y-6">
                            <div class="aspect-[4/5] bg-slate-950 border border-white/5 rounded-sm relative group overflow-hidden flex items-center justify-center luxury-shadow transition-all hover:border-blue-500/30">
                                <img v-if="$page.props.theme_settings[`focal_${i}_bg`]" :src="$page.props.theme_settings[`focal_${i}_bg`]" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-1000">
                                <div v-else class="text-center opacity-10">
                                    <span class="material-symbols-outlined text-7xl text-slate-500">photo_filter</span>
                                </div>
                                <div class="absolute inset-0 bg-slate-950/90 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center gap-4 backdrop-blur-md">
                                    <input :id="`focal-up-${i}`" type="file" @input="updateThemeAsset(`focal_${i}_bg`, $event.target.files[0])" class="hidden">
                                    <label :for="`focal-up-${i}`" class="px-6 py-2 bg-blue-600 text-white text-[9px] font-bold uppercase tracking-widest cursor-pointer rounded-sm shadow-xl shadow-blue-900/40">Replace Media</label>
                                    <button v-if="$page.props.theme_settings[`focal_${i}_bg`]" @click="deleteThemeAsset(`focal_${i}_bg`)" 
                                            class="text-slate-500 text-[10px] font-bold uppercase tracking-[0.2em] hover:text-red-400 transition-colors">
                                        Purge Asset
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.2em]">Focal Narrative #0{{ i }}</label>
                                <div class="flex gap-4 items-center bg-slate-950/50 border border-white/5 p-1 rounded-sm px-4">
                                    <input v-model="siteInfoForm.settings[`focal_${i}_label`]" 
                                           class="flex-grow bg-transparent py-3 text-sm font-headline text-white focus:ring-0 outline-none placeholder:text-slate-800"
                                           :placeholder="`Editorial Slot ${i}...`" />
                                    <button @click="updateThemeAsset(`focal_${i}_label`, siteInfoForm.settings[`focal_${i}_label`])" 
                                            class="material-symbols-outlined text-blue-500 hover:text-white transition-all text-xl">commit</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 6: Collections Registry (Categories) -->
                <div class="bg-slate-900/60 p-10 rounded-sm border border-white/5 luxury-shadow shadow-slate-950/40">
                    <div class="flex items-center justify-between mb-10">
                        <div>
                            <label class="text-[11px] uppercase tracking-[0.3em] text-slate-500 font-bold">Scope Segments & Scaffolding</label>
                            <p class="text-[10px] text-slate-600 font-body mt-2 italic">Manage the brand's core technical segments and shop collection scaffolding.</p>
                        </div>
                        <button @click="isEditingCategory = false; categoryForm.reset(); showAddCategoryModal = true"
                                class="px-8 py-3 bg-blue-600 text-white text-[10px] font-bold uppercase tracking-widest rounded-sm hover:bg-blue-500 transition-all shadow-xl shadow-blue-900/40">
                            New Segment
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <div v-for="cat in categories" :key="cat.id" 
                             class="p-6 bg-slate-950 border border-white/5 rounded-sm flex items-center justify-between group hover:border-blue-500/30 transition-all shadow-inner">
                            <div>
                                <h4 class="font-headline text-2xl text-white group-hover:text-blue-400 transition-colors">{{ cat.name }}</h4>
                                <p class="text-[9px] text-slate-500 uppercase tracking-[0.3em] font-bold mt-2">Ref: SEC-{{ String(cat.id).padStart(3, '0') }}</p>
                            </div>
                            <div class="flex gap-6 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="editCategory(cat)" class="material-symbols-outlined text-slate-500 hover:text-blue-400 text-2xl transition-all">edit_note</button>
                                <button @click="deleteCategory(cat.id)" class="material-symbols-outlined text-slate-500 hover:text-red-400 text-2xl transition-all">delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div v-if="currentTab === 'settings'" class="max-w-4xl space-y-12 animate-in pb-20">
                <div class="glass-panel p-12 rounded-sm border border-white/5 luxury-shadow shadow-slate-950/60">
                    <h3 class="font-headline text-4xl text-white italic mb-6">Archive Parameters</h3>
                    <p class="text-sm text-slate-400 font-body leading-relaxed mb-12 max-w-xl">Configure the global technische parameters of the Serana digital atelier, including precision and administrative access protocols.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                        <div class="p-10 bg-blue-600 rounded-sm flex flex-col justify-between group h-56 shadow-2xl shadow-blue-900/30">
                            <p class="text-[11px] text-blue-200 uppercase font-bold tracking-[0.2em]">Atelier Precision</p>
                            <div class="flex items-center justify-between">
                                <span class="font-headline text-5xl text-white italic">± 0.2 <span class="text-xs uppercase not-italic opacity-40">cm</span></span>
                                <button class="w-12 h-12 rounded-sm bg-white/10 text-white material-symbols-outlined hover:bg-white/20 transition-all shadow-inner">tune</button>
                            </div>
                        </div>
                        <div class="p-10 bg-slate-900 border border-white/5 rounded-sm flex flex-col justify-between h-56 group shadow-inner">
                            <p class="text-[11px] text-slate-500 uppercase font-bold tracking-[0.2em]">Administrative Gateway</p>
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-[0.3em]">Protocol_Staged</span>
                                <div class="w-14 h-7 bg-slate-950 rounded-full p-1.5 cursor-not-allowed border border-white/10">
                                    <div class="w-4 h-4 bg-slate-700 rounded-full shadow-sm"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </section>
        </main>

        <!-- Command Detail Side Panel: The Document Registry -->
        <aside id="order-sheet-mobile" 
               :class="[
                 'transition-all duration-700 fixed lg:relative z-[160] lg:z-40 lg:flex-shrink-0 bg-slate-950 border-white/5',
                 'w-full lg:w-[620px] overflow-y-auto lg:h-screen lg:rounded-l-sm luxury-shadow shadow-2xl',
                 'inset-x-0 bottom-0 h-[92vh] lg:h-screen lg:inset-auto translate-y-0',
                 form.id ? 'flex flex-col p-10 lg:p-16 glass-panel' : 'hidden'
               ]">
            <header class="mb-12 flex-shrink-0 animate-in">
                <div class="flex items-center justify-between mb-12">
                    <div class="flex items-center gap-6">
                        <div class="w-12 h-12 rounded-sm bg-blue-600 flex items-center justify-center text-white material-symbols-outlined shadow-lg shadow-blue-900/30">
                            {{ selectedOrder?.deleted_at ? 'archive' : 'description' }}
                        </div>
                        <div>
                            <h1 class="font-headline text-3xl text-white italic leading-none">
                                {{ selectedOrder?.deleted_at ? 'Archived Order' : 'Order Info' }}
                            </h1>
                            <p class="text-[10px] text-blue-400 tracking-[0.4em] uppercase font-bold mt-3">ORDER_ID #{{ String(form.id).padStart(5, '0') }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button v-if="selectedOrder?.deleted_at" @click="restoreOrder(selectedOrder.id)" 
                                class="w-10 h-10 rounded-full border border-green-100 bg-green-50 text-green-700 hover:bg-green-600 hover:text-white material-symbols-outlined transition-all" title="Restore order">restore</button>
                        <button v-if="selectedOrder?.deleted_at" @click="forceDeleteOrder(selectedOrder.id)" 
                                class="w-10 h-10 rounded-full border border-red-100 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white material-symbols-outlined transition-all" title="Purge permanently">delete_forever</button>
                        <button v-if="!selectedOrder?.deleted_at" @click="deleteOrder(selectedOrder.id)" 
                                class="w-10 h-10 rounded-full border border-red-100 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white material-symbols-outlined transition-all" title="Archive order">archive</button>
                        <button @click="form.id = null" class="w-10 h-10 rounded-full border border-[#f0ede8] hover:bg-[#f0ede8] material-symbols-outlined text-[#5f5e5e] transition-all">close</button>
                    </div>
                </div>
                
                <div class="flex items-center justify-between py-8 border-y border-white/5">
                    <div class="flex items-center gap-4">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500 status-pulse shadow-[0_0_10px_#3b82f6]"></span>
                        <p class="text-[10px] text-white font-bold uppercase tracking-widest">Active Sequence</p>
                    </div>
                    <button @click="copyOrderBrief" class="flex items-center gap-4 px-6 py-3 bg-slate-900 border border-white/5 rounded-sm group hover:bg-blue-600 transition-all duration-500">
                        <span class="text-[10px] text-slate-400 group-hover:text-white tracking-widest font-bold uppercase">{{ showBriefNotification ? 'COPIED' : 'COPY BRIEF' }}</span>
                        <span class="material-symbols-outlined text-lg text-slate-500 group-hover:text-white">file_copy</span>
                    </button>
                </div>
            </header>

            <div class="mb-16 space-y-12 animate-in flex-grow">
                <!-- Visual Asset Display -->
                <div class="aspect-[16/10] bg-slate-900 rounded-sm overflow-hidden luxury-shadow relative group border border-white/5">
                    <img v-if="getOrderThumbnails(selectedOrder).length" 
                         :src="getOrderThumbnails(selectedOrder)[0]" 
                         class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-[3000ms]" />
                    <div v-else class="w-full h-full flex flex-col items-center justify-center text-slate-600">
                        <span class="material-symbols-outlined text-6xl opacity-20 mb-3">image_not_supported</span>
                        <p class="text-[10px] tracking-[0.4em] uppercase font-bold">No Image Available</p>
                    </div>
                </div>

                <!-- Anatomical Blueprint -->
                <div class="p-8 bg-slate-900/60 border border-white/5 rounded-sm">
                    <p class="text-[10px] text-blue-400 tracking-[0.4em] uppercase font-bold mb-10 flex items-center gap-4">
                        <span class="w-8 h-[1px] bg-blue-500/40"></span>
                        Product Specifications
                    </p>
                    <div class="grid grid-cols-2 gap-10">
                        <div v-for="m in [['BUST', selectedOrder?.bust_cm], ['WAIST', selectedOrder?.waist_cm], ['HIPS', selectedOrder?.hips_cm], ['FABRIC', selectedOrder?.fabric_preference || 'STND']]" :key="m[0]" class="space-y-2">
                            <label class="text-[10px] text-slate-500 tracking-widest font-bold uppercase">{{ m[0] }}</label>
                            <p class="font-headline text-3xl text-white">{{ m[1] }}{{ typeof m[1] === 'number' ? ' CM' : '' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Production Traceability -->
                <div class="p-8 glass-panel border border-white/5 rounded-sm space-y-10 luxury-shadow">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] text-blue-400 tracking-[0.4em] uppercase font-bold flex items-center gap-4">
                            <span class="w-8 h-[1px] bg-blue-500/40"></span>
                            Progress Photos
                        </p>
                        <p v-if="progressSnapForm.processing" class="text-[10px] text-blue-400 font-bold animate-pulse uppercase tracking-[0.2em]">Saving...</p>
                    </div>

                    <div class="grid grid-cols-4 gap-4">
                        <div v-for="snap in selectedOrder?.progress_snaps" :key="snap.id" 
                             class="aspect-square bg-slate-900 rounded-sm shadow-inner overflow-hidden relative group border border-white/5">
                            <img :src="snap.image_url" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700">
                        </div>
                        <div class="aspect-square bg-slate-800 border border-dashed border-white/20 rounded-sm flex items-center justify-center relative group hover:border-blue-500/50 transition-all">
                             <input type="file" id="snap-up" @input="progressSnapForm.image = $event.target.files[0]; submitProgressSnap()" class="hidden">
                             <label for="snap-up" class="absolute inset-0 cursor-pointer flex items-center justify-center text-slate-600 group-hover:text-blue-400 transition-colors">
                                 <span class="material-symbols-outlined text-3xl">{{ progressSnapForm.processing ? 'sync' : 'add_a_photo' }}</span>
                             </label>
                             <img v-if="progressSnapForm.image" :src="getObjectURL(progressSnapForm.image)" class="absolute inset-0 w-full h-full object-cover opacity-50">
                        </div>
                    </div>
                </div>

                <!-- Technical Notes -->
                <div class="space-y-10">
                    <div class="space-y-4">
                        <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Artisan Directives</label>
                        <textarea v-model="form.internal_notes" rows="5" class="w-full bg-slate-900 border border-white/5 rounded-sm p-6 text-sm font-body text-white focus:border-blue-500/50 focus:ring-0 outline-none transition-all placeholder:text-slate-700" placeholder="Matrix construction details..."></textarea>
                    </div>
                    <div class="space-y-4">
                        <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Sequence Status</label>
                        <div class="relative bg-slate-900 border border-white/5 rounded-sm p-1">
                             <select v-model="form.status" class="w-full bg-transparent p-5 text-[11px] tracking-[0.3em] font-bold uppercase text-blue-400 focus:text-white outline-none appearance-none cursor-pointer">
                                <option v-for="s in statuses" :key="s" :value="s" class="bg-slate-950">{{ s }}</option>
                             </select>
                             <span class="absolute right-6 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-600 pointer-events-none">expand_more</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-auto pt-10 border-t border-white/5 animate-in">
                <button @click="updateOrder" :disabled="form.processing"
                        class="w-full py-6 bg-blue-600 text-white rounded-sm text-[11px] font-bold uppercase tracking-[0.4em] hover:bg-blue-500 transition-all disabled:opacity-30 shadow-xl shadow-blue-900/20">
                    {{ form.processing ? 'SAVING...' : 'SAVE CHANGES' }}
                </button>
            </div>
        </aside>

        <!-- Product Modal: The Boutique Curator -->
        <div v-if="showAddProductModal" class="fixed inset-0 z-[300] flex items-center justify-center p-6 backdrop-blur-3xl bg-slate-950/40">
            <div @click="showAddProductModal = false" class="absolute inset-0"></div>
            <div class="relative w-full max-w-6xl bg-slate-950 border border-white/10 rounded-sm shadow-[0_0_100px_rgba(30,41,59,0.3)] flex flex-col md:flex-row overflow-hidden animate-in">
                <div class="flex-1 p-10 lg:p-16 glass-panel">
                    <h2 class="font-headline text-5xl text-white italic mb-16">{{ isEditingProduct ? 'Edit Product' : 'Add New Product' }}</h2>
                    <div class="grid grid-cols-2 gap-10">
                        <div class="col-span-2 space-y-3">
                             <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Product Name</label>
                             <input v-model="productForm.name" class="w-full bg-slate-900 border border-white/5 py-5 px-6 text-2xl font-headline text-white focus:border-blue-500/50 outline-none transition-all" />
                        </div>
                        <div class="space-y-3">
                             <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Valuation (KES)</label>
                             <input v-model="productForm.price" type="number" class="w-full bg-slate-900 border border-white/5 py-5 px-6 text-2xl font-headline text-white focus:border-blue-500/50 outline-none transition-all" />
                        </div>
                        <div class="space-y-3">
                             <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Category</label>
                             <select v-model="productForm.category_id" class="w-full bg-slate-900 border border-white/5 py-5 px-6 text-[11px] font-bold uppercase tracking-[0.2em] text-blue-400 focus:text-white outline-none cursor-pointer">
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id" class="bg-slate-950 text-white">{{ cat.name }}</option>
                             </select>
                        </div>
                        <div class="col-span-2 space-y-4">
                             <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Curation Brief</label>
                             <textarea v-model="productForm.description" rows="3" class="w-full bg-slate-900 border border-white/5 rounded-sm py-6 px-8 text-sm font-body text-slate-400 focus:border-blue-500/50 transition-all placeholder:text-slate-800"></textarea>
                        </div>

                        <!-- Technical Specs -->
                        <div class="col-span-2 mt-6 pt-10 border-t border-white/10">
                            <p class="text-[11px] text-blue-400 tracking-[0.4em] uppercase font-bold mb-10">Technical Specifications Matrix</p>
                            <div class="grid grid-cols-2 gap-10">
                                <div class="space-y-3">
                                     <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Batch Designation</label>
                                     <input v-model="productForm.specifications.batch_name" placeholder="e.g. Active Batch #04" class="w-full bg-slate-900 border border-white/5 py-3 px-4 text-sm font-headline text-white focus:border-blue-500/50 outline-none" />
                                </div>
                                <div class="space-y-3">
                                     <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Fibre Density</label>
                                     <input v-model="productForm.specifications.fabric_weight" placeholder="e.g. 450GSM Cotton" class="w-full bg-slate-900 border border-white/5 py-3 px-4 text-sm font-headline text-white focus:border-blue-500/50 outline-none" />
                                </div>
                                <div class="space-y-3">
                                     <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Mass Index (0-100)</label>
                                     <input v-model="productForm.specifications.weight_index" type="number" class="w-full bg-slate-900 border border-white/5 py-3 px-4 text-sm font-headline text-white focus:border-blue-500/50 outline-none" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-8 mt-10">
                                <div v-for="i in 4" :key="i" class="space-y-2">
                                    <label class="text-[9px] text-slate-600 font-bold uppercase tracking-[0.2em]">Metric #0{{ i }}</label>
                                    <input v-model="productForm.specifications.tech_specs[i-1]" placeholder="Technical data literal..." class="w-full bg-transparent border-b border-white/10 py-2 text-xs font-body text-slate-400 focus:border-blue-500/80 outline-none" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <button @click="submitProduct" :disabled="productForm.processing" class="w-full py-7 mt-16 bg-blue-600 text-white rounded-sm text-[11px] font-bold uppercase tracking-[0.5em] hover:bg-blue-500 transition-all shadow-2xl shadow-blue-900/40">
                        {{ productForm.processing ? 'SAVING...' : 'SAVE PRODUCT' }}
                    </button>
                </div>
                <div class="w-full md:w-5/12 bg-slate-900 border-l border-white/10 p-10 lg:p-16 flex flex-col justify-center space-y-10">
                    <label class="text-[11px] uppercase tracking-[0.4em] text-slate-500 font-bold">Media Repository</label>
                    
                    <!-- Main Product Image -->
                    <div class="aspect-[4/3] bg-slate-950 border border-dashed border-white/10 rounded-sm relative group flex items-center justify-center overflow-hidden transition-all hover:border-blue-500/30">
                        <input type="file" id="product-img" @input="productForm.image = $event.target.files[0]; productPreview = getObjectURL($event.target.files[0])" class="hidden" />
                        <label for="product-img" class="absolute inset-0 cursor-pointer flex flex-col items-center justify-center z-10 text-center p-12 group transition-all">
                            <span class="material-symbols-outlined text-6xl text-slate-800 group-hover:text-blue-500 transition-colors mb-4">linked_camera</span>
                            <p class="text-[10px] text-slate-600 font-bold uppercase tracking-[0.2em] group-hover:text-slate-400">Primary Signature Asset</p>
                        </label>
                        <img v-if="productForm.image" :src="getObjectURL(productForm.image)" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-[2000ms]" />
                        <img v-else-if="productPreview" :src="productPreview" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-[2000ms]" />
                    </div>

                    <!-- Secondary Curations -->
                    <div class="grid grid-cols-2 gap-6">
                        <div class="aspect-square bg-slate-950 border border-dashed border-white/10 rounded-sm relative group flex items-center justify-center overflow-hidden transition-all hover:border-blue-500/30">
                            <input type="file" id="sec-img-1" @input="productForm.secondary_image_1 = $event.target.files[0]; secondaryPreview1 = getObjectURL($event.target.files[0])" class="hidden" />
                            <label for="sec-img-1" class="absolute inset-0 cursor-pointer flex flex-col items-center justify-center z-10 text-center group">
                                <span class="material-symbols-outlined text-3xl text-slate-800 group-hover:text-blue-500 transition-colors">add_photo_alternate</span>
                                <p class="text-[8px] text-slate-600 font-bold uppercase tracking-widest mt-2">Matrix_A</p>
                            </label>
                            <img v-if="productForm.secondary_image_1" :src="getObjectURL(productForm.secondary_image_1)" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all" />
                            <img v-else-if="secondaryPreview1" :src="secondaryPreview1" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all" />
                        </div>
                        <div class="aspect-square bg-slate-950 border border-dashed border-white/10 rounded-sm relative group flex items-center justify-center overflow-hidden transition-all hover:border-blue-500/30">
                            <input type="file" id="sec-img-2" @input="productForm.secondary_image_2 = $event.target.files[0]; secondaryPreview2 = getObjectURL($event.target.files[0])" class="hidden" />
                            <label for="sec-img-2" class="absolute inset-0 cursor-pointer flex flex-col items-center justify-center z-10 text-center group">
                                <span class="material-symbols-outlined text-3xl text-slate-800 group-hover:text-blue-500 transition-colors">add_photo_alternate</span>
                                <p class="text-[8px] text-slate-600 font-bold uppercase tracking-widest mt-2">Matrix_B</p>
                            </label>
                            <img v-if="productForm.secondary_image_2" :src="getObjectURL(productForm.secondary_image_2)" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all" />
                            <img v-else-if="secondaryPreview2" :src="secondaryPreview2" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gallery Modal: Showcase Registry -->
        <div v-if="showAddGalleryModal" class="fixed inset-0 z-[300] flex items-center justify-center p-6 backdrop-blur-3xl bg-slate-950/40">
            <div @click="showAddGalleryModal = false" class="absolute inset-0"></div>
            <div class="relative w-full max-w-6xl bg-slate-950 border border-white/10 rounded-sm shadow-[0_0_100px_rgba(30,41,59,0.3)] flex flex-col md:flex-row overflow-hidden animate-in">
                <div class="flex-1 p-10 lg:p-16 glass-panel">
                    <h2 class="font-headline text-5xl text-white italic mb-16">{{ isEditingGallery ? 'Edit_Showcase' : 'New_Showcase_Entry' }}</h2>
                    <div class="grid grid-cols-2 gap-10">
                        <div class="space-y-4">
                             <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Client ID</label>
                             <input v-model="galleryForm.client_name" class="w-full bg-slate-900 border border-white/5 py-5 px-6 text-2xl font-headline text-white outline-none focus:border-blue-500/50" />
                        </div>
                        <div class="space-y-4">
                             <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Garment Designation</label>
                             <input v-model="galleryForm.garment_name" class="w-full bg-slate-900 border border-white/5 py-5 px-6 text-2xl font-headline text-white outline-none focus:border-blue-500/50" />
                        </div>
                        <div class="col-span-2 space-y-4">
                             <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Technical Testimonial</label>
                             <textarea v-model="galleryForm.testimonial" rows="3" class="w-full bg-slate-900 border border-white/5 rounded-sm py-6 px-8 text-sm font-body text-slate-400 outline-none focus:border-blue-500/50"></textarea>
                        </div>
                    </div>
                    <button @click="submitGallery" :disabled="galleryForm.processing" class="w-full py-7 mt-16 bg-blue-600 text-white rounded-sm text-[11px] font-bold uppercase tracking-[0.5em] hover:bg-blue-500 transition-all shadow-2xl shadow-blue-900/40">
                        {{ galleryForm.processing ? 'ARCHIVING_ASSET...' : 'SECURE_SHOWCASE_ENTRY' }}
                    </button>
                </div>
                <div class="w-full md:w-5/12 bg-slate-900 border-l border-white/10 p-10 flex flex-col justify-center">
                    <div class="aspect-square bg-slate-950 border border-dashed border-white/10 rounded-sm relative group flex items-center justify-center overflow-hidden shadow-inner transition-all hover:border-blue-500/30">
                        <input type="file" id="gallery-img" @input="galleryForm.image = $event.target.files[0]" class="hidden" />
                        <label for="gallery-img" class="absolute inset-0 cursor-pointer flex flex-col items-center justify-center z-10 text-center p-12 group">
                            <span class="material-symbols-outlined text-6xl text-slate-800 group-hover:text-blue-500 transition-colors mb-6">linked_camera</span>
                            <p class="text-[10px] text-slate-600 font-bold uppercase tracking-widest">Upload Master Asset</p>
                        </label>
                        <img v-if="galleryForm.image" :src="getObjectURL(galleryForm.image)" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-[2000ms]" />
                        <img v-else-if="galleryPreview" :src="galleryPreview" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-[2000ms]" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Modal: Collection Management -->
        <div v-if="showAddCategoryModal" class="fixed inset-0 z-[300] flex items-center justify-center p-6 backdrop-blur-3xl bg-slate-950/40">
            <div @click="showAddCategoryModal = false" class="absolute inset-0"></div>
            <div class="relative w-full max-w-2xl bg-slate-950 border border-white/10 rounded-sm shadow-2xl overflow-hidden animate-in">
                <div class="p-10 lg:p-16 glass-panel">
                    <h2 class="font-headline text-5xl text-white italic mb-16">{{ isEditingCategory ? 'Update_Collection' : 'New_Collection_Scope' }}</h2>
                    <div class="space-y-12">
                        <div class="space-y-4">
                             <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Segment Title</label>
                             <input v-model="categoryForm.name" class="w-full bg-slate-900 border border-white/5 py-5 px-6 text-2xl font-headline text-white focus:border-blue-500/50 outline-none transition-all placeholder:text-slate-800" placeholder="e.g. Corporate Wear" />
                        </div>
                        <div class="space-y-4">
                             <label class="text-[10px] text-slate-500 font-bold uppercase tracking-[0.3em]">Scope Narrative</label>
                             <textarea v-model="categoryForm.description" rows="3" class="w-full bg-slate-900 border border-white/5 rounded-sm py-6 px-8 text-sm font-body text-slate-400 focus:border-blue-500/50 outline-none transition-all placeholder:text-slate-800" placeholder="Briefly describe this collection's aesthetic..."></textarea>
                        </div>
                    </div>
                    <div class="flex gap-6 mt-16">
                        <button @click="showAddCategoryModal = false" class="px-10 py-5 bg-slate-900 text-slate-500 rounded-sm text-[10px] font-bold uppercase tracking-widest hover:text-white transition-all border border-white/5">Abort</button>
                        <button @click="submitCategory" :disabled="categoryForm.processing" class="flex-grow py-5 bg-blue-600 text-white rounded-sm text-[11px] font-bold uppercase tracking-[0.5em] hover:bg-blue-500 transition-all shadow-xl shadow-blue-900/40">
                            {{ categoryForm.processing ? 'SYNCING...' : 'VERIFY_SEGMENT' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Reveal Gallery: Asset Inspection -->
        <div v-if="hoveredOrder && getAllOrderImages(hoveredOrder).length" 
             class="fixed top-40 left-[420px] z-[220] w-[360px] glass-panel p-10 rounded-sm border border-blue-500/30 animate-in pointer-events-none shadow-2xl shadow-blue-950/60 transition-all duration-700">
            <div class="flex items-center justify-between mb-8 border-b border-white/5 pb-6">
                <p class="text-[10px] text-blue-400 tracking-[0.4em] font-bold uppercase">Technical Asset Drilldown</p>
                <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 status-pulse"></span>
                    <span class="text-[11px] text-white font-bold">NODE_{{ String(hoveredOrder.id).padStart(4, '0') }}</span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div v-for="(img, i) in getAllOrderImages(hoveredOrder).slice(0, 4)" :key="i" 
                     class="aspect-square bg-slate-900 overflow-hidden rounded-sm border border-white/5 shadow-inner group">
                    <img :src="img" class="w-full h-full object-cover grayscale transition-all duration-700 opacity-80 group-hover:opacity-100 group-hover:scale-110" />
                </div>
            </div>
            <div class="mt-8 flex items-center justify-between">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.3em] font-body italic">{{ getAllOrderImages(hoveredOrder).length }} Assets Tracked</p>
                <span class="material-symbols-outlined text-blue-400 opacity-60">sensors</span>
            </div>
        </div>

    </div>
</template>
