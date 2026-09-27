<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Occupations (partner workspace)
//  Location: resources/js/Pages/App/Occupations/Index.vue
//  Route: GET /app/occupations (app.occupations.index) → App\OccupationController@index
//  Permission: occupations.view
//
//  Search and browse every occupation in the standard chosen in the
//  top bar — ENOC (Egyptian), ISCO-08 (international) or ESCO
//  (detailed) — using the same OccupationBrowser as the Super Admin.
//  Opening an occupation shows it in all three standards with the
//  Egypt labour market panel. Read-only.
// ══════════════════════════════════════════════════════════════════

import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import OccupationBrowser from '@/Components/Backbone/OccupationBrowser.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    standard: { type: String, required: true },
    filters: { type: Object, required: true },
    loaded: { type: Boolean, required: true },
    majors: { type: Array, default: () => [] },
    browse: { type: Object, default: null },
});

const { t } = useTranslations();
</script>

<template>
    <AppLayout :title="t('occ.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('occ.title') }}</h1>
                <div class="page-sub">{{ t('occ.sub') }}</div>
            </div>
        </div>

        <EmptyState v-if="!loaded" icon="layers" :title="t('occ.not_ready_title')" :text="t('occ.not_ready')" />

        <OccupationBrowser v-else :standard="standard" :filters="filters" :majors="majors" :browse="browse"
                           :routes="{ index: 'app.occupations.index', unit: 'app.occupations.unit', esco: 'app.occupations.esco' }" />
    </AppLayout>
</template>
