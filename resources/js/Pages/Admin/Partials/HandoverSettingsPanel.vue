<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import WorkflowContext from '@/Components/WorkflowContext.vue';
const props = defineProps({ location: { type: Object, required: true } });
const page = usePage();
const form = useForm({ name: props.location.name, address: props.location.address || '', accepts_pickup: props.location.accepts_pickup, reason: '' });
function save() { form.put(route('studio.handover.update', props.location.id), { preserveScroll: true, onSuccess: () => { form.reason = ''; form.defaults(); } }); }
</script>
<template>
    <section class="rounded-2xl bg-slate-900/60 ring-1 ring-white/10 p-4 sm:p-5 space-y-3 mb-5">
        <h2 class="text-white text-lg font-semibold">Collection shop · {{ location.name }}</h2>
        <WorkflowContext :actor="page.props.auth?.user?.name" task="Set where customers collect their orders" reason="Pickup is offered only for an active shop with an exact address." when="Before enabling pickup, or when collection instructions change." :where="location.name" confirmation="Saved confirmation appears. New checkout orders show this shop; existing orders keep their address snapshot." />
        <form @submit.prevent="save" class="grid sm:grid-cols-2 gap-3 text-sm text-slate-300">
            <label>Shop name<input v-model="form.name" required maxlength="255" class="block w-full rounded bg-slate-950 mt-1" /></label>
            <label>Exact collection address<input v-model="form.address" :required="form.accepts_pickup" maxlength="255" placeholder="Building, floor/shop number, street, town" class="block w-full rounded bg-slate-950 mt-1" /></label>
            <label class="flex gap-2 items-center"><input v-model="form.accepts_pickup" type="checkbox" />Offer collection at checkout</label>
            <label>Reason for this change<input v-model="form.reason" required maxlength="255" class="block w-full rounded bg-slate-950 mt-1" /></label>
            <p v-if="!location.active" class="text-amber-300 text-xs sm:col-span-2">This location is inactive and will not appear at checkout.</p>
            <p v-if="!location.address" class="text-amber-300 text-xs sm:col-span-2">Enter and save the exact address before pickup can appear at checkout.</p>
            <p v-for="(message, field) in form.errors" :key="field" role="alert" class="text-rose-300 text-sm">{{ message }}</p>
            <button type="submit" :disabled="form.processing" class="table-action justify-self-start">Save collection shop</button>
        </form>
    </section>
</template>
