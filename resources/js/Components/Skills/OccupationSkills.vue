<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — OccupationSkills (the skills of one ESCO job)
//  Location: resources/js/Components/Skills/OccupationSkills.vue
//
//      <OccupationSkills :skills="page.skills" route-name="app.occupations.skill" />
//
//  Two tabs, Essential and Optional (ESCO's own words: essential =
//  normally needed for the job; optional = needed in some workplaces).
//  Inside each: skills, then knowledge. `skills` is null when the ESCO
//  skills have not been imported yet.
// ══════════════════════════════════════════════════════════════════

import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import SkillTags from '@/Components/Skills/SkillTags.vue';
import { useTranslations } from '@/composables/useTranslations';
import { byType } from '@/Components/Skills/skl';

const props = defineProps({
    skills: { type: Object, default: null },
    routeName: { type: String, required: true },
});

const { t } = useTranslations();
const tab = ref('essential');
const parts = computed(() => (props.skills ? byType(props.skills[tab.value]) : null));
const empty = computed(() => props.skills && !props.skills.essential.length && !props.skills.optional.length);
</script>

<template>
    <div class="panel">
        <h3><AppIcon name="sparkles" :size="16" />{{ t('sk.title') }}</h3>
        <div class="sub">{{ t('sk.occupation_sub') }}</div>

        <div v-if="!skills" class="alert info mt-2"><AppIcon name="info" :size="16" /><span>{{ t('sk.not_loaded') }}</span></div>
        <div v-else-if="empty" class="small mute mt-2">{{ t('sk.none_for_job') }}</div>
        <template v-else>
            <div class="tabs mt-2 mb-3" role="tablist">
                <button v-for="k in ['essential', 'optional']" :key="k" type="button" role="tab" class="tab" :aria-selected="tab === k" @click="tab = k">
                    {{ t(`sk.${k}`) }}<span class="cnt">{{ skills[k].length }}</span>
                </button>
            </div>
            <p class="small mute mt-0">{{ t(`sk.${tab}_help`) }}</p>
            <template v-for="kind in ['skill', 'knowledge']" :key="kind">
                <div v-if="parts[kind].length" class="mb-3">
                    <div class="kv-label mb-2"><b>{{ t(`sk.kind_${kind}`) }}</b> · <span class="ltr">{{ parts[kind].length }}</span></div>
                    <SkillTags :skills="parts[kind]" :route-name="routeName" />
                </div>
            </template>
            <div class="small mute">{{ t('sk.legend') }}</div>
        </template>
    </div>
</template>
