<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Partner Organisations (Super Admin)
//  Location: resources/js/Pages/Admin/Companies/Index.vue
//  Route: GET /admin/companies (admin.companies.index) → Admin\CompanyController
//  Permission: platform.companies
//
//  · List with search and status filter (active / suspended / ending
//    soon), seats used of limit, subscription days left.
//  · "Add partner" opens a form that creates the partner AND its
//    first Company Admin in one step (?new=1 opens it straight away —
//    used by the dashboard button).
//  · Edit details, seats and subscription end date.
//  · Suspend / reactivate (with confirmation) and permanent delete
//    (the organisation name must be typed to confirm).
// ══════════════════════════════════════════════════════════════════

import { onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Field from '@/Components/Field.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';

const props = defineProps({
    companies: { type: Object, required: true },
    filters: { type: Object, required: true },
    types: { type: Array, required: true },
    defaults: { type: Object, required: true },
});
const { t, locale } = useTranslations();

const GOVS = ['cai', 'giz', 'alx', 'qal', 'dak', 'sha', 'gha', 'mnf', 'beh', 'kfs', 'dam', 'pts', 'ism', 'suz', 'fay', 'bns', 'min', 'ast', 'soh', 'qen', 'lux', 'asw', 'red', 'wad', 'mat', 'nsi', 'ssi'];

// ── Filters ───────────────────────────────────────────────────────
const q = ref(props.filters.q ?? '');
function applyFilters(status = props.filters.status) {
    router.get(route('admin.companies.index'), { q: q.value || undefined, status: status || undefined }, { preserveState: true, replace: true });
}

// ── Create / edit ─────────────────────────────────────────────────
const blank = () => ({
    name: '', name_ar: '', type: 'ngo', governorate: '', contact_email: '', contact_phone: '',
    seat_limit: props.defaults.seat_limit, subscription_ends_at: '',
    admin_name: '', admin_email: '', admin_job_title: '', admin_language: 'ar', admin_password: '', admin_password_confirmation: '',
});
const editing = ref(null);  // null closed · {} new · company edit
const form = useForm(blank());

function openNew() {
    form.defaults(blank()); form.reset(); form.clearErrors();
    editing.value = {};
}
function openEdit(c) {
    form.clearErrors();
    Object.assign(form, {
        name: c.name, name_ar: c.name_ar ?? '', type: c.type, governorate: c.governorate ?? '',
        contact_email: c.contact_email ?? '', contact_phone: c.contact_phone ?? '',
        seat_limit: c.seat_limit, subscription_ends_at: c.subscription_ends_at ?? '',
    });
    editing.value = c;
}
function save() {
    const opts = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    if (editing.value?.id) form.patch(route('admin.companies.update', editing.value.id), opts);
    else form.post(route('admin.companies.store'), opts);
}

onMounted(() => {
    if (new URLSearchParams(window.location.search).get('new')) openNew();
});

// ── Suspend / delete ──────────────────────────────────────────────
const toSuspend = ref(null);
const toDelete = ref(null);
const busy = ref(false);

function toggle(c) {
    if (c.is_active) { toSuspend.value = c; return; }
    router.patch(route('admin.companies.toggle-active', c.id), {}, { preserveScroll: true });
}
function confirmSuspend() {
    busy.value = true;
    router.patch(route('admin.companies.toggle-active', toSuspend.value.id), {}, {
        preserveScroll: true, onFinish: () => { busy.value = false; toSuspend.value = null; },
    });
}
function confirmDelete() {
    busy.value = true;
    router.delete(route('admin.companies.destroy', toDelete.value.id), {
        preserveScroll: true, onFinish: () => { busy.value = false; toDelete.value = null; },
    });
}

const displayName = (c) => (locale.value === 'ar' && c.name_ar ? c.name_ar : c.name);
</script>

<template>
    <AdminLayout :title="t('companies.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('companies.title') }}</h1>
                <div class="page-sub">{{ t('companies.sub') }}</div>
            </div>
            <div class="page-actions">
                <button type="button" class="btn btn-primary" @click="openNew"><AppIcon name="plus" :size="15" />{{ t('companies.add') }}</button>
            </div>
        </div>

        <div class="filter-bar">
            <form class="search grow" style="max-width:420px" @submit.prevent="applyFilters()">
                <AppIcon name="search" :size="16" />
                <input v-model="q" type="search" :placeholder="t('companies.search_ph')">
            </form>
            <div class="chips">
                <button v-for="s in ['', 'active', 'suspended', 'expiring']" :key="s" type="button" class="chip"
                        :class="{ on: (filters.status || '') === s }" @click="applyFilters(s)">
                    {{ t(`companies.status_${s || 'all'}`) }}
                </button>
            </div>
        </div>

        <EmptyState v-if="!companies.data.length" icon="building" :text="t('companies.empty')">
            <button type="button" class="btn btn-primary sm" @click="openNew">{{ t('companies.add') }}</button>
        </EmptyState>

        <div v-else class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ t('companies.col_partner') }}</th>
                        <th>{{ t('companies.col_seats') }}</th>
                        <th>{{ t('companies.col_subscription') }}</th>
                        <th>{{ t('companies.col_status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in companies.data" :key="c.id">
                        <td>
                            <div class="name-cell">
                                <span class="avatar" style="--av:linear-gradient(135deg,var(--ms-teal),var(--ms-navy))"><AppIcon name="building" :size="15" /></span>
                                <div>
                                    <b>{{ displayName(c) }}</b>
                                    <div class="meta">{{ t(`companies.type_${c.type}`) }}<span v-if="c.governorate"> · {{ t(`gov.${c.governorate}`) }}</span><span v-if="c.contact_email"> · <span class="ltr">{{ c.contact_email }}</span></span></div>
                                </div>
                            </div>
                        </td>
                        <td class="num"><b>{{ c.users_count }}</b><span class="mute">/{{ c.seat_limit }}</span></td>
                        <td class="small">
                            <span v-if="!c.subscription_ends_at" class="mute">{{ t('companies.no_expiry') }}</span>
                            <template v-else>
                                <span :class="c.lapsed ? 'c-danger' : c.expiring_soon ? 'c-orange' : ''">
                                    {{ c.lapsed ? t('companies.lapsed') : t('companies.days_left', { days: c.days_left }) }}
                                </span>
                                <div class="mute">{{ formatDate(c.subscription_ends_at, locale) }}</div>
                            </template>
                        </td>
                        <td><span class="badge" :class="c.is_active ? 'green' : 'plain'">{{ c.is_active ? t('common.active') : t('common.suspended') }}</span></td>
                        <td class="actions">
                            <button type="button" class="btn btn-ghost sm" @click="openEdit(c)"><AppIcon name="edit" :size="14" />{{ t('common.edit') }}</button>
                            <button type="button" class="btn btn-ghost sm" @click="toggle(c)"><AppIcon name="power" :size="14" />{{ c.is_active ? t('companies.suspend') : t('companies.reactivate') }}</button>
                            <button type="button" class="btn btn-ghost sm c-danger" @click="toDelete = c" :aria-label="t('common.delete')"><AppIcon name="trash" :size="14" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Pagination :paginator="companies" />
        </div>

        <!-- ── Create / edit ─────────────────────────────────────── -->
        <Modal :show="editing !== null" size="lg" :title="editing?.id ? t('companies.edit') : t('companies.add')" @close="editing = null">
            <form id="company-form" @submit.prevent="save" novalidate>
                <div class="section-label" style="margin-top:0">{{ t('companies.section_partner') }}</div>
                <div class="form-grid">
                    <Field :label="t('common.name')" :error="form.errors.name" required><input v-model="form.name" type="text"></Field>
                    <Field :label="t('common.name_ar')" :error="form.errors.name_ar" optional><input v-model="form.name_ar" type="text" dir="rtl"></Field>
                    <Field :label="t('companies.type')" :error="form.errors.type" required>
                        <select v-model="form.type"><option v-for="ty in types" :key="ty" :value="ty">{{ t(`companies.type_${ty}`) }}</option></select>
                    </Field>
                    <Field :label="t('companies.governorate')" :error="form.errors.governorate" optional>
                        <select v-model="form.governorate"><option value="">—</option><option v-for="g in GOVS" :key="g" :value="g">{{ t(`gov.${g}`) }}</option></select>
                    </Field>
                    <Field :label="t('companies.contact_email')" :error="form.errors.contact_email" optional><input v-model="form.contact_email" type="email"></Field>
                    <Field :label="t('companies.contact_phone')" :error="form.errors.contact_phone" optional><input v-model="form.contact_phone" type="tel"></Field>
                    <Field :label="t('companies.seat_limit')" :error="form.errors.seat_limit" :hint="t('companies.seat_help')" required><input v-model="form.seat_limit" type="number" min="1"></Field>
                    <Field :label="t('companies.ends_at')" :error="form.errors.subscription_ends_at"
                           :hint="editing?.id ? '' : t('companies.ends_help', { months: defaults.months })" optional>
                        <input v-model="form.subscription_ends_at" type="date">
                    </Field>
                </div>

                <template v-if="!editing?.id">
                    <div class="section-label">{{ t('companies.section_admin') }}</div>
                    <p class="small mute">{{ t('companies.section_admin_help') }}</p>
                    <div class="form-grid">
                        <Field :label="t('companies.admin_name')" :error="form.errors.admin_name" required><input v-model="form.admin_name" type="text"></Field>
                        <Field :label="t('companies.admin_email')" :error="form.errors.admin_email" required><input v-model="form.admin_email" type="email"></Field>
                        <Field :label="t('companies.admin_job_title')" :error="form.errors.admin_job_title" optional><input v-model="form.admin_job_title" type="text"></Field>
                        <Field :label="t('companies.admin_language')" :error="form.errors.admin_language">
                            <select v-model="form.admin_language"><option value="ar">{{ t('common.arabic') }}</option><option value="en">{{ t('common.english') }}</option></select>
                        </Field>
                        <Field :label="t('common.password')" :error="form.errors.admin_password" :hint="t('auth.password_hint')" required>
                            <PasswordInput v-model="form.admin_password" autocomplete="new-password" />
                        </Field>
                        <Field :label="t('common.password_confirm')" :error="form.errors.admin_password_confirmation" required>
                            <PasswordInput v-model="form.admin_password_confirmation" autocomplete="new-password" />
                        </Field>
                    </div>
                </template>
            </form>
            <template #footer>
                <button type="button" class="btn btn-line" @click="editing = null">{{ t('common.cancel') }}</button>
                <button type="submit" form="company-form" class="btn btn-primary" :class="{ 'is-loading': form.processing }" :disabled="form.processing">{{ t('common.save') }}</button>
            </template>
        </Modal>

        <ConfirmDialog :show="!!toSuspend" danger :processing="busy" :confirm-label="t('companies.suspend')"
                       :message="t('companies.suspend_confirm', { name: toSuspend ? displayName(toSuspend) : '' })"
                       @confirm="confirmSuspend" @close="toSuspend = null" />

        <ConfirmDialog :show="!!toDelete" danger :processing="busy" :confirm-label="t('common.delete')"
                       :message="t('companies.delete_confirm', { name: toDelete ? displayName(toDelete) : '' })"
                       :type-to-confirm="toDelete?.name ?? ''" :type-label="t('companies.delete_type')"
                       @confirm="confirmDelete" @close="toDelete = null" />
    </AdminLayout>
</template>
