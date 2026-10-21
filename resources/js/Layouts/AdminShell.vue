<!--
  AdminShell.vue — chrome for the Studio admin.

  Desktop: fixed left sidebar with plain-language sections.
  Mobile : sticky top bar + app-style bottom tab bar (4 primary + "More" sheet).

  Usage:
    <AdminShell v-model:current="tab" :title="..." :subtitle="...">
      <template #actions> ...primary action button... </template>
      ...section content...
    </AdminShell>
-->
<script setup>
import { sectionsFor, studioSection, groupedSections, completionFor } from '@/Support/studioSections';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import SiteSeo from '@/Components/SiteSeo.vue';
import Notification from '@/Components/Notification.vue';
import Modal from '@/Components/Modal.vue';
import WorkflowContext from '@/Components/WorkflowContext.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    current: { type: String, default: 'home' },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
const emit = defineEmits(['update:current']);

const page = usePage();

// One source of truth for the navigation. `primary` items show in the mobile
// bottom bar; the rest live behind "More".
const abilities = computed(() => page.props.auth?.user?.abilities || []);
const sections = computed(() => sectionsFor(abilities.value, { drops_enabled: !!page.props.drops_enabled }));
const canUseTill = computed(() => abilities.value.includes('pos.sell'));
const guidance = computed(() => studioSection(props.current));

const primary = computed(() => sections.value.filter(s => s.primary));
const secondary = computed(() => sections.value.filter(s => !s.primary));

const groups = computed(() => groupedSections(sections.value));
const moreGroups = computed(() => groupedSections(secondary.value));
const moreOpen = ref(false);

const storeName = computed(() => page.props.store?.name || 'Serana Studio');
const logoUrl = computed(() => page.props.theme_settings?.site_logo || null);

// Owner initials for the avatar (no external image).
const initials = computed(() => {
    const name = page.props.auth?.user?.name || storeName.value || 'S';
    return name.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
});

function go(id) {
    emit('update:current', id);
    moreOpen.value = false;
}

const accent = '#57d5fc';

// Scope Studio surface overrides to this workspace, including its dialogs.
onMounted(() => document.documentElement.classList.add('studio-active'));
onUnmounted(() => document.documentElement.classList.remove('studio-active'));

</script>

<template>
    <SiteSeo />
    <div class="min-h-screen bg-[#020617] text-slate-200 font-body selection:bg-cyan-900 selection:text-white">
        <Notification />
        <!-- ───────── Desktop sidebar ───────── -->
        <aside class="hidden lg:flex fixed left-0 top-0 h-screen w-64 flex-col bg-slate-950 border-r border-white/5 px-5 py-7 z-40">
            <Link :href="route('home')" class="flex items-center gap-3 px-2 mb-10 group">
                <img v-if="logoUrl" :src="logoUrl" alt="" class="h-8 w-8 object-contain" />
                <span v-else class="h-8 w-8 rounded-lg grid place-items-center text-slate-900 font-bold text-sm" :style="{ background: accent }">{{ initials }}</span>
                <span class="font-headline text-lg text-white truncate group-hover:text-[#57d5fc] transition-colors">{{ storeName }}</span>
            </Link>

            <nav aria-label="Studio sections" class="flex-1 min-h-0 overflow-y-auto space-y-1">
                <section v-for="group in groups" :key="group.label" class="mb-3">
                    <h2 class="px-4 pt-2 pb-1 text-xs font-semibold text-slate-500">{{ group.label }}</h2>
                <button v-for="s in group.sections" :key="s.id" @click="go(s.id)" :aria-current="current === s.id ? 'page' : undefined"
                    :class="['w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-colors',
                             current === s.id ? 'bg-[#57d5fc]/10 text-white font-semibold ring-1 ring-[#57d5fc]/25' : 'text-slate-400 hover:text-white hover:bg-white/5']">
                    <span class="material-symbols-outlined text-[21px]" :class="current === s.id ? 'text-[#57d5fc]' : ''">{{ s.icon }}</span>
                    {{ s.label }}
                </button>
                <a v-if="group.label === 'Daily work' && canUseTill" :href="route('pos.index')" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm text-slate-300 hover:bg-white/5"><span class="material-symbols-outlined text-[21px]">point_of_sale</span>Sell at till</a>
                </section>
            </nav>

            <div class="mt-auto pt-6 border-t border-white/5 space-y-1">
                <div class="flex items-center justify-between px-4 pb-2"><span class="text-sm text-slate-400">Appearance</span><ThemeToggle /></div>

                <Link :href="route('home')" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:text-white hover:bg-white/5 transition-colors">
                    <span class="material-symbols-outlined text-[20px]">storefront</span> Back to shop
                </Link>
                <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:text-red-400 hover:bg-white/5 transition-colors text-left">
                    <span class="material-symbols-outlined text-[20px]">logout</span> Sign out
                </Link>
            </div>
        </aside>

        <!-- ───────── Mobile top bar ───────── -->
        <header class="lg:hidden sticky top-0 z-40 flex items-center justify-between px-4 h-14 bg-slate-950/90 backdrop-blur-xl border-b border-white/5">
            <Link :href="route('home')" class="flex items-center gap-2">
                <img v-if="logoUrl" :src="logoUrl" alt="" class="h-7 w-7 object-contain" />
                <span v-else class="h-7 w-7 rounded-lg grid place-items-center text-slate-900 font-bold text-xs" :style="{ background: accent }">{{ initials }}</span>
                <span class="font-headline text-base text-white truncate max-w-[55vw]">{{ storeName }}</span>
            </Link>
            <ThemeToggle />
        </header>

        <!-- ───────── Main content ───────── -->
        <main class="lg:ml-64 min-h-screen pb-24 lg:pb-0">
            <!-- Section header -->
            <div class="px-3 sm:px-5 lg:px-7 pt-3 sm:pt-5 lg:pt-6 pb-4 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-3xl lg:text-4xl font-headline font-light text-white tracking-tight">{{ title }}</h1>
                    <p v-if="subtitle" class="text-slate-400 text-sm mt-1.5 max-w-xl">{{ subtitle }}</p>
                </div>
                <div class="shrink-0"><slot name="actions" /></div>
            </div>

            <div class="px-3 sm:px-5 lg:px-7 pb-8 sm:pb-12">
                <WorkflowContext class="mb-4 text-slate-300" :actor="`${page.props.auth?.user?.name || 'Signed-in staff'} · ${page.props.auth?.user?.role || 'staff'}`" :task="guidance.question" :reason="guidance.next" :when="guidance.when" :where="guidance.where" :confirmation="completionFor(current)" />
                <slot />
            </div>
        </main>

        <!-- ───────── Mobile bottom tab bar ───────── -->
        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-slate-950/95 backdrop-blur-xl border-t border-white/10 flex items-stretch">
            <button v-for="s in primary" :key="s.id" @click="go(s.id)" :aria-current="current === s.id ? 'page' : undefined"
                class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5"
                :class="current === s.id ? 'text-[#57d5fc]' : 'text-slate-500'">
                <span class="material-symbols-outlined text-[22px]">{{ s.icon }}</span>
                <span class="text-xs font-medium">{{ s.label }}</span>
            </button>
            <a v-if="canUseTill" :href="route('pos.index')" class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 text-slate-400"><span class="material-symbols-outlined text-[22px]">point_of_sale</span><span class="text-xs font-medium">Sell</span></a>
            <button @click="moreOpen = true"
                class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5"
                :class="secondary.some(s => s.id === current) ? 'text-[#57d5fc]' : 'text-slate-500'">
                <span class="material-symbols-outlined text-[22px]">more_horiz</span>
                <span class="text-xs font-medium">More</span>
            </button>
        </nav>

        <!-- ───────── Mobile "More" sheet ───────── -->
        <Modal :show="moreOpen" max-width="md" labelled-by="studio-more-title" @close="moreOpen = false">
                <div class="max-h-[85dvh] overflow-y-auto bg-slate-900 p-4 pb-8 space-y-1">
                    <div class="flex items-center justify-between"><h2 id="studio-more-title" class="text-white font-semibold">More Studio sections</h2><button type="button" @click="moreOpen = false" class="table-action">Close</button></div>
                    <div class="w-10 h-1 rounded-full bg-white/15 mx-auto mb-4"></div>
                    <section v-for="group in moreGroups" :key="group.label">
                    <h3 class="px-4 pt-3 text-xs font-semibold text-slate-500">{{ group.label }}</h3>
                    <button v-for="s in group.sections" :key="s.id" @click="go(s.id)" :aria-current="current === s.id ? 'page' : undefined"
                        :class="['w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sm',
                                 current === s.id ? 'bg-[#57d5fc]/10 text-white' : 'text-slate-300 hover:bg-white/5']">
                        <span class="material-symbols-outlined text-[22px]">{{ s.icon }}</span> {{ s.label }}
                    </button>
                    </section>
                    <a v-if="canUseTill" :href="route('pos.index')" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sm text-slate-300 hover:bg-white/5">
                        <span class="material-symbols-outlined text-[22px]">point_of_sale</span> Open the till
                    </a>
                    <Link :href="route('home')" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sm text-slate-300 hover:bg-white/5">
                        <span class="material-symbols-outlined text-[22px]">storefront</span> Back to shop
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sm text-red-400 hover:bg-white/5 text-left">
                        <span class="material-symbols-outlined text-[22px]">logout</span> Sign out
                    </Link>
                </div>
        </Modal>
    </div>
</template>

<style scoped>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
.sheet-enter-active, .sheet-leave-active { transition: opacity 0.25s ease; }
.sheet-enter-from, .sheet-leave-to { opacity: 0; }
</style>
