<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — UnitDetail (one occupation: ENOC = ISCO-08 unit group)
//  Location: resources/js/Components/Backbone/UnitDetail.vue
//
//      <UnitDetail v-bind="page" :routes="{ esco: 'app.occupations.esco', skill: 'app.occupations.skill' }" />
//
//  Shared by the Super Admin page and the partner Occupations page:
//  the classification path, the three standards side by side, the
//  ENOC Arabic task description, the Egypt labour market panel
//  (current edition), the skills most of its ESCO jobs need (Step 5),
//  every ESCO occupation in the group (indented =
//  more specific) and the ILO definition. The page around it adds the
//  layout and the back link.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import MarketPanel from '@/Components/Backbone/MarketPanel.vue';
import StandardCards from '@/Components/Backbone/StandardCards.vue';
import UnitSkills from '@/Components/Skills/UnitSkills.vue';
import { useTranslations } from '@/composables/useTranslations';
import { titles } from '@/Components/Backbone/occ';

const props = defineProps({
    unit: { type: Object, required: true },
    lineage: { type: Array, required: true },
    enoc: { type: Object, default: null },
    market: { type: Object, default: null },
    esco: { type: Array, required: true },
    skills: { type: Object, default: null },
    routes: { type: Object, required: true },   // { esco, skill } route names
});

const { t, locale } = useTranslations();
const title = computed(() => titles(props.unit, locale.value));
const iscoTitle = computed(() => (locale.value === 'ar' ? props.unit.title_ar : props.unit.title_en));
const sections = computed(() => [
    ['bb.tasks', props.unit.tasks_en],
    ['bb.included', props.unit.included_en],
    ['bb.excluded', props.unit.excluded_en],
    ['bb.notes', props.unit.notes_en],
].filter(([, text]) => text));
</script>

<template>
    <div>

        <div class="hero">
            <div class="top">
                <div>
                    <div class="page-eyebrow">{{ t('bb.level_4') }} · <span class="ltr">{{ unit.code }}</span></div>
                    <h1>{{ enoc && locale === 'ar' ? enoc.title_ar : title[0] }}</h1>
                    <div class="alt">{{ enoc && locale === 'ar' ? unit.title_ar : title[1] }}</div>
                    <div class="meta">
                        <span v-if="!enoc" class="tag plain">{{ t('bb.not_in_enoc') }}</span>
                        <span v-if="esco.length" class="tag purple">{{ t('bb.group_count', { n: esco.length }) }}</span>
                        <span v-else class="tag orange">{{ t('bb.group_only') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid-2 mt-4" style="align-items:start">
            <div class="panel">
                <h3>{{ t('bb.same_record') }}</h3>
                <div class="sub">{{ t('bb.same_record_sub') }}</div>
                <StandardCards
                    :enoc="enoc ? { code: enoc.code, title: enoc.title_ar } : null"
                    :isco="{ code: unit.code, title: iscoTitle }"
                    :esco="esco.length ? { code: `${unit.code}.*`, title: t('bb.group_count', { n: esco.length }) } : null"
                    :esco-empty="t('bb.group_only')" />
            </div>
            <div class="panel">
                <h3>{{ t('bb.ladder') }}</h3>
                <ul class="ladder">
                    <li v-for="(g, i) in lineage" :key="g.code" :style="{ '--i': i }" :class="{ on: g.code === unit.code }">
                        <span class="lv">{{ t(`bb.level_${g.level}`) }}</span>
                        <code>{{ g.code }}</code>
                        <span>{{ locale === 'ar' ? g.title_ar : g.title_en }}</span>
                    </li>
                    <li v-if="enoc" :style="{ '--i': lineage.length - 1 }">
                        <span class="lv">{{ t('bb.enoc_label') }}</span>
                        <code>{{ enoc.code }}</code>
                        <span>{{ enoc.title_ar }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="enoc?.description_ar" class="panel mt-4" dir="rtl" lang="ar">
            <h3>{{ t('bb.enoc_desc') }}</h3>
            <div v-if="enoc.major_title_ar" class="sub">{{ t('bb.enoc_major') }}: {{ enoc.major_title_ar }}</div>
            <p class="prose">{{ enoc.description_ar }}</p>
        </div>

        <MarketPanel v-if="enoc" class="mt-4" :market="market" />

        <div class="panel mt-4">
            <h3>{{ t('bb.esco_in_unit') }}</h3>
            <template v-if="esco.length">
                <div class="sub">{{ t('bb.esco_in_unit_sub', { n: esco.length }) }}</div>
                <Link v-for="o in esco" :key="o.id" class="orow" :href="route(routes.esco, o.id)"
                      :style="{ paddingInlineStart: `${10 + (o.depth - 1) * 22}px` }">
                    <code>{{ o.code }}</code>
                    <span class="ot"><b>{{ titles(o, locale)[0] }}</b><span>{{ titles(o, locale)[1] }}</span></span>
                    <span v-if="o.regulated" class="tag orange">{{ t('bb.regulated') }}</span>
                </Link>
            </template>
            <div v-else class="alert warning mt-2"><AppIcon name="info" :size="16" /><span>{{ t('bb.esco_none') }}</span></div>
        </div>

        <UnitSkills class="mt-4" :skills="skills" :route-name="routes.skill" />

        <div class="panel mt-4" dir="ltr" lang="en">
            <h3>{{ t('bb.isco_def') }}</h3>
            <p class="prose">{{ unit.definition_en }}</p>
            <template v-for="[label, text] in sections" :key="label">
                <div class="kv-label mt-3"><b>{{ t(label) }}</b></div>
                <p class="prose">{{ text }}</p>
            </template>
        </div>
    </div>
</template>

<style scoped>
.prose { white-space: pre-line; font-size: 13.5px; line-height: 1.75; margin: 6px 0 0; }
.orow { grid-template-columns: 120px minmax(0, 1fr) auto; }
</style>
