<script setup>
import { computed } from 'vue';
import { useCurrency } from '@/Composables/useCurrency';
import { usePage } from '@inertiajs/vue3';
const props = defineProps({ order: { type: Object, required: true } });
const page = usePage();
const { formatAmount } = useCurrency();
const balance = computed(() => Number(props.order.balance_due ?? 0));
const money = value => formatAmount(value, page.props);
</script>
<template>
    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 rounded-xl border border-white/10 p-4 text-sm" aria-label="Order money position">
        <div><dt class="text-slate-400">Order total</dt><dd class="mt-1 font-semibold">{{ money(order.chargeable_total ?? order.price_quoted ?? 0) }}</dd></div>
        <div><dt class="text-slate-400">Amount received</dt><dd class="mt-1 font-semibold">{{ money(order.amount_paid ?? 0) }}</dd></div>
        <div><dt class="text-slate-400">Balance due</dt><dd class="mt-1 font-semibold">{{ money(Math.max(0, balance)) }}</dd></div>
        <div><dt class="text-slate-400">Refund owed</dt><dd class="mt-1 font-semibold" :class="balance < 0 ? 'text-rose-300' : ''">{{ money(Math.max(0, -balance)) }}</dd></div>
    </dl>
</template>
