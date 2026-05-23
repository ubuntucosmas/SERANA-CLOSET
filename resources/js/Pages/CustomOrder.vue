<script setup>
import { ref, reactive } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import StorefrontLayout from "@/Layouts/StorefrontLayout.vue";
import { useCurrency } from "@/Composables/useCurrency";
import SizingIntelligence from "@/Components/SizingIntelligence.vue";

const page = usePage();
const { formatAmount } = useCurrency();

const isSubmitting = ref(false);
const globalError = ref(null);
const fileInput = ref(null);
const activeField = ref(null);

const measurementGuidance = {
    height_cm:
        "Stand straight, barefoot against a wall. Measure from the crown of your head straight down to the floor.",
    bust_cm:
        "Wrap the tape around the fullest part of your chest. Keep it parallel to the floor and breathe normally.",
    waist_cm:
        "Locate your natural waist (the narrowest point, usually above the navel) and measure around it.",
    hips_cm:
        "Measure around the widest part of your hips, approximately 20cm below the waistline.",
    length_cm:
        "Decide your preferred garment length and measure from the shoulder seam straight down to that point.",
};

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
            window.open(response.data.whatsapp_url, "_blank");
            // Revoke all object URLs before clearing
            form.images.forEach((file) => {
                try {
                    URL.revokeObjectURL(getObjectURL(file));
                } catch (e) {}
            });

            // Reset form
            Object.keys(form).forEach((k) => {
                if (Array.isArray(form[k])) form[k] = [];
                else if (typeof form[k] === "boolean") form[k] = false;
                else form[k] = "";
            });
        }
    } catch (err) {
        globalError.value =
            err.response?.data?.message ||
            "Something went wrong. Please try again.";
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <StorefrontLayout>
        <Head title="Private Commission | Serana Closet" />

        <div class="min-h-screen bg-background text-on-surface pt-24 pb-20">
            <div class="max-w-5xl mx-auto px-5 md:px-8 lg:px-10">
                <!-- Modern Header -->
                <div class="mb-12 text-center">
                    <div
                        class="inline-block px-5 py-1.5 bg-primary/10 text-primary text-[10px] tracking-[4px] font-medium mb-5 rounded-sm"
                    >
                        PRIVATE COMMISSION
                    </div>
                    <h1
                        class="text-6xl md:text-7xl font-light tracking-tighter leading-none"
                    >
                        Begin your bespoke piece.
                    </h1>
                    <p
                        class="mt-4 text-lg text-on-surface-variant max-w-md mx-auto"
                    >
                        A private briefing for the atelier. We craft it
                        perfectly.
                    </p>
                </div>

                <!-- The Form -->
                <form @submit.prevent="submitOrder" class="space-y-7">
                    <!-- Your Details -->
                    <div
                        class="bg-surface border border-white/10 rounded-3xl p-7 md:p-8"
                    >
                        <div
                            class="text-xs tracking-[3px] text-primary mb-6 font-medium"
                        >
                            01 — YOUR DETAILS
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label
                                    class="block text-sm text-on-surface-variant mb-2"
                                    >Full Name</label
                                >
                                <input
                                    v-model="form.full_name"
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
                                    >WhatsApp Number</label
                                >
                                <input
                                    v-model="form.whatsapp_number"
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
                                >City / Country</label
                            >
                            <input
                                v-model="form.location"
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
                            class="text-xs tracking-[3px] text-primary mb-6 font-medium"
                        >
                            02 — THE GARMENT
                        </div>

                        <div>
                            <label
                                class="block text-sm text-on-surface-variant mb-3"
                                >What are we making?</label
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
                                    >Occasion</label
                                >
                                <input
                                    v-model="form.occasion"
                                    type="text"
                                    placeholder="Wedding, Gala, Daily wear..."
                                    class="w-full bg-background border border-white/20 focus:border-primary px-5 py-4 rounded-2xl"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm text-on-surface-variant mb-2"
                                    >When do you need it?</label
                                >
                                <input
                                    v-model="form.deadline"
                                    type="date"
                                    class="w-full bg-black border border-white/20 focus:border-[#57d5fc] px-5 py-4 rounded-xl text-white"
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
                                    class="text-xs tracking-[3px] text-primary font-medium"
                                >
                                    03 — PRECISION MAPPING
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
                                            class="w-full bg-black border border-white/20 focus:border-[#57d5fc] px-3 py-2.5 rounded-lg text-sm text-center transition-all"
                                        />
                                    </div>
                                </div>

                                <!-- Live Guidance - Simple & Below Fields -->
                                <div
                                    v-if="
                                        activeField &&
                                        measurementGuidance[activeField]
                                    "
                                    class="mt-2.5 text-sm text-white/80 bg-black/30 px-3 py-2 rounded-lg"
                                >
                                    <span class="text-[#57d5fc] font-medium"
                                        >{{
                                            activeField
                                                .replace("_cm", "")
                                                .toUpperCase()
                                        }}:</span
                                    >
                                    {{ measurementGuidance[activeField] }}
                                </div>
                            </div>

                            <!-- Precision Mapping Visual (Simple Figure) -->
                            <div class="lg:col-span-7">
                                <SizingIntelligence
                                    :active-field="activeField"
                                    :measurements="form"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Fabric & Color -->
                    <div
                        class="bg-surface border border-white/10 rounded-3xl p-7 md:p-8"
                    >
                        <div
                            class="text-xs tracking-[3px] text-primary mb-6 font-medium"
                        >
                            04 — FABRIC & COLOUR
                        </div>

                        <div class="mb-8">
                            <label
                                class="block text-sm text-on-surface-variant mb-3"
                                >Preferred Fabric</label
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
                                        class="text-xs tracking-[2px] text-white/40 mb-1"
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
                            class="text-xs tracking-[3px] text-primary mb-6 font-medium"
                        >
                            05 — INSPIRATION & NOTES
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
                                >Anything else we should know?</label
                            >
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                placeholder="Special requests, fit preferences, deadline pressure..."
                                class="w-full bg-black border border-white/20 focus:border-[#57d5fc] px-4 py-3 rounded-xl text-sm resize-y"
                            ></textarea>
                        </div>
                    </div>

                    <!-- Priority Pass -->
                    <div
                        class="flex items-center justify-between bg-surface border border-white/10 rounded-3xl px-7 py-5"
                    >
                        <div>
                            <div class="font-medium">Expedite my order</div>
                            <div class="text-sm text-on-surface-variant">
                                Skip the standard queue (+KSh 1,500)
                            </div>
                        </div>
                        <label
                            class="relative inline-flex items-center cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                v-model="form.priority_pass"
                                class="sr-only peer"
                            />
                            <div
                                class="w-11 h-6 bg-white/10 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"
                            ></div>
                        </label>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="w-full py-6 bg-primary hover:bg-white text-black font-bold text-[11px] tracking-[3px] rounded-2xl transition-all disabled:opacity-60 flex items-center justify-center gap-3 active:scale-[0.985]"
                    >
                        <span>{{
                            isSubmitting
                                ? "SENDING TO THE ATELIER..."
                                : "SEND BRIEF TO THE ATELIER"
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
                        We’ll receive your brief on WhatsApp and reply within a
                        few hours.
                    </p>

                    <div
                        v-if="globalError"
                        class="text-center text-red-400 text-sm"
                    >
                        {{ globalError }}
                    </div>
                </form>
            </div>
        </div>
    </StorefrontLayout>
</template>
