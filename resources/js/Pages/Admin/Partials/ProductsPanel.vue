<!-- Products grid. Add/edit/category forms live in the parent (shared useForm). -->
<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    products: { type: Array, default: () => [] },
    archived: { type: Array, default: () => [] },
});
const emit = defineEmits(['edit', 'delete', 'restore', 'forceDelete']);

const page = usePage();
const { formatAmount } = useCurrency();

const showArchived = ref(false);
const query = ref('');

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

        <div v-else class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div v-for="p in list" :key="p.id" class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl overflow-hidden flex flex-col">
                <div class="aspect-square bg-slate-950 relative">
                    <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full grid place-items-center text-slate-700">
                        <span class="material-symbols-outlined text-4xl">image</span>
                    </div>
                    <span v-if="!p.in_stock && !showArchived" class="absolute top-2 left-2 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-rose-500/90 text-white">Out of stock</span>
                </div>
                <div class="p-3 flex flex-col gap-1 flex-1">
                    <p class="text-white text-sm font-medium truncate">{{ p.name }}</p>
                    <p class="text-slate-500 text-xs truncate">{{ p.category?.name || 'Uncategorised' }}</p>
                    <p class="text-[#57d5fc] font-headline mt-1">{{ formatAmount(p.price, page.props) }}</p>

                    <div class="mt-3 flex gap-2">
                        <template v-if="!showArchived">
                            <button @click="emit('edit', p)" class="flex-1 text-xs font-semibold py-2 rounded-lg bg-white/5 text-slate-200 hover:bg-white/10">Edit</button>
                            <button @click="emit('delete', p.id)" title="Archive" :aria-label="`Archive ${p.name}`" class="px-2.5 py-2 rounded-lg bg-white/5 text-slate-400 hover:text-rose-400">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">archive</span>
                            </button>
                        </template>
                        <template v-else>
                            <button @click="emit('restore', p.id)" class="flex-1 text-xs font-semibold py-2 rounded-lg bg-[#57d5fc]/15 text-[#57d5fc] hover:bg-[#57d5fc]/25">Restore</button>
                            <button @click="emit('forceDelete', p.id)" title="Delete permanently" :aria-label="`Delete ${p.name} permanently`" class="px-2.5 py-2 rounded-lg bg-white/5 text-slate-400 hover:text-rose-400">
                                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete_forever</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300; vertical-align: middle; }
</style>
