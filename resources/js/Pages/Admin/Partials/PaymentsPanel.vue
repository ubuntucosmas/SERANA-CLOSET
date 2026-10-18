<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';

// How customers pay. M-Pesa = STK prompt now; WhatsApp = the order is recorded,
// the customer sends it on WhatsApp and pays as instructed, the studio confirms.
// The server enforces the same rules shown here (at least one on, M-Pesa only
// when configured, instructions required when WhatsApp is the only way).
const props = defineProps({
    settings: { type: Object, required: true },
});

const form = useForm({
    mpesa_enabled: props.settings.mpesa_enabled && props.settings.mpesa_configured,
    whatsapp_enabled: props.settings.whatsapp_enabled,
    instructions: props.settings.instructions || '',
    whatsapp_hold_hours: props.settings.whatsapp_hold_hours,
});

const configured = computed(() => props.settings.mpesa_configured);
// Live preview of the placeholders, filled from the store profile.
const page = usePage();
const preview_text = computed(() => form.instructions
    .replaceAll('{whatsapp}', page.props.store?.whatsapp_display || '{whatsapp}')
    .replaceAll('{phone}', page.props.store?.phone_display || '{phone}')
    .replaceAll('{email}', page.props.store?.email || '{email}')
    .replaceAll('{store}', page.props.store?.name || '{store}'));
const noneOn = computed(() => !form.mpesa_enabled && !form.whatsapp_enabled);
const needsInstructions = computed(() => form.whatsapp_enabled && !form.mpesa_enabled && !form.instructions.trim());
const preview = computed(() => [form.mpesa_enabled && 'M-Pesa now', form.whatsapp_enabled && 'Order on WhatsApp'].filter(Boolean));

const test = ref({ state: 'idle', message: '', env: props.settings.mpesa_env });
async function testConnection() {
    test.value = { ...test.value, state: 'testing', message: '' };
    try {
        const { data } = await axios.post(route('admin.payment_settings.test_mpesa'));
        test.value = { state: data.ok ? 'ok' : 'fail', message: data.message, env: data.env || test.value.env };
    } catch {
        test.value = { ...test.value, state: 'fail', message: 'Could not run the test. Try again in a minute.' };
    }
}

function save() {
    form.put(route('admin.payment_settings.update'), { preserveScroll: true });
}

const toggle = 'relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#57d5fc] disabled:cursor-not-allowed disabled:opacity-40';
const knob = 'inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform';
const field = 'w-full bg-slate-950 border border-white/10 rounded-xl px-4 py-3 text-white focus:ring-1 focus:ring-[#57d5fc] outline-none';
</script>

<template>
    <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-6 mb-6" aria-labelledby="payments-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="payments-heading" class="text-white font-headline text-xl mb-1">Payments</h2>
                <p class="text-slate-500 text-sm">Choose how customers pay at checkout. Changes apply as soon as you save.</p>
            </div>
            <p class="rounded-full bg-white/5 px-3 py-1.5 text-xs text-slate-300" aria-live="polite">
                Customers see: <strong class="text-white">{{ preview.join(' · ') || 'nothing (keep one on)' }}</strong>
            </p>
        </div>

        <!-- M-Pesa -->
        <div class="mt-6 rounded-2xl bg-slate-950/60 p-5 ring-1" :class="form.mpesa_enabled ? 'ring-emerald-400/30' : 'ring-white/5'">
            <div class="flex items-start gap-4">
                <button type="button" role="switch" :aria-checked="form.mpesa_enabled" :disabled="!configured" @click="form.mpesa_enabled = !form.mpesa_enabled"
                    :class="[toggle, form.mpesa_enabled ? 'bg-emerald-500' : 'bg-slate-700']" aria-label="M-Pesa payments">
                    <span :class="[knob, form.mpesa_enabled ? 'translate-x-6' : 'translate-x-1']"></span>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-white">M-Pesa now</h3>
                        <span v-if="!configured" class="rounded-full bg-amber-400/15 px-2 py-0.5 text-[11px] font-semibold text-amber-300">Not set up on the server</span>
                        <span v-else class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="settings.mpesa_env === 'live' ? 'bg-emerald-400/15 text-emerald-300' : 'bg-sky-400/15 text-sky-300'">{{ settings.mpesa_env === 'live' ? 'Live' : 'Sandbox · test money only' }}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-400">Customers approve a prompt on their phone; orders confirm automatically.</p>
                    <p v-if="!configured" class="mt-2 text-xs text-slate-500">Add the Daraja keys, shortcode, passkey and callback token to the server’s settings to switch this on. Until then, WhatsApp ordering stays on.</p>
                    <p v-else-if="!form.mpesa_enabled" class="mt-2 text-xs text-slate-500">Switching off stops new prompts only. <template v-if="settings.prompts_in_progress">{{ settings.prompts_in_progress }} payment{{ settings.prompts_in_progress === 1 ? '' : 's' }} in progress will still complete.</template><template v-else>Payments already in progress still complete.</template></p>
                    <div v-if="configured" class="mt-3 flex flex-wrap items-center gap-3">
                        <button type="button" @click="testConnection" :disabled="test.state === 'testing'" class="rounded-lg bg-white/5 px-3 py-2 text-sm text-slate-200 hover:bg-white/10 disabled:opacity-40">
                            {{ test.state === 'testing' ? 'Testing…' : 'Test connection' }}
                        </button>
                        <span v-if="test.state === 'ok'" role="status" class="text-sm text-emerald-300">✓ {{ test.message }}</span>
                        <span v-else-if="test.state === 'fail'" role="alert" class="text-sm text-rose-300">{{ test.message }}</span>
                    </div>
                    <p v-if="form.errors.mpesa_enabled" class="mt-2 text-xs text-rose-400">{{ form.errors.mpesa_enabled }}</p>
                </div>
            </div>
        </div>

        <!-- WhatsApp -->
        <div class="mt-4 rounded-2xl bg-slate-950/60 p-5 ring-1" :class="form.whatsapp_enabled ? 'ring-emerald-400/30' : 'ring-white/5'">
            <div class="flex items-start gap-4">
                <button type="button" role="switch" :aria-checked="form.whatsapp_enabled" @click="form.whatsapp_enabled = !form.whatsapp_enabled"
                    :class="[toggle, form.whatsapp_enabled ? 'bg-emerald-500' : 'bg-slate-700']" aria-label="WhatsApp orders">
                    <span :class="[knob, form.whatsapp_enabled ? 'translate-x-6' : 'translate-x-1']"></span>
                </button>
                <div class="min-w-0 flex-1">
                    <h3 class="font-semibold text-white">Order on WhatsApp</h3>
                    <p class="mt-1 text-sm text-slate-400">The order is recorded with a code, its pieces are held, and it appears here. The customer sends it on WhatsApp and pays as you instruct; you tap <em>Confirm payment</em> on the order and their receipt goes out.</p>

                    <label class="mt-4 block">
                        <span class="block text-sm text-slate-300 mb-2">How customers pay you</span>
                        <textarea v-model="form.instructions" rows="3" maxlength="600" :class="field"
                            placeholder="e.g. Pay via M-Pesa Send Money to {whatsapp} ({store}). Use your order code as the reference."></textarea>
                        <span class="mt-1 block text-xs text-slate-500">Shown after checkout, on tracking and in the order email. {{ form.instructions.length }}/600</span>
                        <span class="mt-1 block text-xs text-slate-500">Tip: write <code class="text-slate-300">{whatsapp}</code>, <code class="text-slate-300">{phone}</code> or <code class="text-slate-300">{store}</code> and your current details from Store details are filled in, so changing your number never leaves old instructions behind.</span>
                        <span v-if="preview_text && preview_text !== form.instructions" class="mt-2 block rounded-lg bg-white/5 px-3 py-2 text-xs text-slate-300"><span class="text-slate-500">Customers read:</span> {{ preview_text }}</span>
                        <span v-if="form.errors.instructions || needsInstructions" class="block text-xs text-rose-400">{{ form.errors.instructions || 'Required while WhatsApp is the only way to pay.' }}</span>
                    </label>

                    <label class="mt-4 block sm:max-w-xs">
                        <span class="block text-sm text-slate-300 mb-2">Hold pieces for</span>
                        <select v-model.number="form.whatsapp_hold_hours" :class="field">
                            <option v-for="h in settings.hold_options" :key="h" :value="h">{{ h }} hours</option>
                        </select>
                        <span class="mt-1 block text-xs text-slate-500">Unpaid WhatsApp orders release their stock and expire after this.</span>
                    </label>
                </div>
            </div>
        </div>

        <p v-if="noneOn || form.errors.whatsapp_enabled" class="mt-4 text-sm text-rose-400">{{ form.errors.whatsapp_enabled || 'Keep at least one way to pay switched on.' }}</p>

        <div class="mt-6 flex items-center justify-end gap-4">
            <span v-if="form.recentlySuccessful" role="status" class="text-sm text-emerald-300">Saved</span>
            <button @click="save" :disabled="form.processing || noneOn || needsInstructions"
                class="px-7 py-3 rounded-xl bg-[#57d5fc] text-slate-900 font-semibold text-sm hover:brightness-105 disabled:opacity-40 transition-all">
                {{ form.processing ? 'Saving…' : 'Save payment options' }}
            </button>
        </div>
    </section>
</template>
