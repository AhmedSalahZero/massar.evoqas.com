<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Backbone · Unit group (Super Admin)
//  Location: resources/js/Pages/Admin/Backbone/Unit.vue
//  Route: GET /admin/backbone/units/{code} (admin.backbone.unit)
//  Permission: platform.backbone
//
//  One 4-digit group (ISCO-08 unit group = ENOC occupation). The
//  content is the shared UnitDetail; the Super Admin sees every
//  figure, flagged ones with a warning.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
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
const title = computed(() => titles(props.unit, locale.value));
</script>

<template>
    <AdminLayout :title="`${unit.code} · ${title[0]}`">
        <Link :href="route('admin.backbone.index')" class="back"><AppIcon name="arrow-left" :size="14" />{{ t('bb.title') }}</Link>
        <UnitDetail v-bind="props" :routes="{ esco: 'admin.backbone.esco', skill: 'admin.backbone.skill' }" />
    </AdminLayout>
</template>
