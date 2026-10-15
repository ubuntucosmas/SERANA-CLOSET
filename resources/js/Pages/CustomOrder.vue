<script setup>
import { ref, reactive } from "vue";
import { Head } from "@inertiajs/vue3";
import StorefrontLayout from "@/Layouts/StorefrontLayout.vue";
import SizingFigure from "@/Components/SizingFigure.vue";

const props = defineProps({
    baseProduct: { type: Object, default: null },
    showcaseProducts: { type: Array, default: () => [] },
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

const outfitTypes = [
    "Dress",
    "Hoodie",
    "Suit",
    "Corporate",
    "Casual",
    "Gown",
    "Blazer",
];
const eliteColors = [
    { name: "Midnight", hex: "#0a0a0a" },
    { name: "Optic White", hex: "#f8f8f8" },
    { name: "Electric Cyan", hex: "#57d5fc" },
    { name: "Desert Sand", hex: "#e2cba3" },
    { name: "Deep Navy", hex: "#0f172a" },
    { name: "Charcoal", hex: "#1f2937" },
];
const fabrics = [
    { name: "Premium Cotton" },
    { name: "Italian Wool" },
    { name: "Silk Charmeuse" },
    { name: "Japanese Denim" },
];

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

// Pre-fill from a product the client arrived from (e.g. "Make this custom").
if (props.baseProduct) {
    form.notes = `Inspired by: ${props.baseProduct.name}`;
    const match = outfitTypes.find(
        (t) => t.toLowerCase() === String(props.baseProduct.garment_type || "").toLowerCase()
    );
    if (match) form.outfit_type = match;
}

function triggerFileInput() {
    fileInput.value?.click();
}

function handleFileSelect(e) {
    const files = Array.from(e.target.files || []);
    form.images = [...form.images, ...files].slice(0, 6); // max 6
}

function getObjectURL(file) {
    return file ? URL.createObjectURL(file) : "";
}

function removeImage(index) {
    // Revoke the object URL to free memory
    if (form.images[index]) {
        try {
            URL.revokeObjectURL(getObjectURL(form.images[index]));
        } catch (e) {}
    }
    form.images.splice(index, 1);
}

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
            form.images.forEach((file) => {
                try {
                    URL.revokeObjectURL(getObjectURL(file));
                } catch (e) {}
            });

            // Show the confirmation panel (works even if the popup is blocked),
            // then optimistically try to open WhatsApp in a new tab.
            submitted.value = true;
            window.scrollTo({ top: 0, behavior: "smooth" });
            window.open(whatsappUrl.value, "_blank");
        }
    } catch (err) {
        globalError.value =
            err.response?.data?.message ||
            "Something went wrong. Please try again.";
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
    validationErrors.value = {};
    globalError.value = null;
    whatsappUrl.value = null;
    reference.value = null;
    submitted.value = false;
}
</script>

<template>
    <StorefrontLayout>
        <Head title="Private Commission | Serana Closet" />

        <div class="min-h-screen bg-background text-on-surface pb-24">
            <div class="max-w-5xl mx-auto px-6 sm:px-8">
                <!-- Modern Header -->
                <div v-if="!submitted" class="mb-12 text-center">
                    <p class="text-[10px] font-headline font-black tracking-[0.4em] uppercase text-primary mb-4">Bespoke Request</p>
                    <h1
                        class="text-5xl md:text-7xl font-headline font-light tracking-tight leading-[0.95]"
                    >
                        Begin your <span class="text-primary">bespoke</span> piece.
                    </h1>
                    <p
                        class="mt-4 text-lg text-on-surface-variant max-w-md mx-auto"
                    >
                        Tell us what you have in mind. We’ll discuss the design, quote and timing with you.
                    </p>
                </div>

                <!-- The Form -->
                <form v-if="!submitted" @submit.prevent="submitOrder" class="space-y-7">
                    <!-- Your Details -->
                    <div
                        class="bg-surface border border-white/10 rounded-3xl p-7 md:p-8"
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
                                    required
                                    class="w-full bg-background border border-white/20 focus:border-primary px-4 py-3.5 rounded-2xl text-base placeholder:text-on-surface-variant/50"
                                    placeholder="+254 712 345 678"
                                />
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
                        class="bg-surface border border-white/10 rounded-3xl p-7 md:p-8"
                    >
                        <div
                            class="text-xs tracking-wider text-primary mb-6 font-medium"
                        >
                            02 — The garment
                        </div>

                        <div>
                            <label
                                class="block text-sm text-on-surface-variant mb-3"
                                 for="customorder-occasion">What are we making?</label
                            >
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="type in outfitTypes"
                                    :key="type"
                                    type="button"
                                    @click="form.outfit_type = type"
                                    class="px-6 py-2.5 rounded-full border text-sm transition-all"
                                    :class="
                                        form.outfit_type === type
                                            ? 'bg-primary text-black border-primary'
                                            : 'border-white/20 hover:border-white/40 hover:text-on-surface'
                                    "
                                >
                                    {{ type }}
                                </button>
                            </div>
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
                                    class="w-full bg-background border border-white/20 focus:border-primary px-5 py-4 rounded-xl text-white"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Measurements with Precision Mapping -->
                    <div
                        class="bg-surface border border-white/10 rounded-3xl p-7 md:p-8"
                    >
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <div
                                    class="text-xs tracking-wider text-primary font-medium"
                                >
                                    03 — Precision mapping
                                </div>
                                <p class="text-on-surface-variant text-sm mt-1">
                                    Smart guidance for perfect bespoke fit
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
                                            class="block text-xs text-white/50 mb-1.5"
                                            >{{ m[0] }} (cm)</label
                                        >
                                        <input
                                            v-model="form[m[1]]"
                                            type="number"
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
                        class="bg-surface border border-white/10 rounded-3xl p-7 md:p-8"
                    >
                        <div
                            class="text-xs tracking-wider text-primary mb-6 font-medium"
                        >
                            04 — Fabric & colour
                        </div>

                        <div class="mb-8">
                            <label
                                class="block text-sm text-on-surface-variant mb-3"
                                 for="customorder-notes">Preferred Fabric</label
                            >
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div
                                    v-for="fabric in fabrics"
                                    :key="fabric.name"
                                    @click="
                                        form.fabric_preference = fabric.name
                                    "
                                    class="cursor-pointer rounded-2xl border px-5 py-6 text-center transition-all flex flex-col items-center justify-center"
                                    :class="
                                        form.fabric_preference === fabric.name
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : 'border-white/10 hover:border-white/30 hover:bg-white/[0.015]'
                                    "
                                >
                                    <div
                                        class="text-xs tracking-wider text-white/40 mb-1"
                                    >
                                        FABRIC
                                    </div>
                                    <div class="font-medium text-lg">
                                        {{ fabric.name }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label
                                class="block text-sm text-on-surface-variant mb-3"
                                >Colour Direction</label
                            >
                            <div class="flex flex-wrap gap-3">
                                <button
                                    v-for="color in eliteColors"
                                    :key="color.name"
                                    type="button"
                                    @click="form.selected_color = color.name"
                                    class="flex items-center gap-3 px-5 py-2.5 rounded-full border text-sm transition-all"
                                    :class="
                                        form.selected_color === color.name
                                            ? 'border-primary bg-primary/10 text-primary'
                                            : 'border-white/20 hover:border-white/50'
                                    "
                                >
                                    <div
                                        class="w-6 h-6 rounded-full border border-white/20 shadow-inner"
                                        :style="{ backgroundColor: color.hex }"
                                    ></div>
                                    {{ color.name }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Inspiration & Notes -->
                    <div
                        class="bg-surface border border-white/10 rounded-3xl p-7 md:p-8"
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
                                @click="triggerFileInput"
                                class="border-2 border-dashed border-white/20 hover:border-primary/60 rounded-2xl p-7 text-center cursor-pointer transition-all"
                            >
                                <span
                                    class="material-symbols-outlined text-4xl text-on-surface-variant/50 mb-2"
                                    >add_photo_alternate</span
                                >
                                <p class="text-white/60">
                                    Click or drop images here
                                </p>
                                <p class="text-xs text-white/40 mt-1">
                                    Up to 6 images • JPG, PNG, PDF
                                </p>
                            </div>
                            <input
                                type="file"
                                ref="fileInput"
                                multiple
                                accept="image/*,.pdf"
                                @change="handleFileSelect"
                                class="hidden"
                            />

                            <div
                                v-if="form.images.length"
                                class="flex flex-wrap gap-3 mt-4"
                            >
                                <div
                                    v-for="(file, i) in form.images"
                                    :key="i"
                                    class="relative w-20 h-20 bg-black rounded-xl overflow-hidden border border-white/10"
                                >
                                    <img
                                        :src="getObjectURL(file)"
                                        class="w-full h-full object-cover"
                                    />
                                    <button
                                        type="button"
                                        @click.stop="removeImage(i)"
                                        class="absolute top-1 right-1 w-5 h-5 bg-black/70 rounded-full flex items-center justify-center text-xs"
                                    >
                                        ×
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
                        class="w-full py-6 bg-primary hover:bg-on-surface text-background font-bold text-[11px] tracking-wider rounded-2xl transition-all disabled:opacity-60 flex items-center justify-center gap-3 active:scale-[0.985]"
                    >
                        <span>{{
                            isSubmitting
                                ? "Saving Request…"
                                : "Submit Bespoke Request"
                        }}</span>
                        <span
                            v-if="!isSubmitting"
                            class="material-symbols-outlined text-lg"
                            >arrow_forward</span
                        >
                    </button>

                    <p
                        class="text-center text-xs text-on-surface-variant/70 tracking-wider"
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
                        <span class="material-symbols-outlined text-[#4fb33f] text-4xl" style="font-variation-settings:'FILL' 1">task_alt</span>
                    </div>
                    <h2 class="font-headline text-4xl md:text-5xl text-on-surface tracking-tight mb-4">Bespoke Request Saved</h2>
                    <p class="text-on-surface-variant leading-relaxed mb-10">
                        We’ve opened WhatsApp so you can send your commission brief to the atelier.
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
                            Keep this safe. Track your commission's progress anytime with your
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
                            Start another commission
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
