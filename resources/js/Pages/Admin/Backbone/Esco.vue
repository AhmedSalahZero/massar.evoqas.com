<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Backbone · ESCO occupation (Super Admin)
//  Location: resources/js/Pages/Admin/Backbone/Esco.vue
//  Route: GET /admin/backbone/esco/{id} (admin.backbone.esco)
//  Permission: platform.backbone
//
//  One detailed ESCO occupation. The content is the shared EscoDetail;
//  the Super Admin sees every figure, flagged ones with a warning.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
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
    <AdminLayout :title="title">
        <Link :href="route('admin.backbone.unit', unit.code)" class="back">
            <AppIcon name="arrow-left" :size="14" /><span class="ltr">{{ unit.code }}</span>&nbsp;{{ locale === 'ar' ? unit.title_ar : unit.title_en }}
        </Link>
        <EscoDetail v-bind="props" :routes="{ esco: 'admin.backbone.esco', skill: 'admin.backbone.skill' }" />
    </AdminLayout>
</template>
