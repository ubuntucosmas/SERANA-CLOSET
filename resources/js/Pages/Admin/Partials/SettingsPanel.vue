<!--
  Settings — merges the old "Identity" + "Settings" tabs into one plain-language
  page. Three groups: Store details, Branding, Home page images.
  Forms/handlers live in the parent; this is presentational + emits.
-->
<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    form: { type: Object, required: true }, // siteInfoForm (useForm)
});
const emit = defineEmits(['save', 'uploadAsset', 'removeAsset']);

const page = usePage();
const ts = computed(() => page.props.theme_settings || {});

function pick(key, e) {
    const file = e.target.files?.[0];
    if (file) emit('uploadAsset', key, file);
    e.target.value = '';
}

// Image settings rendered generically.
const images = [
    { key: 'site_logo', label: 'Logo', hint: 'Shown in the header and admin. PNG with transparency works best.' },
    { key: 'site_favicon', label: 'Browser icon', hint: 'The little icon in the browser tab. A square image.' },
    { key: 'hero_bg', label: 'Home background', hint: 'Large background image on the home page.' },
    { key: 'hero_detail_bg', label: 'Home accent image', hint: 'Secondary image used in the home layout.' },
];
</script>

<template>
    <div class="space-y-6 max-w-3xl">
        <!-- Store details -->
        <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-6">
            <h2 class="text-white font-headline text-xl mb-1">Store details</h2>
            <p class="text-slate-500 text-sm mb-6">The basics customers see across the site.</p>

            <div class="grid sm:grid-cols-2 gap-5">
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Store name</span>
                    <input v-model="form.settings.site_name" placeholder="e.g. Serana Closet"
                        class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                </label>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Tagline</span>
                    <input v-model="form.settings.site_tagline" placeholder="e.g. Bespoke, made with love"
                        class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                </label>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">WhatsApp number</span>
                    <input v-model="form.settings.whatsapp_number" placeholder="e.g. 0712 345 678"
                        class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    <span class="block text-xs text-slate-600 mt-1.5">Where customer chat and order messages are sent.</span>
                </label>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Studio capacity (pieces)</span>
                    <input v-model="form.settings.studio_capacity" type="number" min="1" placeholder="e.g. 20"
                        class="w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                    <span class="block text-xs text-slate-600 mt-1.5">How many orders count as a "full" studio — drives the load % on the home page.</span>
                </label>
            </div>

            <div class="mt-6 flex justify-end">
                <button @click="emit('save')" :disabled="form.processing"
                    class="px-7 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm hover:brightness-105 disabled:opacity-40 transition-all">
                    {{ form.processing ? 'Saving…' : 'Save changes' }}
                </button>
            </div>
        </section>

        <!-- Images -->
        <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-6">
            <h2 class="text-white font-headline text-xl mb-1">Branding & images</h2>
            <p class="text-slate-500 text-sm mb-6">Upload your logo and the pictures used around the site. Changes save as soon as you upload.</p>

            <div class="grid sm:grid-cols-2 gap-5">
                <div v-for="img in images" :key="img.key" class="bg-slate-950/60 ring-1 ring-white/5 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-white font-medium">{{ img.label }}</span>
                        <button v-if="ts[img.key]" @click="emit('removeAsset', img.key)" class="text-xs text-slate-500 hover:text-rose-400">Remove</button>
                    </div>
                    <div class="aspect-video rounded-lg bg-slate-900 border border-dashed border-white/10 grid place-items-center overflow-hidden mb-3">
                        <img v-if="ts[img.key]" :src="ts[img.key]" :alt="img.label" class="w-full h-full object-contain" />
                        <span v-else class="text-slate-600 text-xs">No image yet</span>
                    </div>
                    <label class="block">
                        <input type="file" accept="image/*" class="hidden" @change="pick(img.key, $event)" />
                        <span class="block text-center cursor-pointer text-xs font-semibold py-2.5 rounded-lg bg-white/5 text-slate-200 hover:bg-white/10">
                            {{ ts[img.key] ? 'Replace' : 'Upload' }}
                        </span>
                    </label>
                    <p class="text-[11px] text-slate-600 mt-2 leading-snug">{{ img.hint }}</p>
                </div>
            </div>
        </section>
    </div>
</template>
