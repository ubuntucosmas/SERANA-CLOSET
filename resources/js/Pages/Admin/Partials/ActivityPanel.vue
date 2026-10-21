<script setup>
import { onMounted, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
const props = defineProps({ activity: { type: Object, default: null } });
const form = reactive({ h_type: props.activity?.filters?.h_type || 'actions', h_q: props.activity?.filters?.h_q || '', h_from: props.activity?.filters?.h_from || '', h_to: props.activity?.filters?.h_to || '', h_actor: props.activity?.filters?.h_actor || '' });
const loading = ref(false);
function load(number = 1) {
    loading.value = true;
    router.reload({ only: ['activity'], data: { ...form, tab: 'activity', h_page: number }, preserveScroll: true, onFinish: () => loading.value = false });
}
onMounted(() => { if (!props.activity) load(); });
function clear() { Object.assign(form, { h_type: 'actions', h_q: '', h_from: '', h_to: '', h_actor: '' }); load(); }
const readable = text => String(text || '').replaceAll('till', 'register').replace(/[._]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
const value = item => item === null ? '—' : typeof item === 'boolean' ? (item ? 'Yes' : 'No') : String(item);
</script>
<template>
    <div class="space-y-4">
        <form @submit.prevent="load()" class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6 text-xs text-slate-300">
            <label>Records<select v-model="form.h_type" class="block mt-1 w-full rounded bg-slate-900 text-white"><option value="actions">Staff / system actions</option><option value="stock">Stock movements</option></select></label>
            <label>Reference, product, action or reason<input v-model="form.h_q" maxlength="120" class="block mt-1 w-full rounded bg-slate-900 text-white" /></label>
            <label>From date (Nairobi)<input v-model="form.h_from" type="date" class="block mt-1 w-full rounded bg-slate-900 text-white" /></label>
            <label>To date (Nairobi)<input v-model="form.h_to" type="date" :min="form.h_from" class="block mt-1 w-full rounded bg-slate-900 text-white" /></label>
            <label>Acting staff<select v-model="form.h_actor" class="block mt-1 w-full rounded bg-slate-900 text-white"><option value="">All actors</option><option v-for="actor in activity?.actors || []" :key="actor.id" :value="actor.id">{{ actor.name }}</option></select></label>
            <div class="flex items-end gap-2"><button type="submit" :disabled="loading" class="table-action">{{ loading ? 'Searching…' : 'Search history' }}</button><button type="button" @click="clear" class="table-action">Clear</button></div>
        </form>
        <p v-for="(message, key) in $page.props.errors" :key="key" role="alert" class="text-sm text-rose-300">{{ message }}</p>
        <p v-if="activity" role="status" class="text-sm text-slate-400">{{ activity.total }} matching records · page {{ activity.page }} of {{ activity.last_page }} · latest first</p>
        <p v-if="activity && !activity.rows.length" class="text-sm text-slate-400">No records match these filters. Clear them or choose another date range.</p>
        <div v-if="activity?.rows.length" class="studio-table-wrap" role="region" aria-label="Matching history records" tabindex="0"><table class="studio-table"><thead><tr><th>When / who</th><th>What / where</th><th>Why / recorded change</th><th class="row-action">Related record</th></tr></thead><tbody>
            <tr v-for="row in activity.rows" :key="row.id"><td><span class="block">{{ row.at }}</span><span class="text-xs text-slate-400">{{ row.actor }}</span></td><td><span class="block font-semibold">{{ readable(row.action) }}</span><span class="block">{{ row.subject }} <template v-if="row.size">· {{ row.size }}</template><template v-if="row.quantity !== null"> · {{ row.quantity > 0 ? '+' : '' }}{{ row.quantity }}</template></span><span class="text-xs text-slate-400">{{ row.where }}</span></td><td><p class="whitespace-normal">{{ row.reason }}</p><p v-for="change in row.changes" :key="change.field" class="text-xs text-slate-400">{{ readable(change.field) }}: {{ value(change.before) }} → {{ value(change.after) }}</p></td><td class="row-action"><Link v-if="row.url" :href="row.url" class="table-action">Open record</Link><span v-else class="text-xs text-slate-400">Retained history</span></td></tr>
        </tbody></table></div>
        <div v-if="activity" class="flex gap-2"><button v-if="activity.page > 1" @click="load(activity.page - 1)" :disabled="loading" class="table-action">Newer records</button><button v-if="activity.page < activity.last_page" @click="load(activity.page + 1)" :disabled="loading" class="table-action">Older records</button></div>
        <p class="text-xs text-slate-400">Records are read-only. Payment references and credentials are not displayed here. An audit entry confirms a recorded action, not proof that a bank transfer occurred.</p>
    </div>
</template>
