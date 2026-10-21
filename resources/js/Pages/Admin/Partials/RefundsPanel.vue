<!-- Refunds owed to customers (Phase C): what, why, since when, and who to
     contact. Staff send the money themselves and record it here; nothing is
     ever sent automatically. A part refund leaves the rest in the queue. -->
<script setup>
import { useCurrency } from '@/Composables/useCurrency';
import { usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';

defineProps({
    refunds: { type: Array, default: () => [] },
});
const emit = defineEmits(['openOrder']);

const page = usePage();
const { formatAmount } = useCurrency();
const money = value => formatAmount(value, page.props);
const age = (days) => days === 0 ? 'today' : days === 1 ? '1 day' : `${days} days`;

const openId = ref(null);
const form = useForm({ amount: '', method: 'mpesa_manual', reference: '', reason: '' });

function start(row) {
    openId.value = row.id;
    form.reset();
    form.clearErrors();
    form.amount = row.owed;
    form.reason = row.cause;
}
function submit(row) {
    form.post(route('studio.order.refund', row.id), {
        preserveScroll: true,
        onSuccess: () => { openId.value = null; form.reset(); },
    });
}
</script>

<template>
    <div class="space-y-5">
        <p class="text-sm text-slate-400 max-w-2xl">
            Send the money to the customer first (M-Pesa, cash or bank), then record it here with its code.
            The customer gets a refund receipt by email. If you send part of it, the rest stays on this list.
        </p>

        <div v-if="refunds.length === 0" class="text-center text-slate-500 py-16 bg-slate-900/40 rounded-2xl ring-1 ring-white/5">
            <span class="material-symbols-outlined text-3xl">task_alt</span>
            <p class="mt-2 text-sm">No refunds owed. Orders appear here when a customer has paid more than they owe.</p>
        </div>

        <ul v-else class="space-y-3" aria-label="Refunds owed">
            <li v-for="row in refunds" :key="row.id" class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-5">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="min-w-0">
                        <button type="button" @click="emit('openOrder', row.id)" class="font-mono text-sm text-[#57d5fc] hover:underline">{{ row.reference }}</button>
                        <p class="text-white font-medium mt-1">{{ row.customer }}</p>
                        <p class="text-xs text-slate-400 mt-0.5 break-words">
                            <a v-if="row.phone" :href="`https://wa.me/${row.phone}`" target="_blank" rel="noopener" class="hover:text-white">{{ row.phone }}</a>
                            <template v-if="row.phone && row.email"> · </template>
                            <a v-if="row.email" :href="`mailto:${row.email}`" class="hover:text-white">{{ row.email }}</a>
                        </p>
                        <p class="text-sm text-slate-300 mt-2">{{ row.cause }}</p>
                    </div>
                    <div class="sm:text-right shrink-0">
                        <p class="text-xl font-semibold text-amber-400">{{ money(row.owed) }}</p>
                        <p class="text-xs text-slate-500">owed for {{ age(row.age_days) }}</p>
                        <div v-if="openId !== row.id" class="mt-3 flex flex-wrap gap-2 sm:justify-end">
                            <a v-if="row.whatsapp_url" :href="row.whatsapp_url" target="_blank" rel="noopener"
                                class="px-4 py-2 rounded-xl text-sm font-semibold bg-white/5 text-slate-200 hover:bg-white/10">Tell the customer on WhatsApp</a>
                            <button type="button" @click="start(row)"
                                class="px-4 py-2 rounded-xl text-sm font-semibold bg-[#57d5fc] text-slate-900 hover:brightness-105">Mark refunded</button>
                        </div>
                    </div>
                </div>

                <form v-if="openId === row.id" @submit.prevent="submit(row)" class="mt-4 pt-4 border-t border-white/10 grid gap-3 sm:grid-cols-2">
                    <label class="text-xs text-slate-400">Amount sent (KSh)
                        <input v-model="form.amount" type="number" min="1" :max="row.owed" inputmode="numeric" required
                            class="mt-1 w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-sm text-white" />
                        <span v-if="form.errors.amount" class="block mt-1 text-rose-300">{{ form.errors.amount }}</span>
                    </label>
                    <label class="text-xs text-slate-400">Sent by
                        <select v-model="form.method" class="mt-1 w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-sm text-white">
                            <option value="mpesa_manual">M-Pesa</option>
                            <option value="cash">Cash</option>
                            <option value="bank">Bank transfer</option>
                        </select>
                        <span v-if="form.errors.method" class="block mt-1 text-rose-300">{{ form.errors.method }}</span>
                    </label>
                    <label class="text-xs text-slate-400">{{ form.method === 'mpesa_manual' ? 'M-Pesa code' : 'Reference' }}
                        <input v-model="form.reference" type="text" maxlength="100" required
                            :placeholder="form.method === 'mpesa_manual' ? 'e.g. SKL4ABCD12' : 'e.g. Cash at the studio'"
                            class="mt-1 w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-sm text-white" />
                        <span v-if="form.errors.reference" class="block mt-1 text-rose-300">{{ form.errors.reference }}</span>
                    </label>
                    <label class="text-xs text-slate-400">Reason
                        <input v-model="form.reason" type="text" maxlength="255" required
                            class="mt-1 w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-sm text-white" />
                        <span v-if="form.errors.reason" class="block mt-1 text-rose-300">{{ form.errors.reason }}</span>
                    </label>
                    <div class="sm:col-span-2 flex gap-2 justify-end">
                        <button type="button" @click="openId = null" class="px-4 py-2 rounded-xl text-sm bg-white/5 text-slate-200 hover:bg-white/10">Cancel</button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 rounded-xl text-sm font-semibold bg-[#57d5fc] text-slate-900 hover:brightness-105 disabled:opacity-50">Record refund</button>
                    </div>
                </form>
            </li>
        </ul>
    </div>
</template>
