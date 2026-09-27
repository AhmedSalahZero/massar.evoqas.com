<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Occupation Backbone (Super Admin)
//  Location: resources/js/Pages/Admin/Backbone/Index.vue
//  Route: GET /admin/backbone (admin.backbone.index) → Admin\BackboneController@index
//  Permission: platform.backbone
//
//  What is loaded (counts per standard), the data checks found during
//  import, a browser + search that follows the Standard switch in the
//  top bar (ENOC list · ISCO-08 tree · ESCO list), the ESCO skills
//  (Step 5: counts, data checks, a skill search, the skills import
//  history), the Egypt labour market editions (coverage, findings,
//  which one is in use, and a "Use this edition" button), and the
//  import history. The figures change only via `backbone:import`,
//  `skills:import` and `market:import`; the one action here is
//  choosing which labour market edition is shown everywhere.
// ══════════════════════════════════════════════════════════════════

import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import OccupationBrowser from '@/Components/Backbone/OccupationBrowser.vue';
import SkillTags from '@/Components/Skills/SkillTags.vue';
import { skillNames } from '@/Components/Skills/skl';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { editionName, editionPeriod } from '@/Components/Backbone/occ';

const props = defineProps({
    standard: { type: String, required: true },
    filters: { type: Object, required: true },
    loaded: { type: Boolean, required: true },
    summary: { type: Object, default: null },
    majors: { type: Array, default: () => [] },
    browse: { type: Object, default: null },
    imports: { type: Array, required: true },
    skills: { type: Object, default: null },
    skillSearch: { type: Object, default: () => ({ q: '', results: [] }) },
});

const { t, locale } = useTranslations();

const n = (v) => Number(v ?? 0).toLocaleString(locale.value === 'ar' ? 'ar-EG' : 'en-US');
const mk = computed(() => props.summary?.market?.current ?? null);
const statusClass = { completed: 'green', failed: 'danger', unchanged: 'plain', running: 'orange' };

// ── Skill search (reloads only the results) ─────────────────────────
const skillQ = ref(props.skillSearch.q || '');
const searching = ref(false);
function searchSkills() {
    searching.value = true;
    router.reload({ data: { skill_q: skillQ.value.trim() || undefined }, only: ['skillSearch'], preserveScroll: true, onFinish: () => { searching.value = false; } });
}
function clearSkills() { skillQ.value = ''; searchSkills(); }
const sk = computed(() => props.skills);
const skc = computed(() => props.skills?.last?.checks ?? {});
// "Matched: …" only when the matching name is not already on screen.
const skillMatch = (row) => {
    if (!row.match) return '';
    const shown = skillNames(row, locale.value).join(' ').toLowerCase();
    return shown.includes(row.match.label.toLowerCase()) ? '' : row.match.label;
};

// ── "Use this edition" ─────────────────────────────────────────────
const toUse = ref(null);
const busy = ref(false);
const flaggedTotal = (e) => Object.values(e.flagged || {}).reduce((a, b) => a + b, 0);
function confirmUse() {
    busy.value = true;
    router.patch(route('admin.backbone.editions.use', toUse.value.id), {}, {
        preserveScroll: true, onFinish: () => { busy.value = false; toUse.value = null; },
    });
}
</script>

<template>
    <AdminLayout :title="t('bb.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('bb.title') }}</h1>
                <div class="page-sub">{{ t('bb.sub') }}</div>
            </div>
            <div v-if="summary?.last" class="page-sub">
                {{ t('bb.last_import', { date: formatDate(summary.last.at, locale) }) }}
            </div>
        </div>

        <!-- ── Not loaded yet ─────────────────────────────────────── -->
        <EmptyState v-if="!loaded" icon="layers" :title="t('bb.not_loaded_title')" :text="t('bb.not_loaded')">
            <code class="tag code" style="font-size:13px;padding:6px 12px">php artisan backbone:import</code>
        </EmptyState>

        <template v-else>
            <!-- ── What is loaded ─────────────────────────────────── -->
            <div class="grid-4">
                <div class="stat-card teal">
                    <div class="stat-label">{{ t('bb.stat_enoc') }}</div>
                    <div class="stat-value">{{ n(summary.counts.enoc) }}</div>
                    <div class="stat-foot">{{ t('bb.stat_enoc_foot', { n: summary.counts.enoc_major }) }}</div>
                </div>
                <div class="stat-card navy">
                    <div class="stat-label">{{ t('bb.stat_isco') }}</div>
                    <div class="stat-value">{{ n(summary.counts.isco) }}</div>
                    <div class="stat-foot">{{ t('bb.stat_isco_foot', { a: summary.counts.isco_levels[1], b: summary.counts.isco_levels[2], c: summary.counts.isco_levels[3], d: summary.counts.isco_levels[4] }) }}</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-label">{{ t('bb.stat_esco') }}</div>
                    <div class="stat-value">{{ n(summary.counts.esco) }}</div>
                    <div class="stat-foot">{{ t('bb.stat_esco_foot') }}</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-label">{{ t('bb.stat_labels') }}</div>
                    <div class="stat-value">{{ n(summary.counts.labels) }}</div>
                    <div class="stat-foot">{{ t('bb.stat_labels_foot', { ar: n(summary.counts.labels_ar), en: n(summary.counts.labels_en) }) }}</div>
                </div>
            </div>

            <div class="src mt-2">
                <AppIcon name="book" :size="13" />
                <span>{{ t('bb.editions') }}: {{ Object.values(summary.editions || {}).join(' · ') }}</span>
            </div>

            <!-- ── Data checks ────────────────────────────────────── -->
            <div class="panel mt-4">
                <div class="panel-h">
                    <div><h3><AppIcon name="check-circle" :size="16" />{{ t('bb.checks') }}</h3><div class="sub mb-0">{{ t('bb.checks_sub') }}</div></div>
                </div>
                <div class="grid-2">
                    <div>
                        <div class="kv-label mb-2"><b>{{ t('bb.check_no_esco') }}</b> — {{ t('bb.check_no_esco_help') }}</div>
                        <div class="tags">
                            <Link v-for="u in summary.checks.units_without_esco" :key="u.code" class="tag orange" :href="route('admin.backbone.unit', u.code)" :title="locale === 'ar' ? u.title_ar : u.title_en">
                                <span class="ltr">{{ u.code }}</span> {{ locale === 'ar' ? u.title_ar : u.title_en }}
                            </Link>
                        </div>
                    </div>
                    <div>
                        <div class="kv-label mb-2"><b>{{ t('bb.check_no_enoc') }}</b> — {{ t('bb.check_no_enoc_help') }}</div>
                        <div class="tags">
                            <Link v-for="u in summary.checks.units_without_enoc" :key="u.code" class="tag plain" :href="route('admin.backbone.unit', u.code)">
                                <span class="ltr">{{ u.code }}</span> {{ locale === 'ar' ? u.title_ar : u.title_en }}
                            </Link>
                        </div>
                    </div>
                </div>
                <ul class="small mute mt-3 mb-0" style="padding-inline-start:18px;line-height:1.8">
                    <li v-if="summary.checks.enoc_without_esco !== null">{{ t('bb.check_enoc_no_esco', { n: summary.checks.enoc_without_esco }) }}</li>
                    <li v-if="summary.checks.esco_duplicates !== null">{{ t('bb.check_dupes', { n: summary.checks.esco_duplicates }) }}</li>
                    <li v-if="summary.checks.esco_with_arabic_alt !== null">{{ t('bb.check_ar_alt', { n: summary.checks.esco_with_arabic_alt }) }}</li>
                </ul>

                <!-- Egypt labour market: coverage and findings of the edition in use -->
                <template v-if="mk">
                    <hr class="rule mt-4 mb-3">
                    <div class="kv-label mb-2"><b>{{ t('mk.check_title', { id: mk.id }) }}</b> — {{ editionName(mk, locale) }} · {{ editionPeriod(mk, t) }}</div>
                    <p class="small mt-0 mb-2">{{ t('mk.coverage', { n: mk.counts.occupations, workers: mk.counts.workers, wages: mk.counts.wages, wf: mk.counts.wages_gender, edu: mk.counts.education, outlook: mk.counts.outlook, green: mk.counts.green }) }}</p>
                    <div v-if="mk.quality.regions_outside_cairo_identical" class="alert warning mb-2"><AppIcon name="alert" :size="15" /><span>{{ t('mk.regions_suspect') }}</span></div>
                    <div v-for="(items, kind) in mk.quality.flagged" :key="kind" class="mb-2">
                        <span class="small"><b>{{ t('mk.check_flagged') }} · {{ t(`mk.check_flag.${kind}`) }}:</b></span>
                        <div class="tags mt-1">
                            <Link v-for="o in items" :key="o.code" class="tag orange" :href="route('admin.backbone.unit', o.code)"><span class="ltr">{{ o.code }}</span> {{ o.title_ar }}</Link>
                        </div>
                    </div>
                    <p class="small mute mb-0">{{ mk.quality.wage_copies_disagree.length ? t('mk.check_wages_disagree', { codes: mk.quality.wage_copies_disagree.join(', ') }) : t('mk.check_wages_agree') }}</p>
                </template>
            </div>

            <!-- ── ESCO skills (Step 5) ───────────────────────────── -->
            <div class="section-label">{{ t('sk.section') }}</div>
            <div v-if="!sk?.loaded" class="alert info">
                <AppIcon name="info" :size="16" />
                <span>{{ t('sk.admin_not_loaded') }} <code class="tag code">php artisan skills:import</code></span>
            </div>
            <template v-else>
                <div class="grid-4">
                    <div class="stat-card teal">
                        <div class="stat-label">{{ t('sk.stat_skills') }}</div>
                        <div class="stat-value">{{ n(sk.counts.skills) }}</div>
                        <div class="stat-foot">{{ t('sk.stat_skills_foot', { s: n(sk.counts.skills - sk.counts.knowledge), k: n(sk.counts.knowledge) }) }}</div>
                    </div>
                    <div class="stat-card navy">
                        <div class="stat-label">{{ t('sk.stat_groups') }}</div>
                        <div class="stat-value">{{ n(sk.counts.groups) }}</div>
                        <div class="stat-foot">{{ t('sk.stat_groups_foot') }}</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-label">{{ t('sk.stat_links') }}</div>
                        <div class="stat-value">{{ n(sk.counts.links) }}</div>
                        <div class="stat-foot">{{ t('sk.stat_links_foot', { e: n(sk.counts.essential), o: n(sk.counts.links - sk.counts.essential) }) }}</div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-label">{{ t('sk.stat_labels') }}</div>
                        <div class="stat-value">{{ n(sk.counts.labels) }}</div>
                        <div class="stat-foot">{{ sk.last ? t('bb.last_import', { date: formatDate(sk.last.at, locale) }) : '' }}</div>
                    </div>
                </div>

                <div v-if="sk.last" class="panel mt-4">
                    <div class="panel-h">
                        <div><h3><AppIcon name="check-circle" :size="16" />{{ t('sk.checks') }}</h3><div class="sub mb-0">{{ t('bb.checks_sub') }} · {{ sk.last.edition }}</div></div>
                    </div>
                    <ul class="small mt-0 mb-0" style="padding-inline-start:18px;line-height:1.9">
                        <li>{{ t('sk.check_no_arabic_groups', { n: skc.groups_without_arabic ?? 0 }) }}</li>
                        <li>{{ t('sk.check_latin', { n: skc.skills_arabic_in_latin ?? 0 }) }}</li>
                        <li>{{ t('sk.check_ar_alt', { n: skc.skills_with_arabic_alt ?? 0 }) }}</li>
                        <li>{{ t('sk.check_dupes', { n: skc.skills_duplicate_rows_skipped ?? 0 }) }}</li>
                        <li>{{ t('sk.check_unused', { n: n(skc.skills_without_occupation) }) }}</li>
                        <li v-if="skc.skills_without_type?.length">{{ t('sk.check_no_type', { n: skc.skills_without_type.length }) }} <span class="mute" dir="ltr">({{ skc.skills_without_type.join(' · ') }})</span></li>
                        <li v-if="skc.related_self_links_skipped">{{ t('sk.check_self', { n: skc.related_self_links_skipped }) }}</li>
                        <li :class="skc.occupations_without_skills_count ? 'c-orange' : ''">
                            {{ skc.occupations_without_skills_count ? t('sk.check_jobs_without', { n: skc.occupations_without_skills_count, codes: (skc.occupations_without_skills || []).join(', ') }) : t('sk.check_jobs_all') }}
                        </li>
                    </ul>
                </div>

                <div class="panel mt-4">
                    <h3><AppIcon name="search" :size="16" />{{ t('sk.search_title') }}</h3>
                    <div class="sub">{{ t('sk.search_sub') }}</div>
                    <form class="filter-bar" @submit.prevent="searchSkills">
                        <div class="search grow">
                            <AppIcon name="search" :size="16" />
                            <input v-model="skillQ" type="search" :placeholder="t('sk.search_ph')" :aria-label="t('common.search')">
                            <button v-if="skillQ" type="button" class="btn btn-ghost sm" @click="clearSkills">{{ t('common.clear') }}</button>
                            <button type="submit" class="btn btn-teal sm" :disabled="searching">{{ t('common.search') }}</button>
                        </div>
                    </form>
                    <template v-if="skillSearch.q">
                        <div v-if="!skillSearch.results.length" class="small mute">{{ t('bb.no_results') }}</div>
                        <template v-else>
                            <Link v-for="r in skillSearch.results" :key="r.id" class="orow srow" :href="route('admin.backbone.skill', r.id)">
                                <span class="tag" :class="r.type === 'knowledge' ? 'teal' : 'plain'">{{ t(r.type === 'knowledge' ? 'sk.kind_knowledge_one' : 'sk.kind_skill_one') }}</span>
                                <span class="ot">
                                    <b>{{ skillNames(r, locale)[0] }}</b>
                                    <span v-if="skillNames(r, locale)[1]">{{ skillNames(r, locale)[1] }}</span>
                                    <span v-if="skillMatch(r)" class="snip" style="display:inline-block">{{ t('bb.matched') }}: <mark>{{ skillMatch(r) }}</mark></span>
                                </span>
                                <span v-if="r.reuse" class="tag plain">{{ t(`sk.reuse.${r.reuse}`) }}</span>
                            </Link>
                            <div v-if="skillSearch.results.length >= 40" class="small mute mt-2">{{ t('sk.search_more') }}</div>
                        </template>
                    </template>
                </div>

                <div class="section-label">{{ t('sk.imports') }}</div>
                <div class="table-wrap compact">
                    <table>
                        <thead><tr><th>#</th><th>{{ t('bb.col_when') }}</th><th>{{ t('bb.col_status') }}</th><th>{{ t('bb.col_result') }}</th><th>{{ t('bb.col_by') }}</th></tr></thead>
                        <tbody>
                            <tr v-for="imp in sk.imports" :key="imp.id">
                                <td class="num ltr">{{ imp.id }}</td>
                                <td>{{ formatDate(imp.started_at, locale) }} <span class="mute ltr">{{ imp.started_at?.slice(11, 16) }}</span></td>
                                <td><span class="badge" :class="statusClass[imp.status]">{{ t(`bb.status.${imp.status}`) }}</span></td>
                                <td>
                                    <span v-if="imp.status === 'completed' && imp.skills !== null">{{ t('sk.result', { s: n(imp.skills), l: n(imp.links) }) }}</span>
                                    <span v-else class="mute small">{{ imp.message }}</span>
                                </td>
                                <td class="mute">{{ imp.by || t('bb.via_console') }}<span v-if="imp.seconds !== null"> · {{ t('bb.seconds', { n: imp.seconds }) }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- ── Browse & search ────────────────────────────────── -->
            <div class="section-label">{{ t('bb.browse') }}</div>

            <OccupationBrowser :standard="standard" :filters="filters" :majors="majors" :browse="browse"
                               :routes="{ index: 'admin.backbone.index', unit: 'admin.backbone.unit', esco: 'admin.backbone.esco' }" />

            <!-- ── Labour market editions ─────────────────────────── -->
            <div class="section-label">{{ t('mk.editions') }}</div>
            <p class="page-sub mt-0 mb-3">{{ t('mk.editions_sub') }}</p>
            <div v-if="!summary.market.editions.length" class="alert info"><AppIcon name="info" :size="16" /><span>{{ t('mk.no_edition') }}</span></div>
            <div v-else class="table-wrap compact">
                <table>
                    <thead><tr><th>#</th><th>{{ t('mk.col_edition') }}</th><th>{{ t('mk.col_period') }}</th><th>{{ t('bb.col_status') }}</th><th>{{ t('mk.col_compare') }}</th><th>{{ t('mk.col_to_check') }}</th><th></th></tr></thead>
                    <tbody>
                        <tr v-for="e in summary.market.editions" :key="e.id">
                            <td class="num ltr">{{ e.id }}</td>
                            <td><b>{{ editionName(e, locale) }}</b><div class="mute xsmall">{{ formatDate(e.loaded_at, locale) }}</div></td>
                            <td class="small">{{ editionPeriod(e, t) }}</td>
                            <td>
                                <span v-if="e.status !== 'completed'" class="badge danger" :title="e.message">{{ t(`bb.status.${e.status}`) }}</span>
                                <span v-else-if="e.is_current" class="badge green">{{ t('mk.in_use') }}</span>
                                <span v-else class="badge plain">{{ t('mk.not_in_use') }}</span>
                                <div v-if="e.is_current && e.made_current_at" class="mute xsmall mt-1">
                                    {{ t('mk.in_use_since', { date: formatDate(e.made_current_at, locale) }) }}<span v-if="e.made_current_by"> · {{ e.made_current_by }}</span>
                                </div>
                            </td>
                            <td class="small mute">{{ e.comparison ? t('mk.compare', { added: e.comparison.added, dropped: e.comparison.dropped, big: e.comparison.big_changes }) : t('mk.first') }}</td>
                            <td class="small">
                                <template v-if="e.status === 'completed'">
                                    <div v-for="(count, kind) in e.flagged" :key="kind" class="c-orange">{{ t(`mk.check_flag.${kind}`) }}: <span class="ltr">{{ count }}</span></div>
                                    <div v-if="e.regions_suspect" class="c-orange">{{ t('mk.check_regions') }}</div>
                                    <span v-if="!flaggedTotal(e) && !e.regions_suspect" class="c-green">{{ t('mk.nothing_to_check') }}</span>
                                </template>
                                <span v-else class="mute">—</span>
                            </td>
                            <td class="actions">
                                <button v-if="e.status === 'completed' && !e.is_current" type="button" class="btn btn-primary sm" @click="toUse = e">
                                    {{ t('mk.use_edition') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Import history ─────────────────────────────────── -->
            <div class="section-label">{{ t('bb.imports') }}</div>
            <p class="page-sub mt-0 mb-3">{{ t('bb.imports_sub') }}</p>
            <div class="table-wrap compact">
                <table>
                    <thead><tr><th>#</th><th>{{ t('bb.col_when') }}</th><th>{{ t('bb.col_status') }}</th><th>{{ t('bb.col_result') }}</th><th>{{ t('bb.col_by') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="imp in imports" :key="imp.id">
                            <td class="num ltr">{{ imp.id }}</td>
                            <td>{{ formatDate(imp.started_at, locale) }} <span class="mute ltr">{{ imp.started_at?.slice(11, 16) }}</span></td>
                            <td><span class="badge" :class="statusClass[imp.status]">{{ t(`bb.status.${imp.status}`) }}</span></td>
                            <td>
                                <span v-if="imp.status === 'completed' && imp.counts">{{ t('bb.result', { enoc: imp.counts.enoc_occupations, units: imp.counts.isco_unit, esco: n(imp.counts.esco_occupations), labels: n(imp.counts.labels) }) }}</span>
                                <span v-else class="mute small">{{ imp.message }}</span>
                            </td>
                            <td class="mute">{{ imp.by || t('bb.via_console') }}<span v-if="imp.seconds !== null"> · {{ t('bb.seconds', { n: imp.seconds }) }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <ConfirmDialog :show="!!toUse" :processing="busy" :title="t('mk.use_title', { id: toUse?.id })"
                       :message="toUse ? t('mk.use_message', { id: toUse.id, current: mk?.id ?? '—' }) + (flaggedTotal(toUse) ? ' ' + t('mk.use_flagged', { n: flaggedTotal(toUse) }) : '') : ''"
                       :confirm-label="t('mk.use_edition')" @confirm="confirmUse" @close="toUse = null" />
    </AdminLayout>
</template>

<style scoped>
.rule { border: 0; border-top: 1px solid var(--ms-border); }
.srow { grid-template-columns: 110px minmax(0, 1fr) auto; }
</style>
