<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { paymentStatus, fulfilmentStatus } from '@/Support/orderStatus';
import { useCurrency } from '@/Composables/useCurrency';
defineProps({ orders: { type: Array, default: () => [] }, user: Object });
const page = usePage();
const { formatAmount } = useCurrency();
</script>
<template>
    <StorefrontLayout hideMobileCard>
        <Head title="Your Orders — Serana" />
        <section class="max-w-5xl mx-auto px-6 pb-24">
            <header class="flex flex-wrap items-start justify-between gap-5 mb-8">
                <div>
                    <h1 class="text-4xl font-headline mb-3">Your Orders</h1>
                    <p class="text-on-surface-variant">Review payment and fulfilment updates for orders placed while signed in.</p>
                </div>
                <Link :href="route('profile.edit')" class="action-secondary">Account Settings</Link>
            </header>
            <p class="text-sm text-on-surface-variant mb-6">Ordered as a guest? <Link :href="route('track')" class="text-primary underline">Track Order</Link> with your reference and phone number.</p>
            <div v-if="!orders.length" class="rounded-2xl bg-surface-container p-8 text-center">
                <h2 class="text-xl mb-3">No account orders yet</h2>
                <p class="text-on-surface-variant mb-6">Orders placed while signed in will appear here.</p>
                <Link :href="route('shop')" class="action-primary">Shop Collection</Link>
            </div>
            <ul v-else class="space-y-5">
                <li v-for="order in orders" :key="order.id" class="rounded-2xl bg-surface-container border border-outline-variant p-6">
                    <div class="flex flex-wrap justify-between gap-4 mb-5">
                        <div>
                            <h2 class="font-semibold text-lg">{{ order.type === 'drop' ? 'Drop Reservation' : order.type === 'standard' ? 'Shop Order' : 'Bespoke Request' }}</h2>
                            <p class="text-sm text-on-surface-variant">{{ order.cipher }} · {{ new Date(order.created_at).toLocaleDateString('en-GB') }}</p>
                        </div>
                        <p v-if="order.price_quoted" class="text-lg font-semibold">{{ formatAmount(order.price_quoted, page.props) }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <StatusBadge v-bind="paymentStatus(order)" />
                        <span class="text-sm">Fulfilment: {{ fulfilmentStatus(order) }}</span>
                    </div>
                    <p class="text-sm text-on-surface-variant mb-5">{{ paymentStatus(order).next }}</p>
                    <Link :href="route('track', { ref: order.cipher })" class="action-primary">Track Order</Link>
                </li>
            </ul>
        </section>
    </StorefrontLayout>
</template>
