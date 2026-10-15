<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
const page = usePage();
const seo = computed(() => page.props?.seo || {});
const structured = computed(() => JSON.stringify(seo.value.structured_data || {}).replace(/</g, '\\u003c'));
</script>
<template>
    <Head>
        <component :is="'script'" v-if="seo.structured_data" head-key="structured-data" type="application/ld+json">{{ structured }}</component>
        <meta head-key="description" name="description" :content="seo.description" />
        <meta head-key="robots" name="robots" :content="seo.robots" />
        <link head-key="canonical" rel="canonical" :href="seo.url" />
        <meta head-key="og:title" property="og:title" :content="seo.title" />
        <meta head-key="og:description" property="og:description" :content="seo.description" />
        <meta head-key="og:url" property="og:url" :content="seo.url" />
        <meta head-key="og:type" property="og:type" :content="seo.type" />
        <meta v-if="seo.image" head-key="og:image" property="og:image" :content="seo.image" />
        <meta head-key="twitter:title" name="twitter:title" :content="seo.title" />
        <meta head-key="twitter:description" name="twitter:description" :content="seo.description" />
        <meta head-key="twitter:card" name="twitter:card" :content="seo.image ? 'summary_large_image' : 'summary'" />
        <meta v-if="seo.image" head-key="twitter:image" name="twitter:image" :content="seo.image" />
    </Head>
</template>
