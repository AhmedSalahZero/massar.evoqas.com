<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Partner Dashboard (Step 15, exactly as the agreed demo)
//  Location: resources/js/Pages/App/Dashboard.vue
//  Route: GET /app/dashboard?range=m1|m3|m12|all (app.dashboard) → App\DashboardController
//  The agreed demo: docs/dashboard-demo.html · the numbers: App\Services\Dashboard\PartnerDashboard
//
//  · five numbers at the top, with small monthly lines
//  · the placement journey (registered → placed) and "Needs your attention"
//  · your people: new people and placements by month (chart or table),
//    gender, education, age, governorates, industry
//  · your people and Egypt's labour market: the top occupations next to
//    the real market wage and outlook to 2030, expected salary vs market
//    wage, the ESCO skills most lacking among Eligible people
//  · Jobs & Training, matches that need a follow-up, team activity
//  The date range reloads the numbers; "Download PDF" prints the page
//  (choose "Save as PDF"). Parts a person may not see are not sent.
// ══════════════════════════════════════════════════════════════════

import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DashTooltip from '@/Components/Dashboard/DashTooltip.vue';
import { columns, dumbbell, hbars, lines, spark, stackedColumns } from '@/Components/Dashboard/charts';
import '@/Components/Dashboard/dash.css';
import { usePermissions } from '@/composables/usePermissions';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';
import { occIn } from '@/Components/Beneficiaries/ben';
import { sectionOf } from '@/Components/Assessments/elig';
import { stageText } from '@/Components/Matches/match';
import { formatDate } from '@/Utils/date';

const props = defineProps({
    team: { type: Object, required: true },
    subscription: { type: Object, required: true },
    beneficiaries: { type: Object, default: null },
    pipeline: { type: Object, default: null },
    range: { type: String, default: 'm12' },
    dash: { type: Object, required: true },
});
const { t, locale } = useTranslations();
const { user } = usePermissions();
const { prefs } = usePreferences();
const D = computed(() => props.dash);

const nf = (v, d = 0) => (v === null || v === undefined ? '—' : new Intl.NumberFormat(locale.value === 'ar' ? 'ar-EG' : 'en-US', { maximumFractionDigits: d, minimumFractionDigits: d }).format(v));
const monthName = (ym, long = false) => new Date(`${ym}-01T00:00:00`).toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-GB', { month: long ? 'long' : 'short', year: long ? 'numeric' : undefined });
const dateTxt = (d) => (d ? formatDate(d, locale.value) : '—');
const pct = (a, b) => (b ? Math.round((a / b) * 100) : 0);

const greeting = computed(() => {
    const h = new Date().getHours();
    const key = h < 12 ? 'dash.greeting_morning' : h < 18 ? 'dash.greeting_afternoon' : 'dash.greeting_evening';
    return t(key, { name: (user.value?.name ?? '').split(' ')[0] });
});
const workspace = computed(() => (locale.value === 'ar' && user.value?.company?.name_ar ? user.value.company.name_ar : user.value?.company?.name));
const RANGES = ['m1', 'm3', 'm12', 'all'];
const printPage = () => window.print();
const loading = ref(false);
const setRange = (r) => router.get(route('app.dashboard'), { range: r }, {
    preserveScroll: true, preserveState: true, only: ['dash', 'range'],
    onStart: () => { loading.value = true; }, onFinish: () => { loading.value = false; },
});

// ── Charts (the demo's own drawings) ──────────────────────────────
const K = computed(() => D.value.kpis);
const STAGES = ['registered', 'assessed', 'eligible', 'matched', 'placed'];
const SOURCES = ['intake', 'cv_upload', 'pool', 'manual'];
const C4 = ['var(--s1)', 'var(--s2)', 'var(--s3)', 'var(--s4)'];
const srcNames = computed(() => SOURCES.map((s) => t(`db.src_${s}`)));
const tables = reactive({ reg: false, plc: false });
const regChart = computed(() => (D.value.months_new ? stackedColumns({ months: D.value.months, series: D.value.months_new, keys: SOURCES, colors: C4, names: srcNames.value, monthName, nf, totalWord: t('db.total') }) : ''));
const plcSeries = computed(() => ({ hired: D.value.months_placed.job, completed: D.value.months_placed.training }));
const plcChart = computed(() => lines({ months: D.value.months, series: plcSeries.value, keys: ['hired', 'completed'], colors: ['var(--s1)', 'var(--s3)'], names: [t('db.hired'), t('db.completed')], monthName, nf }));
const P = computed(() => D.value.people);
const AGE = ['lt20', '20_24', '25_29', '30_34', '35_44', '45p'];
const ageChart = computed(() => (P.value ? columns({ items: AGE.map((k) => [t(`db.age_${k}`), P.value.age[k] || 0]), color: 'var(--s1)', nf, title: t('db.age'), word: t('db.people') }) : ''));
const otherLabel = (r) => t('db.other_n', { n: nf(r.more) });
const eduBars = computed(() => (P.value ? hbars({ rows: P.value.education.map((r) => [r.key === '_other' ? otherLabel(r) : r.key === '_none' ? t('db.not_given') : t(`ben.edu_${r.key}`), r.n]), color: 'var(--s1)', nf, word: t('db.people') }) : ''));
const govBars = computed(() => (P.value ? hbars({ rows: P.value.governorate.map((r) => [r.key === '_other' ? otherLabel(r) : t(`gov.${r.key}`), r.n]), color: 'var(--s1)', nf, word: t('db.people') }) : ''));
const indBars = computed(() => (P.value ? hbars({ rows: P.value.industry.map((r) => [r.key === '_none' ? t('db.no_work') : r.key === '_other' ? otherLabel(r) : ((locale.value === 'ar' ? r.name_ar : r.name_en) || r.key), r.n]), color: 'var(--s1)', nf, word: t('db.people') }) : ''));
const gender = computed(() => (P.value ? P.value.gender : { female: 0, male: 0 }));
const gTotal = computed(() => gender.value.female + gender.value.male);

// Market
const occName = (row) => {
    const s = occIn(row.block, prefs.standard, locale.value);
    return { std: STANDARD_LABELS[prefs.standard] && !s.note ? STANDARD_LABELS[prefs.standard] : 'ISCO-08', code: s.code, title: s.title };
};
const M = computed(() => D.value.market);
const topPeople = computed(() => Math.max(1, ...(M.value?.rows || []).map((r) => r.people)));
const TREND = { much_faster: 'green', faster: 'green', slower: 'orange', much_slower: 'orange', decline: 'danger' };
const salRows = computed(() => (M.value?.rows || []).filter((r) => r.expected && r.wage).slice(0, 8));
const salChart = computed(() => (salRows.value.length ? dumbbell({ rows: salRows.value.map((r) => { const o = occName(r); return [o.title, r.expected, r.wage]; }), nf, names: [t('db.sal_e'), t('db.sal_m')], gapWord: t('db.gap') }) : ''));
const skillBars = computed(() => (D.value.skills?.rows?.length ? hbars({ rows: D.value.skills.rows.map((r) => [locale.value === 'ar' ? (r.ar || r.en) : r.en, r.n]), color: 'var(--s2)', nf, word: t('db.people') }) : ''));

// Attention
const ATT = [
    { k: 'follow', icon: 'warn', route: () => route('app.matches.index', { follow: 1 }) },
    { k: 'look', icon: 'warn', route: () => route('app.jobs.index') },
    { k: 'queue', icon: 'info', route: () => route('app.review-queue.index') },
    { k: 'deadline', icon: 'bad', route: () => route('app.jobs.index') },
    { k: 'full', icon: 'info', route: () => route('app.jobs.index') },
];
const att = computed(() => ATT.filter((a) => D.value.attention[a.k] !== null && D.value.attention[a.k] !== undefined));
</script>

<template>
    <AppLayout :title="t('nav.dashboard')">
        <div class="dash" :style="loading ? 'opacity:.6;transition:opacity .15s' : ''">
            <div class="page-head">
                <div>
                    <div class="page-eyebrow">{{ workspace }}</div>
                    <h1 class="page-title">{{ greeting }}</h1>
                    <div class="page-sub">{{ t('db.sub') }}</div>
                </div>
                <div class="actions">
                    <div class="seg" role="group" :aria-label="t('db.range')">
                        <button v-for="r in RANGES" :key="r" type="button" :aria-pressed="range === r" @click="setRange(r)">{{ t(`db.range_${r}`) }}</button>
                    </div>
                    <button type="button" class="btn btn-line" @click="printPage"><AppIcon name="file" :size="15" />{{ t('db.pdf') }}</button>
                </div>
            </div>

            <!-- ── The five numbers ─────────────────────────────── -->
            <div class="dgrid g5">
                <div class="panel kpi" style="--accent:var(--ms-navy)">
                    <div class="lbl">{{ t('db.k_people') }}</div><div class="val num">{{ nf(K.people) }}</div>
                    <div class="foot"><span class="delta">▲ {{ nf(K.people_new) }}</span> {{ t('db.k_people_f') }}</div>
                    <div v-html="spark(K.people_spark, 'var(--s1)')" />
                </div>
                <div class="panel kpi" style="--accent:var(--ms-teal)">
                    <div class="lbl">{{ t('db.k_cv') }}</div><div class="val num">{{ nf(K.cvs) }}</div>
                    <div class="foot">{{ K.cvs_auto === null ? t('db.k_cv_none') : t('db.k_cv_f', { p: nf(K.cvs_auto) }) }}</div>
                    <div v-html="spark(K.cvs_spark, 'var(--s1)')" />
                </div>
                <div class="panel kpi" style="--accent:var(--ms-green)">
                    <div class="lbl">{{ t('db.k_opp') }}</div><div class="val num">{{ nf(K.jobs + K.trainings) }}</div>
                    <div class="foot">{{ t('db.k_opp_f', { j: nf(K.jobs), t: nf(K.trainings), s: nf(K.seats) }) }}</div>
                </div>
                <div class="panel kpi" style="--accent:var(--ms-green)">
                    <div class="lbl">{{ t('db.k_placed') }}</div><div class="val num">{{ nf(K.hired + K.completed) }}</div>
                    <div class="foot">{{ t('db.k_placed_f', { h: nf(K.hired), c: nf(K.completed) }) }}</div>
                    <div v-html="spark(K.placed_spark, 'var(--s3)')" />
                </div>
                <div class="panel kpi" style="--accent:var(--ms-purple)">
                    <div class="lbl">{{ t('db.k_rate') }}</div><div class="val num">{{ K.rate === null ? '—' : `${nf(K.rate, 1)}%` }}</div>
                    <div class="foot">{{ t('db.k_rate_f') }}<template v-if="K.days !== null"> · {{ t('db.k_days', { d: nf(K.days) }) }}</template></div>
                </div>
            </div>

            <!-- ── The placement journey ────────────────────────── -->
            <template v-if="D.funnel">
                <div class="section-label">{{ t('db.s_journey') }}</div>
                <div class="dgrid g32">
                    <div class="panel">
                        <div class="ph"><div><h3>{{ t('db.funnel') }}</h3><div class="hint">{{ t('db.funnel_h') }}</div></div></div>
                        <div class="funnel">
                            <div v-for="(s, i) in STAGES" :key="s" class="fnrow" :data-tip="JSON.stringify({ title: t(`db.st_${s}`), rows: [['var(--s1)', t('db.people'), nf(D.funnel[s])]].concat(i ? [[null, t('db.of_before', { p: nf(pct(D.funnel[s], D.funnel[STAGES[i - 1]])) }), '']] : []) })">
                                <div class="nm">{{ t(`db.st_${s}`) }}<small>{{ t(`db.stl_${s}`) }}</small></div>
                                <div class="ftrack"><i :style="`width:${D.funnel.registered ? Math.max(D.funnel[s] ? 2 : 0, (D.funnel[s] / D.funnel.registered) * 100) : 0}%`" /></div>
                                <div class="fv num">{{ nf(D.funnel[s]) }}<small>{{ i ? t('db.of_before', { p: nf(pct(D.funnel[s], D.funnel[STAGES[i - 1]])) }) : ' ' }}</small></div>
                            </div>
                        </div>
                    </div>
                    <div class="panel">
                        <div class="ph"><div><h3>{{ t('db.att') }}</h3><div class="hint">{{ t('db.att_h') }}</div></div></div>
                        <div class="att">
                            <Link v-for="a in att" :key="a.k" :href="a.route()" :class="{ zero: !D.attention[a.k] }">
                                <span class="ic" :class="D.attention[a.k] ? a.icon : 'ok'">{{ D.attention[a.k] ? (a.icon === 'info' ? 'i' : '!') : '✓' }}</span>{{ t(`db.a_${a.k}`) }}<b class="num">{{ nf(D.attention[a.k]) }}</b>
                            </Link>
                        </div>
                    </div>
                </div>
            </template>

            <!-- ── Your people ──────────────────────────────────── -->
            <template v-if="P">
                <div class="section-label">{{ t('db.s_people') }}</div>
                <div class="dgrid g2">
                    <div class="panel">
                        <div class="ph"><div><h3>{{ t('db.reg') }}</h3><div class="hint">{{ t('db.reg_h') }}</div></div>
                            <button type="button" class="linkbtn small" @click="tables.reg = !tables.reg">{{ tables.reg ? t('db.chart') : t('db.table') }}</button></div>
                        <div v-if="!tables.reg" v-html="regChart" />
                        <div v-else class="twrap"><table><thead><tr><th>{{ t('db.month') }}</th><th v-for="n in srcNames" :key="n" class="n">{{ n }}</th><th class="n">{{ t('db.total') }}</th></tr></thead>
                            <tbody><tr v-for="(m, i) in D.months" :key="m"><td>{{ monthName(m, true) }}</td><td v-for="s in SOURCES" :key="s" class="n">{{ nf(D.months_new[s][i]) }}</td><td class="n"><b>{{ nf(SOURCES.reduce((a, s) => a + D.months_new[s][i], 0)) }}</b></td></tr></tbody></table></div>
                        <div class="legend"><span v-for="(n, j) in srcNames" :key="n"><i :style="`background:${C4[j]}`" />{{ n }}</span></div>
                    </div>
                    <div class="panel">
                        <div class="ph"><div><h3>{{ t('db.placedm') }}</h3><div class="hint">{{ t('db.placedm_h') }}</div></div>
                            <button type="button" class="linkbtn small" @click="tables.plc = !tables.plc">{{ tables.plc ? t('db.chart') : t('db.table') }}</button></div>
                        <div v-if="!tables.plc" v-html="plcChart" />
                        <div v-else class="twrap"><table><thead><tr><th>{{ t('db.month') }}</th><th class="n">{{ t('db.hired') }}</th><th class="n">{{ t('db.completed') }}</th></tr></thead>
                            <tbody><tr v-for="(m, i) in D.months" :key="m"><td>{{ monthName(m, true) }}</td><td class="n">{{ nf(D.months_placed.job[i]) }}</td><td class="n">{{ nf(D.months_placed.training[i]) }}</td></tr></tbody></table></div>
                        <div class="legend"><span><i class="line" style="background:var(--s1)" />{{ t('db.hired') }}</span><span><i class="line" style="background:var(--s3)" />{{ t('db.completed') }}</span></div>
                    </div>
                </div>
                <div class="dgrid g4" style="margin-top:14px">
                    <div class="panel">
                        <h3>{{ t('db.gender') }}</h3>
                        <div class="split" :data-tip="JSON.stringify({ title: t('db.gender'), rows: [['var(--s1)', t('db.women'), nf(gender.female)], ['var(--s2)', t('db.men'), nf(gender.male)]] })">
                            <i :style="`flex:${gender.female};background:var(--s1)`" /><i :style="`flex:${gender.male};background:var(--s2)`" />
                        </div>
                        <div class="two">
                            <span><b class="num">{{ nf(pct(gender.female, gTotal)) }}%</b><span class="mute"><i class="sw" style="background:var(--s1)" />{{ t('db.women') }} · {{ nf(gender.female) }}</span></span>
                            <span style="text-align:end"><b class="num">{{ nf(pct(gender.male, gTotal)) }}%</b><span class="mute"><i class="sw" style="background:var(--s2)" />{{ t('db.men') }} · {{ nf(gender.male) }}</span></span>
                        </div>
                        <h3 style="margin-top:18px">{{ t('db.edu') }}</h3>
                        <div v-html="eduBars" />
                    </div>
                    <div class="panel"><div class="ph"><div><h3>{{ t('db.age') }}</h3><div class="hint">{{ t('db.age_h') }}</div></div></div><div v-html="ageChart" /></div>
                    <div class="panel"><h3>{{ t('db.gov') }}</h3><div v-html="govBars" /></div>
                    <div class="panel"><h3>{{ t('db.ind') }}</h3><div v-html="indBars" /></div>
                </div>
            </template>

            <!-- ── Your people and Egypt's labour market ────────── -->
            <template v-if="M">
                <div class="section-label">{{ t('db.s_market') }}</div>
                <div class="panel">
                    <div class="ph"><div><h3>{{ t('db.occ_t') }}</h3><div class="hint">{{ t('db.occ_h', { std: STANDARD_LABELS[prefs.standard] }) }}</div></div></div>
                    <div v-if="!M.rows.length" class="empty-note">{{ t('db.occ_none') }}</div>
                    <div v-else class="twrap"><table>
                        <thead><tr><th>{{ t('db.c_occ') }}</th><th class="n">{{ t('db.c_people') }}</th><th class="n">{{ t('db.c_elig') }}</th><th class="n">{{ t('db.c_placed') }}</th><th>{{ t('db.c_trend') }}</th><th class="n">{{ t('db.c_wage') }}</th><th class="n">{{ t('db.c_exp') }}</th></tr></thead>
                        <tbody>
                            <tr v-for="r in M.rows" :key="r.code">
                                <td><span class="code">{{ occName(r).std }} {{ occName(r).code }}</span> {{ occName(r).title }}</td>
                                <td class="n nowrap"><span class="tiny-bar" :style="`width:${(r.people / topPeople) * 60}px`" />{{ nf(r.people) }}</td>
                                <td class="n">{{ nf(r.eligible) }}</td>
                                <td class="n">{{ nf(r.placed) }}</td>
                                <td><span v-if="r.trend" class="badge" :class="TREND[r.trend]">{{ TREND[r.trend] === 'green' ? '▲' : '▼' }} {{ t(`mk.trend.${r.trend}`) }}</span><span v-else class="mute small">—</span></td>
                                <td class="n">{{ nf(r.wage) }}</td>
                                <td class="n">{{ nf(r.expected) }}</td>
                            </tr>
                        </tbody>
                    </table></div>
                </div>
                <div class="dgrid g2" style="margin-top:14px">
                    <div class="panel">
                        <div class="ph"><div><h3>{{ t('db.sal') }}</h3><div class="hint">{{ t('db.sal_h') }}</div></div></div>
                        <div v-if="!salChart" class="empty-note">{{ t('db.sal_none') }}</div>
                        <div v-else v-html="salChart" />
                        <div v-if="salChart" class="legend"><span><i class="dot" style="background:var(--s1)" />{{ t('db.sal_e') }}</span><span><i class="dot" style="background:var(--s2)" />{{ t('db.sal_m') }}</span></div>
                    </div>
                    <div v-if="D.skills" class="panel">
                        <div class="ph"><div><h3>{{ t('db.skills') }}</h3><div class="hint">{{ t('db.skills_h') }}</div></div></div>
                        <div v-if="!skillBars" class="empty-note">{{ t('db.skills_none') }}</div>
                        <div v-else v-html="skillBars" />
                    </div>
                </div>
            </template>

            <!-- ── Jobs & Training ──────────────────────────────── -->
            <template v-if="D.opportunities || D.follow_up || D.team">
                <div class="section-label">{{ t('db.s_opp') }}</div>
                <div v-if="D.opportunities" class="panel">
                    <div class="ph"><h3>{{ t('db.opp_t') }}</h3>
                        <span class="small"><Link :href="route('app.jobs.index')">{{ t('nav.jobs') }}</Link> · <Link :href="route('app.training.index')">{{ t('nav.training') }}</Link></span></div>
                    <div v-if="!D.opportunities.rows.length" class="empty-note">{{ t('db.opp_none') }}</div>
                    <div v-else class="twrap"><table>
                        <thead><tr><th>{{ t('db.c_title') }}</th><th>{{ t('db.c_kind') }}</th><th class="n">{{ t('db.c_elig') }}</th><th class="n">{{ t('db.c_ref') }}</th><th>{{ t('db.c_seats') }}</th><th>{{ t('db.c_dead') }}</th><th /></tr></thead>
                        <tbody>
                            <tr v-for="o in D.opportunities.rows" :key="o.id">
                                <td><Link :href="route(`app.${o.section}.show`, o.id)">{{ o.title }}</Link><div v-if="o.by" class="xsmall mute">{{ o.by }}</div></td>
                                <td><span class="badge" :class="o.kind === 'job' ? 'navy' : 'teal'">{{ t(`db.${o.kind}`) }}</span></td>
                                <td class="n">{{ nf(o.eligible) }}</td>
                                <td class="n">{{ nf(o.referred) }}</td>
                                <td><div class="small num">{{ nf(o.taken) }} / {{ nf(o.seats) }}</div><div class="meter"><i :style="`width:${Math.min(100, (o.taken / (o.seats || 1)) * 100)}%;--c:${o.taken >= o.seats ? 'var(--ms-orange)' : 'var(--s1)'}`" /></div></td>
                                <td class="small">{{ dateTxt(o.deadline) }}</td>
                                <td><span class="badge" :class="o.state === 'full' ? 'orange' : o.state === 'passed' ? 'danger' : 'green'">{{ t(`db.st_${o.state}`) }}</span></td>
                            </tr>
                        </tbody>
                    </table></div>
                </div>
                <div class="dgrid g2" style="margin-top:14px">
                    <div v-if="D.follow_up" class="panel">
                        <div class="ph"><h3>{{ t('db.follow') }}</h3><Link :href="route('app.matches.index', { follow: 1 })" class="small">{{ t('nav.matches') }} →</Link></div>
                        <div v-if="!D.follow_up.rows.length" class="empty-note">✓ {{ t('db.follow_none') }}</div>
                        <div v-else class="twrap"><table>
                            <thead><tr><th>{{ t('db.c_person') }}</th><th>{{ t('db.c_opp') }}</th><th>{{ t('db.c_stage') }}</th><th class="n">{{ t('db.c_days') }}</th></tr></thead>
                            <tbody>
                                <tr v-for="r in D.follow_up.rows" :key="r.id">
                                    <td class="nowrap"><Link v-if="r.person" :href="route('app.beneficiaries.show', r.person.number) + '#matches'">{{ r.person.name }}</Link></td>
                                    <td class="small"><Link v-if="r.opportunity" :href="route(`app.${r.opportunity.section}.show`, r.opportunity.id)">{{ r.opportunity.title }}</Link></td>
                                    <td><span class="badge navy">{{ stageText(r.kind, r.stage, t) }}</span></td>
                                    <td class="n nowrap"><span style="color:var(--ms-orange);font-weight:700">⚠ {{ t('db.days', { n: nf(r.days) }) }}</span></td>
                                </tr>
                            </tbody>
                        </table></div>
                    </div>
                    <div v-if="D.team" class="panel">
                        <div class="ph"><h3>{{ t('db.team') }}</h3></div>
                        <div class="twrap"><table>
                            <thead><tr><th>{{ t('db.c_member') }}</th><th class="n">{{ t('db.c_reg') }}</th><th class="n">{{ t('db.c_chk') }}</th><th class="n">{{ t('db.c_refd') }}</th><th class="n">{{ t('db.c_plc') }}</th></tr></thead>
                            <tbody><tr v-for="m in D.team" :key="m.id"><td class="nowrap">{{ m.name }}</td><td class="n">{{ nf(m.registered) }}</td><td class="n">{{ nf(m.checked) }}</td><td class="n">{{ nf(m.referred) }}</td><td class="n">{{ nf(m.placed) }}</td></tr></tbody>
                        </table></div>
                    </div>
                </div>
            </template>
        </div>
        <DashTooltip />
    </AppLayout>
</template>
