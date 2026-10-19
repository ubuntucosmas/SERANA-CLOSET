<!-- Admin Drops: campaigns with live progress + reservations. Form lives in parent. -->
<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    drops: { type: Array, default: () => [] },
});
const emit = defineEmits(['edit', 'archive']);

const page = usePage();
const { formatAmount } = useCurrency();

const expanded = ref(null);
function toggle(id) { expanded.value = expanded.value === id ? null : id; }

const statusStyle = {
    draft: 'bg-white/5 text-slate-400',
    live: 'bg-[#57d5fc]/15 text-[#57d5fc]',
    greenlit: 'bg-emerald-400/15 text-emerald-400',
    fulfilled: 'bg-violet-400/15 text-violet-300',
    closed: 'bg-rose-400/15 text-rose-300',
};
function fmtDate(d) { return d ? new Date(d).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : 'No deadline'; }
function sizeOf(r) { return r.items_json?.size || null; }
function measurementsOf(r) {
    const parts = [];
    if (r.bust_cm) parts.push(`Bust ${r.bust_cm}`);
    if (r.waist_cm) parts.push(`Waist ${r.waist_cm}`);
    if (r.hips_cm) parts.push(`Hips ${r.hips_cm}`);
    if (r.items_json?.height_cm) parts.push(`Height ${r.items_json.height_cm}`);
    return parts.length ? parts.join(' · ') + ' cm' : null;
}
const hasDrops = computed(() => props.drops.length > 0);
</script>

<template>
    <div class="space-y-4">
        <div v-if="!hasDrops" class="text-center text-slate-500 py-16 bg-slate-900/40 rounded-2xl ring-1 ring-white/5">
            <span class="material-symbols-outlined text-3xl">bolt</span>
            <p class="mt-2 text-sm">No pre-order campaigns yet. Select New campaign to set a price and production goal.</p>
        </div>

        <div v-for="d in drops" :key="d.id" class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl overflow-hidden">
            <div class="flex gap-4 p-4">
                <div class="w-20 h-24 rounded-xl bg-slate-950 overflow-hidden shrink-0">
                    <img v-if="d.image_url" :src="d.image_url" :alt="d.title" class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full grid place-items-center text-slate-700"><span class="material-symbols-outlined">checkroom</span></div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="text-white font-medium truncate">{{ d.title }}</h3>
                                <span :class="['text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full', statusStyle[d.status] || 'bg-white/5 text-slate-400']">{{ ({ draft: 'Draft — hidden', live: 'Accepting reservations', greenlit: 'In production', fulfilled: 'Dispatched', closed: 'Closed' })[d.status] || d.status }}</span>
                            </div>
                            <p class="text-slate-500 text-xs mt-0.5">{{ formatAmount(d.price, page.props) }} · {{ d.ends_at ? 'Deadline: ' + fmtDate(d.ends_at) : 'No deadline' }}</p>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <button @click="emit('edit', d)" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white/5 text-slate-200 hover:bg-white/10">Edit</button>
                            <button @click="emit('archive', d.id)" title="Archive" class="px-2 py-1.5 rounded-lg bg-white/5 text-slate-400 hover:text-rose-400"><span class="material-symbols-outlined text-[18px]">archive</span></button>
                        </div>
                    </div>
                    <!-- progress -->
                    <div class="mt-3">
                        <div class="h-1.5 rounded-full bg-white/10 overflow-hidden">
                            <div class="h-full bg-[#57d5fc] transition-all" :style="{ width: d.funded_percent + '%' }"></div>
                        </div>
                        <div class="mt-1.5 flex items-center justify-between text-xs">
                            <span class="text-slate-400">{{ d.reserved_count }} / {{ d.target_quantity }} reserved <span v-if="d.is_funded" class="text-emerald-400 font-semibold">· goal met ✓</span></span>
                            <button @click="toggle(d.id)" class="text-[#57d5fc] hover:underline">{{ expanded === d.id ? 'Hide' : 'View' }} reservations ({{ d.reservations?.length || 0 }})</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- reservations -->
            <div v-if="expanded === d.id" class="border-t border-white/5 divide-y divide-white/5">
                <p v-if="!d.reservations?.length" class="text-slate-500 text-sm text-center py-4">No reservations yet.</p>
                <div v-for="r in d.reservations" :key="r.id" class="px-5 py-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-white">{{ r.full_name || 'Customer' }}</span>
                            <span class="text-slate-500 ml-2">{{ r.phone }}</span>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span v-if="sizeOf(r)" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[#57d5fc]/15 text-[#57d5fc]">Size {{ sizeOf(r) }}</span>
                            <span class="text-slate-400">{{ formatAmount(r.price_quoted, page.props) }}</span>
                            <span :class="['text-[10px] font-semibold px-2 py-0.5 rounded-full', r.is_paid ? 'text-emerald-400 bg-emerald-400/10' : 'text-amber-400 bg-amber-400/10']">{{ r.is_paid ? 'Paid' : 'Unpaid' }}</span>
                        </div>
                    </div>
                    <p v-if="measurementsOf(r)" class="mt-1 text-xs text-slate-500">{{ measurementsOf(r) }}</p>
                    <p v-if="r.items_json?.fit_notes" class="mt-0.5 text-xs text-slate-400 italic">“{{ r.items_json.fit_notes }}”</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300; vertical-align: middle; }
</style>
