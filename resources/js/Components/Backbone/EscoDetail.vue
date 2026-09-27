<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — EscoDetail (one detailed ESCO occupation)
//  Location: resources/js/Components/Backbone/EscoDetail.vue
//
//      <EscoDetail v-bind="page" :routes="{ esco: 'app.occupations.esco', skill: 'app.occupations.skill' }" />
//
//  Shared by the Super Admin page and the partner Occupations page:
//  how the job reads in each standard (ENOC and ISCO-08 through its
//  unit group — "switching up" is always possible — and ESCO as
//  itself), Arabic masculine / feminine titles, alternative titles,
//  descriptions, broader and more specific ESCO occupations, and the
//  Egypt labour market figures of its ENOC occupation (labelled), and
//  its ESCO skills and knowledge, essential and optional (Step 5).
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import MarketPanel from '@/Components/Backbone/MarketPanel.vue';
import StandardCards from '@/Components/Backbone/StandardCards.vue';
import OccupationSkills from '@/Components/Skills/OccupationSkills.vue';
import { useTranslations } from '@/composables/useTranslations';
import { cap, titles } from '@/Components/Backbone/occ';

const props = defineProps({
    occupation: { type: Object, required: true },
    ancestors: { type: Array, required: true },
    children: { type: Array, required: true },
    lineage: { type: Array, required: true },
    unit: { type: Object, required: true },
    enoc: { type: Object, default: null },
    market: { type: Object, default: null },
    skills: { type: Object, default: null },
    routes: { type: Object, required: true },   // { esco, skill } route names
});

const { t, locale } = useTranslations();
const o = computed(() => props.occupation);
const main = computed(() => titles({ title_en: o.value.title_en, title_ar: o.value.title_ar_m, title_ar_f: o.value.title_ar_f }, locale.value));
const shortTitle = (row) => (locale.value === 'ar' ? (row.title_ar || cap(row.title_en)) : cap(row.title_en));
</script>

<template>
    <div>
        <div class="hero">
            <div class="top">
                <div>
                    <div class="page-eyebrow">{{ t('bb.level_esco') }} · <span class="ltr">{{ o.code }}</span></div>
                    <h1>{{ main[0] }}</h1>
                    <div class="alt">{{ main[1] }}</div>
                    <div class="meta">
                        <span v-if="o.regulated" class="tag orange">{{ t('bb.regulated') }}</span>
                        <span v-if="!o.active" class="tag plain">{{ t('bb.inactive') }}</span>
                        <span v-if="o.version" class="tag plain code">ESCO v{{ o.version }}</span>
                    </div>
                </div>
                <div class="acts">
                    <a :href="o.uri" target="_blank" rel="noopener" class="btn btn-line sm"><AppIcon name="link" :size="14" />{{ t('bb.view_on_esco') }}</a>
                </div>
            </div>
        </div>

        <div class="grid-2 mt-4" style="align-items:start">
            <div class="panel">
                <h3>{{ t('bb.same_record') }}</h3>
                <div class="sub">{{ t('bb.same_record_sub') }}</div>
                <StandardCards
                    :enoc="enoc ? { code: enoc.code, title: enoc.title_ar } : null"
                    :isco="{ code: unit.code, title: locale === 'ar' ? unit.title_ar : unit.title_en }"
                    :esco="{ code: o.code, title: main[0] }" />
            </div>
            <div class="panel">
                <h3>{{ t('bb.ladder') }}</h3>
                <ul class="ladder">
                    <li v-for="(g, i) in lineage" :key="g.code" :style="{ '--i': i }">
                        <span class="lv">{{ t(`bb.level_${g.level}`) }}</span><code>{{ g.code }}</code>
                        <span>{{ locale === 'ar' ? g.title_ar : g.title_en }}</span>
                    </li>
                    <li v-for="(a, i) in ancestors" :key="a.id" :style="{ '--i': lineage.length + i }">
                        <span class="lv">{{ t('bb.level_esco') }}</span><code>{{ a.code }}</code>
                        <Link :href="route(routes.esco, a.id)">{{ shortTitle(a) }}</Link>
                    </li>
                    <li class="on" :style="{ '--i': lineage.length + ancestors.length }">
                        <span class="lv">{{ t('bb.level_esco') }}</span><code>{{ o.code }}</code><span>{{ main[0] }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="grid-2 mt-4" style="align-items:start">
            <div class="panel" dir="rtl" lang="ar">
                <h3>{{ t('bb.description_ar') }}</h3>
                <p class="prose">{{ o.description_ar || '—' }}</p>
            </div>
            <div class="panel" dir="ltr" lang="en">
                <h3>{{ t('bb.description_en') }}</h3>
                <p class="prose">{{ o.description_en || '—' }}</p>
                <p v-if="o.scope_note_en" class="prose mute">{{ o.scope_note_en }}</p>
            </div>
        </div>

        <OccupationSkills class="mt-4" :skills="skills" :route-name="routes.skill" />

        <MarketPanel v-if="enoc" class="mt-4" :market="market" :inherited="{ code: enoc.code, title: enoc.title_ar }" />

        <div class="panel mt-4">
            <h3>{{ t('bb.alt_titles') }}</h3>
            <div class="kv-label mt-3 mb-2"><b>{{ t('bb.alt_ar', { n: o.alt_ar.length }) }}</b></div>
            <div v-if="o.alt_ar.length" class="tags" dir="rtl"><span v-for="a in o.alt_ar" :key="a" class="tag plain">{{ a }}</span></div>
            <div v-else class="small mute">{{ t('bb.alt_ar_none') }}</div>
            <div class="kv-label mt-4 mb-2"><b>{{ t('bb.alt_en', { n: o.alt_en.length }) }}</b></div>
            <div class="tags" dir="ltr"><span v-for="a in o.alt_en" :key="a" class="tag plain">{{ a }}</span></div>
        </div>

        <div v-if="children.length" class="panel mt-4">
            <h3>{{ t('bb.children') }}</h3>
            <Link v-for="c in children" :key="c.id" class="orow" :href="route(routes.esco, c.id)">
                <code>{{ c.code }}</code>
                <span class="ot"><b>{{ titles(c, locale)[0] }}</b><span>{{ titles(c, locale)[1] }}</span></span>
                <span></span>
            </Link>
        </div>
    </div>
</template>

<style scoped>
.prose { white-space: pre-line; font-size: 13.5px; line-height: 1.75; margin: 6px 0 0; }
.orow { grid-template-columns: 120px minmax(0, 1fr) auto; }
.ladder li { grid-template-columns: 130px 110px 1fr; }
</style>
