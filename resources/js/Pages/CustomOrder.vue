<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import { ref, reactive } from "vue";
import { Head } from "@inertiajs/vue3";
import StorefrontLayout from "@/Layouts/StorefrontLayout.vue";
import CustomOrderBrief from "@/Components/CustomOrderBrief.vue";

const props = defineProps({
    baseProduct: { type: Object, default: null },
    // Managed in Studio → Settings → Custom order options.
    options: { type: Object, default: () => ({ garments: [], fabrics: [], colours: [] }) },
});

const isSubmitting = ref(false);
const globalError = ref(null);
const brief = ref(null);

// Success state (shown after the brief is sent — survives popup blockers).
const submitted = ref(false);
const whatsappUrl = ref(null);
const reference = ref(null);

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
    // A product's garment_type (set / hoodie / jogger) picks the matching garment.
    const match = (props.options.garments || []).find((g) => g.key === props.baseProduct.garment_type);
    if (match) form.outfit_type = match.label;
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
            brief.value?.releasePreviews();

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
    brief.value?.releasePreviews();
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
                    <CustomOrderBrief ref="brief" :form="form" :options="options" :errors="validationErrors" @blur="validateField" />

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
