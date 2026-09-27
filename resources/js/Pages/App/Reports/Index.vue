<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Reports (partner workspace, Step 14)
//  Location: resources/js/Pages/App/Reports/Index.vue
//  Route: GET /app/reports (reports.view) — App\ReportController@index
//  The page itself: resources/js/Components/Reports/ReportPage.vue
// ══════════════════════════════════════════════════════════════════

import AppLayout from '@/Layouts/AppLayout.vue';
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
    <AppLayout :title="t('nav.reports')">
        <ReportPage v-bind="$props" />
    </AppLayout>
</template>
