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
    productForm.secondary_image_2 = null;
    
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
	batch_limit: null
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
@import url('https://fonts.googleapis.com/css2?family=Noto+Serif:ital,wght@0,300;0,400;0,700;1,300;1,400&family=Manrope:wght@200;400;600;800&display=swap');

.serif-text { font-family: "Noto Serif", serif; }
.font-headline { font-family: 'Noto Serif', serif; }
.font-body { font-family: 'Manrope', sans-serif; }

.bg-atelier-bg { background-color: #fcf9f4; }
.bg-atelier-surface { background-color: #f6f3ee; }
.text-atelier-primary { color: #390908; }
.text-atelier-secondary { color: #5f5e5e; }

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
    box-shadow: 0 10px 30px -10px rgba(57, 9, 8, 0.05);
}
</style>

<template>
    <Head title="Serana Studio | The Atelier" />

    <div class="flex h-screen bg-[#fcf9f4] text-[#1c1c19] font-body overflow-hidden selection:bg-[#ffdad6] relative">
        
        <!-- SideNavBar -->
        <aside :class="[
            'h-screen w-64 fixed left-0 top-0 bg-[#f6f3ee] flex flex-col py-8 px-6 z-50 transition-transform duration-500',
            menuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">
            <div class="mb-12">
                <h2 class="font-headline text-2xl text-[#390908] leading-tight">The Atelier</h2>
                <p class="font-body tracking-wider uppercase text-[10px] text-[#5f5e5e] mt-1">Luxury Dashboard</p>
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
                        ? 'text-[#390908] font-bold bg-[#fcf9f4]/50' 
                        : 'text-[#5f5e5e] hover:translate-x-1'
                ]">
                    <span class="material-symbols-outlined text-[20px]">{{ tab.icon }}</span>
                    <span class="font-body tracking-wider uppercase text-[10px]">{{ tab.label }}</span>
                </button>

                <a href="#" class="group flex items-center gap-3 px-4 py-3 text-[#5f5e5e] hover:translate-x-1 transition-transform duration-200">
                    <span class="material-symbols-outlined text-[20px]">analytics</span>
                    <span class="font-body tracking-wider uppercase text-[10px]">Analytics</span>
                </a>
                <a href="#" class="group flex items-center gap-3 px-4 py-3 text-[#5f5e5e] hover:translate-x-1 transition-transform duration-200">
                    <span class="material-symbols-outlined text-[20px]">event_note</span>
                    <span class="font-body tracking-wider uppercase text-[10px]">Schedule</span>
                </a>
            </nav>

            <div class="mt-auto space-y-6">
                <button @click="currentTab === 'catalog' ? showAddProductModal = true : (currentTab === 'gallery' ? showAddGalleryModal = true : null)" 
                        class="w-full py-4 bg-[#390908] text-white font-headline text-sm rounded-lg hover:opacity-90 transition-all active:scale-95">
                    {{ currentTab === 'catalog' ? 'New Product' : (currentTab === 'gallery' ? 'Add Showcase' : 'Create New Line') }}
                </button>
                <div class="space-y-2">
                    <a class="group flex items-center gap-3 px-4 py-2 text-[#5f5e5e]" href="#">
                        <span class="material-symbols-outlined text-[18px]">help_outline</span>
                        <span class="font-body tracking-wider uppercase text-[10px]">Support</span>
                    </a>
                    <Link :href="route('logout')" method="post" as="button" class="group flex items-center gap-3 px-4 py-2 text-[#5f5e5e] w-full text-left">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        <span class="font-body tracking-wider uppercase text-[10px]">Sign Out</span>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="ml-0 lg:ml-64 flex-1 flex flex-col min-h-screen overflow-y-auto no-scrollbar">
            
            <!-- TopAppBar -->
            <header class="w-full sticky top-0 z-40 bg-[#fcf9f4]/90 backdrop-blur-md flex justify-between items-center px-8 py-4 border-b border-[#f0ede8]">
                <div class="flex items-center gap-8">
                    <button @click="menuOpen = !menuOpen" class="lg:hidden material-symbols-outlined text-[#390908]">menu</button>
                    <h1 class="text-2xl font-headline italic text-[#390908]">Serana Closet</h1>
                    <div class="hidden md:flex relative w-72">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[#5f5e5e] text-lg">search</span>
                        <input class="w-full pl-10 pr-4 py-2 bg-[#f0ede8] border-none text-sm font-body rounded-full focus:ring-1 focus:ring-[#390908]" placeholder="Search archive..." type="text"/>
                    </div>
                </div>
                <div class="flex items-center gap-6">
                    <div class="flex gap-4">
                        <button class="material-symbols-outlined text-[#5f5e5e] hover:text-[#390908] transition-colors">notifications</button>
                        <button @click="currentTab = 'settings'" class="material-symbols-outlined text-[#5f5e5e] hover:text-[#390908] transition-colors">settings</button>
                    </div>
                    <div class="h-10 w-10 rounded-full bg-[#f0ede8] overflow-hidden border border-[#ebe8e3]">
                        <img alt="Manager" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB4Jge4oeulbricIpFRaOi6sWjXn0__QQLEnVspMapERjhco2dLuiQHyXEvPu-HKvoSBk6XCsJrCn9Z1L3kINWNn8gli7Y-IwpvcE6iiYjZxFyQZxx0YRAl0gZaQ6k5G2G2RoOjPiRI2oMM3ZDP1CQXmRYM2nPsoVBSYiQ2Rr79Nb9P19zPy05SUVxv8U1xm1d5SphCUKCMTfzL4cSR49gE8r-rLfYVXcUiS0oWxg9IJOVE-sIby2IzVld81OR5HopATKu7hiDCjcc"/>
                    </div>
                </div>
            </header>

            <!-- Canvas -->
            <section class="flex-1 p-8 lg:p-12 animate-in">
                <!-- Editorial Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-12 gap-6">
                    <div class="max-w-2xl">
                        <h2 class="text-5xl font-light tracking-tight text-[#390908] mb-4">
                            {{ currentTab === 'overview' ? 'Orders' : (currentTab === 'catalog' ? 'Inventory' : (currentTab === 'gallery' ? 'Showcase' : 'Identity')) }}
                        </h2>
                        <p class="text-[#5f5e5e] font-body leading-relaxed max-w-lg">
                            {{ 
                                currentTab === 'overview' ? 'Manage customer orders and track production status.' :
                                (currentTab === 'catalog' ? 'View and manage the boutique inventory.' :
                                (currentTab === 'gallery' ? 'Manage the brand showcase and gallery items.' :
                                'Update brand assets and identity elements.'))
                            }}
                        </p>
                    </div>
                    <button v-if="currentTab === 'catalog' || currentTab === 'gallery'" 
                            @click="currentTab === 'catalog' ? showAddProductModal = true : showAddGalleryModal = true"
                            class="flex items-center gap-2 px-6 py-3 bg-[#390908] text-white rounded-lg font-body font-semibold tracking-wide hover:opacity-90 transition-all">
                        <span class="material-symbols-outlined">add</span>
                        {{ currentTab === 'catalog' ? 'New Product' : 'New Photo' }}
                    </button>
                </div>
            <!-- Bento Filter Section & Stock Health (Mapped to Atelier) -->
            <div v-if="currentTab === 'overview'" class="grid grid-cols-12 gap-6 mb-12">
                <div class="col-span-full xl:col-span-8 bg-[#f6f3ee] p-8 rounded-xl flex flex-col md:flex-row items-center justify-between gap-6 luxury-shadow">
                    <div class="flex flex-col md:flex-row gap-8 w-full md:w-auto">
                        <div class="flex flex-col gap-2">
                            <span class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold">Total Orders</span>
                            <div class="flex items-baseline gap-2">
                                <span class="font-headline text-2xl text-[#390908]">{{ stats.total_orders }}</span>
                                <span class="text-[9px] text-[#5f5e5e] uppercase font-bold tracking-tighter">Active Tasks</span>
                            </div>
                        </div>
                        <div class="hidden md:block w-px h-10 bg-[#ebe8e3]"></div>
                        <div class="flex flex-col gap-2">
                            <span class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold">In Production</span>
                            <div class="flex items-baseline gap-2">
                                <span class="font-headline text-2xl text-[#390908]">{{ orders.filter(o => o.status !== 'completed').length }}</span>
                                <span class="text-[9px] text-[#5f5e5e] uppercase font-bold tracking-tighter">In Production</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button @click="showArchivedOrders = !showArchivedOrders" 
                                :class="[
                                    'px-4 py-2 rounded-lg text-[9px] font-bold uppercase tracking-widest transition-all border',
                                    showArchivedOrders 
                                        ? 'bg-[#390908] text-white border-[#390908]' 
                                        : 'bg-[#fcf9f4] text-[#5f5e5e] border-[#f0ede8] hover:bg-[#f0ede8]'
                                ]">
                            {{ showArchivedOrders ? 'Active Registry' : 'View Archive' }}
                        </button>
                        <button class="p-3 rounded-lg bg-[#fcf9f4] text-[#1c1c19] border border-[#f0ede8] hover:bg-[#f0ede8] transition-colors">
                            <span class="material-symbols-outlined text-xl">filter_list</span>
                        </button>
                        <button class="p-3 rounded-lg bg-[#fcf9f4] text-[#1c1c19] border border-[#f0ede8] hover:bg-[#f0ede8] transition-colors">
                            <span class="material-symbols-outlined text-xl">sort</span>
                        </button>
                    </div>
                </div>
                <div class="col-span-full xl:col-span-4 bg-[#541e1b] p-8 rounded-xl flex flex-col justify-between luxury-shadow group">
                    <span class="text-[10px] uppercase tracking-widest text-[#d1827c] font-bold">Revenue</span>
                    <div class="flex justify-between items-baseline mb-4">
                        <p class="text-3xl font-headline text-white">{{ formatAmount(stats.total_revenue, $page.props) }}</p>
                        <span class="text-[#d1827c] text-xs font-body italic">Cumulative</span>
                    </div>
                    <div class="w-full bg-[#390908]/30 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-white h-full w-[85%] transition-all duration-1000"></div>
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
                        <div class="grid grid-cols-12 min-w-[1000px] px-8 py-5 bg-[#e5e2dd]/30 rounded-t-xl mb-3 border-b border-[#f0ede8]">
                            <div class="col-span-4 text-[10px] uppercase tracking-widest font-bold text-[#5f5e5e]">Order Details</div>
                            <div class="col-span-2 text-[10px] uppercase tracking-widest font-bold text-[#5f5e5e]">Order ID</div>
                            <div class="col-span-2 text-[10px] uppercase tracking-widest font-bold text-[#5f5e5e]">Progress</div>
                            <div class="col-span-2 text-[10px] uppercase tracking-widest font-bold text-[#5f5e5e]">Status</div>
                            <div class="col-span-2 text-[10px] uppercase tracking-widest font-bold text-[#5f5e5e] text-right">Price</div>
                        </div>
                        
                        <!-- Ledger Rows -->
                        <div v-for="order in filteredOrders" :key="order.id" 
                             @click="selectOrder(order)"
                             @mouseenter="hoveredOrder = order"
                             @mouseleave="hoveredOrder = null"
                             class="grid grid-cols-12 min-w-[1000px] px-8 py-6 bg-[#f6f3ee] hover:bg-[#f0ede8] transition-all items-center group cursor-pointer mb-2 rounded-lg luxury-shadow"
                             :class="form.id === order.id ? 'ring-1 ring-[#390908]' : ''">
                            
                            <div class="col-span-4 flex items-center gap-6">
                                <div class="h-20 w-16 bg-[#e5e2dd] rounded-lg overflow-hidden flex-shrink-0 relative group-hover:scale-105 transition-transform duration-500">
                                    <img :src="getOrderThumbnails(order)[0] || '/images/hero_editorial.png'" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" />
                                </div>
                                <div>
                                    <p class="font-headline text-lg text-[#390908]">{{ order.full_name }}</p>
                                    <p class="text-xs text-[#5f5e5e] font-body">{{ order.garment_name || 'Bespoke Item' }}</p>
                                </div>
                            </div>

                            <div class="col-span-2 font-body text-sm tracking-tight text-[#5f5e5e]">#{{ String(order.id).padStart(5, '0') }}</div>
                            
                            <div class="col-span-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-headline text-xl text-[#390908] uppercase text-xs">{{ order.status }}</span>
                                </div>
                                <div class="w-16 h-0.5 bg-[#d8c1bf] mt-2 relative overflow-hidden">
                                    <div class="bg-[#390908] h-full transition-all duration-1000" 
                                         :style="{ width: order.status === 'completed' ? '100%' : (order.status === 'sewing' ? '75%' : (order.status === 'designing' ? '40%' : '15%')) }"></div>
                                </div>
                            </div>

                            <div class="col-span-2">
                                <span :class="[
                                    'px-3 py-1 rounded-full text-[9px] uppercase font-bold tracking-wider',
                                    order.is_paid ? 'bg-[#f6f3ee] text-[#1c1c19] border border-[#ebe8e3]' : 'bg-[#ffdad6] text-[#93000a]'
                                ]">
                                    {{ order.is_paid ? 'SECURED_FUNDS' : 'UNSECURED' }}
                                </span>
                            </div>

                            <div class="col-span-2 text-right font-headline text-lg text-[#390908]">
                                {{ formatAmount(order.price_quoted || 0, page.props) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile Card Registry -->
                <div class="lg:hidden space-y-6 pb-20 mt-6">
                    <div v-for="order in filteredOrders" :key="order.id" 
                        @click="selectOrder(order)"
                        class="bg-[#f6f3ee] p-6 border border-[#f0ede8] active:bg-[#e5e2dd] transition-all rounded-xl flex gap-6 luxury-shadow"
                        :class="form.id === order.id ? 'border-[#390908]/40 border-2' : ''">
                        
                        <div class="w-16 h-20 bg-[#e5e2dd] border border-[#f0ede8] flex-shrink-0 overflow-hidden rounded-md">
                            <img :src="getOrderThumbnails(order)[0] || '/images/hero_editorial.png'" class="w-full h-full object-cover">
                        </div>

                        <div class="flex-grow flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start">
                                    <p class="text-[9px] text-[#390908] tracking-widest font-bold">#{{ String(order.id).padStart(4, '0') }}</p>
                                    <span :class="`text-[7px] font-black tracking-widest uppercase px-2 py-1 rounded-sm border border-[#f0ede8] ${order.status === 'completed' ? 'bg-[#390908] text-white' : 'bg-white text-[#5f5e5e]'}`">
                                        {{ order.status }}
                                    </span>
                                </div>
                                <p class="font-headline text-lg text-[#390908]">{{ order.full_name }}</p>
                                <p class="font-body text-xs text-[#5f5e5e] italic">{{ order.garment_name || 'Bespoke' }}</p>
                            </div>
                            <div class="flex justify-between items-end mt-2">
                                <p class="text-[10px] text-[#390908] font-black uppercase">{{ formatAmount(order.price_quoted || 0, page.props) }}</p>
                                <p class="text-[7px] text-[#5f5e5e] tracking-tighter uppercase font-bold">{{ new Date(order.created_at).toLocaleDateString() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Tab -->
            <div v-if="currentTab === 'catalog'">
                <div class="grid grid-cols-12 gap-8 mb-12">
                    <div class="col-span-full md:col-span-8 bg-[#f6f3ee] p-8 rounded-xl flex items-center justify-between gap-6 luxury-shadow">
                        <div class="flex gap-8">
                            <div class="flex flex-col gap-2">
                            <span class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold">Category Filter</span>
                                <select class="bg-transparent border-none p-0 font-headline text-lg focus:ring-0 cursor-pointer text-[#390908]">
                                    <option>Full Collection</option>
                                    <option v-for="cat in categories" :key="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex gap-2">
                           <button @click="showArchivedProducts = !showArchivedProducts" 
                                   class="px-5 py-2 rounded-lg bg-[#fcf9f4] border border-[#f0ede8] text-[#5f5e5e] text-[9px] font-bold uppercase tracking-widest hover:text-[#390908] transition-colors">
                               {{ showArchivedProducts ? 'Live Catalog' : 'Archive Entry' }}
                           </button>
                        </div>
                    </div>
                    <div class="col-span-full md:col-span-4 bg-[#f6f3ee] p-8 rounded-xl flex flex-col justify-between border border-[#ebe8e3] luxury-shadow">
                        <span class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold">Stock Status</span>
                        <div class="flex justify-between items-baseline">
                            <p class="text-3xl font-headline text-[#390908]">98%</p>
                            <span class="text-[#5f5e5e] text-[10px] font-bold uppercase tracking-tighter">In Stock</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <div v-for="product in (showArchivedProducts ? archived_products : products)" :key="product.id"
                         class="grid grid-cols-12 px-8 py-6 bg-[#f6f3ee] hover:bg-[#f0ede8] transition-all items-center group mb-2 rounded-xl luxury-shadow">
                        <div class="col-span-5 flex items-center gap-6">
                            <div class="h-24 w-20 bg-[#e5e2dd] rounded-lg overflow-hidden flex-shrink-0 relative group-hover:scale-105 transition-all duration-700">
                                <img :src="product.image_url" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" />
                            </div>
                            <div>
                                <p class="font-headline text-xl text-[#390908] leading-tight mb-1">{{ product.name }}</p>
                                <p class="text-xs text-[#5f5e5e] font-body">{{ product.category?.name || 'Artisan Series' }}</p>
                            </div>
                        </div>
                        <div class="col-span-2 font-body text-xs tracking-widest text-[#5f5e5e] uppercase">SNC-{{ String(product.id).padStart(4, '0') }}</div>
                        <div class="col-span-2">
                            <div class="flex items-center gap-2">
                                <span :class="['font-headline text-xl', product.in_stock ? 'text-[#390908]' : 'text-[#ba1a1a]']">
                                    {{ product.in_stock ? 'READY' : 'EXHAUSTED' }}
                                </span>
                            </div>
                        </div>
                        <div class="col-span-3 text-right">
                            <div class="flex flex-col items-end gap-4">
                                <p class="font-headline text-2xl text-[#390908]">{{ formatAmount(product.price, page.props) }}</p>
                                <div class="flex gap-4">
                                    <button @click="editProduct(product)" class="material-symbols-outlined text-[#5f5e5e] hover:text-[#390908] text-xl transition-colors">edit_note</button>
                                    <button @click="deleteProduct(product.id)" class="material-symbols-outlined text-[#5f5e5e] hover:text-[#ba1a1a] text-xl transition-colors">
                                        {{ showArchivedProducts ? 'restore_from_trash' : 'archive' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Showcase Tab -->
            <div v-if="currentTab === 'gallery'">
                <div class="grid grid-cols-12 gap-8 mb-12">
                    <div class="col-span-full md:col-span-12 bg-[#f6f3ee] p-8 rounded-xl flex items-center justify-between luxury-shadow border border-[#f0ede8]">
                        <div class="flex gap-12">
                            <button @click="showArchivedGallery = false" 
                                    class="text-[10px] uppercase tracking-[0.2em] font-bold transition-all relative py-2"
                                    :class="!showArchivedGallery ? 'text-[#390908]' : 'text-[#5f5e5e]'">
                                Active Showcase
                                <span v-if="!showArchivedGallery" class="absolute bottom-0 left-0 w-full h-[1px] bg-[#390908]"></span>
                            </button>
                            <button @click="showArchivedGallery = true" 
                                    class="text-[10px] uppercase tracking-[0.2em] font-bold transition-all relative py-2"
                                    :class="showArchivedGallery ? 'text-[#390908]' : 'text-[#5f5e5e]'">
                                Archive
                                <span v-if="showArchivedGallery" class="absolute bottom-0 left-0 w-full h-[1px] bg-[#390908]"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!showArchivedGallery" class="grid grid-cols-1 md:grid-cols-2 gap-12 animate-in pb-20">
                    <div v-for="item in gallery" :key="item.id" class="bg-[#f6f3ee] p-3 rounded-xl luxury-shadow group relative">
                        <div class="aspect-[16/11] bg-[#e5e2dd] rounded-lg overflow-hidden mb-6 relative">
                            <img :src="item.image_url" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-1000" />
                            <div class="absolute top-4 left-4">
                                <span class="bg-[#390908]/90 text-white px-4 py-2 rounded-lg text-[9px] font-bold uppercase tracking-widest">{{ item.garment_name }}</span>
                            </div>
                        </div>
                        <div class="p-4 px-6 mb-4">
                            <h3 class="font-headline text-3xl text-[#390908] italic mb-3">{{ item.client_name }}</h3>
                            <p class="text-sm text-[#5f5e5e] font-body leading-relaxed">"{{ item.testimonial }}"</p>
                        </div>
                        <div class="flex justify-end gap-6 p-4 border-t border-[#f0ede8]">
                            <button @click="editGallery(item)" class="material-symbols-outlined text-[#5f5e5e] hover:text-[#390908] transition-colors">edit</button>
                            <button @click="deleteGallery(item.id)" class="material-symbols-outlined text-[#5f5e5e] hover:text-[#ba1a1a] transition-colors">archive</button>
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
                <div v-for="lead in leads" :key="lead.id" class="bg-[#f6f3ee] p-8 rounded-xl luxury-shadow border border-[#f0ede8] flex items-center justify-between group hover:bg-[#f0ede8] transition-colors">
                    <div class="flex items-center gap-8">
                        <div class="w-12 h-12 bg-[#e5e2dd] rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#390908] text-lg">mail</span>
                        </div>
                        <div>
                            <p class="font-headline text-xl text-[#390908]">{{ lead.email }}</p>
                            <p class="text-[9px] text-[#5f5e5e] font-bold tracking-widest uppercase mt-1">Subscribed: Ref #{{ String(lead.id).padStart(4, '0') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Identity Tab -->
            <div v-if="currentTab === 'brand'" class="space-y-12 pb-20 animate-in">

                <!-- Row 1: Site Info (Text Fields) -->
                <div class="bg-[#f6f3ee] p-10 rounded-xl luxury-shadow border border-[#f0ede8]">
                    <div class="flex items-center justify-between mb-8">
                        <label class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold">Site Info</label>
                        <button @click="saveSiteInfo" :disabled="siteInfoForm.processing"
                                class="px-6 py-2 bg-[#390908] text-white text-[9px] font-bold uppercase tracking-widest rounded-lg hover:opacity-90 transition-all disabled:opacity-30">
                            {{ siteInfoForm.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="space-y-2">
                            <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-[0.2em]">Site Name</label>
                            <input v-model="siteInfoForm.settings.site_name"
                                   class="w-full bg-transparent border-b border-[#d8c1bf] py-3 text-lg font-headline text-[#390908] focus:border-[#390908] outline-none transition-colors"
                                   placeholder="e.g. Serana Closet" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-[0.2em]">Tagline</label>
                            <input v-model="siteInfoForm.settings.site_tagline"
                                   class="w-full bg-transparent border-b border-[#d8c1bf] py-3 text-lg font-headline text-[#390908] focus:border-[#390908] outline-none transition-colors"
                                   placeholder="e.g. Bespoke Made With Love" />
                        </div>
                        <div class="space-y-2">
                            <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-[0.2em]">WhatsApp Number</label>
                            <input v-model="siteInfoForm.settings.whatsapp_number"
                                   class="w-full bg-transparent border-b border-[#d8c1bf] py-3 text-lg font-headline text-[#390908] focus:border-[#390908] outline-none transition-colors"
                                   placeholder="e.g. 254712345678" />
                        </div>
                    </div>
                </div>

                <!-- Row 2: Logo + Favicon -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Main Logo -->
                    <div class="bg-[#f6f3ee] p-10 rounded-xl luxury-shadow border border-[#f0ede8]">
                        <label class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold mb-6 block">Main Logo</label>
                        <div class="h-56 bg-[#fcf9f4] border border-dashed border-[#d8c1bf] rounded-lg relative group flex items-center justify-center overflow-hidden">
                            <img v-if="$page.props.theme_settings.site_logo" :src="$page.props.theme_settings.site_logo" class="max-h-28 object-contain grayscale group-hover:grayscale-0 transition-all duration-700">
                            <h1 v-else class="font-headline text-2xl text-[#390908] opacity-20 italic">No Logo Set</h1>
                            <div class="absolute inset-0 bg-[#390908]/90 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-3 backdrop-blur-sm">
                                <input type="file" id="logo-up" @input="updateThemeAsset('site_logo', $event.target.files[0])" class="hidden">
                                <label for="logo-up" class="px-6 py-2 bg-white text-[#390908] text-[9px] font-bold uppercase tracking-widest cursor-pointer rounded-lg hover:bg-[#f6f3ee] transition-colors">Upload Logo</label>
                                <button v-if="$page.props.theme_settings.site_logo" @click="deleteThemeAsset('site_logo')" class="text-white text-[9px] font-bold uppercase tracking-widest opacity-60 hover:opacity-100 transition-opacity">Remove</button>
                            </div>
                        </div>
                    </div>

                    <!-- Site Icon / Favicon -->
                    <div class="bg-[#f6f3ee] p-10 rounded-xl luxury-shadow border border-[#f0ede8]">
                        <label class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold mb-6 block">Site Icon</label>
                        <div class="h-56 bg-[#fcf9f4] border border-dashed border-[#d8c1bf] rounded-lg relative group flex items-center justify-center overflow-hidden">
                            <img v-if="$page.props.theme_settings.site_favicon" :src="$page.props.theme_settings.site_favicon" class="w-16 h-16 object-contain grayscale group-hover:grayscale-0 transition-all duration-700">
                            <span v-else class="material-symbols-outlined text-6xl text-[#390908] opacity-10">fingerprint</span>
                            <div class="absolute inset-0 bg-[#390908]/90 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-3 backdrop-blur-sm">
                                <input type="file" id="fav-up" @input="updateThemeAsset('site_favicon', $event.target.files[0])" class="hidden">
                                <label for="fav-up" class="px-6 py-2 bg-white text-[#390908] text-[9px] font-bold uppercase tracking-widest cursor-pointer rounded-lg hover:bg-[#f6f3ee] transition-colors">Upload Icon</label>
                                <button v-if="$page.props.theme_settings.site_favicon" @click="deleteThemeAsset('site_favicon')" class="text-white text-[9px] font-bold uppercase tracking-widest opacity-60 hover:opacity-100 transition-opacity">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Hero Background -->
                <div class="bg-[#f6f3ee] p-10 rounded-xl luxury-shadow border border-[#f0ede8]">
                    <label class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold mb-6 block">Homepage Hero</label>
                    <div class="aspect-[21/6] bg-[#fcf9f4] border border-dashed border-[#d8c1bf] rounded-xl relative group overflow-hidden flex items-center justify-center">
                        <img v-if="$page.props.theme_settings.hero_bg" :src="$page.props.theme_settings.hero_bg" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-[2000ms]">
                        <span v-else class="material-symbols-outlined text-6xl text-[#390908] opacity-10">panorama</span>
                        <div class="absolute inset-0 bg-[#390908]/80 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center gap-4 backdrop-blur-sm">
                            <input id="hero-bg-up" type="file" @input="updateThemeAsset('hero_bg', $event.target.files[0])" class="hidden">
                            <label for="hero-bg-up" class="px-8 py-3 bg-white text-[#390908] text-[9px] font-bold uppercase tracking-widest cursor-pointer rounded-lg">Upload Hero Image</label>
                            <button v-if="$page.props.theme_settings.hero_bg" @click="deleteThemeAsset('hero_bg')" class="text-white text-[9px] font-bold uppercase tracking-widest opacity-60 hover:opacity-100 transition-opacity">Remove</button>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Page Banners (Category Backgrounds) -->
                <div class="bg-[#f6f3ee] p-10 rounded-xl luxury-shadow border border-[#f0ede8]">
                    <label class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold mb-6 block">Page Banners</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div v-for="cat in [
                            { key: 'cat_men_bg', label: 'Menswear Page' },
                            { key: 'cat_women_bg', label: 'Womenswear Page' },
                            { key: 'cat_acc_bg', label: 'Studio / Accessories' },
                        ]" :key="cat.key" class="space-y-3">
                            <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-[0.2em]">{{ cat.label }}</label>
                            <div class="aspect-[3/4] bg-[#fcf9f4] border border-[#f0ede8] rounded-lg relative group overflow-hidden flex items-center justify-center">
                                <img v-if="$page.props.theme_settings[cat.key]" :src="$page.props.theme_settings[cat.key]" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-[2000ms]">
                                <span v-else class="material-symbols-outlined text-4xl text-[#390908] opacity-10">image</span>
                                <div class="absolute inset-0 bg-[#390908]/80 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center gap-3 text-center backdrop-blur-sm">
                                    <input :id="`banner-${cat.key}`" type="file" @input="updateThemeAsset(cat.key, $event.target.files[0])" class="hidden">
                                    <label :for="`banner-${cat.key}`" class="px-5 py-2 bg-white text-[#390908] text-[9px] font-bold uppercase tracking-widest cursor-pointer rounded-lg">Update Banner</label>
                                    <button v-if="$page.props.theme_settings[cat.key]" @click="deleteThemeAsset(cat.key)" class="text-white text-[9px] font-bold uppercase tracking-widest opacity-60 hover:opacity-100 transition-opacity">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 5: Editorial Focal Points -->
                <div class="bg-[#f6f3ee] p-10 rounded-xl luxury-shadow border border-[#f0ede8]">
                    <div class="flex items-center justify-between mb-8">
                        <label class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold">Editorial Curation</label>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                        <div v-for="i in [1, 2, 3]" :key="i" class="space-y-6">
                            <div class="aspect-[4/5] bg-[#fcf9f4] border border-[#f0ede8] rounded-xl relative group overflow-hidden flex items-center justify-center luxury-shadow">
                                <img v-if="$page.props.theme_settings[`focal_${i}_bg`]" :src="$page.props.theme_settings[`focal_${i}_bg`]" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-1000">
                                <div v-else class="text-center opacity-10">
                                    <span class="material-symbols-outlined text-6xl">photo_filter</span>
                                </div>
                                <div class="absolute inset-0 bg-[#390908]/90 opacity-0 group-hover:opacity-100 transition-all flex flex-col items-center justify-center gap-3 backdrop-blur-md">
                                    <input :id="`focal-up-${i}`" type="file" @input="updateThemeAsset(`focal_${i}_bg`, $event.target.files[0])" class="hidden">
                                    <label :for="`focal-up-${i}`" class="px-6 py-2 bg-white text-[#390908] text-[8px] font-bold uppercase tracking-widest cursor-pointer rounded-lg">Replace Media</label>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-[0.2em]">Focal Point {{ i }} Label</label>
                                <div class="flex gap-4">
                                    <input v-model="siteInfoForm.settings[`focal_${i}_label`]" 
                                           class="flex-grow bg-transparent border-b border-[#d8c1bf] py-2 text-sm font-headline text-[#390908] focus:border-[#390908] outline-none"
                                           :placeholder="`Editorial Slot ${i}...`" />
                                    <button @click="updateThemeAsset(`focal_${i}_label`, siteInfoForm.settings[`focal_${i}_label`])" 
                                            class="material-symbols-outlined text-[#390908] hover:scale-110 transition-transform">check_circle</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Tab: System Configuration -->
            <div v-if="currentTab === 'settings'" class="max-w-4xl space-y-12 animate-in pb-20">
                <div class="bg-[#f6f3ee] p-12 rounded-xl luxury-shadow border border-[#f0ede8]">
                    <h3 class="font-headline text-3xl text-[#390908] italic mb-6">Archive Settings</h3>
                    <p class="text-sm text-[#5f5e5e] font-body leading-relaxed mb-10 max-w-xl">Configure the technical parameters of the Serana digital atelier, including precision and access protocols.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="p-8 bg-[#390908] rounded-xl flex flex-col justify-between group h-48">
                            <p class="text-[10px] text-[#d1827c] uppercase font-bold tracking-widest">Pricing Precision</p>
                            <div class="flex items-center justify-between">
                                <span class="font-headline text-4xl text-white italic">± 0.2 <span class="text-xs uppercase not-italic opacity-60">cm</span></span>
                                <button class="w-10 h-10 rounded-lg bg-white/10 text-white material-symbols-outlined hover:bg-white/20 transition-all">tune</button>
                            </div>
                        </div>
                        <div class="p-8 bg-[#fcf9f4] border border-[#f0ede8] rounded-xl flex flex-col justify-between h-48 group">
                            <p class="text-[10px] text-[#5f5e5e] uppercase font-bold tracking-widest">Developer Gateway</p>
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold text-[#390908] uppercase tracking-widest">Station Locked</span>
                                <div class="w-12 h-6 bg-[#ebe8e3] rounded-full p-1 cursor-not-allowed">
                                    <div class="w-4 h-4 bg-white rounded-full shadow-sm"></div>
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
                 'transition-all duration-700 fixed lg:relative z-[160] lg:z-40 lg:flex-shrink-0 bg-[#f6f3ee] border-l border-[#f0ede8]',
                 'w-full lg:w-[620px] overflow-y-auto lg:h-screen lg:rounded-l-3xl luxury-shadow',
                 'inset-x-0 bottom-0 h-[92vh] lg:h-screen lg:inset-auto translate-y-0',
                 form.id ? 'flex flex-col p-10 lg:p-16' : 'hidden'
               ]">
            <header class="mb-12 flex-shrink-0 animate-in">
                <div class="flex items-center justify-between mb-12">
                    <div class="flex items-center gap-6">
                        <div class="w-12 h-12 rounded-lg bg-[#390908] flex items-center justify-center text-white material-symbols-outlined">
                            {{ selectedOrder?.deleted_at ? 'archive' : 'description' }}
                        </div>
                        <div>
                            <h1 class="font-headline text-3xl text-[#390908] italic leading-none">
                                {{ selectedOrder?.deleted_at ? 'Archived Brief' : 'Order Details' }}
                            </h1>
                            <p class="text-[9px] text-[#5f5e5e] tracking-[0.4em] uppercase font-bold mt-2">ID #{{ String(form.id).padStart(5, '0') }}</p>
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
                
                <div class="flex items-center justify-between py-6 border-y border-[#f0ede8]">
                    <div class="flex items-center gap-3">
                        <span class="w-2 h-2 rounded-full bg-[#390908] status-pulse"></span>
                        <p class="text-[10px] text-[#390908] font-bold uppercase tracking-widest">Order Status</p>
                    </div>
                    <button @click="copyOrderBrief" class="flex items-center gap-3 px-5 py-2 bg-[#fcf9f4] border border-[#f0ede8] rounded-lg group hover:bg-[#390908] transition-all">
                        <span class="text-[9px] text-[#5f5e5e] group-hover:text-white tracking-widest font-bold uppercase">{{ showBriefNotification ? 'ID_COPIED' : 'TRANSFER_BRIEF' }}</span>
                        <span class="material-symbols-outlined text-sm text-[#5f5e5e] group-hover:text-white">file_copy</span>
                    </button>
                </div>
            </header>

            <div class="mb-16 space-y-12 animate-in flex-grow">
                <!-- Visual Asset Display -->
                <div class="aspect-[16/10] bg-[#e5e2dd] rounded-xl overflow-hidden luxury-shadow relative group">
                    <img v-if="getOrderThumbnails(selectedOrder).length" 
                         :src="getOrderThumbnails(selectedOrder)[0]" 
                         class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-[3000ms]" />
                    <div v-else class="w-full h-full flex flex-col items-center justify-center text-[#5f5e5e]">
                        <span class="material-symbols-outlined text-6xl opacity-20 mb-3">image_not_supported</span>
                        <p class="text-[9px] tracking-[0.4em] uppercase font-bold">No Visual Linked</p>
                    </div>
                </div>

                <!-- Anatomical Blueprint -->
                <div class="p-8 bg-[#fcf9f4] border border-[#f0ede8] rounded-xl">
                    <p class="text-[10px] text-[#390908] tracking-[0.4em] uppercase font-bold mb-8 flex items-center gap-3">
                        <span class="w-6 h-[1px] bg-[#d8c1bf]"></span>
                        Measurements
                    </p>
                    <div class="grid grid-cols-2 gap-8">
                        <div v-for="m in [['BUST', selectedOrder?.bust_cm], ['WAIST', selectedOrder?.waist_cm], ['HIPS', selectedOrder?.hips_cm], ['FABRIC', selectedOrder?.fabric_preference || 'STND']]" :key="m[0]" class="space-y-1">
                            <label class="text-[9px] text-[#5f5e5e] tracking-widest font-bold uppercase">{{ m[0] }}</label>
                            <p class="font-headline text-2xl text-[#390908]">{{ m[1] }}{{ typeof m[1] === 'number' ? ' CM' : '' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Production Traceability -->
                <div class="p-8 bg-[#f6f3ee] border border-[#f0ede8] rounded-xl space-y-8 luxury-shadow">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] text-[#390908] tracking-[0.4em] uppercase font-bold flex items-center gap-3">
                            <span class="w-6 h-[1px] bg-[#d8c1bf]"></span>
                            Production Proofs
                        </p>
                        <p v-if="progressSnapForm.processing" class="text-[9px] text-[#390908] font-bold animate-pulse uppercase tracking-widest text-[#d1827c]">Syncing...</p>
                    </div>

                    <div class="grid grid-cols-4 gap-3">
                        <div v-for="snap in selectedOrder?.progress_snaps" :key="snap.id" 
                             class="aspect-square bg-[#e5e2dd] rounded shadow-sm overflow-hidden relative group">
                            <img :src="snap.image_url" class="w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-500">
                        </div>
                        <div class="aspect-square bg-[#fcf9f4] border border-dashed border-[#d8c1bf] rounded flex items-center justify-center relative group">
                             <input type="file" id="snap-up" @input="progressSnapForm.image = $event.target.files[0]; submitProgressSnap()" class="hidden">
                             <label for="snap-up" class="absolute inset-0 cursor-pointer flex items-center justify-center text-[#d8c1bf] group-hover:text-[#390908] transition-colors">
                                 <span class="material-symbols-outlined">{{ progressSnapForm.processing ? 'sync' : 'add_a_photo' }}</span>
                             </label>
                             <img v-if="progressSnapForm.image" :src="getObjectURL(progressSnapForm.image)" class="absolute inset-0 w-full h-full object-cover opacity-50">
                        </div>
                    </div>
                </div>

                <!-- Technical Notes -->
                <div class="space-y-8">
                    <div class="space-y-4">
                        <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-[0.3em]">Internal Notes</label>
                        <textarea v-model="form.internal_notes" rows="4" class="w-full bg-[#fcf9f4] border border-[#f0ede8] rounded-xl p-6 text-sm font-body focus:ring-1 focus:ring-[#390908] outline-none transition-all placeholder:text-[#ebe8e3]" placeholder="Enter garment construction details..."></textarea>
                    </div>
                    <div class="space-y-4">
                        <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-[0.3em]">Status</label>
                        <div class="relative bg-[#fcf9f4] border border-[#f0ede8] rounded-xl p-1">
                             <select v-model="form.status" class="w-full bg-transparent p-4 text-[10px] tracking-[0.2em] font-bold uppercase text-[#390908] outline-none appearance-none cursor-pointer">
                                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                             </select>
                             <span class="absolute right-6 top-1/2 -translate-y-1/2 material-symbols-outlined text-[#d8c1bf] pointer-events-none">expand_more</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-auto pt-10 border-t border-[#f0ede8] animate-in">
                <button @click="updateOrder" :disabled="form.processing"
                        class="w-full py-5 bg-[#390908] text-white rounded-lg text-[10px] font-bold uppercase tracking-[0.3em] hover:scale-[1.01] active:scale-[0.98] transition-all disabled:opacity-30">
                    {{ form.processing ? 'Saving Changes...' : 'Save Changes' }}
                </button>
            </div>
        </aside>

        <!-- Product Modal: The Boutique Curator -->
        <div v-if="showAddProductModal" class="fixed inset-0 z-[300] flex items-center justify-center p-6 backdrop-blur-xl bg-[#390908]/20">
            <div @click="showAddProductModal = false" class="absolute inset-0"></div>
            <div class="relative w-full max-w-5xl bg-[#fcf9f4] rounded-2xl shadow-2xl flex flex-col md:flex-row overflow-hidden animate-in border border-[#f0ede8]">
                <div class="flex-1 p-10 lg:p-16">
                    <h2 class="font-headline text-4xl text-[#390908] italic mb-12">{{ isEditingProduct ? 'Edit Product' : 'Add New Product' }}</h2>
                    <div class="grid grid-cols-2 gap-8">
                        <div class="col-span-2 space-y-2">
                             <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-widest">Product Name</label>
                             <input v-model="productForm.name" class="w-full bg-transparent border-b border-[#d8c1bf] py-4 text-xl font-headline text-[#390908] focus:border-[#390908] outline-none transition-colors" />
                        </div>
                        <div class="space-y-2">
                             <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-widest">Price (KES)</label>
                             <input v-model="productForm.price" type="number" class="w-full bg-transparent border-b border-[#d8c1bf] py-4 text-xl font-headline text-[#390908] focus:border-[#390908] outline-none transition-colors" />
                        </div>
                        <div class="space-y-2">
                             <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-widest">Category</label>
                             <select v-model="productForm.category_id" class="w-full bg-transparent border-b border-[#d8c1bf] py-4 text-[10px] font-bold uppercase tracking-widest text-[#390908] focus:border-[#390908] outline-none cursor-pointer">
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                             </select>
                        </div>
                        <div class="col-span-2 space-y-2">
                             <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-widest">Description</label>
                             <textarea v-model="productForm.description" rows="3" class="w-full bg-[#f6f3ee] rounded-lg p-6 text-sm font-body outline-none focus:ring-1 focus:ring-[#390908] transition-all"></textarea>
                        </div>
                    </div>
                    <button @click="submitProduct" :disabled="productForm.processing" class="w-full py-6 mt-12 bg-[#390908] text-white rounded-lg text-[10px] font-bold uppercase tracking-[0.4em] hover:opacity-90 active:scale-[0.99] transition-all">
                        {{ productForm.processing ? 'PROCESSING...' : 'Verify Entry' }}
                    </button>
                </div>
                <div class="w-full md:w-5/12 bg-[#f6f3ee] p-10 flex flex-col justify-center border-l border-[#f0ede8] space-y-6">
                    <label class="text-[10px] uppercase tracking-widest text-[#5f5e5e] font-bold">Media Registry</label>
                    
                    <!-- Main Product Image -->
                    <div class="aspect-[4/3] bg-[#fcf9f4] border border-dashed border-[#d8c1bf] rounded-lg relative group flex items-center justify-center overflow-hidden luxury-shadow">
                        <input type="file" id="product-img" @input="productForm.image = $event.target.files[0]; productPreview = getObjectURL($event.target.files[0])" class="hidden" />
                        <label for="product-img" class="absolute inset-0 cursor-pointer flex flex-col items-center justify-center z-10 text-center p-8 group">
                            <span class="material-symbols-outlined text-4xl text-[#d8c1bf] group-hover:text-[#390908] transition-colors mb-2">linked_camera</span>
                            <p class="text-[8px] text-[#5f5e5e] font-bold uppercase tracking-widest">Primary Signature</p>
                        </label>
                        <img v-if="productForm.image" :src="getObjectURL(productForm.image)" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" />
                        <img v-else-if="productPreview" :src="productPreview" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" />
                    </div>

                    <!-- Secondary Curations -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="aspect-square bg-[#fcf9f4] border border-dashed border-[#d8c1bf] rounded-lg relative group flex items-center justify-center overflow-hidden">
                            <input type="file" id="sec-img-1" @input="productForm.secondary_image_1 = $event.target.files[0]; secondaryPreview1 = getObjectURL($event.target.files[0])" class="hidden" />
                            <label for="sec-img-1" class="absolute inset-0 cursor-pointer flex flex-col items-center justify-center z-10 text-center group">
                                <span class="material-symbols-outlined text-2xl text-[#d8c1bf] group-hover:text-[#390908] transition-colors">add_photo_alternate</span>
                                <p class="text-[7px] text-[#5f5e5e] font-bold uppercase tracking-widest mt-1">Slot A</p>
                            </label>
                            <img v-if="productForm.secondary_image_1" :src="getObjectURL(productForm.secondary_image_1)" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" />
                            <img v-else-if="secondaryPreview1" :src="secondaryPreview1" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" />
                        </div>
                        <div class="aspect-square bg-[#fcf9f4] border border-dashed border-[#d8c1bf] rounded-lg relative group flex items-center justify-center overflow-hidden">
                            <input type="file" id="sec-img-2" @input="productForm.secondary_image_2 = $event.target.files[0]; secondaryPreview2 = getObjectURL($event.target.files[0])" class="hidden" />
                            <label for="sec-img-2" class="absolute inset-0 cursor-pointer flex flex-col items-center justify-center z-10 text-center group">
                                <span class="material-symbols-outlined text-2xl text-[#d8c1bf] group-hover:text-[#390908] transition-colors">add_photo_alternate</span>
                                <p class="text-[7px] text-[#5f5e5e] font-bold uppercase tracking-widest mt-1">Slot B</p>
                            </label>
                            <img v-if="productForm.secondary_image_2" :src="getObjectURL(productForm.secondary_image_2)" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" />
                            <img v-else-if="secondaryPreview2" :src="secondaryPreview2" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gallery Modal: Showcase Registry -->
        <div v-if="showAddGalleryModal" class="fixed inset-0 z-[300] flex items-center justify-center p-6 backdrop-blur-xl bg-[#390908]/20">
            <div @click="showAddGalleryModal = false" class="absolute inset-0"></div>
            <div class="relative w-full max-w-5xl bg-[#fcf9f4] rounded-2xl shadow-2xl flex flex-col md:flex-row overflow-hidden animate-in border border-[#f0ede8]">
                <div class="flex-1 p-10 lg:p-16">
                    <h2 class="font-headline text-4xl text-[#390908] italic mb-12">{{ isEditingGallery ? 'Edit Showcase' : 'Add Showcase' }}</h2>
                    <div class="grid grid-cols-2 gap-8">
                        <div class="space-y-2">
                             <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-widest">Client Name</label>
                             <input v-model="galleryForm.client_name" class="w-full bg-transparent border-b border-[#d8c1bf] py-4 text-xl font-headline text-[#390908] outline-none" />
                        </div>
                        <div class="space-y-2">
                             <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-widest">Garment Name</label>
                             <input v-model="galleryForm.garment_name" class="w-full bg-transparent border-b border-[#d8c1bf] py-4 text-xl font-headline text-[#390908] outline-none" />
                        </div>
                        <div class="col-span-2 space-y-2">
                             <label class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-widest">Testimonial</label>
                             <textarea v-model="galleryForm.testimonial" rows="3" class="w-full bg-[#f6f3ee] rounded-lg p-6 text-sm font-body outline-none"></textarea>
                        </div>
                    </div>
                    <button @click="submitGallery" :disabled="galleryForm.processing" class="w-full py-6 mt-12 bg-[#390908] text-white rounded-lg text-[10px] font-bold uppercase tracking-[0.4em] hover:opacity-90 active:scale-[0.99] transition-all">
                        {{ galleryForm.processing ? 'ARCHIVING...' : 'Secure Showcase' }}
                    </button>
                </div>
                <div class="w-full md:w-5/12 bg-[#f6f3ee] border-l border-[#f0ede8] p-10 flex flex-col justify-center">
                    <div class="aspect-square bg-[#fcf9f4] border border-dashed border-[#d8c1bf] rounded-lg relative group flex items-center justify-center overflow-hidden luxury-shadow">
                        <input type="file" id="gallery-img" @input="galleryForm.image = $event.target.files[0]" class="hidden" />
                        <label for="gallery-img" class="absolute inset-0 cursor-pointer flex flex-col items-center justify-center z-10 text-center p-8 group">
                            <span class="material-symbols-outlined text-5xl text-[#d8c1bf] group-hover:text-[#390908] transition-colors mb-4">linked_camera</span>
                            <p class="text-[9px] text-[#5f5e5e] font-bold uppercase tracking-widest">Upload Photo</p>
                        </label>
                        <img v-if="galleryForm.image" :src="getObjectURL(galleryForm.image)" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all" />
                        <img v-else-if="galleryPreview" :src="galleryPreview" class="absolute inset-0 w-full h-full object-cover grayscale group-hover:grayscale-0 transition-all" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Reveal Gallery: Asset Inspection -->
        <div v-if="hoveredOrder && getAllOrderImages(hoveredOrder).length" 
             class="fixed top-40 left-[420px] z-[220] w-[320px] bg-[#fcf9f4] p-8 rounded-2xl shadow-2xl border border-[#f0ede8] animate-in pointer-events-none">
            <div class="flex items-center justify-between mb-8 border-b border-[#f0ede8] pb-4">
                <p class="text-[9px] text-[#390908] tracking-widest font-bold uppercase">Asset Drilldown</p>
                <span class="text-[10px] text-[#5f5e5e] font-bold">#{{ String(hoveredOrder.id).padStart(4, '0') }}</span>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div v-for="(img, i) in getAllOrderImages(hoveredOrder).slice(0, 4)" :key="i" 
                     class="aspect-square bg-[#e5e2dd] overflow-hidden rounded-lg border border-[#f0ede8] shadow-inner group">
                    <img :src="img" class="w-full h-full object-cover grayscale transition-all duration-700" />
                </div>
            </div>
            <div class="mt-8 flex items-center justify-between">
                <p class="text-[9px] font-bold text-[#5f5e5e] uppercase tracking-widest italic opacity-60">{{ getAllOrderImages(hoveredOrder).length }} Assets</p>
                <span class="material-symbols-outlined text-[#390908] opacity-30">visibility</span>
            </div>
        </div>

    </div>
</template>
