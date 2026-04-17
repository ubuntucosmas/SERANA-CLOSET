<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
	orders: Array,
	products: { type: Array, default: () => [] },
    archived_products: { type: Array, default: () => [] },
	categories: { type: Array, default: () => [] },
	gallery: { type: Array, default: () => [] },
    archived_gallery: { type: Array, default: () => [] },
	stats: { type: Object, default: () => ({}) },
	leads: { type: Array, default: () => [] },
	active_tab: { type: String, default: 'overview' }
});

const page = usePage();
const currentTab = ref(props.active_tab);
const showArchivedProducts = ref(false);
const showArchivedGallery = ref(false);

const filteredOrders = computed(() => {
	return props.orders.filter(o => currentTab.value === 'overview' ? o.type === 'standard' : o.type !== 'standard');
});

const productPreview = ref(null);
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
    productForm.image = null; // Only if replacing
    productPreview.value = product.image_url;
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

function submitProgressSnap() {
    if (!selectedOrder.value) return;
    progressSnapForm.post(route('studio.progress_snap', selectedOrder.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            progressSnapForm.reset();
        }
    });
}

function deleteThemeAsset(key) {
    if (confirm(`Reset ${key} to default?`)) {
        router.delete(route('admin.theme.destroy', key), {
            preserveScroll: true
        });
    }
}

const statuses = ['pending', 'designing', 'sewing', 'completed', 'shipped'];

const selectedOrder = ref(null);

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
                productForm.reset();
            }
        });
    } else {
        productForm.post(route('admin.catalog.store_product'), {
            onSuccess: () => {
                showAddProductModal.value = false;
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
.serif-text { font-family: "Noto Serif", serif; }
.body-text { font-family: "Space Grotesk", sans-serif; }

.grain-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 9999;
    opacity: 0.03;
    background-image: url(https://lh3.googleusercontent.com/aida-public/AB6AXuANd_1l5vSNlqCaap9xyuJ7wljCLCb-Q-fbaXEEoUYJ5a7CNPUv_rgLBn_Um2FTcqAOKu-EwaylogmNtIZBD9TjfaqUIcn783CYOEDel16ybTSd-kKdpIioKf4JPOtmNFDtDfa_cdU8ddffXw67rHSQKcLhHnPFJWY8mZtyJuO8cgPZVvi8NV1JhZOinwfpek7CHVaRUBhhKMrfjjRkwzJzYDNjJWzolUP8yDv8TlkT31janlAuesR5fnUQP-Wn96h13HtddXxGOu0);
}

.glow-indigo {
    box-shadow: 0 0 40px rgba(138, 157, 255, 0.15);
}

::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: #050505; }
::-webkit-scrollbar-thumb { background: #1C1B1B; border-radius: 0px; }
::-webkit-scrollbar-thumb:hover { background: #B9C3FF; }

.animate-in {
    animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form Styles for Clarity */
.curator-label {
    display: block;
    font-size: 9px;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    font-weight: 700;
    color: #454652;
    margin-bottom: 8px;
}

.curator-input {
    width: 100%;
    background: transparent;
    border: none;
    border-bottom: 1px solid #1C1B1B;
    padding: 12px 0;
    font-size: 1.25rem;
    font-family: inherit;
    color: #e5e2e1;
    outline: none;
    transition: all 0.3s ease;
}

.curator-input:focus {
    border-color: #B9C3FF;
    border-bottom-width: 2px;
}

.curator-textarea {
    width: 100%;
    background: transparent;
    border: 1px solid #1C1B1B;
    padding: 16px;
    font-size: 0.875rem;
    line-height: 1.6;
    color: #e5e2e1;
    outline: none;
    transition: all 0.3s ease;
}

.curator-textarea:focus {
    border-color: #B9C3FF;
}
</style>

<template>
    <Head title="THE ATELIER | CURATOR DASHBOARD" />

    <div class="flex h-screen bg-[#050505] text-[#e5e2e1] body-text overflow-hidden selection:bg-[#B9C3FF]/20 relative">
        <div class="grain-overlay"></div>

        <!-- Sidebar -->
        <nav class="h-screen w-64 flex-shrink-0 bg-[#0E0E0E] flex flex-col py-10 px-6 z-50 border-r border-[#1C1B1B]">
            <div class="mb-12">
                <span class="text-[#B9C3FF] serif-text italic text-xl tracking-tighter uppercase font-light">Serana Studio</span>
                <p class="text-[#454652] text-[10px] tracking-[0.2em] mt-1 font-medium uppercase text-opacity-50">V 1.0.42</p>
            </div>

            <div class="flex flex-col gap-y-2 flex-grow overflow-y-auto no-scrollbar">
                <button v-for="tab in [
                    { id: 'overview', label: 'Ready Orders', icon: 'grid_view' },
                    { id: 'orders', label: 'Custom Design', icon: 'architecture' },
                    { id: 'catalog', label: 'Product List', icon: 'inventory_2' },
                    { id: 'gallery', label: 'Client Gallery', icon: 'auto_awesome' },
                    { id: 'leads', label: 'Subscribers', icon: 'insights' },
                    { id: 'brand', label: 'Brand Assets', icon: 'palette' },
                    { id: 'settings', label: 'Measurements', icon: 'settings' }
                ]" :key="tab.id"
                @click="currentTab = tab.id"
                class="flex items-center gap-4 px-4 py-3 transition-all duration-300 border-l-4"
                :class="currentTab === tab.id ? 'bg-[#1C1B1B] text-[#B9C3FF] border-[#B9C3FF] translate-x-1' : 'text-[#454652] border-transparent hover:bg-[#1C1B1B] hover:text-[#B9C3FF]'">
                    <span class="material-symbols-outlined text-[20px]">{{ tab.icon }}</span>
                    <span class="text-sm font-light tracking-wide">{{ tab.label }}</span>
                </button>
            </div>

            <div class="mt-auto pt-8">
                <button v-if="currentTab !== 'gallery'" @click="showAddProductModal = true" class="w-full py-4 mb-8 bg-gradient-to-r from-[#B9C3FF] to-[#8A9DFF] text-[#092484] text-[10px] font-bold tracking-[0.2em] uppercase glow-indigo transition-all transform active:scale-95">
                    Add New Product
                </button>
                <button v-else @click="showAddGalleryModal = true" class="w-full py-4 mb-8 bg-gradient-to-r from-[#B9C3FF] to-[#8A9DFF] text-[#092484] text-[10px] font-bold tracking-[0.2em] uppercase glow-indigo transition-all transform active:scale-95">
                    Add Gallery Photo
                </button>
                <div class="flex flex-col gap-2">
                    <Link :href="route('home')" class="flex items-center gap-4 px-4 py-2 text-[#454652] hover:text-[#B9C3FF] transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">help_outline</span>
                        <span class="text-[11px] uppercase tracking-widest font-semibold">Support</span>
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="flex items-center gap-4 px-4 py-2 text-[#454652] hover:text-[#B9C3FF] transition-colors cursor-pointer w-full text-left">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        <span class="text-[11px] uppercase tracking-widest font-semibold">Sign Out</span>
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-grow bg-[#050505] p-12 overflow-y-auto no-scrollbar animate-in">
            <header class="flex justify-between items-end mb-16">
                <div>
                    <span class="text-[10px] text-[#8A9DFF] tracking-[0.4em] uppercase font-bold mb-2 block opacity-80">Studio Status</span>
                    <h1 class="serif-text text-5xl font-light text-[#B9C3FF] tracking-tight">
                        {{ currentTab === 'overview' ? 'Order Queue' : (currentTab === 'orders' ? 'Custom Lab' : (currentTab === 'gallery' ? 'Gallery' : (currentTab === 'brand' ? 'Brand Concept' : currentTab.charAt(0).toUpperCase() + currentTab.slice(1)))) }}
                    </h1>
                </div>
                <div class="text-right">
                    <p class="text-[#454652] text-[10px] tracking-widest uppercase font-bold">System Status</p>
                    <p class="text-[#B9C3FF] text-sm font-medium tracking-widest">ONLINE / OPTIMIZED</p>
                </div>
            </header>

            <!-- Stats/Bento Grid -->
            <div v-if="currentTab === 'overview'" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
                <div class="bg-[#0E0E0E] p-8 h-40 flex flex-col justify-between border border-[#1C1B1B] hover:bg-[#131313] transition-colors">
                    <p class="text-[10px] text-[#454652] tracking-widest uppercase font-bold">In Production</p>
                    <div>
                        <p class="serif-text text-4xl text-[#B9C3FF]">{{ stats.active_orders }}</p>
                        <p class="text-[9px] text-[#8A9DFF] tracking-tighter uppercase font-black">Active Orders</p>
                    </div>
                </div>
                <div class="bg-[#0E0E0E] p-8 h-40 flex flex-col justify-between border border-[#1C1B1B] hover:bg-[#131313] transition-colors">
                    <p class="text-[10px] text-[#454652] tracking-widest uppercase font-bold">Quality Score</p>
                    <div>
                        <p class="serif-text text-4xl text-[#B9C3FF]">99.8%</p>
                        <p class="text-[9px] text-[#8A9DFF] tracking-tighter uppercase font-black">Precision Rating</p>
                    </div>
                </div>
                <div class="bg-[#0E0E0E] p-8 h-40 flex flex-col justify-between border border-[#1C1B1B] hover:bg-[#131313] transition-colors">
                    <p class="text-[10px] text-[#454652] tracking-widest uppercase font-bold">Revenue Flow</p>
                    <div>
                        <p class="serif-text text-4xl text-[#B9C3FF]">KES {{ (stats.total_revenue / 1000).toFixed(1) }}k</p>
                        <p class="text-[9px] text-[#8A9DFF] tracking-tighter uppercase font-black">Month to Date</p>
                    </div>
                </div>
            </div>

            <div v-if="currentTab === 'overview' || currentTab === 'orders'">
                
                <!-- Desktop Tabular Registry -->
                <div class="hidden lg:block overflow-x-auto no-scrollbar border border-white/5 bg-[#0E0E0E]">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-white/5">
                                <th class="p-6 text-[10px] text-[#454652] tracking-[0.3em] uppercase font-bold">Registry</th>
                                <th class="p-6 text-[10px] text-[#454652] tracking-[0.3em] uppercase font-bold">Identifier</th>
                                <th class="p-6 text-[10px] text-[#454652] tracking-[0.3em] uppercase font-bold">Client / Partner</th>
                                <th class="p-6 text-[10px] text-[#454652] tracking-[0.3em] uppercase font-bold">Piece Designation</th>
                                <th class="p-6 text-[10px] text-[#454652] tracking-[0.3em] uppercase font-bold">Status</th>
                                <th class="p-6 text-[10px] text-[#454652] tracking-[0.3em] uppercase font-bold text-right">Value (KES)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(order, idx) in filteredOrders" :key="order.id" 
                                @click="selectOrder(order)"
                                @mouseenter="hoveredOrder = order"
                                @mouseleave="hoveredOrder = null"
                                class="border-b border-white/5 group cursor-pointer hover:bg-[#131313] transition-all duration-300"
                                :class="form.id === order.id ? 'bg-[#1C1B1B]/50' : ''">
                                
                                <td class="p-6">
                                    <div class="w-12 h-16 bg-[#050505] border border-white/5 overflow-hidden">
                                        <img :src="getOrderThumbnails(order)[0] || '/images/hero_editorial.png'" 
                                             class="w-full h-full object-cover grayscale opacity-50 group-hover:opacity-100 group-hover:grayscale-0 transition-all duration-700" />
                                    </div>
                                </td>
                                
                                <td class="p-6">
                                    <p class="text-[10px] text-[#B9C3FF] tracking-widest font-bold">#ORD-{{ String(order.id).padStart(4, '0') }}</p>
                                    <p class="text-[9px] text-[#454652] mt-1">{{ new Date(order.created_at).toLocaleDateString() }}</p>
                                </td>
                                
                                <td class="p-6">
                                    <p class="text-[11px] text-[#e5e2e1] font-medium tracking-wide">{{ order.full_name }}</p>
                                    <p class="text-[9px] text-[#454652] mt-1 font-bold uppercase tracking-widest">{{ order.type === 'bespoke' ? 'Bespoke Artisan' : 'Ready Acquisition' }}</p>
                                </td>
                                
                                <td class="p-6">
                                    <p class="serif-text text-sm font-light text-[#e5e2e1]">{{ order.garment_name || (order.type === 'standard' ? 'Ready-to-Wear' : 'Custom Piece') }}</p>
                                    <p class="text-[9px] text-[#454652] mt-1 italic line-clamp-1 max-w-[200px]">{{ order.internal_notes || 'No notes' }}</p>
                                </td>
                                
                                <td class="p-6">
                                    <span :class="`inline-flex items-center px-3 py-1 text-[8px] font-bold tracking-widest uppercase ${order.status === 'completed' ? 'text-[#B9C3FF]' : 'text-[#454652]'}`">
                                        <span :class="`w-1.5 h-1.5 rounded-full mr-2 ${order.status === 'completed' ? 'bg-[#B9C3FF] shadow-[0_0_8px_#B9C3FF]' : 'bg-[#454652]'}`"></span>
                                        {{ order.status }}
                                    </span>
                                </td>
                                
                                <td class="p-6 text-right">
                                    <p class="serif-text text-sm text-[#B9C3FF] tracking-tighter">{{ Number(order.price_quoted || 0).toLocaleString() }}</p>
                                    <p :class="`text-[8px] tracking-widest uppercase font-black mt-1 ${order.is_paid ? 'text-[#8A9DFF]' : 'text-red-900/50'}`">
                                        {{ order.is_paid ? 'Secured' : 'Unpaid' }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card Registry -->
                <div class="lg:hidden space-y-6 pb-20">
                    <div v-for="order in filteredOrders" :key="order.id" 
                        @click="selectOrder(order)"
                        class="bg-[#0E0E0E] p-6 border border-white/5 active:bg-[#131313] transition-all rounded-sm flex gap-6"
                        :class="form.id === order.id ? 'border-[#B9C3FF]/40' : ''">
                        
                        <div class="w-16 h-20 bg-[#050505] border border-white/5 flex-shrink-0 overflow-hidden">
                            <img :src="getOrderThumbnails(order)[0] || '/images/hero_editorial.png'" class="w-full h-full object-cover">
                        </div>

                        <div class="flex-grow flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start">
                                    <p class="text-[9px] text-[#B9C3FF] tracking-widest font-bold">#{{ String(order.id).padStart(4, '0') }}</p>
                                    <span :class="`text-[7px] font-black tracking-widest uppercase px-2 py-1 bg-white/5 ${order.status === 'completed' ? 'text-primary' : 'text-white/40'}`">
                                        {{ order.status }}
                                    </span>
                                </div>
                                <p class="text-[13px] text-white mt-1 font-medium">{{ order.full_name }}</p>
                                <p class="serif-text text-xs text-white/40 italic">{{ order.garment_name }}</p>
                            </div>
                            <div class="flex justify-between items-end mt-2">
                                <p class="text-[10px] text-primary font-black">KES {{ Number(order.price_quoted || 0).toLocaleString() }}</p>
                                <p class="text-[7px] text-white/20 tracking-tighter">{{ new Date(order.created_at).toLocaleDateString() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Catalog -->
            <div v-if="currentTab === 'catalog'">
                <div class="flex items-center justify-between mb-10 pb-6 border-b border-[#1C1B1B]">
                    <div class="flex gap-8">
                        <button @click="showArchivedProducts = false" 
                                class="text-[10px] tracking-[0.3em] uppercase font-bold transition-all"
                                :class="!showArchivedProducts ? 'text-[#B9C3FF] border-b border-[#B9C3FF] pb-1' : 'text-[#454652] hover:text-[#B9C3FF]'">
                            Live Collection ({{ products.length }})
                        </button>
                        <button @click="showArchivedProducts = true" 
                                class="text-[10px] tracking-[0.3em] uppercase font-bold transition-all"
                                :class="showArchivedProducts ? 'text-[#B9C3FF] border-b border-[#B9C3FF] pb-1' : 'text-[#454652] hover:text-[#B9C3FF]'">
                            Archived Registry ({{ archived_products.length }})
                        </button>
                    </div>
                </div>

                <div v-if="!showArchivedProducts" class="grid grid-cols-1 md:grid-cols-3 gap-10 animate-in">
                    <div v-for="product in products" :key="product.id" class="bg-[#0E0E0E] p-4 border border-[#1C1B1B] group">
                        <div class="aspect-[3/4] bg-[#050505] overflow-hidden mb-6 relative">
                            <img :src="product.image_url" class="w-full h-full object-cover grayscale opacity-60 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-1000" />
                            <div class="absolute inset-x-0 bottom-0 p-6 bg-gradient-to-t from-[#0E0E0E] to-transparent">
                                <span class="text-[9px] text-[#8A9DFF] tracking-widest uppercase font-bold">KES {{ Number(product.price).toLocaleString() }}</span>
                            </div>
                        </div>
                        <div class="px-2 pb-4 flex justify-between items-end">
                            <div>
                                <h3 class="serif-text text-xl text-[#e5e2e1] font-light">{{ product.name }}</h3>
                                <p class="text-[10px] text-[#454652] tracking-widest uppercase mt-2">Active Collection</p>
                            </div>
                            <div class="flex gap-4">
                                <button @click.stop="editProduct(product)" class="text-[#454652] hover:text-[#B9C3FF] transition-colors" title="Edit Piece">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                <button @click.stop="deleteProduct(product.id)" class="text-[#454652] hover:text-red-500 transition-colors" title="Unpublish (Archive)">
                                    <span class="material-symbols-outlined text-sm">archive</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-10 animate-in">
                    <div v-for="product in archived_products" :key="product.id" class="bg-[#0E0E0E] p-4 border border-[#1C1B1B]/40 group opacity-70 hover:opacity-100 transition-opacity">
                        <div class="aspect-[3/4] bg-[#050505] overflow-hidden mb-6 relative grayscale">
                            <img :src="product.image_url" class="w-full h-full object-cover opacity-30" />
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-[8px] tracking-[0.4em] uppercase font-bold text-white/20 bg-black/40 px-4 py-2 border border-white/5 backdrop-blur-sm italic">Archived</span>
                            </div>
                        </div>
                        <div class="px-2 pb-4 flex justify-between items-end">
                            <div>
                                <h3 class="serif-text text-xl text-[#B9C3FF]/50 font-light">{{ product.name }}</h3>
                                <p class="text-[10px] text-[#454652] tracking-widest uppercase mt-2">Offline Product</p>
                            </div>
                            <div class="flex gap-4">
                                <button @click.stop="restoreProduct(product.id)" class="text-[#B9C3FF] hover:text-white transition-colors" title="Publish (Restore)">
                                    <span class="material-symbols-outlined text-sm">unarchive</span>
                                </button>
                                <button @click.stop="forceDeleteProduct(product.id)" class="text-[#454652] hover:text-red-500 transition-colors" title="Permanent Purge">
                                    <span class="material-symbols-outlined text-sm">delete_forever</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="archived_products.length === 0" class="col-span-full py-20 text-center border border-dashed border-[#1C1B1B]">
                        <p class="text-[10px] text-[#454652] tracking-[0.5em] uppercase font-bold">No pieces in the archive</p>
                    </div>
                </div>
            </div>

            <!-- Gallery -->
            <div v-if="currentTab === 'gallery'">
                <div class="flex items-center justify-between mb-10 pb-6 border-b border-[#1C1B1B]">
                    <div class="flex gap-8">
                        <button @click="showArchivedGallery = false" 
                                class="text-[10px] tracking-[0.3em] uppercase font-bold transition-all"
                                :class="!showArchivedGallery ? 'text-[#B9C3FF] border-b border-[#B9C3FF] pb-1' : 'text-[#454652] hover:text-[#B9C3FF]'">
                            Live Gallery ({{ gallery.length }})
                        </button>
                        <button @click="showArchivedGallery = true" 
                                class="text-[10px] tracking-[0.3em] uppercase font-bold transition-all"
                                :class="showArchivedGallery ? 'text-[#B9C3FF] border-b border-[#B9C3FF] pb-1' : 'text-[#454652] hover:text-[#B9C3FF]'">
                            Showcase Archive ({{ archived_gallery.length }})
                        </button>
                    </div>
                </div>

                <div v-if="!showArchivedGallery" class="grid grid-cols-1 md:grid-cols-2 gap-12 animate-in">
                    <div v-for="item in gallery" :key="item.id" class="bg-[#0E0E0E] p-10 border border-[#1C1B1B] group">
                        <div class="aspect-[16/9] bg-[#050505] mb-8 overflow-hidden relative border border-[#1C1B1B]">
                            <img :src="item.image_url" class="w-full h-full object-cover grayscale opacity-40 group-hover:opacity-100 group-hover:grayscale-0 transition-all duration-1000" />
                            <div class="absolute bottom-6 left-6 px-4 py-2 bg-[#0E0E0E]/80 backdrop-blur-md border border-[#1C1B1B]">
                                <span class="text-[9px] text-[#B9C3FF] tracking-widest uppercase font-bold">Piece: {{ item.garment_name }}</span>
                            </div>
                        </div>
                        <div class="px-2">
                            <h3 class="serif-text text-3xl text-[#e5e2e1] font-light mb-4">{{ item.client_name }}</h3>
                            <p class="text-sm text-[#454652] leading-loose italic opacity-80">"{{ item.testimonial }}"</p>
                        </div>
                        <div class="mt-10 pt-8 border-t border-[#1C1B1B] flex justify-end gap-6">
                            <button @click="editGallery(item)" class="text-[10px] text-[#454652] hover:text-[#B9C3FF] tracking-widest uppercase font-bold transition-colors">Edit Photo</button>
                            <button @click="deleteGallery(item.id)" class="text-[10px] text-[#454652] hover:text-red-500 tracking-widest uppercase font-bold transition-colors">Unpublish Photo</button>
                        </div>
                    </div>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-12 animate-in">
                    <div v-for="item in archived_gallery" :key="item.id" class="bg-[#0E0E0E] p-10 border border-[#1C1B1B]/40 group opacity-70 hover:opacity-100 transition-opacity">
                        <div class="aspect-[16/9] bg-[#050505] mb-8 overflow-hidden relative border border-[#1C1B1B] grayscale">
                            <img :src="item.image_url" class="w-full h-full object-cover opacity-30" />
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-[8px] tracking-[0.4em] uppercase font-bold text-white/20 bg-black/40 px-4 py-2 border border-white/5 backdrop-blur-sm italic">Archived</span>
                            </div>
                        </div>
                        <div class="px-2">
                            <h3 class="serif-text text-3xl text-[#B9C3FF]/50 font-light mb-4">{{ item.client_name }}</h3>
                            <p class="text-[10px] text-[#454652] tracking-widest uppercase font-bold">Offline Showcase</p>
                        </div>
                        <div class="mt-10 pt-8 border-t border-[#1C1B1B] flex justify-end gap-10">
                            <button @click="restoreGallery(item.id)" class="text-[10px] text-[#B9C3FF] hover:text-white tracking-widest uppercase font-bold transition-colors">Publish Photo</button>
                            <button @click="forceDeleteGallery(item.id)" class="text-[10px] text-[#454652] hover:text-red-500 tracking-widest uppercase font-bold transition-colors">Purge Permanently</button>
                        </div>
                    </div>
                    <div v-if="archived_gallery.length === 0" class="col-span-full py-40 text-center border border-dashed border-[#1C1B1B]">
                        <p class="text-[10px] text-[#454652] tracking-[0.5em] uppercase font-bold">No showcases in the archive</p>
                    </div>
                </div>
            </div>

            <!-- Subscribers -->
            <div v-if="currentTab === 'leads'" class="space-y-4 animate-in">
                <div v-for="lead in leads" :key="lead.id" class="bg-[#0E0E0E] p-8 border border-[#1C1B1B] flex items-center justify-between group hover:bg-[#131313] transition-colors">
                    <div class="flex items-center gap-8">
                        <div class="w-12 h-12 bg-[#050505] border border-[#1C1B1B] flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#B9C3FF] text-lg font-light">mail</span>
                        </div>
                        <div>
                            <p class="serif-text text-xl text-[#e5e2e1] font-light">{{ lead.email }}</p>
                            <p class="text-[9px] text-[#454652] tracking-widest uppercase mt-1">Subscribed: Ref #{{ String(lead.id).padStart(4, '0') }}</p>
                        </div>
                    </div>
                    <span class="text-[10px] text-[#B9C3FF] tracking-[0.4em] uppercase font-bold opacity-40 group-hover:opacity-100 transition-opacity">Active Member</span>
                </div>
            </div>

            <!-- Brand Assets -->
            <div v-if="currentTab === 'brand'" class="max-w-6xl animate-in space-y-12 pb-20">
                
                <!-- 1. Core Identity -->
                <div class="bg-[#0E0E0E] p-12 border border-[#1C1B1B]">
                    <div class="flex justify-between items-start mb-12">
                        <div>
                            <h3 class="serif-text text-3xl text-[#B9C3FF] font-light mb-2">Core Identity</h3>
                            <p class="text-[10px] text-[#454652] tracking-widest uppercase font-bold">Logos & Symbols</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                        <!-- Site Logo -->
                        <div class="space-y-6">
                            <label class="curator-label uppercase">Application Logo (Light)</label>
                            <div class="h-40 bg-[#050505] border border-[#1C1B1B] relative group flex items-center justify-center">
                                <img v-if="$page.props.theme_settings.site_logo" :src="$page.props.theme_settings.site_logo" class="max-h-24 object-contain">
                                <span v-else class="serif-text text-2xl italic text-[#454652] opacity-40 uppercase tracking-tighter">Serana Studio</span>
                                
                                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-4">
                                    <input type="file" id="logo-up" @input="updateThemeAsset('site_logo', $event.target.files[0])" class="hidden">
                                    <label for="logo-up" class="px-6 py-2 bg-[#B9C3FF] text-[#092484] text-[9px] font-black tracking-widest uppercase cursor-pointer">Upload</label>
                                    <button v-if="$page.props.theme_settings.site_logo" @click="deleteThemeAsset('site_logo')" class="px-6 py-2 bg-white/10 text-white text-[9px] font-black tracking-widest uppercase">Reset</button>
                                </div>
                            </div>
                        </div>

                        <!-- Favicon -->
                        <div class="space-y-6">
                            <label class="curator-label uppercase">Site Favicon (Browser Icon)</label>
                            <div class="h-40 bg-[#050505] border border-[#1C1B1B] relative group flex items-center justify-center">
                                <img v-if="$page.props.theme_settings.site_favicon" :src="$page.props.theme_settings.site_favicon" class="w-12 h-12 object-contain">
                                <span v-else class="material-symbols-outlined text-4xl text-[#454652] opacity-40">tab</span>
                                
                                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-4">
                                    <input type="file" id="fav-up" @input="updateThemeAsset('site_favicon', $event.target.files[0])" class="hidden">
                                    <label for="fav-up" class="px-6 py-2 bg-[#B9C3FF] text-[#092484] text-[9px] font-black tracking-widest uppercase cursor-pointer">Upload</label>
                                    <button v-if="$page.props.theme_settings.site_favicon" @click="deleteThemeAsset('site_favicon')" class="px-6 py-2 bg-white/10 text-white text-[9px] font-black tracking-widest uppercase">Reset</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Cinematic Media -->
                <div class="bg-[#0E0E0E] p-12 border border-[#1C1B1B]">
                    <div class="mb-12">
                        <h3 class="serif-text text-3xl text-[#B9C3FF] font-light mb-2">Cinematic Media</h3>
                        <p class="text-[10px] text-[#454652] tracking-widest uppercase font-bold">Full-Screen & Category Backdrops</p>
                    </div>

                    <!-- Hero Asset Slot -->
                    <div class="mb-16">
                        <label class="curator-label mb-6">Main Hero Backdrop</label>
                        <div class="aspect-[21/9] bg-[#050505] border border-[#1C1B1B] relative group overflow-hidden">
                            <img v-if="$page.props.theme_settings.home_hero_bg" :src="$page.props.theme_settings.home_hero_bg" class="w-full h-full object-cover">
                            <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-[#454652] opacity-30">
                                <span class="material-symbols-outlined text-6xl font-light">landscape</span>
                                <p class="text-[10px] tracking-widest uppercase mt-4">Static Fallback Active</p>
                            </div>
                            <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-6">
                                <input type="file" id="hero-up" @input="updateThemeAsset('home_hero_bg', $event.target.files[0])" class="hidden">
                                <label for="hero-up" class="px-10 py-3 bg-[#B9C3FF] text-[#092484] text-[10px] font-black tracking-widest uppercase cursor-pointer">Update Backdrop</label>
                                <button v-if="$page.props.theme_settings.home_hero_bg" @click="deleteThemeAsset('home_hero_bg')" class="px-10 py-3 bg-white/10 text-white text-[10px] font-black tracking-widest uppercase">Remove Custom</button>
                            </div>
                        </div>
                    </div>

                    <!-- Category Assets Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                            <div v-for="cat in [
                                { key: 'cat_men_bg', label: 'Men\'s Collection' },
                                { key: 'cat_women_bg', label: 'Women\'s Collection' },
                                { key: 'cat_acc_bg', label: 'Accessories' },
                                { key: 'hero_detail_bg', label: 'Hero Detail Overlay' }
                            ]" :key="cat.key" class="space-y-4">
                            <label class="curator-label">{{ cat.label }} Overlay</label>
                            <div class="aspect-[4/5] bg-[#050505] border border-[#1C1B1B] relative group overflow-hidden">
                                <img v-if="$page.props.theme_settings[cat.key]" :src="$page.props.theme_settings[cat.key]" class="w-full h-full object-cover">
                                <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-[#454652] opacity-30">
                                    <span class="material-symbols-outlined text-4xl font-light">image</span>
                                </div>
                                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-4">
                                    <input :id="cat.key" type="file" @input="updateThemeAsset(cat.key, $event.target.files[0])" class="hidden">
                                    <label :for="cat.key" class="px-6 py-2 bg-white text-black text-[9px] font-black tracking-widest uppercase cursor-pointer">Replace</label>
                                    <button v-if="$page.props.theme_settings[cat.key]" @click="deleteThemeAsset(cat.key)" class="px-6 py-2 bg-red-900/40 text-red-100 text-[9px] font-black tracking-widest uppercase">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Focal Images (Marketing Shots) -->
                    <div class="mt-20 pt-16 border-t border-[#1C1B1B]">
                        <div class="mb-12">
                            <h4 class="serif-text text-2xl text-[#e5e2e1] font-light mb-2">Editorial Focal Points</h4>
                            <p class="text-[10px] text-[#454652] tracking-widest uppercase font-bold">The rotating high-fidelity marketing shots</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                            <div v-for="i in [1, 2, 3]" :key="i" class="space-y-6">
                                <label class="curator-label">Focal Shot {{ i }}</label>
                                <div class="aspect-[3/4] bg-[#050505] border border-[#1C1B1B] relative group overflow-hidden">
                                    <img v-if="$page.props.theme_settings['hero_focal_' + i]" :src="$page.props.theme_settings['hero_focal_' + i]" class="w-full h-full object-cover grayscale opacity-80 group-hover:grayscale-0 group-hover:opacity-100 transition-all">
                                    <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-[#454652] opacity-30">
                                        <span class="material-symbols-outlined text-4xl font-light">portrait</span>
                                    </div>
                                    <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-4 px-6 text-center">
                                        <input :id="'focal-' + i" type="file" @input="updateThemeAsset('hero_focal_' + i, $event.target.files[0])" class="hidden">
                                        <label :for="'focal-' + i" class="px-6 py-2 bg-[#B9C3FF] text-[#092484] text-[9px] font-black tracking-widest uppercase cursor-pointer">Update Photo</label>
                                        <button v-if="$page.props.theme_settings['hero_focal_' + i]" @click="deleteThemeAsset('hero_focal_' + i)" class="px-6 py-2 bg-white/10 text-white text-[9px] font-black tracking-widest uppercase">Reset</button>
                                    </div>
                                </div>
                                <div class="pt-4">
                                    <label class="curator-label text-[8px] opacity-60">Visual Label</label>
                                    <input :value="$page.props.theme_settings['hero_focal_label_' + i]" 
                                           @blur="updateThemeAsset('hero_focal_label_' + i, $event.target.value)"
                                           class="curator-input text-xs pt-2" placeholder="e.g. Signature Series">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 3. System Nomenclature & Socials -->
                <div class="bg-[#0E0E0E] p-12 border border-[#1C1B1B]">
                    <div class="mb-12">
                        <h3 class="serif-text text-3xl text-[#B9C3FF] font-light mb-2">Technical Registry</h3>
                        <p class="text-[10px] text-[#454652] tracking-widest uppercase font-bold">Metadata & Global Strings</p>
                    </div>

                    <div class="space-y-12">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                            <div>
                                <label class="curator-label">Site Headline</label>
                                <input :value="$page.props.theme_settings.site_headline" @blur="updateThemeAsset('site_headline', $event.target.value)" class="curator-input text-base" placeholder="THE ATELIER">
                            </div>
                            <div>
                                <label class="curator-label">Support Email</label>
                                <input :value="$page.props.theme_settings.support_email" @blur="updateThemeAsset('support_email', $event.target.value)" class="curator-input text-base" placeholder="curator@serana.com">
                            </div>
                            <div>
                                <label class="curator-label text-[#B9C3FF]">Platform WhatsApp (Ordering)</label>
                                <input :value="$page.props.theme_settings.whatsapp_number || $page.props.whatsapp_number" @blur="updateThemeAsset('whatsapp_number', $event.target.value)" class="curator-input text-base border-b-[#B9C3FF]/30" placeholder="254700000000">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-12 pt-8 border-t border-[#1C1B1B]">
                            <div>
                                <label class="curator-label">Currency Symbol</label>
                                <input :value="$page.props.theme_settings.currency || 'KSh'" @blur="updateThemeAsset('currency', $event.target.value)" class="curator-input text-base border-b-[#B9C3FF]/30 text-[#B9C3FF]" placeholder="KSh">
                            </div>
                            <div>
                                <label class="curator-label">Instagram Handle (URL)</label>
                                <input :value="$page.props.theme_settings.instagram_url" @blur="updateThemeAsset('instagram_url', $event.target.value)" class="curator-input text-base" placeholder="https://instagram.com/serana_studio">
                            </div>
                            <div>
                                <label class="curator-label">Footer Copyright Text</label>
                                <input :value="$page.props.theme_settings.footer_copyright" @blur="updateThemeAsset('footer_copyright', $event.target.value)" class="curator-input text-base" placeholder="© 2026 SERANA CLOSET. NAIROBI.">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Measurements -->
            <div v-if="currentTab === 'settings'" class="max-w-4xl animate-in">
                <div class="bg-[#0E0E0E] p-12 border border-[#1C1B1B] mb-12">
                    <h3 class="serif-text text-3xl text-[#B9C3FF] font-light mb-8">Measurement Settings</h3>
                    <p class="text-sm text-[#454652] leading-loose mb-12 max-w-2xl">Manage your global sizing presets and measurement standards here. These settings ensure a perfect fit across all custom and ready-to-wear pieces.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                        <div class="p-8 border border-[#1C1B1B] bg-[#050505]">
                            <p class="text-[10px] text-[#B9C3FF] tracking-widest uppercase font-bold mb-4">Size Variation</p>
                            <div class="flex justify-between items-end">
                                <span class="serif-text text-4xl font-light">± 0.2cm</span>
                                <span class="text-[9px] text-[#454652] tracking-widest uppercase font-bold py-2 px-4 border border-[#1C1B1B]">Adjust</span>
                            </div>
                        </div>
                        <div class="p-8 border border-[#1C1B1B] bg-[#050505]">
                            <p class="text-[10px] text-[#B9C3FF] tracking-widest uppercase font-bold mb-4">System Overrides</p>
                            <div class="flex justify-between items-end">
                                <span class="serif-text text-4xl font-light italic">Inactive</span>
                                <span class="text-[9px] text-[#454652] tracking-widest uppercase font-bold py-2 px-4 border border-[#1C1B1B]">Enable</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Right Side Panel -->
        <aside class="w-[450px] flex-shrink-0 bg-[#0E0E0E] p-12 overflow-y-auto no-scrollbar border-l border-[#1C1B1B] transition-all duration-700 animate-in" v-if="form.id">
            <header class="mb-14">
                <div class="flex justify-between items-start mb-6">
                    <h2 class="serif-text text-3xl font-light text-[#B9C3FF]">Order Sheet</h2>
                    <button @click="form.id = null" class="material-symbols-outlined text-[#454652] text-2xl hover:text-[#B9C3FF] transition-colors">close</button>
                </div>
                <p class="text-[10px] text-[#454652] tracking-[0.4em] uppercase font-bold mb-1 opacity-60">ID: #ORD-{{ String(form.id).padStart(4, '0') }}</p>
                <div class="w-12 h-[1px] bg-[#B9C3FF]/30 mt-4"></div>
            </header>

            <div class="mb-14 space-y-10">
                <div class="aspect-[3/4] bg-[#1C1B1B] mb-10 overflow-hidden border border-[#1C1B1B] shadow-2xl relative group">
                    <img v-if="getOrderThumbnails(selectedOrder).length" 
                         :src="getOrderThumbnails(selectedOrder)[0]" 
                         class="w-full h-full object-cover grayscale opacity-80 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-1000" />
                    <div v-else class="w-full h-full flex items-center justify-center text-[#454652] opacity-20">
                        <span class="material-symbols-outlined text-8xl font-light">image</span>
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-[#0E0E0E] via-transparent to-transparent opacity-60"></div>
                </div>

                <div class="space-y-10">
                    <div class="grid grid-cols-2 gap-8">
                        <div v-for="m in [['Bust', selectedOrder?.bust_cm + ' cm'], ['Waist', selectedOrder?.waist_cm + ' cm'], ['Hips', selectedOrder?.hips_cm + ' cm'], ['Material', selectedOrder?.fabric_preference || 'Standard Cotton']]" :key="m[0]">
                            <p class="text-[9px] text-[#454652] tracking-widest uppercase mb-2 font-bold">{{ m[0] }}</p>
                            <p class="serif-text text-2xl text-[#e5e2e1] font-light">{{ m[1] || '--' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-20">
                <p class="text-[10px] text-[#454652] tracking-widest uppercase font-bold mb-10 opacity-60">Status Tracking</p>
                <div class="space-y-12 relative ml-1">
                    <div class="absolute left-[5px] top-0 bottom-0 w-[1px] bg-[#1C1B1B]"></div>
                    <div v-for="(p, i) in [{ label: 'Cutting Fabric', active: true, done: true }, { label: 'Sewing Process', active: true, done: false, progress: 42 }, { label: 'Quality Check', active: false, done: false }]" :key="p.label" class="relative pl-10 group">
                        <div :class="`absolute left-0 top-1 w-2.5 h-2.5 transition-all duration-700 ${p.done ? 'bg-[#8A9DFF]' : (p.active ? 'bg-[#B9C3FF] glow-indigo' : 'bg-[#454652]')}`"></div>
                        <p :class="`text-[10px] tracking-[0.2em] uppercase font-bold ${p.active || p.done ? 'text-[#e5e2e1]' : 'text-[#454652]'}`">{{ p.label }}</p>
                        <p class="text-[11px] text-[#454652] mt-2 font-medium uppercase tracking-widest italic opacity-60">{{ p.done ? 'Finished' : (p.active ? `In Progress / ${p.progress}%` : 'Waiting') }}</p>
                        <div v-if="p.active && !p.done" class="mt-4 w-full h-[2px] bg-[#1C1B1B]"><div class="h-full bg-[#B9C3FF] transition-all duration-1000" :style="{ width: p.progress + '%' }"></div></div>
                    </div>
                </div>
            </div>

                <div class="mt-12 pt-10 border-t border-white/10 space-y-8">
                    
                    <!-- Core Order Controls (Restored) -->
                    <div class="space-y-6">
                        <div>
                            <label class="curator-label">Internal Process Notes</label>
                            <textarea v-model="form.internal_notes" rows="3" class="curator-textarea no-scrollbar text-sm" placeholder="Add private notes about this order..."></textarea>
                        </div>
                        <div>
                            <label class="curator-label">Fulfillment Phase</label>
                            <select v-model="form.status" class="w-full bg-[#050505] border border-white/5 p-4 text-[10px] tracking-widest uppercase font-black text-[#B9C3FF] focus:border-[#B9C3FF] outline-none">
                                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                    </div>

                    <p class="text-[10px] text-[#B9C3FF] tracking-[0.6em] uppercase font-bold italic mb-4 pt-4">Artisan Logbook</p>
                    
                    <!-- Progress Photo Upload -->
                    <div class="bg-[#1C1B1B]/40 p-6 border border-white/5 space-y-6">
                        <div class="flex items-center justify-between">
                             <p class="text-[9px] text-white/40 uppercase tracking-[0.3em] font-black">Upload Build Progress</p>
                             <span v-if="progressSnapForm.processing" class="text-[8px] text-[#B9C3FF] animate-pulse">UPLOADING_STREAM_</span>
                        </div>
                        
                        <input type="file" id="snap-up" @input="progressSnapForm.image = $event.target.files[0]" class="hidden" />
                        <label for="snap-up" class="block w-full py-4 border border-dashed border-white/10 text-center cursor-pointer hover:border-[#B9C3FF] transition-all">
                            <span v-if="!progressSnapForm.image" class="text-[9px] text-white/20 uppercase tracking-widest">Select Snapshot</span>
                            <span v-else class="text-[9px] text-[#B9C3FF] uppercase tracking-widest font-black">{{ progressSnapForm.image.name }}</span>
                        </label>
                        
                        <textarea v-model="progressSnapForm.description" rows="2" class="curator-textarea text-[11px] h-auto py-3" placeholder="Brief process note..."></textarea>
                        
                        <div v-if="progressSnapForm.progress" class="h-[1px] bg-white/5 w-full">
                            <div class="h-full bg-[#B9C3FF] transition-all duration-300" :style="{ width: progressSnapForm.progress.percentage + '%' }"></div>
                        </div>

                        <button @click="submitProgressSnap" :disabled="progressSnapForm.processing || !progressSnapForm.image"
                                class="w-full py-4 bg-white/5 text-white/40 hover:bg-[#B9C3FF] hover:text-[#092484] text-[9px] font-black tracking-[0.4em] uppercase transition-all disabled:opacity-30">
                            {{ progressSnapForm.processing ? 'TRANSFERRING...' : 'Sync to Registry' }}
                        </button>
                    </div>

                    <button @click="updateOrder" :disabled="form.processing"
                            class="w-full py-5 bg-[#B9C3FF] text-[#092484] text-[10px] font-black tracking-[0.3em] uppercase glow-indigo disabled:opacity-50">
                        {{ form.processing ? 'DOCUMENTING...' : 'Update Order Status' }}
                    </button>
                </div>
        </aside>

        <!-- Product Modal -->
        <div v-if="showAddProductModal" class="fixed inset-0 z-[200] flex items-center justify-center p-6 sm:p-10">
            <div @click="showAddProductModal = false; isEditingProduct = false; productPreview = null; productForm.reset()" class="absolute inset-0 bg-[#050505]/95 backdrop-blur-3xl"></div>
            <div class="relative w-full max-w-5xl bg-[#0E0E0E] border border-[#1C1B1B] shadow-[0_40px_100_rgba(0,0,0,1)] flex flex-col md:flex-row overflow-hidden animate-in">
                <div class="w-full md:w-1/2 p-20 border-r border-[#1C1B1B]">
                    <span class="text-[10px] text-[#454652] tracking-[0.4em] uppercase font-bold mb-4 block">{{ isEditingProduct ? 'Editorial Management' : 'Product Entry' }}</span>
                    <h2 class="serif-text text-4xl font-light text-[#B9C3FF] mb-12">{{ isEditingProduct ? 'Edit Piece' : 'Product Details' }}</h2>
                    <div class="space-y-10">
                        <div>
                            <label class="curator-label">Product Name</label>
                            <input v-model="productForm.name" placeholder="e.g. Silk Evening Gown" class="curator-input" />
                        </div>
                        <div class="grid grid-cols-2 gap-10">
                            <div>
                                <label class="curator-label">Price (KES)</label>
                                <input v-model="productForm.price" type="number" placeholder="0.00" class="curator-input" />
                            </div>
                            <div>
                                <label class="curator-label">Category</label>
                                <select v-model="productForm.category_id" class="w-full bg-transparent border-b border-[#1C1B1B] py-3 text-[10px] tracking-widest uppercase font-bold text-[#e5e2e1] outline-none focus:border-[#B9C3FF]">
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="curator-label">Description</label>
                            <textarea v-model="productForm.description" rows="4" placeholder="Detail the construction and materials..." class="curator-textarea no-scrollbar"></textarea>
                        </div>
                    </div>
                    <div v-if="productForm.wasSuccessful" class="mt-8 p-4 bg-[#B9C3FF]/10 border border-[#B9C3FF]/20 text-center">
                         <p class="text-[9px] text-[#B9C3FF] tracking-[0.4em] font-black uppercase">COMMISSION_SYNC_COMPLETE</p>
                    </div>
                    <button @click="submitProduct" :disabled="productForm.processing || productForm.wasSuccessful" class="w-full py-6 mt-10 bg-[#B9C3FF] text-[#092484] text-[11px] font-black tracking-[0.4em] uppercase glow-indigo transition-all transform active:scale-95 disabled:opacity-50">
                        {{ productForm.processing ? 'PROCESSING_COMMISSION...' : (productForm.wasSuccessful ? 'RECORDED' : (isEditingProduct ? 'Update Collection Piece' : 'Add to Collection')) }}
                    </button>
                </div>
                <div class="w-full md:w-1/2 bg-[#050505] relative flex flex-col">
                    <!-- Main Image Slot -->
                    <div class="flex-grow relative flex items-center justify-center border-b border-[#1C1B1B]">
                        <input type="file" id="product-img" @input="productForm.image = $event.target.files[0]" class="hidden" />
                        <label for="product-img" class="cursor-pointer group text-center p-10 z-10">
                            <span class="material-symbols-outlined text-5xl text-[#454652] group-hover:text-[#B9C3FF] transition-all">add_a_photo</span>
                            <p class="text-[9px] text-[#454652] tracking-widest uppercase mt-4 group-hover:text-[#B9C3FF]">{{ isEditingProduct ? 'Replace Photo' : 'Primary Photo' }}</p>
                        </label>
                        <img v-if="productForm.image" :src="getObjectURL(productForm.image)" class="absolute inset-0 w-full h-full object-cover grayscale opacity-50 pointer-events-none" />
                        <img v-else-if="productPreview" :src="productPreview" class="absolute inset-0 w-full h-full object-cover grayscale opacity-50 pointer-events-none" />
                    </div>

                    <!-- Secondary Image Slots -->
                    <div class="h-1/3 grid grid-cols-2">
                        <div class="relative flex items-center justify-center border-r border-[#1C1B1B]">
                            <input type="file" id="sec-img-1" @input="productForm.secondary_image_1 = $event.target.files[0]" class="hidden" />
                            <label for="sec-img-1" class="cursor-pointer group text-center p-4 z-10">
                                <span class="material-symbols-outlined text-3xl text-[#454652] group-hover:text-[#B9C3FF] transition-all">add_photo_alternate</span>
                                <p class="text-[8px] text-[#454652] tracking-widest uppercase mt-2 group-hover:text-[#B9C3FF]">{{ isEditingProduct ? 'Update Angle 02' : 'Angle 02' }}</p>
                            </label>
                            <img v-if="productForm.secondary_image_1" :src="getObjectURL(productForm.secondary_image_1)" class="absolute inset-0 w-full h-full object-cover grayscale opacity-40 pointer-events-none" />
                        </div>
                        <div class="relative flex items-center justify-center">
                            <input type="file" id="sec-img-2" @input="productForm.secondary_image_2 = $event.target.files[0]" class="hidden" />
                            <label for="sec-img-2" class="cursor-pointer group text-center p-4 z-10">
                                <span class="material-symbols-outlined text-3xl text-[#454652] group-hover:text-[#B9C3FF] transition-all">add_photo_alternate</span>
                                <p class="text-[8px] text-[#454652] tracking-widest uppercase mt-2 group-hover:text-[#B9C3FF]">{{ isEditingProduct ? 'Update Angle 03' : 'Angle 03' }}</p>
                            </label>
                            <img v-if="productForm.secondary_image_2" :src="getObjectURL(productForm.secondary_image_2)" class="absolute inset-0 w-full h-full object-cover grayscale opacity-40 pointer-events-none" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gallery Modal -->
        <div v-if="showAddGalleryModal" class="fixed inset-0 z-[200] flex items-center justify-center p-6 sm:p-10">
            <div @click="showAddGalleryModal = false; isEditingGallery = false; galleryPreview = null; galleryForm.reset()" class="absolute inset-0 bg-[#050505]/95 backdrop-blur-3xl"></div>
            <div class="relative w-full max-w-5xl bg-[#0E0E0E] border border-[#1C1B1B] shadow-[0_40px_100px_rgba(0,0,0,1)] flex flex-col md:flex-row overflow-hidden animate-in">
                <div class="w-full md:w-1/2 p-20 border-r border-[#1C1B1B]">
                    <span class="text-[10px] text-[#454652] tracking-[0.4em] uppercase font-bold mb-4 block">{{ isEditingGallery ? 'Showcase Refinement' : 'New Photo' }}</span>
                    <h2 class="serif-text text-4xl font-light text-[#B9C3FF] mb-12">{{ isEditingGallery ? 'Edit Showcase' : 'Add to Gallery' }}</h2>
                    <div class="space-y-10">
                        <div>
                            <label class="curator-label">Client Name</label>
                            <input v-model="galleryForm.client_name" placeholder="Name of Client" class="curator-input" />
                        </div>
                        <div>
                            <label class="curator-label">Garment Name</label>
                            <input v-model="galleryForm.garment_name" placeholder="Piece worn by client" class="curator-input" />
                        </div>
                        <div>
                            <label class="curator-label">Description</label>
                            <textarea v-model="galleryForm.testimonial" rows="4" placeholder="Share the client's experience..." class="curator-textarea no-scrollbar"></textarea>
                        </div>
                    </div>
                    <div v-if="galleryForm.wasSuccessful" class="mt-8 p-4 bg-[#B9C3FF]/10 border border-[#B9C3FF]/20 text-center">
                         <p class="text-[9px] text-[#B9C3FF] tracking-[0.4em] font-black uppercase">SYNC_TO_ARCHIVE_SUCCESS</p>
                    </div>
                    <button @click="submitGallery" :disabled="galleryForm.processing || galleryForm.wasSuccessful" class="w-full py-6 mt-10 bg-[#B9C3FF] text-[#092484] text-[11px] font-black tracking-[0.4em] uppercase glow-indigo transition-all transform active:scale-95 disabled:opacity-50">
                        {{ galleryForm.processing ? 'POSTING_SHOT...' : (galleryForm.wasSuccessful ? 'POSTED' : (isEditingGallery ? 'Update Showcase' : 'Post to Gallery')) }}
                    </button>
                </div>
                <div class="w-full md:w-1/2 bg-[#050505] relative flex items-center justify-center">
                    <input type="file" id="gallery-img" @input="galleryForm.image = $event.target.files[0]" class="hidden" />
                    <label for="gallery-img" class="cursor-pointer group text-center p-20">
                        <span class="material-symbols-outlined text-7xl text-[#454652] group-hover:text-[#B9C3FF] transition-all">add_a_photo</span>
                        <p class="text-[10px] text-[#454652] tracking-widest uppercase mt-6 group-hover:text-[#B9C3FF]">{{ isEditingGallery ? 'Replace Shot' : 'Upload Photo' }}</p>
                    </label>
                    <img v-if="galleryForm.image" :src="getObjectURL(galleryForm.image)" class="absolute inset-0 w-full h-full object-cover grayscale opacity-50 pointer-events-none" />
                    <img v-else-if="galleryPreview" :src="galleryPreview" class="absolute inset-0 w-full h-full object-cover grayscale opacity-50 pointer-events-none" />
                </div>
            </div>
        </div>

        <!-- Global Cinematic Hover Gallery (Escaped from transform context) -->
        <div v-if="hoveredOrder && getAllOrderImages(hoveredOrder).length" 
             class="fixed top-32 left-[280px] z-[200] w-72 bg-[#0E0E0E] border border-[#B9C3FF]/30 p-4 shadow-[0_40px_100px_rgba(0,0,0,1)] animate-in pointer-events-none">
            <p class="text-[8px] text-[#B9C3FF] tracking-[0.3em] uppercase font-bold mb-4 flex justify-between">
                <span>Asset Stream</span>
                <span>#{{ String(hoveredOrder.id).padStart(4, '0') }}</span>
            </p>
            <div class="grid grid-cols-2 gap-3">
                <div v-for="(img, i) in getAllOrderImages(hoveredOrder).slice(0, 4)" :key="i" 
                     class="aspect-square bg-[#050505] overflow-hidden border border-[#1C1B1B]">
                    <img :src="img" class="w-full h-full object-cover grayscale hover:grayscale-0 transition-all opacity-80" />
                </div>
            </div>
            <p class="text-[9px] text-[#454652] tracking-widest uppercase font-bold mt-4 text-center">
                {{ getAllOrderImages(hoveredOrder).length }} Pieces
            </p>
        </div>

    </div>
</template>
