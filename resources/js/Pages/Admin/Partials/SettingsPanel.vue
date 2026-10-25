<!--
  Settings — merges the old "Identity" + "Settings" tabs into one plain-language
  page. Store details, branding, home images, home story highlights and per-
  collection banners. Forms/handlers live in the parent; this is presentational.
-->
<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    form: { type: Object, required: true },          // siteInfoForm (useForm)
    categories: { type: Array, default: () => [] },
});
const emit = defineEmits(['save', 'uploadAsset', 'removeAsset', 'uploadCategoryBanner', 'removeCategoryBanner']);

const page = usePage();
const ts = computed(() => page.props.theme_settings || {});

function pickAsset(key, e) {
    const file = e.target.files?.[0];
    if (file) emit('uploadAsset', key, file);
    e.target.value = '';
}
function pickBanner(cat, e) {
    const file = e.target.files?.[0];
    if (file) emit('uploadCategoryBanner', cat, file);
    e.target.value = '';
}

const branding = [
    { key: 'site_logo', label: 'Logo', hint: 'Shown in the header and admin. A transparent PNG works best.' },
    { key: 'site_favicon', label: 'Browser icon', hint: 'The small icon in the browser tab. Use a square image.' },
];
const homeImages = [
    { key: 'hero_bg', label: 'Home background', hint: 'The background behind the home introduction and rotating products.' },
    { key: 'hero_detail_bg', label: 'Home accent image', hint: 'The image in the home custom-order section.' },
];
const focals = [1, 2, 3];

// Same normalisation as the server (2547… international digits), for the live preview.
const toDigits = (raw) => {
    let d = String(raw || '').replace(/\D/g, '');
    if (d.startsWith('0')) d = '254' + d.slice(1);
    else if (d.length === 9 && /^[17]/.test(d)) d = '254' + d;
    return d.length >= 11 ? d : '';
};
const pretty = (d) => (/^254(\d{3})(\d{3})(\d{3})$/.test(d) ? d.replace(/^254(\d{3})(\d{3})(\d{3})$/, '+254 $1 $2 $3') : `+${d}`);
const whatsappDigits = computed(() => toDigits(props.form.settings.whatsapp_number));
const careDigits = computed(() => toDigits(props.form.settings.care_phone));
const err = (key) => props.form.errors[`settings.${key}`];
// Where customers, orders and alerts go: the owner changes these (the server refuses anyone else).
const ownerOnly = computed(() => !(page.props.auth?.user?.abilities || []).includes('settings.contact'));
const socials = [
    { key: 'instagram_url', label: 'Instagram', placeholder: 'https://instagram.com/yourstore' },
    { key: 'tiktok_url', label: 'TikTok', placeholder: 'https://tiktok.com/@yourstore' },
    { key: 'facebook_url', label: 'Facebook', placeholder: 'https://facebook.com/yourstore' },
    { key: 'x_url', label: 'X (Twitter)', placeholder: 'https://x.com/yourstore' },
];
const field = 'w-full min-w-0 bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none';
</script>

<template>
    <div class="store-settings min-w-0 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-white font-headline text-xl">Store settings</h2>
                <p class="mt-1 text-sm text-slate-400">Manage your store profile and storefront appearance.</p>
            </div>
            <span class="rounded-full bg-white/5 px-3 py-1.5 text-xs text-slate-400">Images save on upload</span>
        </div>
        <p v-for="(message, field) in form.errors" :key="field" role="alert" class="text-sm text-rose-300">{{ message }}</p>
        <div class="grid min-w-0 items-start gap-5 xl:grid-cols-12">
        <!-- Store details & contact: the single source for name, numbers, email, address and socials -->
        <section class="min-w-0 bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-5 xl:col-span-7" aria-labelledby="store-profile-heading">
            <h2 id="store-profile-heading" class="text-white font-headline text-xl mb-1">Store details & contact</h2>
            <p class="text-slate-500 text-sm">Set these once. They update everywhere at the same time:</p>
            <p class="mt-2 mb-6 flex flex-wrap gap-1.5 text-[11px] text-slate-400">
                <span v-for="place in ['Header & footer', 'Contact page', 'WhatsApp buttons & orders', 'Emails & receipts', 'Payment instructions', 'Google listing']" :key="place" class="rounded-full bg-white/5 px-2.5 py-1">{{ place }}</span>
            </p>

            <h3 class="text-sm font-semibold text-slate-200 mb-3">Brand</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Store name</span>
                    <input v-model="form.settings.site_name" placeholder="e.g. Serana Closet" :class="field" />
                    <span v-if="err('site_name')" class="block text-xs text-rose-400 mt-1">{{ err('site_name') }}</span>
                </label>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Tagline</span>
                    <input v-model="form.settings.site_tagline" placeholder="e.g. Custom fashion, made with love" :class="field" />
                </label>
            </div>

            <h3 class="text-sm font-semibold text-slate-200 mt-7 mb-3">How customers reach you</h3>
            <p v-if="ownerOnly" class="text-xs text-slate-400 -mt-1 mb-3">The WhatsApp number, phone and emails decide where customers, orders and alerts go, so only the owner can change them.</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">WhatsApp (orders & chat)</span>
                    <input v-model="form.settings.whatsapp_number" :readonly="ownerOnly" :aria-readonly="ownerOnly" inputmode="tel" placeholder="e.g. 0712 345 678" :class="field" />
                    <span class="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <template v-if="whatsappDigits">Customers see <strong class="text-slate-300">{{ pretty(whatsappDigits) }}</strong>
                            <a :href="`https://wa.me/${whatsappDigits}`" target="_blank" rel="noopener" class="text-[#57d5fc] underline">Test</a></template>
                        <template v-else>Where every WhatsApp button and order goes.</template>
                    </span>
                    <span v-if="err('whatsapp_number')" class="block text-xs text-rose-400 mt-1">{{ err('whatsapp_number') }}</span>
                </label>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Phone for calls <span class="text-slate-500">(optional)</span></span>
                    <input v-model="form.settings.care_phone" :readonly="ownerOnly" :aria-readonly="ownerOnly" inputmode="tel" placeholder="Leave blank to use the WhatsApp number" :class="field" />
                    <span class="block text-xs text-slate-500 mt-1.5">{{ careDigits ? `Shown as ${pretty(careDigits)} on the Contact page and receipts.` : 'Customers who prefer to call use your WhatsApp number.' }}</span>
                    <span v-if="err('care_phone')" class="block text-xs text-rose-400 mt-1">{{ err('care_phone') }}</span>
                </label>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Contact email</span>
                    <input v-model="form.settings.contact_email" :readonly="ownerOnly" :aria-readonly="ownerOnly" type="email" placeholder="e.g. hello@yourstore.com" :class="field" />
                    <span v-if="err('contact_email')" class="block text-xs text-rose-400 mt-1">{{ err('contact_email') }}</span>
                </label>
                <label class="block">
                    <span class="block text-sm text-slate-300 mb-2">Address / location</span>
                    <input v-model="form.settings.contact_address" placeholder="e.g. Imenti House, Nairobi, Kenya" :class="field" />
                </label>
            </div>

            <h3 class="text-sm font-semibold text-slate-200 mt-7 mb-3">Order alerts</h3>
            <label class="block">
                <span class="block text-sm text-slate-300 mb-2">Send new-order and receipt copies to</span>
                <input v-model="form.settings.notification_email" :readonly="ownerOnly" :aria-readonly="ownerOnly" type="email" placeholder="Leave blank to use the store's sending address" :class="field" />
                <span class="block text-xs text-slate-500 mt-1.5">You get a copy of every WhatsApp order and every paid receipt here.</span>
                <span v-if="err('notification_email')" class="block text-xs text-rose-400 mt-1">{{ err('notification_email') }}</span>
            </label>

            <h3 class="text-sm font-semibold text-slate-200 mt-7 mb-1">Social media</h3>
            <p class="text-xs text-slate-500 mb-3">Leave a link blank to hide that icon. Filled links also help Google connect your profiles.</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <label v-for="social in socials" :key="social.key" class="block">
                    <span class="block text-sm text-slate-300 mb-2">{{ social.label }}</span>
                    <input v-model="form.settings[social.key]" type="url" :placeholder="social.placeholder" :class="field" />
                    <span v-if="err(social.key)" class="block text-xs text-rose-400 mt-1">{{ err(social.key) }}</span>
                </label>
            </div>

        </section>

        <div class="grid min-w-0 gap-5 md:grid-cols-2 xl:col-span-5 xl:grid-cols-1">
        <!-- Branding -->
        <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-5">
            <h2 class="text-white font-headline text-xl mb-1">Branding</h2>
            <p class="text-slate-500 text-sm mb-6">Your logo and icon. Changes save as soon as you upload.</p>
            <div class="grid sm:grid-cols-2 md:grid-cols-1 lg:grid-cols-2 gap-4">
                <div v-for="img in branding" :key="img.key" class="bg-slate-950/60 ring-1 ring-white/5 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-white font-medium">{{ img.label }}</span>
                        <button v-if="ts[img.key]" @click="emit('removeAsset', img.key)" class="text-xs text-slate-500 hover:text-rose-400">Remove</button>
                    </div>
                    <div class="h-28 rounded-lg bg-slate-900 border border-dashed border-white/10 grid place-items-center overflow-hidden mb-3">
                        <img v-if="ts[img.key]" :src="ts[img.key]" :alt="img.label" class="w-full h-full object-contain" />
                        <span v-else class="text-slate-600 text-xs">No image yet</span>
                    </div>
                    <label class="block">
                        <input type="file" accept="image/*" class="hidden" @change="pickAsset(img.key, $event)" />
                        <span class="block text-center cursor-pointer text-xs font-semibold py-2.5 rounded-lg bg-white/5 text-slate-200 hover:bg-white/10">{{ ts[img.key] ? 'Replace' : 'Upload' }}</span>
                    </label>
                    <p class="text-[11px] text-slate-600 mt-2 leading-snug">{{ img.hint }}</p>
                </div>
            </div>
        </section>

        <!-- Home page images -->
        <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-5">
            <h2 class="text-white font-headline text-xl mb-1">Home page images</h2>
            <p class="text-slate-500 text-sm mb-6">The main pictures on your home page.</p>
            <div class="grid sm:grid-cols-2 md:grid-cols-1 lg:grid-cols-2 gap-4">
                <div v-for="img in homeImages" :key="img.key" class="bg-slate-950/60 ring-1 ring-white/5 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-white font-medium">{{ img.label }}</span>
                        <button v-if="ts[img.key]" @click="emit('removeAsset', img.key)" class="text-xs text-slate-500 hover:text-rose-400">Remove</button>
                    </div>
                    <div class="aspect-video rounded-lg bg-slate-900 border border-dashed border-white/10 grid place-items-center overflow-hidden mb-3">
                        <img v-if="ts[img.key]" :src="ts[img.key]" :alt="img.label" class="w-full h-full object-cover" />
                        <span v-else class="text-slate-600 text-xs">No image yet</span>
                    </div>
                    <label class="block">
                        <input type="file" accept="image/*" class="hidden" @change="pickAsset(img.key, $event)" />
                        <span class="block text-center cursor-pointer text-xs font-semibold py-2.5 rounded-lg bg-white/5 text-slate-200 hover:bg-white/10">{{ ts[img.key] ? 'Replace' : 'Upload' }}</span>
                    </label>
                    <p class="text-[11px] text-slate-600 mt-2 leading-snug">{{ img.hint }}</p>
                </div>
            </div>
        </section>

        </div>
        </div>

        <!-- Home story highlights (focal points) -->
        <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-5">
            <h2 class="text-white font-headline text-xl mb-1">Home story highlights</h2>
            <p class="text-slate-500 text-sm mb-6">Three featured images with a short caption each, shown in the "story" area of the home page.</p>
            <div class="grid sm:grid-cols-3 gap-5">
                <div v-for="i in focals" :key="i" class="bg-slate-950/60 ring-1 ring-white/5 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-white font-medium">Highlight {{ i }}</span>
                        <button v-if="ts[`focal_${i}_bg`]" @click="emit('removeAsset', `focal_${i}_bg`)" class="text-xs text-slate-500 hover:text-rose-400">Remove</button>
                    </div>
                    <div class="aspect-[4/3] max-h-56 rounded-lg bg-slate-900 border border-dashed border-white/10 grid place-items-center overflow-hidden mb-3">
                        <img v-if="ts[`focal_${i}_bg`]" :src="ts[`focal_${i}_bg`]" :alt="`Highlight ${i}`" class="w-full h-full object-cover" />
                        <span v-else class="text-slate-600 text-xs">No image yet</span>
                    </div>
                    <label class="block mb-3">
                        <input type="file" accept="image/*" class="hidden" @change="pickAsset(`focal_${i}_bg`, $event)" />
                        <span class="block text-center cursor-pointer text-xs font-semibold py-2.5 rounded-lg bg-white/5 text-slate-200 hover:bg-white/10">{{ ts[`focal_${i}_bg`] ? 'Replace' : 'Upload' }}</span>
                    </label>
                    <input v-model="form.settings[`focal_${i}_label`]" :placeholder="`Caption ${i}`" :aria-label="`Caption for home image ${i}`"
                        class="w-full bg-slate-950 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:ring-1 focus:ring-[#57d5fc] outline-none" />
                </div>
            </div>
        </section>

        <!-- Collection banners -->
        <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-5">
            <h2 class="text-white font-headline text-xl mb-1">Collection banners</h2>
            <p class="text-slate-500 text-sm mb-6">A background image for each collection's page. Manage the collections themselves under Products → Categories.</p>
            <div v-if="categories.length === 0" class="text-slate-500 text-sm text-center py-8">No collections yet — add some under Products → Categories.</div>
            <div v-else class="grid grid-cols-1 min-[400px]:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                <div v-for="cat in categories" :key="cat.id" class="bg-slate-950/60 ring-1 ring-white/5 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm text-white font-medium truncate">{{ cat.name }}</span>
                        <button v-if="cat.banner_url" @click="emit('removeCategoryBanner', cat)" class="text-xs text-slate-500 hover:text-rose-400 shrink-0">Remove</button>
                    </div>
                    <div class="aspect-[4/3] rounded-lg bg-slate-900 border border-dashed border-white/10 grid place-items-center overflow-hidden mb-3">
                        <img v-if="cat.banner_url" :src="cat.banner_url" :alt="cat.name" class="w-full h-full object-cover" />
                        <span v-else class="text-slate-600 text-xs">No banner yet</span>
                    </div>
                    <label class="block">
                        <input type="file" accept="image/*" class="hidden" @change="pickBanner(cat, $event)" />
                        <span class="block text-center cursor-pointer text-xs font-semibold py-2.5 rounded-lg bg-white/5 text-slate-200 hover:bg-white/10">{{ cat.banner_url ? 'Replace' : 'Upload' }}</span>
                    </label>
                </div>
            </div>
        </section>
        <div class="sticky bottom-24 z-10 lg:bottom-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-slate-900 p-4 shadow-xl">
            <div class="min-w-0 text-sm">
                <p v-if="form.recentlySuccessful" role="status" class="text-emerald-300">Saved: updated everywhere</p>
                <p v-else class="text-slate-300">{{ form.isDirty ? 'You have unsaved changes' : 'Store details & story captions' }}</p>
                <p class="mt-1 text-xs text-slate-400">Save text changes here. Image uploads and removals save immediately.</p>
            </div>
            <button type="button" @click="emit('save')" :disabled="form.processing"
                class="shrink-0 px-6 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm hover:brightness-105 disabled:opacity-40 transition-all">
                {{ form.processing ? 'Saving…' : 'Save changes' }}
            </button>
        </div>
    </div>
</template>
