<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — UnitSkills (skills most needed in one 4-digit group)
//  Location: resources/js/Components/Skills/UnitSkills.vue
//
//      <UnitSkills :skills="page.skills" route-name="app.occupations.skill" />
//
//  For an ENOC occupation (= ISCO-08 unit group): the skills that the
//  most ESCO jobs in the group list as ESSENTIAL, most common first,
//  each with how many of the group's jobs need it. Groups with no ESCO
//  job show nothing (they are classified at group level only).
// ══════════════════════════════════════════════════════════════════

import AppIcon from '@/Components/AppIcon.vue';
import SkillTags from '@/Components/Skills/SkillTags.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    skills: { type: Object, default: null },
    routeName: { type: String, required: true },
});

const { t } = useTranslations();
</script>

<template>
    <div v-if="!skills || skills.jobs" class="panel">
        <h3><AppIcon name="sparkles" :size="16" />{{ t('sk.unit_title') }}</h3>
        <div v-if="!skills" class="alert info mt-2"><AppIcon name="info" :size="16" /><span>{{ t('sk.not_loaded') }}</span></div>
        <template v-else>
            <div class="sub">{{ t('sk.unit_sub', { n: skills.skills.length, total: skills.total, jobs: skills.jobs }) }}</div>
            <SkillTags :skills="skills.skills" :route-name="routeName" :sorted="false" show-jobs />
            <div class="small mute mt-3">{{ t('sk.unit_foot') }} {{ t('sk.legend') }}</div>
        </template>
    </div>
</template>
