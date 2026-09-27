<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — ESCO occupation (partner workspace)
//  Location: resources/js/Pages/App/Occupations/Esco.vue
//  Route: GET /app/occupations/esco/{id} (app.occupations.esco)
//  Permission: occupations.view
//
//  One detailed ESCO job, with the shared EscoDetail: the job in all
//  three standards and the Egypt figures of its ENOC occupation.
//  Figures flagged at import show as "Under review".
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EscoDetail from '@/Components/Backbone/EscoDetail.vue';
import { useTranslations } from '@/composables/useTranslations';
import { titles } from '@/Components/Backbone/occ';

const props = defineProps({
    occupation: { type: Object, required: true },
    ancestors: { type: Array, required: true },
    children: { type: Array, required: true },
    lineage: { type: Array, required: true },
    unit: { type: Object, required: true },
    enoc: { type: Object, default: null },
    market: { type: Object, default: null },
    skills: { type: Object, default: null },
});

const { locale } = useTranslations();
const title = computed(() => titles({ title_en: props.occupation.title_en, title_ar: props.occupation.title_ar_m, title_ar_f: props.occupation.title_ar_f }, locale.value)[0]);
</script>

<template>
    <AppLayout :title="title">
        <Link :href="route('app.occupations.unit', unit.code)" class="back">
            <AppIcon name="arrow-left" :size="14" /><span class="ltr">{{ unit.code }}</span>&nbsp;{{ locale === 'ar' ? unit.title_ar : unit.title_en }}
        </Link>
        <EscoDetail v-bind="props" :routes="{ esco: 'app.occupations.esco', skill: 'app.occupations.skill' }" />
    </AppLayout>
</template>
