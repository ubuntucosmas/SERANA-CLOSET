<!-- Studio → Staff (Phase E-a), owner only: who can sign in, as what, and
     their Sales Desk PIN. Every change asks why and lands in the audit log. A new
     person's one-time password is shown once, here, and must be changed at
     their first sign-in. -->
<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    staff: { type: Object, default: null },
});

const page = usePage();
const me = computed(() => page.props.auth?.user?.id);
onMounted(() => { if (!props.staff) router.reload({ only: ['staff'], data: { tab: 'staff' } }); });
const reload = () => router.reload({ only: ['staff'], data: { tab: 'staff' } });

const issued = ref(page.props.flash?.staff_password || null);
watch(() => page.props.flash?.staff_password, value => { if (value) { issued.value = value; copied.value = false; } });
const showTemporaryPassword = ref(false);
const copied = ref(false);
async function copyPassword() {
    try { await navigator.clipboard.writeText(issued.value.password); copied.value = true; setTimeout(() => (copied.value = false), 2000); } catch { /* select it by hand */ }
}

const addForm = useForm({ name: '', email: '', role: 'cashier', password: '', password_confirmation: '', reason: '' });
function add() {
    addForm.post(route('studio.staff.store'), { preserveScroll: true, onSuccess: response => { issued.value = response.props.flash?.staff_password || null; copied.value = false; showTemporaryPassword.value = false; addForm.reset(); } });
}

const managing = ref(null);
const managementForm = useForm({ name: '', email: '', password: '', password_confirmation: '', reason: '' });
const revealReset = ref(false);
function manage(member, action) {
    if (managementForm.processing) return;
    managementForm.reset(); managementForm.clearErrors();
    managementForm.name = member.name; managementForm.email = member.email;
    revealReset.value = false;
    managing.value = { id: member.id, action };
}
function cancelManagement() {
    managing.value = null; managementForm.reset(); managementForm.clearErrors(); revealReset.value = false;
}
function saveManagement(member) {
    const action = managing.value?.action;
    if (managementForm.processing || !action) return;
    if (action === 'reset' && !window.confirm(`Issue a temporary password for ${member.name}? Their previous password and active sessions will stop working.`)) return;
    if (action === 'sessions' && !window.confirm(`End all current sign-in and Sales Desk sessions for ${member.name}?${member.id === me.value ? ' This includes this browser.' : ''}`)) return;
    if (action === 'details' && managementForm.email !== member.email && !window.confirm(`Change ${member.name}'s sign-in email to ${managementForm.email}? Previous sessions and email reset links will be revoked.`)) return;
    const options = { preserveScroll: true, onSuccess: response => {
        if (action === 'reset') {
            issued.value = response.props.flash?.staff_password || null; copied.value = false;
            nextTick(() => { const details = document.getElementById('staff-issued-credentials'); details?.scrollIntoView({ block: 'start' }); details?.focus({ preventScroll: true }); });
        }
        cancelManagement();
        if (response.component === 'Admin/StudioDashboard') { reload(); if (historyFor.value === member.id) loadHistory(member.id); }
    } };
    if (action === 'details') managementForm.put(route('studio.staff.details', member.id), options);
    else managementForm.post(route(action === 'reset' ? 'studio.staff.reset_password' : 'studio.staff.end_sessions', member.id), options);
}

const historyFor = ref(null);
const accessHistory = ref(null);
const historyLoading = ref(false);
const historyError = ref('');
let historySequence = 0;
async function loadHistory(id, number = 1) {
    const sequence = ++historySequence;
    historyFor.value = id; historyLoading.value = true; historyError.value = '';
    try {
        const { data } = await axios.get(route('studio.staff.history', id), { params: { page: number } });
        if (sequence === historySequence) accessHistory.value = data;
    } catch {
        if (sequence === historySequence) historyError.value = 'Access history could not be loaded. Retry to review the recorded changes.';
    } finally { if (sequence === historySequence) historyLoading.value = false; }
}
function toggleHistory(member) {
    if (historyFor.value === member.id) { historySequence++; historyFor.value = null; historyLoading.value = false; }
    else { accessHistory.value = null; loadHistory(member.id); }
}
const historyValue = value => value === null ? '—' : typeof value === 'boolean' ? (value ? 'Yes' : 'No') : String(value);

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
const pinForm = useForm({ pin: '', reason: 'Sales Desk access' });
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
            <section id="staff-issued-credentials" tabindex="-1" v-if="issued" class="rounded-2xl bg-emerald-500/10 ring-1 ring-emerald-400/30 p-4" role="status">
                <h2 class="text-lg font-semibold text-white mb-2">Staff sign-in details</h2>
                <p class="text-sm text-white">Temporary password for <strong>{{ issued.email }}</strong>. It is shown only now: give it to them in person. Use this email and password at the sign-in page. They must choose their own password on first sign-in; their Sales Desk PIN is separate.</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <code class="rounded-lg bg-slate-950 px-3 py-2 font-mono text-base text-white select-all">{{ issued.password }}</code>
                    <button type="button" @click="copyPassword" class="rounded-xl bg-white/5 px-3 py-2 text-sm text-slate-200 hover:bg-white/10">{{ copied ? 'Copied' : 'Copy' }}</button>
                </div>
                <div class="mt-3 flex flex-wrap gap-3"><a :href="route('login')" target="_blank" rel="noopener" class="table-action">Open sign-in page</a><button type="button" @click="issued = null" class="table-action">Dismiss sign-in details</button></div>
            </section>

            <section class="bg-slate-900/60 ring-1 ring-white/10 rounded-2xl p-4 sm:p-5" aria-labelledby="add-staff-title">
                <h2 id="add-staff-title" class="text-white font-headline text-lg mb-3">Create staff sign-in</h2>
                <p class="text-sm text-slate-400 mb-4">Create an account for a cashier, manager or owner. Set a temporary password below, or leave both password fields blank to generate one.</p>
                <form @submit.prevent="add" class="grid gap-3 sm:grid-cols-2">
                    <label class="text-xs text-slate-400">Name<input v-model="addForm.name" required maxlength="255" autocomplete="off" :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Email (they sign in with it)<input v-model="addForm.email" type="email" required maxlength="255" autocomplete="off" :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Role
                        <select v-model="addForm.role" :class="field + ' mt-1'"><option v-for="r in staff.roles" :key="r.value" :value="r.value">{{ r.label }}</option></select>
                        <span class="block mt-1 text-slate-500">{{ staff.roles.find(r => r.value === addForm.role)?.help }}</span>
                    </label>
                    <label class="text-xs text-slate-400">Reason<input v-model="addForm.reason" required maxlength="255" placeholder="e.g. New cashier for weekends" :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Temporary sign-in password (optional)<input v-model="addForm.password" :required="!!addForm.password_confirmation" :type="showTemporaryPassword ? 'text' : 'password'" minlength="8" maxlength="255" autocomplete="new-password" :class="field + ' mt-1'" /><span class="block mt-1">At least 8 characters. Leave blank to generate a password.</span></label>
                    <label class="text-xs text-slate-400">Confirm temporary password<input v-model="addForm.password_confirmation" :type="showTemporaryPassword ? 'text' : 'password'" :required="!!addForm.password" maxlength="255" autocomplete="new-password" :class="field + ' mt-1'" /></label>
                    <label class="sm:col-span-2 flex items-center gap-2 text-sm text-slate-400"><input v-model="showTemporaryPassword" type="checkbox" /> Show temporary password</label>
                    <div class="sm:col-span-2 flex flex-wrap items-center gap-3">
                        <button type="submit" :disabled="addForm.processing" class="rounded-xl bg-[#57d5fc] px-5 py-2.5 text-sm font-semibold text-slate-900 disabled:opacity-40">{{ addForm.processing ? 'Creating account…' : 'Create staff account' }}</button>
                        <p v-for="(m, f) in addForm.errors" :key="f" role="alert" class="text-sm text-rose-300">{{ m }}</p>
                    </div>
                </form>
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
                                · {{ member.has_pin ? 'Sales Desk PIN set' : 'No Sales Desk PIN' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <select :value="member.role" :disabled="member.id === me" @change="changeRole(member, $event.target.value)" :aria-label="`Role of ${member.name}`"
                                class="bg-slate-950 border border-white/10 rounded-xl pl-3 pr-9 py-2 text-white text-sm disabled:opacity-50">
                                <option v-for="r in staff.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                            </select>
                            <button type="button" :disabled="managementForm.processing" @click="manage(member, 'details')" class="table-action">Edit details</button>
                            <button v-if="member.id !== me" type="button" :disabled="managementForm.processing" @click="manage(member, 'reset')" class="table-action">Reset sign-in password</button>
                            <a v-if="member.id === me" :href="route('profile.edit')" class="table-action">Change my password</a>
                            <button type="button" :disabled="managementForm.processing" @click="manage(member, 'sessions')" class="table-action">End sessions</button>
                            <button type="button" @click="toggleHistory(member)" :aria-expanded="historyFor === member.id" class="table-action">Access history</button>
                            <button type="button" @click="startPin(member)" class="table-action">{{ member.has_pin ? 'Change PIN' : 'Set PIN' }}</button>
                            <button v-if="!member.suspended && member.id !== me" type="button" @click="suspend(member)" class="table-action text-rose-300">Suspend</button>
                            <button v-if="member.suspended" type="button" @click="reactivate(member)" class="table-action">Reactivate</button>
                        </div>
                    </div>
                    <section v-if="managing?.id === member.id" class="mt-4 border-t border-white/10 pt-4" :aria-label="`${member.name}: ${managing.action === 'details' ? 'edit details' : managing.action === 'reset' ? 'reset password' : 'end sessions'}`">
                        <h3 class="font-semibold text-white">{{ managing.action === 'details' ? 'Edit staff details' : managing.action === 'reset' ? 'Reset sign-in password' : 'End active sessions' }}</h3>
                        <p class="mt-1 mb-3 text-sm text-slate-400">{{ managing.action === 'details' ? 'Changing the email updates how this person signs in and ends their previous sessions.' : managing.action === 'reset' ? 'Issue a temporary password. Their previous sessions end, and they must choose their own password before working. Their PIN stays separate.' : 'Previous account sessions and shared-tablet PIN sessions will be rejected on their next request. The password and account status stay unchanged.' }}</p>
                        <form @submit.prevent="saveManagement(member)" class="grid gap-3 sm:grid-cols-2">
                            <template v-if="managing.action === 'details'">
                                <label class="text-sm text-slate-300">Staff name<input v-model="managementForm.name" required maxlength="255" :class="field + ' mt-1'" /></label>
                                <label class="text-sm text-slate-300">Sign-in email<input v-model="managementForm.email" type="email" required maxlength="255" :class="field + ' mt-1'" /></label>
                            </template>
                            <template v-if="managing.action === 'reset'">
                                <label class="text-sm text-slate-300">New temporary password (optional)<input v-model="managementForm.password" :type="revealReset ? 'text' : 'password'" :required="!!managementForm.password_confirmation" minlength="8" maxlength="255" autocomplete="new-password" :class="field + ' mt-1'" /><span class="block mt-1 text-xs text-slate-400">Leave both fields blank to generate one.</span></label>
                                <label class="text-sm text-slate-300">Confirm new temporary password<input v-model="managementForm.password_confirmation" :type="revealReset ? 'text' : 'password'" :required="!!managementForm.password" maxlength="255" autocomplete="new-password" :class="field + ' mt-1'" /></label>
                                <label class="sm:col-span-2 flex items-center gap-2 text-sm text-slate-400"><input v-model="revealReset" type="checkbox" /> Show temporary password</label>
                            </template>
                            <label class="sm:col-span-2 text-sm text-slate-300">Reason for this change<input v-model="managementForm.reason" required maxlength="255" :class="field + ' mt-1'" /></label>
                            <p v-for="(message, key) in managementForm.errors" :key="key" role="alert" class="sm:col-span-2 text-sm text-rose-300">{{ message }}</p>
                            <div class="sm:col-span-2 flex flex-wrap gap-2"><button type="submit" :disabled="managementForm.processing" class="table-action font-semibold">{{ managementForm.processing ? 'Saving…' : managing.action === 'details' ? 'Save staff details' : managing.action === 'reset' ? 'Issue temporary password' : 'End sessions now' }}</button><button type="button" :disabled="managementForm.processing" @click="cancelManagement" class="table-action">Cancel</button></div>
                        </form>
                    </section>
                    <section v-if="historyFor === member.id" class="mt-4 border-t border-white/10 pt-4" :aria-label="`Access history for ${member.name}`" :aria-busy="historyLoading">
                        <h3 class="font-semibold text-white">Access history</h3>
                        <p v-if="historyLoading" role="status" class="mt-2 text-sm text-slate-400">Loading access history…</p>
                        <p v-if="historyError" role="alert" class="mt-2 text-sm text-rose-300">{{ historyError }} <button type="button" @click="loadHistory(member.id)" class="table-action">Retry</button></p>
                        <template v-if="accessHistory && !historyError">
                            <p class="mt-1 text-xs text-slate-400">{{ accessHistory.total }} recorded changes · Page {{ accessHistory.page }} of {{ accessHistory.last_page }}</p>
                            <p v-if="!accessHistory.rows.length" class="mt-2 text-sm text-slate-400">No access changes have been recorded for this account.</p>
                            <ol class="mt-3 divide-y divide-white/10"><li v-for="entry in accessHistory.rows" :key="entry.id" class="py-3 text-sm"><p class="font-semibold text-white">{{ entry.action }}</p><p class="text-xs text-slate-400">{{ entry.actor }} · {{ entry.at }} · Nairobi</p><p class="mt-1 text-slate-300">{{ entry.reason || 'No reason recorded' }}</p><p v-for="change in entry.changes" :key="change.field" class="text-xs text-slate-400">{{ change.field }}: {{ historyValue(change.before) }} → {{ historyValue(change.after) }}</p></li></ol>
                            <div role="group" aria-label="Access history pages" class="mt-3 flex gap-2"><button v-if="accessHistory.page > 1" type="button" :disabled="historyLoading" @click="loadHistory(member.id, accessHistory.page - 1)" class="table-action">Newer changes</button><button v-if="accessHistory.page < accessHistory.last_page" type="button" :disabled="historyLoading" @click="loadHistory(member.id, accessHistory.page + 1)" class="table-action">Older changes</button></div>
                        </template>
                    </section>

                    <form v-if="pinFor === member.id" @submit.prevent="savePin(member)" class="mt-3 grid gap-2 sm:grid-cols-[8rem_1fr_auto]" :aria-label="`Sales Desk PIN for ${member.name}`">
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
                    <h2 id="till-rules-title" class="text-white font-headline text-lg">Sales Desk</h2>
                    <a :href="route('pos.index')" class="rounded-xl bg-white/5 px-4 py-2 text-sm text-slate-200 hover:bg-white/10">Open Sales Desk</a>
                </div>
                <form @submit.prevent="savePolicy" class="grid gap-3 sm:grid-cols-3">
                    <label class="text-xs text-slate-400">Cashier discount, % of the sale<input v-model.number="policyForm.cashier_discount_percent" type="number" min="0" max="100" required :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">…and at most (KSh)<input v-model.number="policyForm.cashier_discount_cap" type="number" min="0" required :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Drawer difference that needs a manager (KSh)<input v-model.number="policyForm.till_variance_alert" type="number" min="0" required :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Hold a counter sale's pieces for (minutes)<input v-model.number="policyForm.pos_hold_minutes" type="number" min="5" max="240" required :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400">Lock Sales Desk after idle (minutes)<input v-model.number="policyForm.pos_idle_minutes" type="number" min="1" max="120" required :class="field + ' mt-1'" /></label>
                    <label class="text-xs text-slate-400 sm:col-span-3">Receipt footer<input v-model="policyForm.receipt_footer" maxlength="300" placeholder="e.g. Exchanges within 7 days with this receipt." :class="field + ' mt-1'" /></label>
                    <div class="sm:col-span-3 flex flex-wrap items-center gap-3">
                        <button type="submit" :disabled="policyForm.processing || !policyForm.isDirty" class="rounded-xl bg-[#57d5fc] px-5 py-2.5 text-sm font-semibold text-slate-900 disabled:opacity-40">Save the Sales Desk rules</button>
                        <span v-if="policySaved" role="status" class="text-sm text-emerald-400">Saved.</span>
                        <p v-for="(m, f) in policyForm.errors" :key="f" role="alert" class="text-sm text-rose-300">{{ m }}</p>
                    </div>
                </form>
            </section>


        </template>
    </div>
</template>
