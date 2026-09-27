<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Occupation (partner workspace)
//  Location: resources/js/Pages/App/Occupations/Unit.vue
//  Route: GET /app/occupations/units/{code} (app.occupations.unit)
//  Permission: occupations.view
//
//  One occupation (ENOC = ISCO-08 unit group), with the shared
//  UnitDetail. Figures flagged at import were removed by the server
//  and show as "Under review".
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import UnitDetail from '@/Components/Backbone/UnitDetail.vue';
import { useTranslations } from '@/composables/useTranslations';
import { titles } from '@/Components/Backbone/occ';

const props = defineProps({
    unit: { type: Object, required: true },
    lineage: { type: Array, required: true },
    enoc: { type: Object, default: null },
    market: { type: Object, default: null },
    skills: { type: Object, default: null },
    esco: { type: Array, required: true },
});

const { t, locale } = useTranslations();
const title = computed(() => (props.enoc && locale.value === 'ar' ? props.enoc.title_ar : titles(props.unit, locale.value)[0]));
</script>

<template>
    <AppLayout :title="`${unit.code} · ${title}`">
        <Link :href="route('app.occupations.index')" class="back"><AppIcon name="arrow-left" :size="14" />{{ t('occ.title') }}</Link>
        <UnitDetail v-bind="props" :routes="{ esco: 'app.occupations.esco', skill: 'app.occupations.skill' }" />
    </AppLayout>
</template>
