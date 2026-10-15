<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
const breadcrumbs = computed(() => {
    const path = page.url.split('?')[0];
    if (path.startsWith('/shop/')) return [{ label: 'Shop', href: route('shop') }, { label: page.props.product?.name || 'Product' }];
    if (path.startsWith('/drops/')) return [{ label: 'Drops', href: route('drops') }, { label: page.props.drop?.title || 'Drop' }];
    if (path === '/checkout') return [{ label: 'Your Bag', href: route('cart') }, { label: 'Checkout' }];
    if (path === '/profile') return [{ label: 'Your Orders', href: route('profile.designs') }, { label: 'Account Settings' }];
    return [];
});
</script>

<template>
    <nav v-if="breadcrumbs.length" aria-label="Breadcrumb" class="max-w-screen-xl mx-auto px-6 mb-6 text-sm text-on-surface-variant">
        <ol class="flex flex-wrap items-center gap-2">
            <li v-for="(crumb, index) in breadcrumbs" :key="crumb.label" class="flex items-center gap-2">
                <span v-if="index" aria-hidden="true">/</span>
                <Link v-if="crumb.href" :href="crumb.href" class="hover:text-primary underline underline-offset-4">{{ crumb.label }}</Link>
                <span v-else aria-current="page">{{ crumb.label }}</span>
            </li>
        </ol>
    </nav>
</template>
