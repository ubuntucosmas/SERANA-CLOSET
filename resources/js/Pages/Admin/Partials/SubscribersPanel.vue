<!-- Newsletter subscribers / leads, with a one-click CSV export. -->
<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    leads: { type: Array, default: () => [] },
});

const query = ref('');
const list = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.leads;
    return props.leads.filter(l => (l.email || '').toLowerCase().includes(q));
});

function exportCsv() {
    const rows = [['Email', 'Joined'], ...props.leads.map(l => [l.email || '', l.created_at || ''])];
    const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = `subscribers-${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
            <div class="relative flex-1 sm:max-w-xs">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-lg">search</span>
                <input v-model="query" type="search" aria-label="Search subscribers" placeholder="Search email…"
                    class="w-full pl-10 pr-3 py-2 bg-slate-900 border border-white/10 rounded-full text-sm text-white placeholder-slate-600 focus:ring-1 focus:ring-[#57d5fc] outline-none" />
            </div>
            <button @click="exportCsv" :disabled="leads.length === 0"
                class="flex items-center justify-center gap-2 px-4 py-2 rounded-full text-xs font-semibold bg-white/5 text-slate-200 hover:bg-white/10 disabled:opacity-40">
                <span class="material-symbols-outlined text-[18px]">download</span> Export CSV
            </button>
        </div>

        <div v-if="list.length === 0" class="text-center text-slate-500 py-16 bg-slate-900/40 rounded-2xl ring-1 ring-white/5">
            <span class="material-symbols-outlined text-3xl">group</span>
            <p class="mt-2 text-sm">{{ query ? 'No subscribers match this search. Clear the search to see all subscribers.' : 'No subscribers yet. Visitors can join using the storefront newsletter form.' }}</p>
        </div>

        <div v-else class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl divide-y divide-white/5">
            <div v-for="l in list" :key="l.id" class="flex items-center justify-between px-5 py-3.5">
                <span class="text-white text-sm truncate">{{ l.email }}</span>
                <span class="text-slate-500 text-xs shrink-0">{{ formatDate(l.created_at) }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300; vertical-align: middle; }
</style>
