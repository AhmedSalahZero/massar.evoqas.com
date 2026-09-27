<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Matches (Step 13)
//  Location: resources/js/Pages/App/Matches/Index.vue
//  Route: GET /app/matches (matches.view) — App\MatchController@index
//  Scope: docs/SCOPE_MATCHES.md
//
//  All the workspace's matches in one list:
//  · counts per stage: Referred · Accepted · In progress · Hired / Completed · Stopped
//  · filters: stage, Job or Training, one job or training, the person's
//    governorate and occupation (any level, any standard), a search by
//    name, mobile or number
//  · "Needs follow-up": no change recorded for 14 days, so nobody is forgotten
//  · on each line: next stage, stop, back, restart, the timeline (matches.manage)
// ══════════════════════════════════════════════════════════════════

import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import MatchActions from '@/Components/Matches/MatchActions.vue';
import { usePermissions } from '@/composables/usePermissions';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';
import { occIn } from '@/Components/Beneficiaries/ben';
import { KIND_ICON } from '@/Components/Assessments/elig';
import { STAGE_BADGE, nextStage, stageShort, stageText, stopReasonText } from '@/Components/Matches/match';

const props = defineProps({
    list: { type: Object, required: true },
    counts: { type: Object, required: true },
    filters: { type: Object, required: true },
    occupation: { type: Object, default: null },
    opportunities: { type: Array, default: () => [] },
    governorates: { type: Array, default: () => [] },
    stop_reasons: { type: Object, default: () => ({}) },
    follow_days: { type: Number, default: 14 },
});
const { t, locale } = useTranslations();
const { can } = usePermissions();
const { prefs } = usePreferences();
const date = (d) => (d ? formatDate(d, locale.value) : '');

// ── Filters ───────────────────────────────────────────────────────
const f = reactive({ ...props.filters, follow: !!props.filters.follow });
const load = () => router.get(route('app.matches.index'),
    Object.fromEntries(Object.entries({ ...f, follow: f.follow ? 1 : '' }).filter(([, v]) => v !== '' && v !== null && v !== false)),
    { preserveState: true, preserveScroll: true, replace: true });
let timer = null;
const onSearch = () => { clearTimeout(timer); timer = setTimeout(load, 350); };
const setStage = (s) => { f.stage = f.stage === s ? '' : s; load(); };
const filtered = computed(() => f.stage || f.kind || f.opportunity || f.governorate || f.occ || f.q || f.follow);
const reset = () => { Object.assign(f, { stage: '', kind: '', opportunity: '', governorate: '', occ: '', q: '', follow: false }); chosenOcc.value = null; load(); };
const shownOpps = computed(() => props.opportunities.filter((o) => !f.kind || o.kind === f.kind));

// ── The person's occupation: any standard, any level ──────────────
const chosenOcc = ref(props.occupation);
const occQ = ref('');
const occResults = ref([]);
const occOpen = ref(false);
let otimer = null;
let seq = 0;
watch(occQ, (q) => {
    clearTimeout(otimer);
    if (!q.trim()) { occResults.value = []; return; }
    otimer = setTimeout(async () => {
        const mine = ++seq;
        try {
            const { data } = await window.axios.get(route('app.cv-bank.occupations'), { params: { q: q.trim() } });
            if (mine === seq) occResults.value = data;
        } catch { occResults.value = []; }
    }, 250);
});
const pickOcc = (o) => { chosenOcc.value = o; f.occ = o.value; occQ.value = ''; occOpen.value = false; load(); };
const clearOcc = () => { chosenOcc.value = null; f.occ = ''; load(); };
const closeLater = () => setTimeout(() => { occOpen.value = false; }, 200);

const occText = (block) => {
    if (!block) return t('bank.no_occupation');
    const s = occIn(block, prefs.standard, locale.value);
    return `${s.code} · ${s.title}`;
};
const acts = ref(null);
const STAT = [
    { k: 'referred', c: 'navy' }, { k: 'accepted', c: 'teal' }, { k: 'in_progress', c: 'orange' }, { k: 'done', c: 'green' }, { k: 'stopped', c: 'plain' },
];
</script>

<template>
    <AppLayout :title="t('nav.matches')">
        <div class="page-head">
            <div>
                <div class="page-eyebrow">{{ t('nav.opportunities_group') }}</div>
                <h1 class="page-title">{{ t('nav.matches') }}</h1>
                <div class="page-sub">{{ t('mt.page_sub') }}</div>
            </div>
            <div v-if="can('opportunities.view')" class="page-actions">
                <Link :href="route('app.jobs.index')" class="btn btn-line"><AppIcon name="briefcase" :size="15" />{{ t('nav.jobs') }}</Link>
                <Link :href="route('app.training.index')" class="btn btn-line"><AppIcon name="graduation" :size="15" />{{ t('nav.training') }}</Link>
            </div>
        </div>

        <!-- ── Counts per stage ─────────────────────────────────── -->
        <div class="stats">
            <button v-for="s in STAT" :key="s.k" type="button" class="stat-card sbtn" :class="[s.c, { on: f.stage === s.k }]" @click="setStage(s.k)">
                <div class="stat-label">{{ s.k === 'done' ? t('mt.s_done_both') : stageShort(s.k, t) }}</div>
                <div class="stat-value">{{ counts[s.k] }}</div>
                <div class="stat-foot">{{ t('mt.of_total', { n: counts.total }) }}</div>
            </button>
            <button type="button" class="stat-card sbtn orange" :class="{ on: f.follow }" @click="f.follow = !f.follow; load()">
                <div class="stat-label">{{ t('mt.follow') }}</div>
                <div class="stat-value">{{ counts.follow }}</div>
                <div class="stat-foot">{{ t('mt.follow_foot', { n: follow_days }) }}</div>
            </button>
        </div>

        <!-- ── Filters ──────────────────────────────────────────── -->
        <div class="toolbar mt-4 mb-4">
            <input v-model="f.q" type="search" class="inp grow" :placeholder="t('mt.search_ph')" @input="onSearch">
            <select v-model="f.kind" class="inp sel" :aria-label="t('mt.f_kind')" @change="f.opportunity = ''; load()">
                <option value="">{{ t('mt.any_kind') }}</option>
                <option value="job">{{ t('nav.jobs') }}</option>
                <option value="training">{{ t('nav.training') }}</option>
            </select>
            <select v-model="f.opportunity" class="inp sel wide" :aria-label="t('mt.f_opportunity')" @change="load">
                <option value="">{{ t('mt.any_opportunity') }}</option>
                <option v-for="o in shownOpps" :key="o.id" :value="o.id">{{ o.title }}<template v-if="o.status === 'closed'"> ({{ t('opp.status_closed') }})</template></option>
            </select>
            <select v-model="f.governorate" class="inp sel" :aria-label="t('opp.governorates')" @change="load">
                <option value="">{{ t('opp.any_governorate') }}</option>
                <option v-for="g in governorates" :key="g" :value="g">{{ t(`gov.${g}`) }}</option>
            </select>
            <div class="occf">
                <span v-if="chosenOcc" class="tag teal">{{ STANDARD_LABELS[chosenOcc.standard] }} {{ chosenOcc.code }} · {{ chosenOcc.title }}<span class="x" role="button" @click="clearOcc">×</span></span>
                <template v-else>
                    <input v-model="occQ" type="search" class="inp" :placeholder="t('mt.f_occ')" @focus="occOpen = true" @blur="closeLater">
                    <div v-if="occOpen && occResults.length" class="occlist">
                        <button v-for="o in occResults" :key="o.value" type="button" @mousedown.prevent="pickOcc(o)">
                            <span class="tag plain">{{ STANDARD_LABELS[o.standard] }}</span> <b><bdi dir="ltr">{{ o.code }}</bdi></b> {{ o.title }}
                        </button>
                    </div>
                </template>
            </div>
            <button v-if="filtered" type="button" class="btn btn-line sm" @click="reset">{{ t('opp.clear_filters') }}</button>
        </div>

        <EmptyState v-if="!list.data.length && !counts.total && !filtered" icon="link" :title="t('mt.empty_title')" :text="t('mt.empty')" />
        <EmptyState v-else-if="!list.data.length" icon="search" :title="t('mt.none_found')" :text="t('opp.none_found_text')" />

        <div v-else class="table-wrap compact">
            <table>
                <thead>
                    <tr>
                        <th>{{ t('elig.col_person') }}</th>
                        <th>{{ t('mt.col_opportunity') }}</th>
                        <th>{{ t('mt.col_stage') }}</th>
                        <th>{{ t('mt.col_changed') }}</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in list.data" :key="m.id">
                        <td>
                            <div v-if="m.person" class="name-cell">
                                <span class="avatar sm">{{ m.person.initials }}</span>
                                <div class="min0">
                                    <Link :href="route('app.beneficiaries.show', m.person.number) + '#matches'" class="strong">{{ m.person.name }}</Link>
                                    <div class="small mute truncate">
                                        <bdi dir="ltr">#{{ m.person.number }}</bdi> · {{ occText(m.person.occupation) }} · {{ t(`gov.${m.person.governorate}`) }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div v-if="m.opportunity" class="min0">
                                <AppIcon :name="KIND_ICON[m.kind]" :size="13" class="kicon" />
                                <Link v-if="can('opportunities.view')" :href="route(`app.${m.opportunity.section}.show`, m.opportunity.id)">{{ m.opportunity.title }}</Link>
                                <span v-else>{{ m.opportunity.title }}</span>
                                <div class="xsmall mute">
                                    {{ t(`opp.kind_${m.kind}`) }}<template v-if="m.opportunity.employer || m.opportunity.provider"> · {{ m.opportunity.employer || m.opportunity.provider }}</template>
                                    <template v-if="m.opportunity.status === 'closed'"> · {{ t('opp.status_closed') }}</template>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span v-if="m.status === 'stopped'" class="badge plain">{{ t('mt.s_stopped') }}</span>
                            <span v-else class="badge" :class="STAGE_BADGE[m.stage]">{{ stageText(m.kind, m.stage, t) }}</span>
                            <div v-if="m.status === 'stopped'" class="xsmall mute mt-1">{{ stopReasonText(m.kind, m.stop_reason, t) }}</div>
                            <div v-if="m.no_longer_eligible" class="xsmall c-danger mt-1"><AppIcon name="alert" :size="11" /> {{ t('mt.no_longer_eligible', { result: t(`elig.r_${m.result}`) }) }}</div>
                        </td>
                        <td class="small">
                            {{ date(m.stage_on) }}
                            <div v-if="m.follow_up" class="xsmall c-orange"><AppIcon name="alert" :size="11" /> {{ t('mt.no_change_days', { n: m.days }) }}</div>
                            <div v-else class="xsmall mute">{{ t('mt.referred_on', { date: date(m.referred_on) }) }}</div>
                        </td>
                        <td class="acts">
                            <template v-if="can('matches.manage')">
                                <template v-if="m.status === 'active'">
                                    <button v-if="nextStage(m.stage)" type="button" class="btn btn-teal sm" @click="acts.open('move', m, m.person?.name)">
                                        {{ t('mt.to_stage', { stage: stageShort(nextStage(m.stage), t, m.kind) }) }}
                                    </button>
                                    <button v-if="m.stage !== 'done'" type="button" class="btn btn-line sm" @click="acts.open('stop', m, m.person?.name)">{{ t('mt.stop') }}</button>
                                    <button v-if="m.stage !== 'referred'" type="button" class="linkbtn small" @click="acts.open('back', m, m.person?.name)">{{ t('mt.back') }}</button>
                                </template>
                                <button v-else-if="m.opportunity?.status === 'open'" type="button" class="btn btn-line sm" @click="acts.open('restart', m, m.person?.name)">{{ t('mt.restart') }}</button>
                            </template>
                            <button type="button" class="linkbtn small" @click="acts.open('timeline', m, m.person?.name)">{{ t('mt.timeline') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination v-if="list.data.length" class="mt-4" :paginator="list" />

        <MatchActions ref="acts" :stop-reasons="stop_reasons" />
    </AppLayout>
</template>

<style scoped>
.stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
.sbtn { text-align: start; cursor: pointer; font: inherit; }
.sbtn.on { box-shadow: var(--ms-ring); }
.stat-card.plain { --accent: var(--ms-text-muted); }
.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.toolbar .grow { flex: 1; min-width: 180px; max-width: 280px; }
.toolbar .sel { width: auto; min-width: 140px; }
.toolbar .wide { max-width: 260px; }
.occf { position: relative; min-width: 200px; }
.occlist { position: absolute; z-index: 20; inset-inline: 0; top: calc(100% + 4px); background: var(--ms-bg-card); border: 1px solid var(--ms-border);
    border-radius: 8px; max-height: 300px; overflow-y: auto; box-shadow: 0 8px 24px rgba(0, 0, 0, .12); min-width: 300px; }
.occlist button { display: block; width: 100%; text-align: start; padding: 8px 10px; border: 0; background: none; font-size: 12.5px; cursor: pointer; color: inherit; }
.occlist button:hover { background: var(--ms-bg-hover); }
.tag { white-space: normal; }
.min0 { min-width: 0; }
.kicon { vertical-align: -2px; margin-inline-end: 4px; color: var(--ms-text-muted); }
.acts { white-space: nowrap; }
.acts > * + * { margin-inline-start: 6px; }
</style>
