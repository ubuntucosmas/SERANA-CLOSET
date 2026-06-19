<!-- Home: real, honest KPIs + a "needs your attention" list. No fake widgets. -->
<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCurrency } from '@/Composables/useCurrency';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    attention: { type: Object, default: () => ({ awaiting: [], low_stock: [] }) },
});
const emit = defineEmits(['go', 'openOrder']);

const page = usePage();
const { formatAmount } = useCurrency();

const ownerName = computed(() => (page.props.auth?.user?.name || '').split(' ')[0] || '');
const greeting = computed(() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
});

const cards = computed(() => [
    { label: 'New orders this week', value: props.stats.new_orders_week ?? 0, icon: 'add_shopping_cart', tab: 'orders' },
    { label: 'Paid this week', value: formatAmount(props.stats.revenue_week ?? 0, page.props), icon: 'payments', tab: 'orders' },
    { label: 'In production', value: props.stats.in_production ?? 0, icon: 'content_cut', tab: 'orders' },
    { label: 'Subscribers', value: props.stats.subscribers_total ?? 0, icon: 'group', tab: 'subscribers' },
]);

const nothingToDo = computed(() =>
    !(props.attention?.awaiting?.length) && !(props.attention?.low_stock?.length));
</script>

<template>
    <div class="space-y-8">
        <p v-if="ownerName" class="text-slate-400 -mt-2">{{ greeting }}, <span class="text-white">{{ ownerName }}</span >.</p>

        <!-- KPI cards -->
        <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            <button v-for="c in cards" :key="c.label" @click="emit('go', c.tab)"
                class="text-left bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-5 hover:ring-[#57d5fc]/40 transition-colors">
                <span class="material-symbols-outlined text-[#57d5fc] text-2xl">{{ c.icon }}</span>
                <p class="mt-3 text-2xl lg:text-3xl font-headline text-white leading-none">{{ c.value }}</p>
                <p class="mt-2 text-[11px] uppercase tracking-wider text-slate-500 font-medium">{{ c.label }}</p>
            </button>
        </div>

        <!-- Needs your attention -->
        <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-6">
            <h2 class="text-white font-headline text-xl mb-1">Needs your attention</h2>
            <p class="text-slate-500 text-sm mb-5">Quick things worth a look right now.</p>

            <div v-if="nothingToDo" class="flex items-center gap-3 text-slate-400 py-6 justify-center">
                <span class="material-symbols-outlined text-[#57d5fc]">check_circle</span>
                You're all caught up.
            </div>

            <div v-else class="space-y-3">
                <button v-for="o in attention.awaiting" :key="'a' + o.id" @click="emit('openOrder', o.id)"
                    class="w-full flex items-center justify-between gap-4 bg-slate-950/50 hover:bg-slate-950 rounded-xl px-4 py-3 text-left transition-colors">
                    <span class="flex items-center gap-3 min-w-0">
                        <span class="material-symbols-outlined text-amber-400 shrink-0">hourglass_top</span>
                        <span class="min-w-0">
                            <span class="block text-white text-sm truncate">{{ o.full_name || 'Customer' }} — awaiting payment</span>
                            <span class="block text-slate-500 text-xs font-mono">{{ o.reference || ('#' + o.id) }}</span>
                        </span>
                    </span>
                    <span class="text-slate-300 text-sm shrink-0">{{ formatAmount(o.amount || 0, page.props) }}</span>
                </button>

                <button v-for="p in attention.low_stock" :key="'l' + p.id" @click="emit('go', 'products')"
                    class="w-full flex items-center gap-3 bg-slate-950/50 hover:bg-slate-950 rounded-xl px-4 py-3 text-left transition-colors">
                    <span class="material-symbols-outlined text-rose-400 shrink-0">inventory</span>
                    <span class="text-white text-sm truncate">{{ p.name }} — out of stock</span>
                </button>
            </div>
        </section>
    </div>
</template>
