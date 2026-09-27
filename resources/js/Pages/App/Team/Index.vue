<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Team page
//  Location: resources/js/Pages/App/Team/Index.vue
//  Route: GET /app/team (app.team.index) → App\TeamController
//  Permission: team.manage
//
//  The partner's staff accounts with seat usage. Add / edit in a
//  modal; deactivate or reactivate with a confirmation. The "Add"
//  button is disabled when every seat is taken — the server refuses
//  it too (seat limit is set by the Massar Super Admin).
// ══════════════════════════════════════════════════════════════════

import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Field from '@/Components/Field.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';

const props = defineProps({
    members: { type: Array, required: true },
    seats: { type: Object, required: true },
});
const { t, locale } = useTranslations();

const full = computed(() => props.seats.used >= props.seats.limit);
const pct = computed(() => Math.min(100, Math.round((props.seats.used / Math.max(1, props.seats.limit)) * 100)));

const editing = ref(null);          // null = closed, {} = new, member = edit
const form = useForm({ name: '', email: '', job_title: '', role: 'employee', password: '', password_confirmation: '' });

function openNew() {
    form.reset(); form.clearErrors();
    editing.value = {};
}
function openEdit(m) {
    form.clearErrors();
    Object.assign(form, { name: m.name, email: m.email, job_title: m.job_title ?? '', role: m.role, password: '', password_confirmation: '' });
    editing.value = m;
}
function save() {
    const done = { preserveScroll: true, onSuccess: () => { editing.value = null; form.reset(); } };
    if (editing.value?.id) form.patch(route('app.team.update', editing.value.id), done);
    else form.post(route('app.team.store'), done);
}

const toToggle = ref(null);
const toggling = ref(false);
function toggle() {
    toggling.value = true;
    router.patch(route('app.team.toggle-active', toToggle.value.id), {}, {
        preserveScroll: true,
        onFinish: () => { toggling.value = false; toToggle.value = null; },
    });
}
function askToggle(m) {
    if (m.is_active) toToggle.value = m;
    else router.patch(route('app.team.toggle-active', m.id), {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="t('team.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('team.title') }}</h1>
                <div class="page-sub">{{ t('team.sub') }}</div>
            </div>
            <div class="page-actions">
                <button type="button" class="btn btn-primary" :disabled="full" @click="openNew"><AppIcon name="plus" :size="15" />{{ t('team.add') }}</button>
            </div>
        </div>

        <div class="panel mb-4">
            <div class="flex items-center justify-between gap-3 wrap">
                <b>{{ t('team.seats') }}</b>
                <span class="small mute">{{ t('team.seats_used', { used: seats.used, limit: seats.limit }) }}</span>
            </div>
            <div class="meter mt-2"><i :style="{ width: pct + '%', '--c': full ? 'var(--ms-orange)' : 'var(--ms-green)' }"></i></div>
            <div v-if="full" class="alert warning mt-3"><AppIcon name="alert" :size="16" /><span class="grow">{{ t('team.no_seat') }}</span></div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ t('team.col_member') }}</th>
                        <th>{{ t('team.col_role') }}</th>
                        <th>{{ t('team.col_status') }}</th>
                        <th>{{ t('team.col_last_login') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in members" :key="m.id">
                        <td>
                            <div class="name-cell">
                                <span class="avatar">{{ m.initials }}</span>
                                <div>
                                    <b>{{ m.name }} <span v-if="m.is_me" class="badge teal" style="margin-inline-start:4px">{{ t('team.you') }}</span></b>
                                    <div class="meta"><span class="ltr">{{ m.email }}</span><span v-if="m.job_title"> · {{ m.job_title }}</span></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge" :class="m.role === 'company_admin' ? 'purple' : 'navy'">{{ m.role === 'company_admin' ? t('team.role_admin') : t('team.role_employee') }}</span></td>
                        <td><span class="badge" :class="m.is_active ? 'green' : 'plain'">{{ m.is_active ? t('common.active') : t('common.inactive') }}</span></td>
                        <td class="small mute">{{ m.last_login_at ? formatDate(m.last_login_at, locale, true) : t('common.never') }}</td>
                        <td class="actions">
                            <button type="button" class="btn btn-ghost sm" @click="openEdit(m)"><AppIcon name="edit" :size="14" />{{ t('common.edit') }}</button>
                            <button v-if="!m.is_me" type="button" class="btn btn-ghost sm" @click="askToggle(m)">
                                <AppIcon name="power" :size="14" />{{ m.is_active ? t('team.deactivate') : t('team.reactivate') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="editing !== null" :title="editing?.id ? t('team.edit') : t('team.add')" @close="editing = null">
            <form id="team-form" class="form-grid" @submit.prevent="save" novalidate>
                <Field :label="t('common.name')" :error="form.errors.name" required><input v-model="form.name" type="text"></Field>
                <Field :label="t('common.email')" :error="form.errors.email" required><input v-model="form.email" type="email"></Field>
                <Field :label="t('team.job_title')" :error="form.errors.job_title" optional><input v-model="form.job_title" type="text" :placeholder="t('team.job_title_ph')"></Field>
                <Field :label="t('team.role')" :error="form.errors.role" :hint="t('team.role_help')">
                    <select v-model="form.role" :disabled="editing?.is_me">
                        <option value="employee">{{ t('team.role_employee') }}</option>
                        <option value="company_admin">{{ t('team.role_admin') }}</option>
                    </select>
                </Field>
                <Field :label="t('common.password')" :error="form.errors.password" :hint="editing?.id ? t('team.password_keep') : t('auth.password_hint')" :required="!editing?.id">
                    <PasswordInput v-model="form.password" autocomplete="new-password" />
                </Field>
                <Field :label="t('common.password_confirm')" :error="form.errors.password_confirmation" :required="!editing?.id">
                    <PasswordInput v-model="form.password_confirmation" autocomplete="new-password" />
                </Field>
            </form>
            <template #footer>
                <button type="button" class="btn btn-line" @click="editing = null">{{ t('common.cancel') }}</button>
                <button type="submit" form="team-form" class="btn btn-primary" :class="{ 'is-loading': form.processing }" :disabled="form.processing">{{ t('common.save') }}</button>
            </template>
        </Modal>

        <ConfirmDialog :show="!!toToggle" danger :processing="toggling" :confirm-label="t('team.deactivate')"
                       :message="t('team.deactivate_confirm', { name: toToggle?.name ?? '' })"
                       @confirm="toggle" @close="toToggle = null" />
    </AppLayout>
</template>
