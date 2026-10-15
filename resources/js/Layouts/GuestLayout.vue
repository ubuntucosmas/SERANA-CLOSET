<script setup>
import SiteSeo from '@/Components/SiteSeo.vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <SiteSeo />
    <div
        class="flex min-h-screen flex-col items-center bg-background text-on-surface pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-20 w-20 fill-current text-primary" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-surface-container px-6 py-4 shadow-md sm:max-w-md rounded-2xl"
        >
            <slot />
        </div>
    </div>
</template>
