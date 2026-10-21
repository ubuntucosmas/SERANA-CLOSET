<!-- Studio → Staff (Phase E-a), owner only: who can sign in, as what, and
     their till PIN. Every change asks why and lands in the audit log. A new
     person's one-time password is shown once, here, and must be changed at
     their first sign-in. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    staff: { type: Object, default: null },
});

const page = usePage();
const me = computed(() => page.props.auth?.user?.id);
onMounted(() => { if (!props.staff) router.reload({ only: ['staff'], data: { tab: 'staff' } }); });
const reload = () => router.reload({ only: ['staff'], data: { tab: 'staff' } });

const issued = computed(() => page.props.flash?.staff_password || null);
const copied = ref(false);
async function copyPassword() {
    try { await navigator.clipboard.writeText(issued.value.password); copied.value = true; setTimeout(() => (copied.value = false), 2000); } catch { /* select it by hand */ }
}

const addForm = useForm({ name: '', email: '', role: 'cashier', reason: '' });
function add() {
    addForm.post(route('studio.staff.store'), { preserveScroll: true, onSuccess: () => { addForm.reset(); reload(); } });
}

const roleLabel = (value) => props.staff?.roles.find(r => r.value === value)?.label || value;
function ask(question) {
    const reason = window.prompt(`${question}\n\nReason (kept in the audit log):`);
    return reason && reason.trim() ? reason.trim() : null;
}
function changeRole(member, role) {
    if (role === member.role) return;
    const reason = ask(`Make ${member.name} ${roleLabel(role).toLowerCase()}?`);
    if (!reason) { reload(); return; }
    router.put(route('studio.staff.role', member.id), { role, reason }, { preserveScroll: true, onFinish: reload });
}
function suspend(member) {
    const reason = ask(`Suspend ${member.name}? They are signed out at once and can't sign in until reactivated.`);
    if (reason) router.post(route('studio.staff.suspend', member.id), { reason }, { preserveScroll: true, onFinish: reload });
}
function reactivate(member) {
    const reason = ask(`Let ${member.name} sign in again?`);
    if (reason) router.post(route('studio.staff.reactivate', member.id), { reason }, { preserveScroll: true, onFinish: reload });
}

const pinFor = ref(null);
const pinForm = useForm({ pin: '', reason: 'Till access' });
function startPin(member) { pinFor.value = member.id; pinForm.reset(); pinForm.clearErrors(); }
function savePin(member) {
    pinForm.put(route('studio.staff.pin', member.id), { preserveScroll: true, onSuccess: () => { pinFor.value = null; reload(); } });
}
const policyForm = useForm({ cashier_discount_percent: 10, cashier_discount_cap: 500, till_variance_alert: 200, pos_hold_minutes: 30, pos_idle_minutes: 10, receipt_footer: '' });
const policySaved = ref(false);
watch(() => props.staff?.policy, (p) => { if (p) { Object.assign(policyForm, p); policyForm.defaults({ ...p }); } }, { immediate: true });
function savePolicy() {
    policyForm.put(route('admin.shop_policy.update'), { preserveScroll: true, onSuccess: () => { policySaved.value = true; setTimeout(() => (policySaved.value = false), 2500); reload(); } });
}
const errors = computed(() => page.props.errors || {});
const field = 'w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:ring-1 focus:ring-[#57d5fc] outline-none';
</script>

<template>
    <div class="space-y-5">
        <p v-if="!staff" class="text-sm text-slate-400" role="status">Loading staff…</p>
        <template v-else>
            <section v-if="issued" class="rounded-2xl bg-emerald-500/10 ring-1 ring-emerald-400/30 p-4" role="status">
                <p class="text-sm text-white">One-time password for <strong>{{ issued.email }}</strong>. It is shown only now: give it to them in person. They set their own at first sign-in.</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <code class="rounded-lg bg-slate-950 px-3 py-2 font-mono text-base text-white select-all">{{ issued.password }}</code>
                    <button type="button" @click="copyPassword" class="rounded-xl bg-white/5 px-3 py-2 text-sm text-slate-200 hover:bg-white/10">{{ copied ? 'Copied' : 'Copy' }}</button>
                </div>
            </section>

            <ul class="space-y-3" aria-label="Staff">
                <li v-for="member in staff.members" :key="member.id" class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-white font-medium">{{ member.name }}<span v-if="member.id === me" class="text-xs text-slate-400"> (you)</span></p>
                            <p class="text-xs text-slate-400 break-all">{{ member.email }}</p>
                            <p class="text-xs mt-1" :class="member.suspended ? 'text-rose-300' : 'text-slate-400'">
                                <template v-if="member.suspended">Suspended · {{ member.suspended_reason }}</template>
                                <template v-else>{{ member.last_login_at ? `Last signed in ${member.last_login_at}` : 'Never signed in' }}<template v-if="member.must_change_password"> · still on the one-time password</template></template>
                                · {{ member.has_pin ? 'Till PIN set' : 'No till PIN' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select :value="member.role" :disabled="member.id === me" @change="changeRole(member, $event.target.value)" :aria-label="`Role of ${member.name}`"
                                class="bg-slate-950 border border-white/10 rounded-xl pl-3 pr-9 py-2 text-white text-sm disabled:opacity-50">
                                <option v-for="r in staff.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                            </select>
                            <button type="button" @click="startPin(member)" class="table-action">{{ member.has_pin ? 'Change PIN' : 'Set PIN' }}</button>
                            <button v-if="!member.suspended && member.id !== me" type="button" @click="suspend(member)" class="table-action text-rose-300">Suspend</button>
                            <button v-if="member.suspended" type="button" @click="reactivate(member)" class="table-action">Reactivate</button>
                        </div>
                    </div>
                    <form v-if="pinFor === member.id" @submit.prevent="savePin(member)" class="mt-3 grid gap-2 sm:grid-cols-[8rem_1fr_auto]" :aria-label="`Till PIN for ${member.name}`">
                        <input v-model="pinForm.pin" inputmode="numeric" pattern="\d{4,6}" maxlength="6" autocomplete="off" aria-label="New PIN (4 to 6 digits)" placeholder="4–6 digits" :class="field" />
                        <input v-model="pinForm.reason" maxlength="255" aria-label="Reason" :class="field" />
                        <span class="flex gap-2">
                            <button type="submit" :disabled="pinForm.processing" class="rounded-xl bg-[#57d5fc] px-4 py-2 text-sm font-semibold text-slate-900">Save PIN</button>
                            <button type="button" @click="pinFor = null" class="rounded-xl bg-white/5 px-3 py-2 text-sm text-slate-300">Cancel</button>
                        </span>
                        <p v-for="(m, f) in pinForm.errors" :key="f" role="alert" class="sm:col-span-3 text-sm text-rose-300">{{ m }}</p>
                    </form>
                </li>
            </ul>
            <p v-for="key in ['role', 'reason']" :key="key" v-show="errors[key]" role="alert" class="text-sm text-rose-300">{{ errors[key] }}</p>

            <section v-if="staff.policy" class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-5" aria-labelledby="till-rules-title">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h2 id="till-rules-title" class="text-white font-headline text-lg">The till</h2>
                    <a :href="route('pos.index')" class="rounded-xl bg-white/5 px-4 py-2 text-sm text-slate-200 hover:bg-white/10">Open the till</a>
                </div>
                <form @submit.prevent="savePolicy" class="grid gap-3 sm:grid-cols-3">
                    <label class="text-xs text-slate-400">Cashier discount, % of the sale<input v-model.number="policyForm.cashier_discount_percent" type="number" min="0" max="100" required :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">…and at most (KSh)<input v-model.number="policyForm.cashier_discount_cap" type="number" min="0" required :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Drawer difference that needs a manager (KSh)<input v-model.number="policyForm.till_variance_alert" type="number" min="0" required :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Hold a counter sale's pieces for (minutes)<input v-model.number="policyForm.pos_hold_minutes" type="number" min="5" max="240" required :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Lock the till after idle (minutes)<input v-model.number="policyForm.pos_idle_minutes" type="number" min="1" max="120" required :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400 sm:col-span-3">Receipt footer<input v-model="policyForm.receipt_footer" maxlength="300" placeholder="e.g. Exchanges within 7 days with this receipt." :class="field + ' mt-1'" /></label>
                    <div class="sm:col-span-3 flex flex-wrap items-center gap-3">
                        <button type="submit" :disabled="policyForm.processing || !policyForm.isDirty" class="rounded-xl bg-[#57d5fc] px-5 py-2.5 text-sm font-semibold text-slate-900 disabled:opacity-40">Save the till rules</button>
                        <span v-if="policySaved" role="status" class="text-sm text-emerald-400">Saved.</span>
                        <p v-for="(m, f) in policyForm.errors" :key="f" role="alert" class="text-sm text-rose-300">{{ m }}</p>
                    </div>
                </form>
            </section>

            <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-5" aria-labelledby="add-staff-title">
                <h2 id="add-staff-title" class="text-white font-headline text-lg mb-3">Add someone</h2>
                <form @submit.prevent="add" class="grid gap-3 sm:grid-cols-2">
                    <label class="text-xs text-slate-400">Name<input v-model="addForm.name" required maxlength="255" autocomplete="off" :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Email (they sign in with it)<input v-model="addForm.email" type="email" required maxlength="255" autocomplete="off" :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Role
                        <select v-model="addForm.role" :class="field + ' mt-1'"><option v-for="r in staff.roles" :key="r.value" :value="r.value">{{ r.label }}</option></select>
                        <span class="block mt-1 text-slate-500">{{ staff.roles.find(r => r.value === addForm.role)?.help }}</span>
                    </label>
                    <label class="text-xs text-slate-400">Reason<input v-model="addForm.reason" required maxlength="255" placeholder="e.g. New cashier for weekends" :class="field + ' mt-1'" /></label>
                    <div class="sm:col-span-2 flex items-center gap-3">
                        <button type="submit" :disabled="addForm.processing" class="rounded-xl bg-[#57d5fc] px-5 py-2.5 text-sm font-semibold text-slate-900 disabled:opacity-40">Add and create a password</button>
                        <p v-for="(m, f) in addForm.errors" :key="f" role="alert" class="text-sm text-rose-300">{{ m }}</p>
                    </div>
                </form>
            </section>
        </template>
    </div>
</template>
