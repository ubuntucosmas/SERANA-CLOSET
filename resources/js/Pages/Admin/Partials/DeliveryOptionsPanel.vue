<!-- Studio → Settings → Delivery (Phase D2, owner): what customers choose at
     checkout and what it costs. Each option is a zone with a fee (free over a
     threshold), collection at the shop, or a quote on WhatsApp. Options are
     switched off, never deleted: orders keep what they were charged. -->
<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    options: { type: Array, default: () => [] },
});

const ksh = (n) => 'KSh ' + Number(n || 0).toLocaleString('en-KE');
const typeLabel = { zone: 'Delivery zone', pickup: 'Collect from the shop', quote: 'Quote on WhatsApp' };
const field = 'w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:ring-1 focus:ring-[#57d5fc] outline-none';

const editing = ref(null); // an option's id, or 'new'
const form = useForm({ name: '', type: 'zone', fee: 0, free_over: '', eta_text: '', active: true, sort: 50, reason: '' });
function start(o) {
    form.clearErrors();
    editing.value = o ? o.id : 'new';
    Object.assign(form, o
        ? { name: o.name, type: o.type, fee: o.fee, free_over: o.free_over ?? '', eta_text: o.eta ?? '', active: o.active, sort: o.sort, reason: '' }
        : { name: '', type: 'zone', fee: 0, free_over: '', eta_text: '', active: true, sort: 50, reason: '' });
}
function save() {
    const done = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    form.transform(d => ({ ...d, free_over: d.free_over === '' ? null : d.free_over }));
    editing.value === 'new' ? form.post(route('studio.delivery.store'), done) : form.put(route('studio.delivery.update', editing.value), done);
}
</script>

<template>
    <section class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 sm:p-5 space-y-3 mb-5" aria-labelledby="delivery-options-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="delivery-options-title" class="text-white text-lg font-semibold">Delivery and collection</h2>
            <button type="button" @click="start(null)" class="table-action">Add a zone</button>
        </div>
        <p class="text-sm text-slate-400">What customers choose at checkout. The fee is added to the order as its own line, so receipts, tracking and the Studio show it. Switch an option off instead of deleting it.</p>
        <ul class="divide-y divide-white/5" aria-label="Delivery options">
            <li v-for="o in options" :key="o.id" class="py-3 flex flex-wrap items-center justify-between gap-3">
                <span class="min-w-0" :class="o.active ? '' : 'opacity-50'">
                    <span class="block text-white text-sm">{{ o.name }}<template v-if="!o.active"> · off</template></span>
                    <span class="block text-xs text-slate-400">{{ typeLabel[o.type] }}<template v-if="o.type === 'zone'"> · {{ o.fee ? ksh(o.fee) : 'Free' }}<template v-if="o.free_over"> · free from {{ ksh(o.free_over) }}</template></template><template v-if="o.eta"> · {{ o.eta }}</template><template v-if="o.type === 'pickup'"> · {{ o.address || 'needs a shop address below' }}</template></span>
                </span>
                <button type="button" @click="start(o)" class="table-action">Edit</button>
            </li>
        </ul>
        <form v-if="editing" @submit.prevent="save" class="grid gap-3 sm:grid-cols-2 rounded-xl bg-slate-950/60 p-3" :aria-label="editing === 'new' ? 'Add a delivery zone' : 'Edit delivery option'">
            <label class="text-xs text-slate-400">Name customers see<input v-model="form.name" required maxlength="120" :class="field + ' mt-1'" placeholder="e.g. Nairobi CBD" /></label>
            <label class="text-xs text-slate-400">Kind
                <select v-model="form.type" :class="field + ' mt-1'" :disabled="editing !== 'new'"><option value="zone">Delivery zone</option><option value="pickup">Collect from the shop</option><option value="quote">Quote on WhatsApp</option></select>
            </label>
            <template v-if="form.type === 'zone'">
                <label class="text-xs text-slate-400">Fee (KSh)<input v-model.number="form.fee" type="number" min="0" required :class="field + ' mt-1'" /></label>
                <label class="text-xs text-slate-400">Free on orders of (KSh, optional)<input v-model="form.free_over" type="number" min="1" :class="field + ' mt-1'" /></label>
            </template>
            <label class="text-xs text-slate-400">When to expect it (optional)<input v-model="form.eta_text" maxlength="120" :class="field + ' mt-1'" placeholder="e.g. Next working day" /></label>
            <label class="text-xs text-slate-400">Order in the list<input v-model.number="form.sort" type="number" min="0" max="1000" :class="field + ' mt-1'" /></label>
            <label class="text-xs text-slate-400 flex items-center gap-2"><input v-model="form.active" type="checkbox" /> Offered at checkout</label>
            <label class="text-xs text-slate-400">Reason (kept in the audit log)<input v-model="form.reason" required maxlength="255" :class="field + ' mt-1'" /></label>
            <div class="sm:col-span-2 flex flex-wrap items-center gap-2">
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-[#57d5fc] px-4 py-2 text-sm font-semibold text-slate-900 disabled:opacity-40">Save</button>
                <button type="button" @click="editing = null" class="rounded-xl bg-white/5 px-3 py-2 text-sm text-slate-300">Cancel</button>
                <p v-for="(m, f) in form.errors" :key="f" role="alert" class="text-sm text-rose-300">{{ m }}</p>
            </div>
        </form>
    </section>
</template>
