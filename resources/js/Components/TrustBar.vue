<script setup>
// Only promises the store actually keeps: delivery or collection is chosen and priced at checkout,
// payment is M-Pesa STK, every order has a reference to track, and fit help
// is offered on WhatsApp. No returns or shipping-threshold claims.
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
const page = usePage();
// Follows Studio → Settings → Payments.
const items = computed(() => [
    { icon: 'local_shipping', title: 'Delivery or Collection', text: 'Choose at checkout; any fee shows before you pay' },
    page.props.payments?.mpesa
        ? { icon: 'verified_user', title: 'Secure M-Pesa Checkout', text: 'Approve payment on your own phone' }
        : { icon: 'chat', title: 'Order on WhatsApp', text: 'Send your order, pay once we confirm' },
    { icon: 'package_2', title: 'Track Every Order', text: 'Reference and phone, no account needed' },
    { icon: 'support_agent', title: 'Fit & Style Help', text: 'Talk to the studio on WhatsApp' },
]);
</script>

<template>
    <div class="card p-6 shadow-xl shadow-black/10 sm:p-7 dark:shadow-black/40">
        <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 lg:divide-x divide-black/5 dark:divide-white/5">
            <li v-for="(item, i) in items" :key="item.title" class="flex items-center gap-4" :class="i > 0 ? 'lg:pl-6' : ''">
                <span class="icon-tile"><span class="material-symbols-outlined text-xl" aria-hidden="true">{{ item.icon }}</span></span>
                <div>
                    <p class="text-base font-semibold text-on-surface">{{ item.title }}</p>
                    <p class="mt-0.5 text-sm text-on-surface-variant">{{ item.text }}</p>
                </div>
            </li>
        </ul>
    </div>
</template>
