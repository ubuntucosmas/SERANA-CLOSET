<!-- Products grid. Add/edit/category forms live in the parent (shared useForm). -->
<script setup>
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import { usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';
import { availableStock } from '@/Support/stock';

const props = defineProps({
    products: { type: Array, default: () => [] },
    archived: { type: Array, default: () => [] },
});
const emit = defineEmits(['edit', 'delete', 'restore', 'forceDelete']);

const page = usePage();
const { formatAmount } = useCurrency();

const showArchived = ref(false);
const query = ref('');
const preview = ref(null);
const previewImage = ref('');
const previewImages = computed(() => [...new Set([preview.value?.image_url, ...(preview.value?.secondary_image_urls || [])].filter(Boolean))]);
function openPreview(product) { preview.value = product; previewImage.value = product.image_url || product.secondary_image_urls?.[0] || ''; }
function editPreview() { const product = preview.value; preview.value = null; emit('edit', product); }

// Same figure the shop uses: limit − sold − unpaid holds.
function stockLine(p) {
    if (p.batch_limit == null) return { main: 'Unlimited', detail: '' };
    const held = Number(p.reserved_quantity || 0);
    const detail = [`${p.batch_sold || 0} sold`, held ? `${held} on hold` : ''].filter(Boolean).join(' · ');
    return { main: `${availableStock(p)} of ${p.batch_limit} left`, detail };
}
function availability(p) {
    if (showArchived.value || p.deleted_at) return { label: 'Archived — hidden', tone: 'stage-waiting' };
    if (!p.in_stock) return { label: 'Out of stock', tone: 'stage-error' };
    if (availableStock(p) === 0) return { label: 'Sold out', tone: 'stage-error' };
    return { label: 'Available', tone: 'stage-complete' };
}

const list = computed(() => {
    const pool = showArchived.value ? props.archived : props.products;
    const q = query.value.trim().toLowerCase();
    if (!q) return pool;
    return pool.filter(p => [p.name, p.category?.name].filter(Boolean).some(v => v.toLowerCase().includes(q)));
});
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
            <div class="relative flex-1 sm:max-w-xs">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-lg">search</span>
                <input v-model="query" type="search" aria-label="Search products" placeholder="Search products…"
                    class="w-full pl-10 pr-3 py-2 bg-slate-900 border border-white/10 rounded-full text-sm text-white placeholder-slate-600 focus:ring-1 focus:ring-[#57d5fc] outline-none" />
            </div>
            <button @click="showArchived = !showArchived"
                :class="['px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-colors',
                         showArchived ? 'bg-[#57d5fc] text-slate-900' : 'bg-white/5 text-slate-400 hover:text-white']">
                {{ showArchived ? 'Active products' : 'View archive' }}
            </button>
        </div>

        <div v-if="list.length === 0" class="text-center text-slate-500 py-16 bg-slate-900/40 rounded-2xl ring-1 ring-white/5">
            <span class="material-symbols-outlined text-3xl">inventory_2</span>
            <p class="mt-2 text-sm">{{ query ? 'No products match this search. Clear the search or change the archive view.' : showArchived ? 'No archived products.' : 'No products yet — use Add Product to create your first one.' }}</p>
        </div>

        <p class="text-xs text-slate-400" aria-live="polite">{{ list.length }} {{ showArchived ? 'archived' : 'active' }} products{{ query ? ' matching search' : '' }}</p>
        <div v-if="list.length" class="studio-table-wrap" role="region" aria-label="Products table — scroll horizontally for all columns" tabindex="0">
            <table class="studio-table">
                <caption class="sr-only">Products with category, price, availability, stock left and actions</caption>
                <thead><tr><th scope="col">Product</th><th scope="col">Category</th><th scope="col" class="numeric">Price</th><th scope="col">Availability</th><th scope="col" class="numeric">Stock</th><th scope="col" class="row-action">Actions</th></tr></thead>
                <tbody><tr v-for="p in list" :key="p.id">
                    <td><div class="flex items-center gap-3 min-w-48"><img v-if="p.image_url" :src="p.image_url" alt="" loading="lazy" class="h-10 w-10 rounded object-cover shrink-0" /><button @click="openPreview(p)" class="text-left font-medium text-white hover:text-cyan-300">{{ p.name }}</button></div></td>
                    <td>{{ p.category?.name || 'Uncategorised' }}</td>
                    <td class="numeric text-white">{{ formatAmount(p.price, page.props) }}</td>
                    <td><span class="stage-badge" :class="availability(p).tone">{{ availability(p).label }}</span></td>
                    <td class="numeric"><span class="block text-white">{{ stockLine(p).main }}</span><span v-if="stockLine(p).detail" class="block text-xs text-slate-400">{{ stockLine(p).detail }}</span></td>
                    <td class="row-action"><div class="flex justify-end gap-2">
                        <button @click="openPreview(p)" :aria-label="`Preview ${p.name}`" class="table-action">Preview</button>
                        <template v-if="!showArchived"><button @click="emit('edit', p)" :aria-label="`Edit ${p.name}`" class="table-action">Edit</button><button @click="emit('delete', p.id)" :aria-label="`Archive ${p.name}`" class="table-action">Archive</button></template>
                        <template v-else><button @click="emit('restore', p.id)" :aria-label="`Restore ${p.name}`" class="table-action">Restore</button><button @click="emit('forceDelete', p.id)" :aria-label="`Permanently delete ${p.name}`" class="table-action text-rose-300">Delete permanently</button></template>
                    </div></td>
                </tr></tbody>
            </table>
        </div>
    </div>
    <Modal :show="!!preview" maxWidth="2xl" @close="preview = null">
        <section v-if="preview" class="bg-slate-900 text-slate-200 p-5" aria-labelledby="product-preview-title">
            <header class="flex items-start justify-between gap-4 mb-4"><div><p class="text-xs text-slate-400">Product preview · saved details</p><h2 id="product-preview-title" class="text-xl font-semibold text-white">{{ preview.name }}</h2></div><button @click="preview = null" class="table-action" aria-label="Close product preview">Close</button></header>
            <div class="grid sm:grid-cols-2 gap-5">
                <div><div class="aspect-square rounded-lg bg-slate-950 overflow-hidden flex items-center justify-center"><img v-if="previewImage" :src="previewImage" :alt="preview.name" class="w-full h-full object-contain" /><p v-else class="text-sm text-slate-400">No product images uploaded.</p></div>
                    <div v-if="previewImages.length > 1" class="flex gap-2 mt-3"><button v-for="(src, index) in previewImages" :key="src" @click="previewImage = src" :aria-label="`View product image ${index + 1}`" :aria-pressed="previewImage === src" class="rounded border-2 overflow-hidden" :class="previewImage === src ? 'border-cyan-400' : 'border-transparent'"><img :src="src" alt="" class="h-14 w-14 object-cover" /></button></div>
                </div>
                <div class="space-y-4"><p class="text-xl font-semibold tabular-nums text-white">{{ formatAmount(preview.price, page.props) }}</p><span class="stage-badge" :class="availability(preview).tone">{{ availability(preview).label }}</span>
                    <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-sm"><dt class="text-slate-400">Category</dt><dd>{{ preview.category?.name || 'Uncategorised' }}</dd><dt class="text-slate-400">Garment type</dt><dd class="capitalize">{{ preview.garment_type || 'Not specified' }}</dd><dt class="text-slate-400">Stock</dt><dd>{{ stockLine(preview).main }}<span v-if="stockLine(preview).detail" class="block text-xs text-slate-400">{{ stockLine(preview).detail }}</span></dd><dt class="text-slate-400">Custom requests</dt><dd>{{ preview.is_customizable ? 'Accepted' : 'Not offered' }}</dd></dl>
                    <div><h3 class="text-sm font-semibold text-white">Description</h3><p class="mt-1 text-sm whitespace-pre-line break-words">{{ preview.description || 'No description added.' }}</p></div>
                    <button @click="editPreview" class="table-action">Edit product</button>
                </div>
            </div>
        </section>
    </Modal>
</template>

<style scoped>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300; vertical-align: middle; }
</style>
