<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Beneficiary profile (partner workspace)
//  Location: resources/js/Pages/App/Beneficiaries/Show.vue
//  Route: GET /app/beneficiaries/{number} (app.beneficiaries.show)
//  Permission: beneficiaries.view
//
//  One person's record: personal and contact details, the occupation
//  in the three standards (and who chose it, when), the expected-
//  salary check, the Egypt labour market panel of the occupation,
//  work history, education, skills, languages, preferences, and the
//  history of who registered and changed the profile.
//  Steps 11–13: the Eligibility panel, the Matches panel (with the
//  suggestions), and eligibility decisions and match changes in the history.
//  Market figures flagged at import were removed by the server and
//  show as "Under review" (same panel as the Occupations page).
// ══════════════════════════════════════════════════════════════════

import { computed, reactive } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import MarketPanel from '@/Components/Backbone/MarketPanel.vue';
import SalaryCheck from '@/Components/Beneficiaries/SalaryCheck.vue';
import EligibilityPanel from '@/Components/Assessments/EligibilityPanel.vue';
import MatchesPanel from '@/Components/Matches/MatchesPanel.vue';
import { stageText, stopReasonText } from '@/Components/Matches/match';
import { RESULT_BADGE } from '@/Components/Assessments/elig';
import { usePermissions } from '@/composables/usePermissions';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';
import { experience, money, names, occIn } from '@/Components/Beneficiaries/ben';
import { STATUS_BADGE, sizeText } from '@/Components/Cv/cv';

const props = defineProps({
    beneficiary: { type: Object, required: true },
    occupation: { type: Object, default: null },
    market: { type: Object, default: null },
    salary: { type: Object, required: true },
    history: { type: Array, required: true },
    cvs: { type: Array, default: () => [] },
    can_download: { type: Boolean, default: false },
    eligibility: { type: Array, default: null },      // Step 11 (null: no permission to see it)
    opportunities: { type: Array, default: () => [] },   // Step 12: the open jobs and trainings
    matches: { type: Array, default: null },             // Step 13 (null: no permission to see them)
    suggested: { type: Array, default: () => [] },
    stop_reasons: { type: Object, default: () => ({}) },
});

const { t, locale } = useTranslations();
const { can } = usePermissions();
const { prefs, setStandard } = usePreferences();

const b = computed(() => props.beneficiary);
const name = computed(() => names(b.value, locale.value));
const occ = computed(() => occIn(props.occupation, prefs.standard, locale.value));
const three = computed(() => props.occupation ? ['enoc', 'isco', 'esco'].map((s) => ({ s, ...occIn(props.occupation, s, locale.value) })) : []);
const marketAvg = computed(() => props.market?.profile?.wage_avg ?? null);
const outlook = computed(() => props.market?.profile?.outlook_trend ?? null);
const when = (iso, time = false) => (iso ? formatDate(iso, locale.value, time) : '');
const monthLabel = (ym) => {
    if (!ym) return '';
    const [y, m] = ym.split('-').map(Number);
    return new Date(y, m - 1, 1).toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-GB', { month: 'short', year: 'numeric' });
};
// Which jobs show all their responsibilities (the first 4 are always shown).
const openDuties = reactive({});
const jobs = computed(() => [...b.value.work_history].sort((x, y) => (y.from || '').localeCompare(x.from || '')));

// ── History: turn stored values into words ───────────────────────
const LIST_FIELDS = ['education', 'work_history', 'skills', 'languages'];
function showValue(field, v) {
    if (v === null || v === undefined || v === '') return '—';
    switch (field) {
        case 'governorate': return t(`gov.${v}`);
        case 'gender': return t(`ben.gender_${v}`);
        case 'military_status': return t(`ben.mil_${v}`);
        case 'education_level': return t(`ben.edu_${v}`);
        case 'job_type': return t(`ben.job_${v}`);
        case 'expected_salary': return `${money(v, locale.value)} ${t('mk.egp')}`;
        case 'date_of_birth': return formatDate(v, locale.value);
        default: return String(v);
    }
}
const fieldLabel = (f) => t(f === 'occupation' ? 'ben.s_occupation' : `ben.f_${f}`);
</script>

<template>
    <AppLayout :title="name[0]">
        <Link :href="route('app.beneficiaries.index')" class="back"><AppIcon name="arrow-left" :size="14" />{{ t('ben.title') }}</Link>

        <!-- ── Hero ────────────────────────────────────────────── -->
        <div class="hero">
            <div class="top">
                <span class="avatar lg">{{ b.initials }}</span>
                <div>
                    <h1>{{ name[0] }}</h1>
                    <div v-if="name[1]" class="alt">{{ name[1] }}</div>
                    <div class="meta">
                        <span class="tag plain code"><bdi dir="ltr">#{{ b.number }}</bdi></span>
                        <span class="tag plain">{{ t(`ben.gender_${b.gender}`) }}<template v-if="b.age !== null"> · {{ t('ben.age', { n: b.age }) }}</template></span>
                        <span class="tag plain">{{ t(`gov.${b.governorate}`) }}<template v-if="b.city"> · {{ b.city }}</template></span>
                        <span v-if="occ" class="tag teal code">{{ STANDARD_LABELS[prefs.standard] }} {{ occ.code }}</span>
                        <span v-else class="tag orange">{{ t('ben.no_occupation_yet') }}</span>
                    </div>
                </div>
                <div class="acts">
                    <Link v-if="can('beneficiaries.edit')" :href="route('app.beneficiaries.edit', b.number)" class="btn btn-teal"><AppIcon name="edit" :size="15" />{{ t('ben.edit') }}</Link>
                </div>
            </div>
            <div class="hstats">
                <div><b class="c-teal">{{ experience(b.experience_months, t, locale) }}</b><span>{{ t('ben.stat_experience') }}</span></div>
                <div><b class="c-orange">{{ b.work_history.length }}</b><span>{{ t('ben.stat_jobs') }}</span></div>
                <div><b class="c-green">{{ b.expected_salary ? money(b.expected_salary, locale) : '—' }}</b><span>{{ t('ben.stat_expected') }}</span></div>
                <div>
                    <b class="c-purple">{{ marketAvg !== null ? money(marketAvg, locale) : '—' }}</b>
                    <span>{{ t('ben.stat_market') }}<template v-if="outlook"> · {{ t(`mk.trend.${outlook}`) }}</template></span>
                </div>
            </div>
        </div>

        <!-- ── Occupation · contact ────────────────────────────── -->
        <div class="grid-2 mt-4" style="align-items:start">
            <div class="panel acc acc-teal">
                <h3><AppIcon name="layers" :size="16" />{{ t('ben.s_occupation') }}</h3>
                <template v-if="occupation">
                    <div class="sub">{{ t('bb.same_record_sub') }}</div>
                    <div class="std3">
                        <button v-for="c in three" :key="c.s" type="button" :aria-pressed="prefs.standard === c.s" @click="setStandard(c.s)">
                            <span class="sl">{{ STANDARD_LABELS[c.s] }}</span>
                            <code>{{ c.code }}</code>
                            <span class="st">{{ c.title }}</span>
                            <span v-if="c.note" class="st mute xsmall">{{ t(c.note) }}</span>
                        </button>
                    </div>
                    <div class="small mute mt-3">
                        {{ t('ben.occ_chosen', { by: occupation.set_by || t('ben.someone'), date: when(occupation.set_at) }) }}
                        <template v-if="occupation.method"> · {{ t(`ben.method_${occupation.method}`) }}</template>
                        <template v-if="can('occupations.view')">
                            ·
                            <Link :href="occupation.esco ? route('app.occupations.esco', occupation.esco.id) : route('app.occupations.unit', occupation.unit_code)">{{ t('ben.occ_open') }}</Link>
                        </template>
                    </div>
                </template>
                <div v-else class="empty-row">
                    {{ t('ben.occ_missing') }}
                    <Link v-if="can('beneficiaries.edit')" :href="route('app.beneficiaries.edit', b.number)">{{ t('ben.occ_add') }}</Link>
                </div>
            </div>

            <div class="panel">
                <h3><AppIcon name="user" :size="16" />{{ t('ben.s_personal') }}</h3>
                <div class="kvs mt-3">
                    <div><span>{{ t('ben.f_phone') }}</span><b class="ltr">{{ b.phone || '—' }}</b></div>
                    <div><span>{{ t('ben.f_email') }}</span><b class="ltr">{{ b.email || '—' }}</b></div>
                    <div><span>{{ t('ben.f_date_of_birth') }}</span><b>{{ b.date_of_birth ? formatDate(b.date_of_birth, locale) : '—' }}</b></div>
                    <div v-if="b.gender === 'male'"><span>{{ t('ben.f_military_status') }}</span><b>{{ b.military_status ? t(`ben.mil_${b.military_status}`) : '—' }}</b></div>
                    <div><span>{{ t('ben.f_education_level') }}</span><b>{{ b.education_level ? t(`ben.edu_${b.education_level}`) : '—' }}</b></div>
                    <div><span>{{ t('ben.f_job_type') }}</span><b>{{ b.job_type ? t(`ben.job_${b.job_type}`) : '—' }}</b></div>
                </div>
            </div>
        </div>

        <!-- ── Eligibility (Step 11) ───────────────────────────── -->
        <EligibilityPanel v-if="eligibility" id="eligibility" class="mt-4" :number="b.number" :items="eligibility" :opportunities="opportunities" />

        <!-- ── Matches (Step 13) ───────────────────────────────── -->
        <MatchesPanel v-if="matches" id="matches" class="mt-4" :number="b.number" :name="name[0]" :items="matches" :suggested="suggested" :stop-reasons="stop_reasons" />

        <!-- ── Expected salary · market ────────────────────────── -->
        <SalaryCheck class="mt-4" :salary="salary" :market="market" />
        <MarketPanel v-if="occupation" class="mt-4" :market="market"
                     :inherited="occupation.esco && occupation.enoc ? { code: occupation.enoc.code, title: occupation.enoc.title } : null" />

        <!-- ── Work · education ───────────────────────────────── -->
        <div class="grid-2 mt-4" style="align-items:start">
            <div class="panel">
                <h3><AppIcon name="briefcase" :size="16" />{{ t('ben.s_work') }}</h3>
                <div v-if="!jobs.length" class="empty-row">{{ t('ben.no_jobs_yet') }}</div>
                <ul v-else class="jobs mt-3">
                    <li v-for="(j, i) in jobs" :key="i" :style="j.current ? '--c:var(--ms-green)' : ''">
                        <div>
                            <b>{{ j.title }}<template v-if="j.employer"> · {{ j.employer }}</template></b>
                            <span>{{ monthLabel(j.from) }} – {{ j.current ? t('ben.present') : monthLabel(j.to) }}<template v-if="j.location"> · {{ j.location }}</template><template v-if="j.governorate"> · {{ t(`gov.${j.governorate}`) }}</template><template v-if="j.country && j.country !== 'EG'"> · {{ t(`country.${j.country}`) }}</template></span>
                            <span v-if="j.sector_label" class="sectag"><AppIcon name="building" :size="11" /> {{ j.sector_label }}</span>
                            <template v-if="j.responsibilities?.length">
                                <ul class="duties">
                                    <li v-for="(d, k) in (openDuties[i] ? j.responsibilities : j.responsibilities.slice(0, 4))" :key="k">{{ d }}</li>
                                </ul>
                                <button v-if="j.responsibilities.length > 4" type="button" class="linkbtn" @click="openDuties[i] = !openDuties[i]">
                                    {{ openDuties[i] ? t('ben.duties_less') : t('ben.duties_all', { n: j.responsibilities.length }) }}
                                </button>
                            </template>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="panel">
                <h3><AppIcon name="graduation" :size="16" />{{ t('ben.s_education') }}</h3>
                <div v-if="!b.education.length" class="empty-row">{{ t('ben.no_education_yet') }}</div>
                <ul v-else class="jobs mt-3">
                    <li v-for="(e, i) in b.education" :key="i" style="--c:var(--ms-teal)">
                        <div>
                            <b>{{ e.qualification }}<template v-if="e.field"> · {{ e.field }}</template></b>
                            <span>{{ [e.institution, e.year].filter(Boolean).join(' · ') }}</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── Skills · languages ─────────────────────────────── -->
        <div class="panel mt-4">
            <h3><AppIcon name="sparkles" :size="16" />{{ t('ben.s_skills') }}</h3>
            <div class="grid-2">
                <div>
                    <div class="kv-label mb-2"><b>{{ t('ben.f_skills') }}</b></div>
                    <div v-if="b.skills.length" class="tags"><span v-for="s in b.skills" :key="s" class="tag plain">{{ s }}</span></div>
                    <div v-else class="small mute">—</div>
                </div>
                <div>
                    <div class="kv-label mb-2"><b>{{ t('ben.f_languages') }}</b></div>
                    <div v-if="b.languages.length" class="tags">
                        <span v-for="l in b.languages" :key="l.code" class="tag navy">{{ t(`ben.lang_${l.code}`) }} · {{ t(`ben.level_${l.level}`) }}</span>
                    </div>
                    <div v-else class="small mute">—</div>
                </div>
            </div>
        </div>

        <!-- ── Original CV files (Scope v2 §3 CV Bank) ─────────── -->
        <div class="panel mt-4">
            <h3><AppIcon name="file" :size="16" />{{ t('cv.files_title') }}</h3>
            <div v-if="!cvs.length" class="empty-row">{{ t('cv.files_none') }}</div>
            <div v-for="c in cvs" :key="c.uuid" class="frow">
                <span class="ft-ico" :class="{ doc: c.extension !== 'pdf' }">{{ c.extension.toUpperCase() }}</span>
                <div class="nm"><b><bdi>{{ c.file }}</bdi></b><span>{{ sizeText(c.size, locale) }} · {{ t('cv.file_by', { by: c.by || '—', date: when(c.at) }) }}</span></div>
                <span class="badge" style="justify-self:start" :class="STATUS_BADGE[c.status]">{{ t(`cv.st_${c.status}`) }}</span>
                <span v-if="can_download" class="flex gap-2">
                    <a v-if="c.extension === 'pdf'" :href="route('app.cv-files.show', c.uuid) + '?view=1'" target="_blank" rel="noopener" class="btn btn-line sm">{{ t('cv.view') }}</a>
                    <a :href="route('app.cv-files.show', c.uuid)" class="btn btn-line sm">{{ t('cv.download') }}</a>
                </span>
                <span v-else />
            </div>
        </div>

        <!-- ── History ─────────────────────────────────────────── -->
        <div class="panel mt-4">
            <h3><AppIcon name="activity" :size="16" />{{ t('ben.s_history') }}</h3>
            <div class="sub">{{ t('ben.history_sub') }}</div>
            <ul class="timeline mt-3">
                <li v-for="h in history" :key="h.id" :style="h.action === 'created' ? '--c:var(--ms-green)' : h.eligibility ? '--c:var(--ms-teal)' : h.match ? '--c:var(--ms-navy)' : ''">
                    <div>
                        <template v-if="h.match">
                            <b>{{ t(`mt.h_${h.match.action}`, { by: h.by || t('ben.someone'), title: h.match.title || '—' }) }}</b>
                            <span>{{ when(h.at, true) }}</span>
                            <div class="small">
                                <template v-if="h.match.action === 'stopped'">{{ stopReasonText(h.match.kind, h.match.reason, t) }}</template>
                                <template v-else-if="h.match.action === 'corrected'">{{ stageText(h.match.kind, h.match.from, t) }} → {{ stageText(h.match.kind, h.match.to, t) }}</template>
                                <template v-else-if="h.match.to">{{ stageText(h.match.kind, h.match.to, t) }}</template>
                                <span v-if="h.match.on" class="mute"> · {{ t('mt.happened', { date: when(h.match.on) }) }}</span>
                                <div v-if="h.match.skipped?.length" class="xsmall mute">{{ t('mt.skipped', { list: h.match.skipped.map((x) => stageText(h.match.kind, x, t)).join(', ') }) }}</div>
                                <div v-if="h.match.note" class="mute mt-1">“{{ h.match.note }}”</div>
                            </div>
                        </template>
                        <b v-else-if="h.eligibility">{{ t('elig.h_decided', { by: h.by || t('ben.someone'), program: h.eligibility.title || (locale === 'ar' ? (h.eligibility.program_ar || h.eligibility.program_en) : (h.eligibility.program_en || h.eligibility.program_ar)) || '—' }) }}</b>
                        <b v-else>{{ t(h.action === 'created' ? 'ben.h_created' : 'ben.h_updated', { by: h.by || t('ben.someone') }) }}</b>
                        <span v-if="!h.match">{{ when(h.at, true) }}</span>
                        <div v-if="h.from_cv" class="small mute"><AppIcon name="file" :size="12" /> {{ t('ben.h_from_cv', { file: h.from_cv }) }}</div>
                        <div v-if="h.from_pool" class="small mute"><AppIcon name="globe" :size="12" /> {{ t('ben.h_from_pool') }}</div>
                        <div v-if="h.eligibility" class="small">
                            <span class="badge" :class="RESULT_BADGE[h.eligibility.from]">{{ t(`elig.r_${h.eligibility.from}`) }}</span> →
                            <span class="badge" :class="RESULT_BADGE[h.eligibility.to]">{{ t(`elig.r_${h.eligibility.to}`) }}</span>
                            <template v-if="h.eligibility.decision === 'auto'"> ({{ t('elig.h_auto') }})</template>
                            <div class="mute mt-1">“{{ h.eligibility.reason }}”</div>
                        </div>
                        <ul v-if="h.changes" class="chg">
                            <li v-for="(c, field) in h.changes" :key="field">
                                <b>{{ fieldLabel(field) }}</b>:
                                <template v-if="LIST_FIELDS.includes(field)">{{ t('ben.h_changed') }}</template>
                                <template v-else><span class="old">{{ showValue(field, c.from) }}</span> → {{ showValue(field, c.to) }}</template>
                            </li>
                        </ul>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>

<style scoped>
.sectag { display: inline-flex !important; gap: 4px; align-items: center; font-size: 11.5px; color: var(--ms-teal) !important; }
.chg { list-style: none; padding: 0; margin: 6px 0 0; font-size: 12.5px; }
.chg li { display: block; padding: 0; }
.chg li::before, .chg li::after { display: none; }
.chg b { display: inline; font-size: 12.5px; }
.chg .old { color: var(--ms-text-muted); text-decoration: line-through; }
.jobs li > div, .timeline li > div { min-width: 0; }
.jobs ul.duties { margin: 6px 0 0; padding-inline-start: 18px; font-size: 12.5px; color: var(--ms-text-secondary, inherit); }
.jobs ul.duties li { display: list-item; padding: 0 0 2px; }
.jobs ul.duties li::before, .jobs ul.duties li::after { display: none; }
.jobs .linkbtn { background: none; border: 0; padding: 2px 0; font-size: 12px; color: var(--ms-navy); cursor: pointer; }
</style>
