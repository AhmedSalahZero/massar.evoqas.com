<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Backbone · ESCO skill (Super Admin)
//  Location: resources/js/Pages/Admin/Backbone/Skill.vue
//  Route: GET /admin/backbone/skills/{id} (admin.backbone.skill)
//  Permission: platform.backbone
//
//  One ESCO skill or knowledge item, with the shared SkillDetail.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import SkillDetail from '@/Components/Skills/SkillDetail.vue';
import { useTranslations } from '@/composables/useTranslations';
import { skillNames } from '@/Components/Skills/skl';

const props = defineProps({
    skill: { type: Object, required: true },
    paths: { type: Array, required: true },
    broader: { type: Array, required: true },
    narrower: { type: Array, required: true },
    needs: { type: Array, required: true },
    needed_by: { type: Array, required: true },
    occupations: { type: Object, required: true },
});

const { t, locale } = useTranslations();
const title = computed(() => skillNames(props.skill, locale.value)[0]);
const back = () => (window.history.length > 1 ? window.history.back() : (window.location.href = route('admin.backbone.index')));
</script>

<template>
    <AdminLayout :title="title">
        <button type="button" class="back" style="border:0;background:none;padding:0" @click="back"><AppIcon name="arrow-left" :size="14" />{{ t('common.back') }}</button>
        <SkillDetail v-bind="props" :routes="{ esco: 'admin.backbone.esco', skill: 'admin.backbone.skill' }" />
    </AdminLayout>
</template>
