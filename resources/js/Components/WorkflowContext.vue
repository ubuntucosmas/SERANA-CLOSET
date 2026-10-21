<script setup>
defineProps({ actor: String, task: String, reason: String, when: String, where: String, confirmation: String });
</script>

<template>
    <section class="rounded-xl border border-current/15 p-3 text-sm" aria-label="Workflow purpose and completion">
        <p class="font-semibold">{{ task }}</p>
        <p class="mt-1 text-xs opacity-80">{{ reason }}</p>
        <details class="mt-2">
            <summary class="cursor-pointer text-xs font-medium">Who, when, where and completion</summary>
            <dl class="mt-2 grid gap-2 sm:grid-cols-2 text-xs">
                <div><dt class="font-semibold">Who is acting?</dt><dd>{{ actor }}</dd></div>
                <div><dt class="font-semibold">When?</dt><dd>{{ when }}</dd></div>
                <div><dt class="font-semibold">Where?</dt><dd>{{ where }}</dd></div>
                <div><dt class="font-semibold">What confirms completion?</dt><dd>{{ confirmation }}</dd></div>
            </dl>
        </details>
    </section>
</template>
