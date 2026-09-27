<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Activity (Super Admin)
//  Location: resources/js/Pages/Admin/Activity/Index.vue
//  Route: GET /admin/activity (admin.activity.index) → Admin\ActivityController
//  Permission: platform.activity
//
//  Who is using Massar: one row per person per day, at the time they
//  first arrived (see the controller for why). Filter by date range,
//  partner, and a name/email search. Summary: distinct people today,
//  this week, this month, out of all partner staff.
// ══════════════════════════════════════════════════════════════════

import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Field from '@/Components/Field.vue';
import Pagination from '@/Components/Pagination.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';

const props = defineProps({
    rows: { type: Object, required: true },
    summary: { type: Object, required: true },
    companies: { type: Array, required: true },
    filters: { type: Object, required: true },
});
const { t, locale } = useTranslations();

const f = reactive({ ...props.filters, company_id: props.filters.company_id ?? '' });
function apply() {
    router.get(route('admin.activity.index'), { ...f, company_id: f.company_id || undefined, q: f.q || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <AdminLayout :title="t('activity.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('activity.title') }}</h1>
                <div class="page-sub">{{ t('activity.sub') }}</div>
            </div>
        </div>

        <div class="grid-4">
            <div class="stat-card teal"><div class="stat-label">{{ t('activity.today') }}</div><div class="stat-value">{{ summary.today }}</div></div>
            <div class="stat-card navy"><div class="stat-label">{{ t('activity.week') }}</div><div class="stat-value">{{ summary.this_week }}</div></div>
            <div class="stat-card purple"><div class="stat-label">{{ t('activity.month') }}</div><div class="stat-value">{{ summary.this_month }}</div></div>
            <div class="stat-card green"><div class="stat-label">{{ t('activity.total') }}</div><div class="stat-value">{{ summary.total_users }}</div></div>
        </div>

        <form class="panel mt-4" @submit.prevent="apply">
            <div class="form-grid cols-3" style="grid-template-columns:repeat(4,minmax(0,1fr))">
                <Field :label="t('activity.from')"><input v-model="f.from" type="date"></Field>
                <Field :label="t('activity.to')"><input v-model="f.to" type="date"></Field>
                <Field :label="t('activity.partner')">
                    <select v-model="f.company_id"><option value="">{{ t('common.all') }}</option><option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option></select>
                </Field>
                <Field :label="t('common.search')"><input v-model="f.q" type="search" :placeholder="t('activity.search_ph')"></Field>
            </div>
            <div class="form-actions" style="border:0;padding-top:0;margin-top:14px">
                <button type="submit" class="btn btn-teal"><AppIcon name="search" :size="15" />{{ t('common.search') }}</button>
            </div>
        </form>

        <EmptyState v-if="!rows.data.length" class="mt-4" icon="activity" :text="t('activity.empty')" />

        <div v-else class="table-wrap mt-4">
            <table>
                <thead>
                    <tr><th>{{ t('activity.col_date') }}</th><th>{{ t('activity.col_time') }}</th><th>{{ t('activity.col_person') }}</th><th>{{ t('activity.col_partner') }}</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in rows.data" :key="`${r.user_id}-${r.date}-${i}`">
                        <td>{{ formatDate(r.date, locale) }}</td>
                        <td class="num ltr">{{ r.time }}</td>
                        <td>
                            <div class="name-cell">
                                <div><b>{{ r.user_name }}</b><div class="meta ltr">{{ r.user_email }}</div></div>
                                <span v-if="!r.is_active" class="badge plain">{{ t('common.inactive') }}</span>
                            </div>
                        </td>
                        <td>{{ r.company_name }}</td>
                    </tr>
                </tbody>
            </table>
            <Pagination :paginator="rows" />
        </div>
    </AdminLayout>
</template>
