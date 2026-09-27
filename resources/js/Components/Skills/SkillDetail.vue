<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — SkillDetail (one ESCO skill or knowledge item)
//  Location: resources/js/Components/Skills/SkillDetail.vue
//
//      <SkillDetail v-bind="page" :routes="{ esco: 'app.occupations.esco', skill: 'app.occupations.skill' }" />
//
//  Shared by the Super Admin page and the partner Occupations page:
//  the names and descriptions in both languages, where the skill sits
//  in the ESCO skills tree (a skill can sit in more than one place),
//  broader / more specific skills, related skills, and the ESCO jobs
//  that need it (essential / optional), with their ISCO-08 / ENOC code.
// ══════════════════════════════════════════════════════════════════

import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import SkillTags from '@/Components/Skills/SkillTags.vue';
import { useTranslations } from '@/composables/useTranslations';
import { titles } from '@/Components/Backbone/occ';
import { skillNames } from '@/Components/Skills/skl';

const props = defineProps({
    skill: { type: Object, required: true },
    paths: { type: Array, required: true },
    broader: { type: Array, required: true },
    narrower: { type: Array, required: true },
    needs: { type: Array, required: true },
    needed_by: { type: Array, required: true },
    occupations: { type: Object, required: true },
    routes: { type: Object, required: true },   // { esco, skill } route names
});

const { t, locale } = useTranslations();
const s = computed(() => props.skill);
const names = computed(() => skillNames(s.value, locale.value));
const tab = ref(props.occupations.essential.total ? 'essential' : 'optional');
const jobs = computed(() => props.occupations[tab.value]);
const groupName = (g) => (locale.value === 'ar' && g.title_ar ? g.title_ar : g.title_en);
</script>

<template>
    <div>
        <div class="hero">
            <div class="top">
                <div>
                    <div class="page-eyebrow">{{ t(s.type === 'knowledge' ? 'sk.kind_knowledge_one' : 'sk.kind_skill_one') }}</div>
                    <h1>{{ names[0] }}</h1>
                    <div class="alt">{{ names[1] }}</div>
                    <div class="meta">
                        <span v-if="s.reuse" class="tag" :class="s.reuse === 'transversal' ? 'green' : 'plain'" :title="t(`sk.reuse_help.${s.reuse}`)">{{ t(`sk.reuse.${s.reuse}`) }}</span>
                        <span v-if="!s.active" class="tag plain">{{ t('bb.inactive') }}</span>
                        <span v-if="s.version" class="tag plain code">ESCO v{{ s.version }}</span>
                    </div>
                </div>
                <div class="acts">
                    <a :href="s.uri" target="_blank" rel="noopener" class="btn btn-line sm"><AppIcon name="link" :size="14" />{{ t('bb.view_on_esco') }}</a>
                </div>
            </div>
        </div>

        <div class="grid-2 mt-4" style="align-items:start">
            <div class="panel" dir="rtl" lang="ar">
                <h3>{{ t('bb.description_ar') }}</h3>
                <p class="prose">{{ s.description_ar || '—' }}</p>
            </div>
            <div class="panel" dir="ltr" lang="en">
                <h3>{{ t('bb.description_en') }}</h3>
                <p class="prose">{{ s.description_en || '—' }}</p>
                <p v-if="s.scope_note_en" class="prose mute">{{ s.scope_note_en }}</p>
            </div>
        </div>

        <!-- ── Jobs that need it ─────────────────────────────────── -->
        <div class="panel mt-4">
            <h3><AppIcon name="briefcase" :size="16" />{{ t('sk.jobs_title') }}</h3>
            <div class="sub">{{ t('sk.jobs_sub') }}</div>
            <div class="tabs mt-2 mb-2" role="tablist">
                <button v-for="k in ['essential', 'optional']" :key="k" type="button" role="tab" class="tab" :aria-selected="tab === k" @click="tab = k">
                    {{ t(`sk.${k}`) }}<span class="cnt">{{ occupations[k].total }}</span>
                </button>
            </div>
            <div v-if="!jobs.total" class="small mute">{{ t('sk.jobs_none') }}</div>
            <Link v-for="o in jobs.rows" :key="o.id" class="orow" :href="route(routes.esco, o.id)">
                <code>{{ o.code }}</code>
                <span class="ot"><b>{{ titles(o, locale)[0] }}</b><span>{{ titles(o, locale)[1] }}</span></span>
                <span class="tag plain code">ISCO {{ o.isco_code }}</span>
            </Link>
            <div v-if="jobs.total > jobs.rows.length" class="small mute mt-2">{{ t('sk.jobs_more', { shown: jobs.rows.length, n: jobs.total }) }}</div>
        </div>

        <!-- ── Where it sits ─────────────────────────────────────── -->
        <div class="grid-2 mt-4" style="align-items:start">
            <div class="panel">
                <h3>{{ t('sk.where') }}</h3>
                <div class="sub">{{ t(paths.length > 1 ? 'sk.where_many' : 'sk.where_sub') }}</div>
                <ul v-for="(path, p) in paths" :key="p" class="ladder" :class="{ 'mt-3': p > 0 }">
                    <li v-for="(g, i) in path" :key="g.code" :style="{ '--i': i }" :class="{ on: i === path.length - 1 }">
                        <span class="lv">{{ i === 0 ? t('sk.pillar') : t('sk.group_level', { n: g.level }) }}</span><code>{{ g.code }}</code>
                        <span>{{ groupName(g) }}<span v-if="locale === 'ar' && !g.title_ar" class="mute xsmall"> · {{ t('sk.no_arabic') }}</span></span>
                    </li>
                </ul>
                <div v-if="!paths.length" class="small mute">—</div>
                <template v-if="broader.length">
                    <div class="kv-label mt-4 mb-2"><b>{{ t('sk.broader') }}</b></div>
                    <SkillTags :skills="broader" :route-name="routes.skill" />
                </template>
                <template v-if="narrower.length">
                    <div class="kv-label mt-4 mb-2"><b>{{ t('sk.narrower') }}</b> · <span class="ltr">{{ narrower.length }}</span></div>
                    <SkillTags :skills="narrower" :route-name="routes.skill" />
                </template>
            </div>
            <div class="panel">
                <h3>{{ t('sk.related') }}</h3>
                <template v-if="needs.length">
                    <div class="kv-label mt-2 mb-2"><b>{{ t('sk.needs') }}</b></div>
                    <SkillTags :skills="needs" :route-name="routes.skill" />
                </template>
                <template v-if="needed_by.length">
                    <div class="kv-label mt-4 mb-2"><b>{{ t('sk.needed_by') }}</b></div>
                    <SkillTags :skills="needed_by" :route-name="routes.skill" />
                </template>
                <div v-if="!needs.length && !needed_by.length" class="small mute mt-2">{{ t('sk.related_none') }}</div>

                <div class="kv-label mt-4 mb-2"><b>{{ t('sk.alt_names', { n: s.alt_en.length + s.alt_ar.length }) }}</b></div>
                <div v-if="s.alt_ar.length" class="tags mb-2" dir="rtl"><span v-for="a in s.alt_ar" :key="a" class="tag plain">{{ a }}</span></div>
                <div class="tags" dir="ltr"><span v-for="a in s.alt_en" :key="a" class="tag plain">{{ a }}</span></div>
                <div v-if="!s.alt_en.length && !s.alt_ar.length" class="small mute">—</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.prose { white-space: pre-line; font-size: 13.5px; line-height: 1.75; margin: 6px 0 0; }
.orow { grid-template-columns: 120px minmax(0, 1fr) auto; }
.ladder li { grid-template-columns: 110px 80px 1fr; }
</style>
