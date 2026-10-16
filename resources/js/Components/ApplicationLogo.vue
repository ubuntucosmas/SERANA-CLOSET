<template>
    <span class="serana-logo inline-flex shrink-0 items-center gap-3">
        <img v-if="$page.props.theme_settings?.site_logo" :src="$page.props.theme_settings.site_logo" class="h-11 max-w-[180px] w-auto object-contain lg:h-12" alt="Serana Closet" />
        <template v-else>
            <svg class="serana-monogram h-11 w-11 shrink-0 lg:h-12 lg:w-12" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path class="monogram-frame" d="M15 3H3v12M33 3h12v12M45 33v12H33M15 45H3V33" stroke-width="1" />
                <path class="monogram-s" d="M28 13.5c-4-4-13-3-14.5 2.5-2 7 16.5 6 15 14-1.5 7-12.5 9-18 2" stroke-width="1.7" stroke-linecap="round" />
                <path class="monogram-c" d="M38 14.5c-6-5-14-1.5-16 6-2.5 9 4.5 17 14.5 12" stroke-width="1.7" stroke-linecap="round" />
            </svg>
            <span class="flex flex-col gap-2">
                <span class="serana-wordmark text-on-surface">{{ $page.props.theme_settings?.site_headline || 'Serana' }}</span>
                <span class="serana-descriptor flex items-center gap-2 text-primary"><span class="h-px w-4 bg-primary" aria-hidden="true"></span>{{ $page.props.theme_settings?.site_headline ? 'Studio' : 'Closet' }}</span>
            </span>
        </template>
    </span>
</template>

<style scoped>
.serana-wordmark {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 22px;
    font-weight: 400;
    line-height: 1;
    letter-spacing: .12em;
    text-transform: uppercase;
}
.serana-descriptor { font-size: 10px; font-weight: 500; line-height: 1; letter-spacing: .3em; text-transform: uppercase; }
.monogram-frame { stroke: var(--on-surface); opacity: .35; }
.monogram-s { stroke: var(--on-surface); }
.monogram-c { stroke: var(--primary); }
@media (min-width: 1024px) { .serana-wordmark { font-size: 24px; } }
</style>
