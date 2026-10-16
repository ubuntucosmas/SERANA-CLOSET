<script setup>
import PageHeader from '@/Components/PageHeader.vue';
import { ref, computed, onMounted } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import StatusBadge from '@/Components/StatusBadge.vue';
import { paymentStatus, fulfilmentStatus } from '@/Support/orderStatus';
import StorefrontLayout from "@/Layouts/StorefrontLayout.vue";
import { useCurrency } from "@/Composables/useCurrency";
import { rememberTrackingPhone, recallTrackingPhone, forgetTrackingPhone } from "@/Support/trackingMemory";

const props = defineProps({
    prefillReference: { type: String, default: null },
});

const page = usePage();
const { formatAmount } = useCurrency();
const money = (amount) => formatAmount(amount, page.props);

const form = ref({ reference: props.prefillReference || "", phone: "" });
const isLoading = ref(false);
const error = ref(null);
const order = ref(null);

const PRODUCTION = { pending: 0, designing: 1, sewing: 2, completed: 3, shipped: 4 };

// Adaptive journey: ready-to-wear is payment-led, bespoke is production-led.
const journey = computed(() => {
    const o = order.value;
    if (!o) return null;

    if (["standard", "drop"].includes(o.type)) {
        const steps = [
            { label: "Placed", icon: "receipt_long" },
            { label: "Paid", icon: "paid" },
            { label: "Crafting", icon: "precision_manufacturing" },
            { label: "Completed", icon: "verified" },
            { label: "On its way", icon: "local_shipping" },
        ];
        let index = o.is_paid ? 1 : 0;
        if (o.is_paid) {
            if (o.status === "designing" || o.status === "sewing") index = 2;
            else if (o.status === "completed") index = 3;
            else if (o.status === "shipped") index = 4;
        }
        return { steps, index, awaitingPayment: !o.is_paid };
    }

    const steps = [
        { label: "Received", icon: "assignment" },
        { label: "Design", icon: "architecture" },
        { label: "Making", icon: "precision_manufacturing" },
        { label: "Completed", icon: "verified" },
        { label: "Transit", icon: "local_shipping" },
    ];
    return { steps, index: PRODUCTION[o.status] ?? 0, awaitingPayment: false };
});

const payment = computed(() => {
    const o = order.value;
    if (!o || !["standard", "drop"].includes(o.type)) return null;
    if (o.is_paid) return { label: "Paid", tone: "ok" };
    const failed = ["Payment Failed", "Amount Mismatch", "Expired"];
    if (failed.includes(o.status)) return { label: o.status, tone: "bad" };
    return { label: "Payment Pending", tone: "warn" };
});

async function lookup() {
    if (isLoading.value) return;
    error.value = null;
    if (!form.value.reference.trim() || !form.value.phone.trim()) {
        error.value = "Please enter both your order reference and phone number.";
        return;
    }

    isLoading.value = true;
    try {
        const res = await axios.post(route("track.lookup"), {
            reference: form.value.reference.trim().toUpperCase(),
            phone: form.value.phone.trim(),
        });
        order.value = res.data.order;
        rememberTrackingPhone(form.value.phone);
    } catch (err) {
        order.value = null;
        error.value =
            err.response?.status === 429
                ? "Too many attempts. Please wait a minute and try again."
                : err.response?.data?.message ||
                  "Something went wrong. Please try again.";
    } finally {
        isLoading.value = false;
    }
}

// "Track another order": same customer, usually the same number.
function reset() {
    order.value = null;
    error.value = null;
    form.value.reference = "";
}

const phoneRemembered = ref(false);
function useDifferentNumber() {
    forgetTrackingPhone();
    form.value.phone = "";
    phoneRemembered.value = false;
    document.getElementById("trackorder-phone")?.focus();
}

// Deep-linked (?ref=…) from a receipt, Your Orders or the success screen: if
// this device already knows the number, show the order straight away.
onMounted(() => {
    form.value.phone = recallTrackingPhone();
    phoneRemembered.value = !!form.value.phone;
    if (props.prefillReference && form.value.phone) lookup();
});
</script>

<template>
    <StorefrontLayout>
        <Head title="Track Your Order | Serana Closet" />

        <div class="bg-background text-on-surface pb-24">
            <div class="page-container">
                <!-- Header -->
                <PageHeader kicker="Order Tracking" title="Track Order" subtitle="Enter your order reference and the phone number you ordered with to see payment, progress photos and your receipt." />

                <!-- Lookup form -->
                <form
                    v-if="!order"
                    @submit.prevent="lookup"
                    class="card space-y-6 p-6 md:p-8"
                >
                    <div class="grid gap-6 md:grid-cols-2"><div>
                        <label class="block text-sm text-on-surface-variant mb-2" for="trackorder-reference">Order Reference</label>
                        <input
                            id="trackorder-reference" v-model="form.reference"
                            type="text"
                            placeholder="SRN-XXXXXXXX-2026"
                            autocomplete="off"
                            class="field py-3.5 text-base" style="text-transform:uppercase"
                        />
                        <p class="text-xs text-on-surface-variant mt-1.5">
                            On your WhatsApp brief, receipt, or order confirmation.
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm text-on-surface-variant mb-2" for="trackorder-phone">Phone Number</label>
                        <input
                            id="trackorder-phone" v-model="form.phone"
                            type="tel"
                            placeholder="+254 712 345 678"
                            autocomplete="tel"
                            inputmode="tel"
                            class="field py-3.5 text-base"
                        />
                        <button v-if="phoneRemembered" type="button" @click="useDifferentNumber" class="mt-1.5 text-xs text-on-surface-variant underline">Remembered on this device · Use a different number</button>
                    </div></div>

                    <p role="alert" v-if="error" class="text-red-400 text-sm">{{ error }}</p>

                    <button
                        type="submit"
                        :disabled="isLoading"
                        class="action-primary w-full py-4 md:w-auto md:px-10"
                    >
                        <span>{{ isLoading ? "Locating your order…" : "Track order" }}</span>
                        <span v-if="!isLoading" class="material-symbols-outlined text-lg">search</span>
                    </button>
                </form>

                <!-- Result -->
                <div v-else class="space-y-7">
                    <!-- Summary card -->
                    <div class="bg-surface border border-white/10 rounded-3xl p-7 md:p-8">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <div class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant">
                                    Ref {{ order.reference }}
                                </div>
                                <h2 class="text-2xl font-light tracking-tight mt-1">
                                    {{ order.type === "standard" ? "Shop Order" : order.type === "drop" ? "Drop Reservation" : "Custom Order" }}
                                </h2>
                                <p class="text-sm text-on-surface-variant mt-1">Placed {{ order.placed_at }}</p>
                            </div>
                            <div class="text-right">
                                <StatusBadge v-bind="paymentStatus(order)" />
                                <div v-if="order.amount" class="text-2xl font-light tracking-tight mt-2">
                                    {{ money(order.amount) }}
                                </div>
                            </div>
                        </div>

                        <p class="mt-5 text-sm font-semibold">Fulfilment: {{ fulfilmentStatus(order) }}</p>
                        <p class="mt-3 text-sm text-on-surface-variant">{{ paymentStatus(order).next }}</p>
                        <div class="mt-5 flex flex-wrap gap-4 text-sm">
                            <button @click="lookup" :disabled="isLoading" class="text-primary underline">{{ isLoading ? 'Checking…' : 'Refresh Status' }}</button>
                            <Link :href="route('contact')" class="underline">Contact Support</Link>
                        </div>
                        <!-- Timeline -->
                        <div v-if="journey" class="mt-9">
                            <div class="relative">
                                <div class="absolute top-4 left-0 right-0 h-px bg-white/10"></div>
                                <div
                                    class="absolute top-4 left-0 h-px bg-primary transition-all duration-700"
                                    :style="{ width: (journey.index / (journey.steps.length - 1)) * 100 + '%' }"
                                ></div>
                                <div class="relative flex justify-between">
                                    <div
                                        v-for="(step, i) in journey.steps"
                                        :key="step.label"
                                        class="flex flex-col items-center gap-2 text-center"
                                        style="flex: 1"
                                    >
                                        <div
                                            class="w-8 h-8 rounded-full border flex items-center justify-center bg-surface"
                                            :class="i <= journey.index ? 'border-primary text-primary' : 'border-white/15 text-on-surface-variant/40'"
                                        >
                                            <span class="material-symbols-outlined text-[16px]">{{ step.icon }}</span>
                                        </div>
                                        <span
                                            class="text-[10px] tracking-wide"
                                            :class="i <= journey.index ? 'text-on-surface' : 'text-on-surface-variant/40'"
                                        >{{ step.label }}</span>
                                    </div>
                                </div>
                            </div>

                            <p
                                v-if="journey.awaitingPayment"
                                class="mt-6 text-sm text-amber-300/90 bg-amber-400/10 rounded-xl px-4 py-3"
                            >
                                We haven't received payment for this order yet. If you've already
                                paid, it can take a moment to confirm — refresh shortly.
                            </p>
                        </div>

                        <!-- Receipt -->
                        <a
                            v-if="order.receipt_url"
                            :href="order.receipt_url"
                            class="mt-8 inline-flex items-center gap-2 px-6 py-3.5 bg-primary text-background font-bold text-[11px] tracking-wider rounded-2xl hover:bg-on-surface transition-all active:scale-[0.985]"
                        >
                            <span class="material-symbols-outlined text-lg">download</span>
                            Download receipt
                        </a>
                    </div>

                    <!-- Items -->
                    <div
                        v-if="order.items?.length"
                        class="bg-surface border border-white/10 rounded-3xl p-7 md:p-8"
                    >
                        <div class="text-xs tracking-wider text-primary mb-5 font-medium">Your pieces</div>
                        <div class="space-y-3">
                            <div
                                v-for="(item, i) in order.items"
                                :key="i"
                                class="flex items-center gap-4"
                            >
                                <div class="w-14 h-14 rounded-xl bg-background border border-white/10 overflow-hidden shrink-0">
                                    <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm truncate">{{ item.name }}</div>
                                    <div class="text-xs text-on-surface-variant">Qty {{ item.quantity }}</div>
                                    <p v-if="item.size_label || item.options?.size" class="text-xs text-on-surface-variant">{{ item.size_label || ('Size ' + item.options.size) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress photos -->
                    <div
                        v-if="order.progress?.length"
                        class="bg-surface border border-white/10 rounded-3xl p-7 md:p-8"
                    >
                        <div class="text-xs tracking-wider text-primary mb-5 font-medium">From the atelier</div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            <figure v-for="(snap, i) in order.progress" :key="i" class="space-y-2">
                                <div class="aspect-square rounded-2xl overflow-hidden bg-background border border-white/10">
                                    <img :src="snap.image_url" class="w-full h-full object-cover" />
                                </div>
                                <figcaption class="text-xs text-on-surface-variant">
                                    <span v-if="snap.description">{{ snap.description }} · </span>{{ snap.date }}
                                </figcaption>
                            </figure>
                        </div>
                    </div>

                    <div class="text-center">
                        <button
                            type="button"
                            @click="reset"
                            class="text-sm text-on-surface-variant hover:text-primary transition-colors inline-flex items-center gap-2"
                        >
                            <span class="material-symbols-outlined text-base">search</span>
                            Track another order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </StorefrontLayout>
</template>
