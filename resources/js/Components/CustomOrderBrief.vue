<!--
  CustomOrderBrief — the custom order form fields, shared by the shop's
  Custom Order page and the Sales Desk. The parent owns the form object and
  the submit; this component only fills it.

  `staff` switches the wording to "the customer", adds an optional email,
  and lets staff type a garment or fabric the shop doesn't list (the desk
  endpoint accepts any; the shop endpoint only the Studio's options).
-->
<script setup>
import { ref, computed, onUnmounted } from "vue";
import SizingFigure from "@/Components/SizingFigure.vue";

const props = defineProps({
    // The parent's reactive form: this component fills its fields in place.
    form: { type: Object, required: true },
    // Managed in Studio → Settings → Custom order options.
    options: { type: Object, default: () => ({ garments: [], fabrics: [], colours: [] }) },
    errors: { type: Object, default: () => ({}) },
    staff: { type: Boolean, default: false },
});
const emit = defineEmits(["blur"]);

const garments = computed(() => props.options.garments || []);
const fabrics = computed(() => props.options.fabrics || []);
const colours = computed(() => props.options.colours || []);
const idp = props.staff ? "desk-co" : "customorder";

const activeField = ref(null);
const fileInput = ref(null);
const fileError = ref(null);
// Local date (not UTC) so "today" is right in Nairobi late at night.
const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

const copy = computed(() => props.staff
    ? { details: "Customer", name: "Customer name", namePh: "Customer's full name", phone: "Phone (WhatsApp)",
        garment: "What are we making?", deadline: "Needed by", notes: "Notes for the workroom",
        notesPh: "Fit preferences, special requests, how they found us...",
        noFabrics: "No fabrics are listed in Studio settings. Type one below.",
        noColours: "No colours are listed in Studio settings. Type one below." }
    : { details: "Your details", name: "Full Name", namePh: "Your full name", phone: "WhatsApp Number",
        garment: "What are we making?", deadline: "When do you need it?", notes: "Anything else we should know?",
        notesPh: "Special requests, fit preferences, deadline pressure...",
        noFabrics: "We’ll suggest fabrics when we discuss your order on WhatsApp.",
        noColours: "Tell us the colour you have in mind in the notes below." });

// Staff may type a value the shop doesn't list; a listed one lights its chip.
const garmentIsListed = computed(() => garments.value.some((g) => g.label === props.form.outfit_type));
const fabricIsListed = computed(() => fabrics.value.includes(props.form.fabric_preference));
const colourIsListed = computed(() => colours.value.some((c) => c.name === props.form.selected_color));

const measurements = [["Height", "height_cm"], ["Bust", "bust_cm"], ["Waist", "waist_cm"], ["Hips", "hips_cm"], ["Length", "length_cm"]];

// One preview URL per file, made once and revoked when the file goes.
const previews = ref([]);
const isDragging = ref(false);
const ACCEPTED = ["image/jpeg", "image/png", "image/webp", "application/pdf"];

function addFiles(files) {
    const room = 6 - props.form.images.length;
    const accepted = Array.from(files || []).filter((file) => ACCEPTED.includes(file.type));
    fileError.value = accepted.length < (files?.length || 0)
        ? "Some files were skipped. Use JPG, PNG, WEBP or PDF. On iPhone, set Camera → Formats → Most Compatible."
        : null;
    accepted.slice(0, room).forEach((file) => {
        props.form.images.push(file);
        previews.value.push({ url: file.type.startsWith("image/") ? URL.createObjectURL(file) : null, name: file.name });
    });
}
function handleFileSelect(e) {
    addFiles(e.target.files);
    e.target.value = ""; // picking the same file again still fires change
}
function handleDrop(e) {
    isDragging.value = false;
    addFiles(e.dataTransfer?.files);
}
function removeImage(index) {
    const preview = previews.value[index];
    if (preview?.url) URL.revokeObjectURL(preview.url);
    previews.value.splice(index, 1);
    props.form.images.splice(index, 1);
}
function releasePreviews() {
    previews.value.forEach((p) => p.url && URL.revokeObjectURL(p.url));
    previews.value = [];
    fileError.value = null;
}
onUnmounted(releasePreviews);
defineExpose({ releasePreviews });

const toggle = (field, value) => { props.form[field] = props.form[field] === value ? "" : value; };

const card = "bg-surface border border-black/10 dark:border-white/10 rounded-3xl p-4 sm:p-6 md:p-8";
const kicker = "text-xs tracking-wider text-primary mb-6 font-medium";
const label = "block text-sm text-on-surface-variant mb-2";
const field = "w-full bg-background border border-black/15 dark:border-white/20 focus:border-primary px-4 py-3.5 rounded-2xl text-base text-on-surface placeholder:text-on-surface-variant/50";
const errorText = "text-red-400 text-sm mt-1";
const chip = (on) => on ? "bg-primary text-black border-primary" : "border-black/15 dark:border-white/20 hover:border-black/40 dark:hover:border-white/40 hover:text-on-surface";
</script>

<template>
    <div class="space-y-7">
        <!-- 01 Details -->
        <div :class="card">
            <div :class="kicker">01 — {{ copy.details }}</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label :class="label" :for="`${idp}-full_name`">{{ copy.name }}</label>
                    <input :id="`${idp}-full_name`" v-model="form.full_name" :autocomplete="staff ? 'off' : 'name'" @blur="emit('blur', 'full_name')"
                        type="text" required maxlength="255" :class="field" :placeholder="copy.namePh" />
                    <p v-if="errors.full_name" :class="errorText">{{ errors.full_name }}</p>
                </div>
                <div>
                    <label :class="label" :for="`${idp}-whatsapp_number`">{{ copy.phone }}</label>
                    <input :id="`${idp}-whatsapp_number`" v-model="form.whatsapp_number" type="tel" :autocomplete="staff ? 'off' : 'tel'" required
                        :aria-invalid="!!errors.whatsapp_number" :class="field" :placeholder="staff ? '07XX XXX XXX' : '+254 712 345 678'" />
                    <p v-if="errors.whatsapp_number" :class="errorText">{{ errors.whatsapp_number }}</p>
                </div>
                <div v-if="staff">
                    <label :class="label" :for="`${idp}-email`">Email (optional)</label>
                    <input :id="`${idp}-email`" v-model="form.email" type="email" autocomplete="off" maxlength="255" :class="field" placeholder="For the receipt" />
                    <p v-if="errors.email" :class="errorText">{{ errors.email }}</p>
                </div>
                <div :class="staff ? '' : 'md:col-span-2'">
                    <label :class="label" :for="`${idp}-location`">City / Country</label>
                    <input :id="`${idp}-location`" v-model="form.location" type="text" maxlength="255" :class="field" placeholder="Nairobi, Kenya" />
                </div>
            </div>
        </div>

        <!-- 02 The garment -->
        <div :class="card">
            <div :class="kicker">02 — The garment</div>
            <p :id="`${idp}-garment-label`" class="block text-sm text-on-surface-variant mb-3">{{ copy.garment }}</p>
            <div class="flex flex-wrap gap-2" role="group" :aria-labelledby="`${idp}-garment-label`">
                <button v-for="garment in garments" :key="garment.key" type="button" @click="toggle('outfit_type', garment.label)"
                    :aria-pressed="form.outfit_type === garment.label"
                    class="px-6 py-2.5 rounded-full border text-sm transition-all" :class="chip(form.outfit_type === garment.label)">
                    {{ garment.label }}
                </button>
            </div>
            <div v-if="staff" class="mt-4 max-w-sm">
                <label :class="label" :for="`${idp}-garment-other`">Something else</label>
                <input :id="`${idp}-garment-other`" :value="garmentIsListed ? '' : form.outfit_type" @input="form.outfit_type = $event.target.value"
                    type="text" maxlength="100" :class="field" placeholder="e.g. Kaftan" />
            </div>
            <p v-if="errors.outfit_type" class="text-red-400 text-sm mt-2">{{ errors.outfit_type }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                <div>
                    <label :for="`${idp}-occasion`" :class="label">Occasion</label>
                    <input :id="`${idp}-occasion`" v-model="form.occasion" type="text" maxlength="100" placeholder="Wedding, Gala, Daily wear..." :class="field" />
                </div>
                <div>
                    <label :class="label" :for="`${idp}-deadline`">{{ copy.deadline }}</label>
                    <input :id="`${idp}-deadline`" v-model="form.deadline" type="date" :min="today" :class="field" />
                    <p v-if="errors.deadline" :class="errorText">{{ errors.deadline }}</p>
                </div>
            </div>
        </div>

        <!-- 03 Measurements -->
        <div :class="card">
            <div class="mb-6">
                <div class="text-xs tracking-wider text-primary font-medium">03 — Precision mapping</div>
                <p class="text-on-surface-variant text-sm mt-1">Guidance for a perfect custom fit (optional)</p>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div class="lg:col-span-5">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div v-for="m in measurements" :key="m[1]">
                            <label :for="`${idp}-${m[1]}`" class="block text-xs text-on-surface-variant mb-1.5">{{ m[0] }} (cm)</label>
                            <input :id="`${idp}-${m[1]}`" v-model="form[m[1]]" type="number" inputmode="decimal" step="0.5"
                                :min="m[1] === 'height_cm' ? 50 : m[1] === 'length_cm' ? 10 : 30" max="250" placeholder="—"
                                @focus="activeField = m[1]" @blur="activeField = null"
                                class="w-full bg-background border border-black/15 dark:border-white/20 focus:border-primary px-3 py-2.5 rounded-lg text-sm text-center text-on-surface transition-all" />
                            <p v-if="errors[m[1]]" class="text-red-400 text-xs mt-1">{{ errors[m[1]] }}</p>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-7">
                    <SizingFigure :active-field="activeField" :measurements="form" @pick="activeField = $event" />
                </div>
            </div>
        </div>

        <!-- 04 Fabric & colour -->
        <div :class="card">
            <div :class="kicker">04 — Fabric & colour</div>
            <div class="mb-8">
                <p class="block text-sm text-on-surface-variant mb-3">Preferred Fabric</p>
                <div v-if="fabrics.length" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <button v-for="fabric in fabrics" :key="fabric" type="button" @click="toggle('fabric_preference', fabric)"
                        :aria-pressed="form.fabric_preference === fabric"
                        class="rounded-2xl border px-5 py-6 text-center transition-all flex flex-col items-center justify-center"
                        :class="form.fabric_preference === fabric ? 'border-primary bg-primary/5 text-primary' : 'border-black/10 dark:border-white/10 hover:border-black/30 dark:hover:border-white/30'">
                        <span class="text-xs tracking-wider text-on-surface-variant mb-1">FABRIC</span>
                        <span class="font-medium text-lg">{{ fabric }}</span>
                    </button>
                </div>
                <p v-else class="text-sm text-on-surface-variant">{{ copy.noFabrics }}</p>
                <div v-if="staff" class="mt-4 max-w-sm">
                    <label :class="label" :for="`${idp}-fabric-other`">Another fabric</label>
                    <input :id="`${idp}-fabric-other`" :value="fabricIsListed ? '' : form.fabric_preference" @input="form.fabric_preference = $event.target.value"
                        type="text" maxlength="255" :class="field" placeholder="e.g. Linen" />
                </div>
                <p v-if="errors.fabric_preference" class="text-red-400 text-sm mt-2">{{ errors.fabric_preference }}</p>
            </div>
            <div>
                <p class="block text-sm text-on-surface-variant mb-3">Colour Direction</p>
                <p v-if="!colours.length" class="text-sm text-on-surface-variant">{{ copy.noColours }}</p>
                <div v-else class="flex flex-wrap gap-3">
                    <button v-for="color in colours" :key="color.name" type="button" @click="toggle('selected_color', color.name)"
                        :aria-pressed="form.selected_color === color.name"
                        class="flex items-center gap-3 px-5 py-2.5 rounded-full border text-sm transition-all"
                        :class="form.selected_color === color.name ? 'border-primary bg-primary/10 text-primary' : 'border-black/15 dark:border-white/20 hover:border-black/40 dark:hover:border-white/50'">
                        <span class="w-6 h-6 rounded-full border border-black/20 dark:border-white/20 shadow-inner" :style="{ backgroundColor: color.hex }"></span>
                        {{ color.name }}
                    </button>
                </div>
                <div v-if="staff" class="mt-4 max-w-sm">
                    <label :class="label" :for="`${idp}-colour-other`">Another colour</label>
                    <input :id="`${idp}-colour-other`" :value="colourIsListed ? '' : form.selected_color" @input="form.selected_color = $event.target.value"
                        type="text" maxlength="100" :class="field" placeholder="e.g. Emerald" />
                </div>
                <p v-if="errors.selected_color" class="text-red-400 text-sm mt-2">{{ errors.selected_color }}</p>
            </div>
        </div>

        <!-- 05 Inspiration & notes -->
        <div :class="card">
            <div :class="kicker">05 — Inspiration & notes</div>
            <div class="mb-8">
                <p class="block text-sm text-on-surface-variant mb-3">Upload reference images (optional)</p>
                <div role="button" tabindex="0" @click="fileInput?.click()" @keydown.enter.prevent="fileInput?.click()" @keydown.space.prevent="fileInput?.click()"
                    @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false" @drop.prevent="handleDrop"
                    class="border-2 border-dashed rounded-2xl p-7 text-center cursor-pointer transition-all"
                    :class="isDragging ? 'border-primary bg-primary/5' : 'border-black/15 dark:border-white/20 hover:border-primary/60'">
                    <span class="material-symbols-outlined text-4xl text-on-surface-variant/50 mb-2" aria-hidden="true">add_photo_alternate</span>
                    <p class="text-on-surface-variant">{{ isDragging ? 'Drop to add' : 'Tap to add photos, or drop them here' }}</p>
                    <p class="text-xs text-on-surface-variant mt-1">Up to 6 images • JPG, PNG, WEBP or PDF, 10MB each</p>
                </div>
                <input type="file" ref="fileInput" multiple accept="image/jpeg,image/png,image/webp,application/pdf" @change="handleFileSelect" class="hidden" />
                <p v-if="fileError" class="text-red-400 text-sm mt-2">{{ fileError }}</p>
                <p v-if="errors.images" class="text-red-400 text-sm mt-2">{{ errors.images }}</p>
                <div v-if="form.images.length" class="flex flex-wrap gap-3 mt-4">
                    <div v-for="(preview, i) in previews" :key="preview.url || preview.name + i"
                        class="relative w-20 h-20 bg-black rounded-xl overflow-hidden border border-white/10">
                        <img v-if="preview.url" :src="preview.url" :alt="`Reference ${i + 1}`" class="w-full h-full object-cover" />
                        <div v-else class="flex h-full w-full flex-col items-center justify-center gap-1 p-1 text-white/80">
                            <span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>
                            <span class="w-full truncate text-center text-[10px]">{{ preview.name }}</span>
                        </div>
                        <button type="button" @click.stop="removeImage(i)" :aria-label="`Remove ${preview.name}`"
                            class="absolute top-0.5 right-0.5 w-8 h-8 bg-black/70 text-white rounded-full flex items-center justify-center">
                            <span class="material-symbols-outlined text-base" aria-hidden="true">close</span>
                        </button>
                    </div>
                </div>
            </div>
            <div>
                <label :for="`${idp}-notes`" :class="label">{{ copy.notes }}</label>
                <textarea :id="`${idp}-notes`" v-model="form.notes" rows="3" maxlength="5000" :placeholder="copy.notesPh"
                    class="w-full bg-background border border-black/15 dark:border-white/20 focus:border-primary px-4 py-3 rounded-xl text-sm text-on-surface resize-y"></textarea>
            </div>
        </div>

        <slot />
    </div>
</template>
