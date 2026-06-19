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
import { ref, computed } from 'vue';
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
const sections = [
    { id: 'home', label: 'Home', icon: 'home', primary: true },
    { id: 'orders', label: 'Orders', icon: 'receipt_long', primary: true },
    { id: 'products', label: 'Products', icon: 'inventory_2', primary: true },
    { id: 'drops', label: 'Drops', icon: 'bolt', primary: true },
    { id: 'showcase', label: 'Showcase', icon: 'photo_library', primary: false },
    { id: 'subscribers', label: 'Subscribers', icon: 'group', primary: false },
    { id: 'settings', label: 'Settings', icon: 'settings', primary: false },
];

const primary = sections.filter(s => s.primary);
const secondary = sections.filter(s => !s.primary);

const moreOpen = ref(false);

const storeName = computed(() => page.props.theme_settings?.site_name || 'Serana Studio');
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
</script>

<template>
    <div class="min-h-screen bg-[#020617] text-slate-200 font-body selection:bg-cyan-900 selection:text-white">
        <!-- ───────── Desktop sidebar ───────── -->
        <aside class="hidden lg:flex fixed left-0 top-0 h-screen w-64 flex-col bg-slate-950 border-r border-white/5 px-5 py-7 z-40">
            <Link :href="route('home')" class="flex items-center gap-3 px-2 mb-10 group">
                <img v-if="logoUrl" :src="logoUrl" alt="" class="h-8 w-8 object-contain" />
                <span v-else class="h-8 w-8 rounded-lg grid place-items-center text-slate-900 font-bold text-sm" :style="{ background: accent }">{{ initials }}</span>
                <span class="font-headline text-lg text-white truncate group-hover:text-[#57d5fc] transition-colors">{{ storeName }}</span>
            </Link>

            <nav class="flex-1 space-y-1">
                <button v-for="s in sections" :key="s.id" @click="go(s.id)"
                    :class="['w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition-colors',
                             current === s.id ? 'bg-[#57d5fc]/10 text-white font-semibold ring-1 ring-[#57d5fc]/25' : 'text-slate-400 hover:text-white hover:bg-white/5']">
                    <span class="material-symbols-outlined text-[21px]" :class="current === s.id ? 'text-[#57d5fc]' : ''">{{ s.icon }}</span>
                    {{ s.label }}
                </button>
            </nav>

            <div class="mt-auto pt-6 border-t border-white/5 space-y-1">
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
            <span class="h-8 w-8 rounded-full grid place-items-center text-slate-900 text-xs font-bold" :style="{ background: accent }">{{ initials }}</span>
        </header>

        <!-- ───────── Main content ───────── -->
        <main class="lg:ml-64 min-h-screen pb-24 lg:pb-0">
            <!-- Section header -->
            <div class="px-5 lg:px-12 pt-7 lg:pt-10 pb-6 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <h1 class="text-3xl lg:text-4xl font-headline font-light text-white tracking-tight">{{ title }}</h1>
                    <p v-if="subtitle" class="text-slate-400 text-sm mt-1.5 max-w-xl">{{ subtitle }}</p>
                </div>
                <div class="shrink-0"><slot name="actions" /></div>
            </div>

            <div class="px-5 lg:px-12 pb-12">
                <slot />
            </div>
        </main>

        <!-- ───────── Mobile bottom tab bar ───────── -->
        <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-slate-950/95 backdrop-blur-xl border-t border-white/10 flex items-stretch">
            <button v-for="s in primary" :key="s.id" @click="go(s.id)"
                class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5"
                :class="current === s.id ? 'text-[#57d5fc]' : 'text-slate-500'">
                <span class="material-symbols-outlined text-[22px]">{{ s.icon }}</span>
                <span class="text-[10px] font-medium">{{ s.label }}</span>
            </button>
            <button @click="moreOpen = true"
                class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5"
                :class="secondary.some(s => s.id === current) ? 'text-[#57d5fc]' : 'text-slate-500'">
                <span class="material-symbols-outlined text-[22px]">more_horiz</span>
                <span class="text-[10px] font-medium">More</span>
            </button>
        </nav>

        <!-- ───────── Mobile "More" sheet ───────── -->
        <transition name="sheet">
            <div v-if="moreOpen" class="lg:hidden fixed inset-0 z-50" @click.self="moreOpen = false">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
                <div class="absolute bottom-0 inset-x-0 bg-slate-900 rounded-t-3xl border-t border-white/10 p-4 pb-8 space-y-1">
                    <div class="w-10 h-1 rounded-full bg-white/15 mx-auto mb-4"></div>
                    <button v-for="s in secondary" :key="s.id" @click="go(s.id)"
                        :class="['w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sm',
                                 current === s.id ? 'bg-[#57d5fc]/10 text-white' : 'text-slate-300 hover:bg-white/5']">
                        <span class="material-symbols-outlined text-[22px]">{{ s.icon }}</span> {{ s.label }}
                    </button>
                    <Link :href="route('home')" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sm text-slate-300 hover:bg-white/5">
                        <span class="material-symbols-outlined text-[22px]">storefront</span> Back to shop
                    </Link>
                    <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center gap-3 px-4 py-3.5 rounded-xl text-sm text-red-400 hover:bg-white/5 text-left">
                        <span class="material-symbols-outlined text-[22px]">logout</span> Sign out
                    </Link>
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
.sheet-enter-active, .sheet-leave-active { transition: opacity 0.25s ease; }
.sheet-enter-from, .sheet-leave-to { opacity: 0; }
</style>
