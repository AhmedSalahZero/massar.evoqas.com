<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Reports across all partners (Super Admin, Step 14)
//  Location: resources/js/Pages/Admin/Reports/Index.vue
//  Route: GET /admin/reports (reports.view) — App\ReportController@adminIndex · counts only
//  The page itself: resources/js/Components/Reports/ReportPage.vue
// ══════════════════════════════════════════════════════════════════

import AdminLayout from '@/Layouts/AdminLayout.vue';
import ReportPage from '@/Components/Reports/ReportPage.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    params: { type: Object, required: true },
    result: { type: Object, required: true },
    summary: { type: Array, default: () => [] },
    admin: { type: Boolean, default: false },
    occupations: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    saved: { type: Array, default: () => [] },
    can_open_people: { type: Boolean, default: false },
});
const { t } = useTranslations();
</script>

<template>
    <AdminLayout :title="t('nav.reports')">
        <ReportPage v-bind="$props" />
    </AdminLayout>
</template>
