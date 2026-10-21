<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import { ref, reactive, onUnmounted } from "vue";
import { Head } from "@inertiajs/vue3";
import StorefrontLayout from "@/Layouts/StorefrontLayout.vue";
import SizingFigure from "@/Components/SizingFigure.vue";

const props = defineProps({
    baseProduct: { type: Object, default: null },
    // Managed in Studio → Settings → Custom order options.
    options: { type: Object, default: () => ({ garments: [], fabrics: [], colours: [] }) },
});

const isSubmitting = ref(false);
const globalError = ref(null);
const fileInput = ref(null);
const activeField = ref(null);

// Success state (shown after the brief is sent — survives popup blockers).
const submitted = ref(false);
const whatsappUrl = ref(null);
const reference = ref(null);

// Measurement guidance now lives inside SizingFigure.

const garments = props.options.garments || [];
const fabrics = props.options.fabrics || [];
const colours = props.options.colours || [];

const form = reactive({
    request_id: crypto.randomUUID(),
    full_name: "",
    whatsapp_number: "",
    location: "",
    outfit_type: "",
    fabric_preference: "",
    selected_color: "",
    notes: "",
    bust_cm: null,
    waist_cm: null,
    hips_cm: null,
    length_cm: null,
    height_cm: null,
    deadline: "",
    occasion: "",
    selected_fit: "",
    images: [],
    priority_pass: false,
});

const validationErrors = ref({});
// Local date (not UTC) so "today" is right in Nairobi late at night.
const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

// Pre-fill from a product the client arrived from (e.g. "Make this custom").
if (props.baseProduct) {
    form.notes = `Inspired by: ${props.baseProduct.name}`;
    // A product's garment_type (set / hoodie / jogger) picks the matching garment.
    const match = garments.find((g) => g.key === props.baseProduct.garment_type);
    if (match) form.outfit_type = match.label;
}

function triggerFileInput() {
    fileInput.value?.click();
}

// One preview URL per file, made once and revoked when the file goes.
// (Creating them in the template minted new blob URLs on every keystroke.)
const previews = ref([]);
const isDragging = ref(false);
const ACCEPTED = ["image/jpeg", "image/png", "image/webp", "application/pdf"];

function addFiles(files) {
    const room = 6 - form.images.length;
    const accepted = Array.from(files || []).filter((file) => ACCEPTED.includes(file.type));
    if (accepted.length < (files?.length || 0)) {
        globalError.value = "Some files were skipped. Use JPG, PNG, WEBP or PDF. On iPhone, set Camera → Formats → Most Compatible.";
    }
    accepted.slice(0, room).forEach((file) => {
        form.images.push(file);
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
    form.images.splice(index, 1);
}

function releasePreviews() {
    previews.value.forEach((p) => p.url && URL.revokeObjectURL(p.url));
    previews.value = [];
}
onUnmounted(releasePreviews);

function validateField(field) {
    // simple client validation
    if (!form[field] && ["full_name", "whatsapp_number"].includes(field)) {
        validationErrors.value[field] = "Required";
    } else {
        delete validationErrors.value[field];
    }
}

async function submitOrder() {
    if (isSubmitting.value) return;
    validationErrors.value = {};
    if (!form.full_name || !form.whatsapp_number) {
        if (!form.full_name)
            validationErrors.value.full_name = "Please enter your name";
        if (!form.whatsapp_number)
            validationErrors.value.whatsapp_number =
                "WhatsApp number is required";
        return;
    }

    isSubmitting.value = true;
    globalError.value = null;

    const formData = new FormData();
    Object.keys(form).forEach((key) => {
        if (key === "images") {
            form.images.forEach((file, i) =>
                formData.append(`images[${i}]`, file),
            );
        } else if (form[key] !== null && form[key] !== "") {
            formData.append(key, form[key]);
        }
    });

    try {
        const response = await axios.post(
            route("custom-orders.store"),
            formData,
            {
                headers: { "Content-Type": "multipart/form-data" },
            },
        );

        if (response.data.success && response.data.whatsapp_url) {
            whatsappUrl.value = response.data.whatsapp_url;
            reference.value = response.data.reference;

            // Free preview memory now that the files are uploaded.
            releasePreviews();

            // Show the confirmation panel (works even if the popup is blocked),
            // then optimistically try to open WhatsApp in a new tab.
            submitted.value = true;
            window.scrollTo({ top: 0, behavior: "smooth" });
            window.open(whatsappUrl.value, "_blank");
        }
    } catch (err) {
        // Put each server message under its own field where we have one.
        const fieldErrors = err.response?.status === 422 ? err.response.data?.errors : null;
        if (fieldErrors) {
            validationErrors.value = Object.fromEntries(Object.entries(fieldErrors).map(([k, v]) => [k.split(".")[0], v[0]]));
        }
        globalError.value = err.response?.status === 413
            ? "Your photos are too large to send together. Try fewer or smaller images (under 10MB each)."
            : (err.response?.data?.message || "Something went wrong. Please try again.");
    } finally {
        isSubmitting.value = false;
    }
}

function resetForm() {
    Object.keys(form).forEach((k) => {
        if (Array.isArray(form[k])) form[k] = [];
        else if (typeof form[k] === "boolean") form[k] = false;
        else if (["bust_cm", "waist_cm", "hips_cm", "length_cm", "height_cm"].includes(k))
            form[k] = null;
        else form[k] = "";
    });
    releasePreviews();
    form.request_id = crypto.randomUUID(); // a new brief gets its own duplicate-protection key
    validationErrors.value = {};
    globalError.value = null;
    whatsappUrl.value = null;
    reference.value = null;
    submitted.value = false;
}
</script>

<template>
    <StorefrontLayout>
        <Head title="Custom Order | Serana Closet" />

        <div class="min-h-screen bg-background text-on-surface pb-24">
            <div class="page-container">
                <!-- Modern Header -->
                <PageHeader v-if="!submitted" kicker="Custom Order" title="Start Your" accent="Custom Order" subtitle="Tell us what you have in mind. We’ll discuss the design, quote and timing with you." />

                <!-- The Form -->
                <form v-if="!submitted" @submit.prevent="submitOrder" class="space-y-7">
                    <!-- Your Details -->
                    <div
                        class="bg-surface border border-white/10 rounded-3xl p-4 sm:p-6 md:p-8"
                    >
                        <div
                            class="text-xs tracking-wider text-primary mb-6 font-medium"
                        >
                            01 — Your details
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-sm text-on-surface-variant mb-2"
                                     for="customorder-full_name">Full Name</label>
                                <input
                                    id="customorder-full_name" v-model="form.full_name"
                                    autocomplete="name"
                                    @blur="validateField('full_name')"
                                    type="text"
                                    required
                                    class="w-full bg-background border border-white/20 focus:border-primary px-4 py-3.5 rounded-2xl text-base placeholder:text-on-surface-variant/50"
                                    placeholder="Your full name"
                                />
                                <p
                                    v-if="validationErrors.full_name"
                                    class="text-red-400 text-sm mt-1"
                                >
                                    {{ validationErrors.full_name }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="block text-sm text-on-surface-variant mb-2"
                                     for="customorder-whatsapp_number">WhatsApp Number</label>
                                <input
                                    id="customorder-whatsapp_number" v-model="form.whatsapp_number"
                                    type="tel"
                                    autocomplete="tel"
                                    required
                                    :aria-invalid="!!validationErrors.whatsapp_number"
                                    class="w-full bg-background border border-white/20 focus:border-primary px-4 py-3.5 rounded-2xl text-base placeholder:text-on-surface-variant/50"
                                    placeholder="+254 712 345 678"
                                />
                                <p v-if="validationErrors.whatsapp_number" class="text-red-400 text-sm mt-1">
                                    {{ validationErrors.whatsapp_number }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <label
                                class="block text-sm text-on-surface-variant mb-2"
                                 for="customorder-location">City / Country</label>
                            <input
                                id="customorder-location" v-model="form.location"
                                type="text"
                                class="w-full bg-background border border-white/20 focus:border-primary px-4 py-3.5 rounded-2xl text-base placeholder:text-on-surface-variant/50"
                                placeholder="Nairobi, Kenya"
                            />
                        </div>
                    </div>

                    <!-- The Garment -->
                    <div
                        class="bg-surface border border-white/10 rounded-3xl p-4 sm:p-6 md:p-8"
                    >
                        <div
                            class="text-xs tracking-wider text-primary mb-6 font-medium"
                        >
                            02 — The garment
                        </div>

                        <div>
                            <p id="customorder-garment-label" class="block text-sm text-on-surface-variant mb-3">What are we making?</p>
                            <div class="flex flex-wrap gap-2" role="group" aria-labelledby="customorder-garment-label">
                                <button
                                    v-for="garment in garments"
                                    :key="garment.key"
                                    type="button"
                                    @click="form.outfit_type = form.outfit_type === garment.label ? '' : garment.label"
                                    :aria-pressed="form.outfit_type === garment.label"
                                    class="px-6 py-2.5 rounded-full border text-sm transition-all"
                                    :class="
                                        form.outfit_type === garment.label
                                            ? 'bg-primary text-black border-primary'
                                            : 'border-black/15 dark:border-white/20 hover:border-black/40 dark:hover:border-white/40 hover:text-on-surface'
                                    "
                                >
                                    {{ garment.label }}
                                </button>
                            </div>
                            <p v-if="validationErrors.outfit_type" class="text-red-400 text-sm mt-2">{{ validationErrors.outfit_type }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8">
                            <div>
                                <label
                                    class="block text-sm text-on-surface-variant mb-2"
                                    >Occasion</label>
                                <input
                                    id="customorder-occasion" v-model="form.occasion"
                                    type="text"
                                    placeholder="Wedding, Gala, Daily wear..."
                                    class="w-full bg-background border border-white/20 focus:border-primary px-5 py-4 rounded-2xl"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm text-on-surface-variant mb-2"
                                     for="customorder-deadline">When do you need it?</label>
                                <input
                                    id="customorder-deadline" v-model="form.deadline"
                                    type="date"
                                    :min="today"
                                    class="w-full bg-background border border-black/15 dark:border-white/20 focus:border-primary px-5 py-4 rounded-xl text-on-surface"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Measurements with Precision Mapping -->
                    <div
                        class="bg-surface border border-white/10 rounded-3xl p-4 sm:p-6 md:p-8"
                    >
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <div
                                    class="text-xs tracking-wider text-primary font-medium"
                                >
                                    03 — Precision mapping
                                </div>
                                <p class="text-on-surface-variant text-sm mt-1">
                                    Guidance for a perfect custom fit
                                    (optional)
                                </p>
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start"
                        >
                            <!-- Inputs -->
                            <div class="lg:col-span-5">
                                <div
                                    class="grid grid-cols-2 md:grid-cols-5 gap-4"
                                >
                                    <div
                                        v-for="m in [
                                            ['Height', 'height_cm'],
                                            ['Bust', 'bust_cm'],
                                            ['Waist', 'waist_cm'],
                                            ['Hips', 'hips_cm'],
                                            ['Length', 'length_cm'],
                                        ]"
                                        :key="m[1]"
                                    >
                                        <label
                                            :for="`customorder-${m[1]}`"
                                            class="block text-xs text-on-surface-variant mb-1.5"
                                            >{{ m[0] }} (cm)</label
                                        >
                                        <input
                                            :id="`customorder-${m[1]}`"
                                            v-model="form[m[1]]"
                                            type="number"
                                            inputmode="decimal"
                                            :min="m[1] === 'height_cm' ? 50 : m[1] === 'length_cm' ? 10 : 30"
                                            max="250"
                                            placeholder="—"
                                            @focus="activeField = m[1]"
                                            @blur="activeField = null"
                                            class="w-full bg-background border border-white/20 focus:border-primary px-3 py-2.5 rounded-lg text-sm text-center transition-all"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Guided sizing figure -->
                            <div class="lg:col-span-7">
                                <SizingFigure
                                    :active-field="activeField"
                                    :measurements="form"
                                    @pick="activeField = $event"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Fabric & Color -->
                    <div
                        class="bg-surface border border-white/10 rounded-3xl p-4 sm:p-6 md:p-8"
                    >
                        <div
                            class="text-xs tracking-wider text-primary mb-6 font-medium"
                        >
                            04 — Fabric & colour
                        </div>

                        <div class="mb-8">
                            <label
                                class="block text-sm text-on-surface-variant mb-3"
                                 >Preferred Fabric</label
                            >
                            <div v-if="fabrics.length" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <button
                                    v-for="fabric in fabrics"
                                    :key="fabric"
                                    type="button"
                                    @click="form.fabric_preference = form.fabric_preference === fabric ? '' : fabric"
                                    :aria-pressed="form.fabric_preference === fabric"
                                    class="rounded-2xl border px-5 py-6 text-center transition-all flex flex-col items-center justify-center"
                                    :class="
                                        form.fabric_preference === fabric
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : 'border-black/10 dark:border-white/10 hover:border-black/30 dark:hover:border-white/30'
                                    "
                                >
                                    <span class="text-xs tracking-wider text-on-surface-variant mb-1">FABRIC</span>
                                    <span class="font-medium text-lg">{{ fabric }}</span>
                                </button>
                            </div>
                            <p v-else class="text-sm text-on-surface-variant">We’ll suggest fabrics when we discuss your order on WhatsApp.</p>
                            <p v-if="validationErrors.fabric_preference" class="text-red-400 text-sm mt-2">{{ validationErrors.fabric_preference }}</p>
                        </div>

                        <div>
                            <label
                                class="block text-sm text-on-surface-variant mb-3"
                                >Colour Direction</label
                            >
                            <p v-if="!colours.length" class="text-sm text-on-surface-variant">Tell us the colour you have in mind in the notes below.</p>
                            <div v-else class="flex flex-wrap gap-3">
                                <button
                                    v-for="color in colours"
                                    :key="color.name"
                                    type="button"
                                    @click="form.selected_color = form.selected_color === color.name ? '' : color.name"
                                    :aria-pressed="form.selected_color === color.name"
                                    class="flex items-center gap-3 px-5 py-2.5 rounded-full border text-sm transition-all"
                                    :class="
                                        form.selected_color === color.name
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-black/15 dark:border-white/20 hover:border-black/40 dark:hover:border-white/50'
                                    "
                                >
                                    <div
                                        class="w-6 h-6 rounded-full border border-black/20 dark:border-white/20 shadow-inner"
                                        :style="{ backgroundColor: color.hex }"
                                    ></div>
                                    {{ color.name }}
                                </button>
                            </div>
                            <p v-if="validationErrors.selected_color" class="text-red-400 text-sm mt-2">{{ validationErrors.selected_color }}</p>
                        </div>
                    </div>

                    <!-- Inspiration & Notes -->
                    <div
                        class="bg-surface border border-white/10 rounded-3xl p-4 sm:p-6 md:p-8"
                    >
                        <div
                            class="text-xs tracking-wider text-primary mb-6 font-medium"
                        >
                            05 — Inspiration & notes
                        </div>

                        <!-- Image Upload -->
                        <div class="mb-8">
                            <label
                                class="block text-sm text-on-surface-variant mb-3"
                                >Upload reference images (optional)</label
                            >
                            <div
                                role="button"
                                tabindex="0"
                                @click="triggerFileInput"
                                @keydown.enter.prevent="triggerFileInput"
                                @keydown.space.prevent="triggerFileInput"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="handleDrop"
                                class="border-2 border-dashed rounded-2xl p-7 text-center cursor-pointer transition-all"
                                :class="isDragging ? 'border-primary bg-primary/5' : 'border-black/15 dark:border-white/20 hover:border-primary/60'"
                            >
                                <span
                                    class="material-symbols-outlined text-4xl text-on-surface-variant/50 mb-2"
                                    >add_photo_alternate</span
                                >
                                <p class="text-on-surface-variant">
                                    {{ isDragging ? 'Drop to add' : 'Tap to add photos, or drop them here' }}
                                </p>
                                <p class="text-xs text-on-surface-variant mt-1">
                                    Up to 6 images • JPG, PNG, WEBP or PDF, 10MB each
                                </p>
                            </div>
                            <input
                                type="file"
                                ref="fileInput"
                                multiple
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                @change="handleFileSelect"
                                class="hidden"
                            />

                            <div
                                v-if="form.images.length"
                                class="flex flex-wrap gap-3 mt-4"
                            >
                                <div
                                    v-for="(preview, i) in previews"
                                    :key="preview.url || preview.name + i"
                                    class="relative w-20 h-20 bg-black rounded-xl overflow-hidden border border-white/10"
                                >
                                    <img
                                        v-if="preview.url"
                                        :src="preview.url"
                                        :alt="`Reference ${i + 1}`"
                                        class="w-full h-full object-cover"
                                    />
                                    <div v-else class="flex h-full w-full flex-col items-center justify-center gap-1 p-1 text-white/80">
                                        <span class="material-symbols-outlined" aria-hidden="true">receipt_long</span>
                                        <span class="w-full truncate text-center text-[10px]">{{ preview.name }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        @click.stop="removeImage(i)"
                                        :aria-label="`Remove ${preview.name}`"
                                        class="absolute top-0.5 right-0.5 w-8 h-8 bg-black/70 text-white rounded-full flex items-center justify-center"
                                    >
                                        <span class="material-symbols-outlined text-base" aria-hidden="true">close</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-sm text-on-surface-variant mb-2"
                                >Anything else we should know?</label>
                            <textarea
                                id="customorder-notes" v-model="form.notes"
                                rows="3"
                                placeholder="Special requests, fit preferences, deadline pressure..."
                                class="w-full bg-background border border-white/20 focus:border-primary px-4 py-3 rounded-xl text-sm resize-y"
                            ></textarea>
                        </div>
                    </div>

                    <p class="text-sm text-on-surface-variant">Your name and WhatsApp number are required. Measurements and other details are optional. No payment is taken here; the studio will confirm the design, price and timing with you on WhatsApp.</p>
                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="action-primary w-full py-4"
                    >
                        <span>{{
                            isSubmitting
                                ? "Saving Request…"
                                : "Send My Custom Order"
                        }}</span>
                        <span
                            v-if="!isSubmitting"
                            class="material-symbols-outlined text-lg"
                            >arrow_forward</span
                        >
                    </button>

                    <p
                        class="text-center text-xs text-on-surface-variant tracking-wider"
                    >
                        Your request is saved first. Open WhatsApp to send the brief to the studio and discuss the quote and timing.
                    </p>

                    <div
                        v-if="globalError"
                        class="text-center text-red-400 text-sm"
                    >
                        {{ globalError }}
                    </div>
                </form>

                <!-- Success / Brief Sent -->
                <div v-else class="max-w-xl mx-auto text-center py-10">
                    <div class="w-20 h-20 mx-auto flex items-center justify-center bg-[#4fb33f]/12 border border-[#4fb33f]/30 mb-8">
                        <span class="material-symbols-outlined text-[#276b1d] dark:text-[#4fb33f] text-4xl" style="font-variation-settings:'FILL' 1">task_alt</span>
                    </div>
                    <h2 class="font-headline text-2xl sm:text-4xl md:text-5xl text-on-surface tracking-tight mb-4">Custom Order Saved</h2>
                    <p class="text-on-surface-variant leading-relaxed mb-10">
                        We’ve opened WhatsApp so you can send your order details to the studio.
                        If it didn’t open automatically, tap the button below — then just hit send.
                    </p>

                    <a
                        :href="whatsappUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center gap-3 w-full sm:w-auto px-10 py-5 bg-[#4fb33f] text-black font-bold tracking-wide hover:bg-[#5cc94a] transition-all active:scale-[0.99] shadow-[0_20px_40px_-12px_rgba(79,179,63,0.5)]"
                    >
                        <span class="material-symbols-outlined">chat</span>
                        Open WhatsApp &amp; send brief
                    </a>

                    <!-- Order reference + tracking -->
                    <div v-if="reference" class="mt-10 bg-surface border border-white/10 rounded-2xl px-6 py-5 text-left">
                        <div class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant">Your order reference</div>
                        <div class="text-xl font-mono tracking-wider text-on-surface mt-1">{{ reference }}</div>
                        <p class="text-sm text-on-surface-variant mt-3">
                            Keep this safe. Track your custom order's progress anytime with your
                            reference and WhatsApp number —
                            <a :href="route('track', { ref: reference })" class="text-primary hover:underline">track your order</a>.
                        </p>
                    </div>

                    <div class="mt-8">
                        <button
                            type="button"
                            @click="resetForm"
                            class="text-sm text-on-surface-variant hover:text-primary transition-colors inline-flex items-center gap-2"
                        >
                            <span class="material-symbols-outlined text-base">add</span>
                            Start another custom order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
