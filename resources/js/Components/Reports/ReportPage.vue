<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — The Reports page (Step 14)
//  Location: resources/js/Components/Reports/ReportPage.vue
//  Used by: Pages/App/Reports/Index.vue (a partner's own people) and
//           Pages/Admin/Reports/Index.vue (every partner, counts only)
//  Scope: docs/SCOPE_REPORTS.md
//
//  · Filters (all together): occupation (any standard, any level) ·
//    industry · gender · age · experience · expected salary ·
//    governorate · education · journey stage
//  · Show: a count or an average; split by one thing, or two (a cross-table)
//  · The date range (registration; the placement date with "Placed")
//  · The result: the question in words, a chart, the table with totals
//  · Excel · PDF · Save for the team · Open these people (CV Bank)
//  The page asks the server again a moment after each change.
// ══════════════════════════════════════════════════════════════════

import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';

const props = defineProps({
    params: { type: Object, required: true },
    result: { type: Object, required: true },
    summary: { type: Array, default: () => [] },
    admin: { type: Boolean, default: false },
    occupations: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    saved: { type: Array, default: () => [] },
    can_open_people: { type: Boolean, default: false },
});
const { t, locale } = useTranslations();
const { can } = usePermissions();
const area = computed(() => (props.admin ? 'admin' : 'app'));
const nf = (v, d = 0) => (v === null || v === undefined ? '—' : new Intl.NumberFormat(locale.value === 'ar' ? 'ar-EG' : 'en-US', { maximumFractionDigits: d, minimumFractionDigits: d }).format(v));
const dec = computed(() => (props.result.measure === 'count' ? 0 : 1));

// ── The question (a copy, sent back after each change) ────────────
const clone = (x) => JSON.parse(JSON.stringify(x));
const q = reactive(clone(props.params));
watch(() => props.params, (p) => Object.assign(q, clone(p)));
const occChips = ref([...props.occupations]);
watch(() => props.occupations, (o) => { occChips.value = [...o]; });
const loading = ref(false);
let timer = null;
const send = (now = false) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route(`${area.value}.reports.index`), { r: JSON.stringify(q) }, {
            preserveState: true, preserveScroll: true, replace: true, only: ['params', 'result', 'summary', 'occupations'],
            onStart: () => { loading.value = true; }, onFinish: () => { loading.value = false; },
        });
    }, now ? 0 : 400);
};
const set = (path, v) => { const k = path.split('.'); let o = q; while (k.length > 1) o = o[k.shift()]; o[k[0]] = v; send(); };
const toggleIn = (list, v) => { const i = list.indexOf(v); i >= 0 ? list.splice(i, 1) : list.push(v); send(); };
const reset = () => {
    Object.assign(q.f, { occ: [], industry: [], gender: '', age: [null, null], exp: [null, null], salary: [null, null], gov: [], edu: [], stage: '' });
    occChips.value = [];
    send(true);
};
const filtersOn = computed(() => {
    const f = q.f;
    return f.occ.length + f.industry.length + f.gov.length + f.edu.length + (f.gender ? 1 : 0) + (f.stage ? 1 : 0)
        + ['age', 'exp', 'salary'].filter((k) => f[k][0] !== null || f[k][1] !== null).length;
});
const num = (v) => (v === '' || v === null || v === undefined ? null : Number(v));
const setPair = (k, i, v) => { q.f[k][i] = num(v); send(); };

// ── Occupation picker (any standard, any level) ───────────────────
const occQ = ref('');
const occResults = ref([]);
const occOpen = ref(false);
let otimer = null;
let seq = 0;
watch(occQ, (text) => {
    clearTimeout(otimer);
    if (!text.trim()) { occResults.value = []; return; }
    otimer = setTimeout(async () => {
        const mine = ++seq;
        try {
            const { data } = await window.axios.get(route(`${area.value}.reports.occupations`), { params: { q: text.trim() } });
            if (mine === seq) occResults.value = data;
        } catch { occResults.value = []; }
    }, 250);
});
const pickOcc = (o) => {
    if (!q.f.occ.includes(o.value)) { q.f.occ.push(o.value); occChips.value.push(o); send(); }
    occQ.value = ''; occOpen.value = false;
};
const dropOcc = (v) => { q.f.occ = q.f.occ.filter((x) => x !== v); occChips.value = occChips.value.filter((x) => x.value !== v); send(); };
const closeLater = () => setTimeout(() => { occOpen.value = false; }, 200);

// ── Names ─────────────────────────────────────────────────────────
const sname = (x) => (locale.value === 'ar' ? x.name_ar : x.name_en);
const sectorName = (code) => {
    for (const s of props.options.sectors || []) {
        if (s.code === code) return sname(s);
        const x = s.subs.find((y) => y.code === code);
        if (x) return sname(x);
    }
    return code;
};
const addFrom = (key, e) => { const v = e.target.value; e.target.value = ''; if (v && !q.f[key].includes(v)) { q.f[key].push(v); send(); } };

// ── The answer ────────────────────────────────────────────────────
const R = computed(() => props.result);
const cellOf = (r, c) => R.value.cells?.[r]?.[c ?? '_'] || null;
const show = (cell) => (!cell ? '0' : cell.small ? t('rep.lt5') : nf(cell.v, dec.value));
const share = (cell) => (cell && R.value.total.v ? Math.round((cell.v / R.value.total.v) * 1000) / 10 : 0);
const isTime = computed(() => ['month', 'quarter'].includes(q.rows) && !q.cols);

// Chart rows: the biggest 15 (in the order of the table); the rest folded into "Other" for counts.
const COLORS = ['var(--s1)', 'var(--s2)', 'var(--s3)', 'var(--s4)'];
const chartRows = computed(() => {
    const rows = R.value.rows.filter((r) => r.key !== '_none');
    const top = isTime.value ? rows : rows.slice(0, 15);
    const out = top.map((r) => ({ ...r, total: R.value.row_totals[r.key] }));
    if (!isTime.value && rows.length > 15 && R.value.measure === 'count') {
        const rest = rows.slice(15).reduce((a, r) => a + (R.value.row_totals[r.key]?.v || 0), 0);
        out.push({ key: '_other', label: t('rep.other', { n: rows.length - 15 }), total: { v: rest, n: rest } });
    }
    return out;
});
// Cross-table in the chart: the three biggest columns, the rest as "Other" (counts only).
const chartCols = computed(() => {
    if (!R.value.cols || R.value.measure !== 'count') return null;
    const cols = [...R.value.cols].sort((a, b) => (R.value.col_totals[b.key]?.v || 0) - (R.value.col_totals[a.key]?.v || 0));
    const top = cols.slice(0, cols.length > 4 ? 3 : 4);
    return { top, rest: cols.slice(top.length) };
});
const segs = (row) => {
    if (row.key === '_other') return [{ c: 'var(--axis)', v: row.total.v, label: t('rep.all') }];
    const cc = chartCols.value;
    const list = cc.top.map((c, i) => ({ c: COLORS[i], v: cellOf(row.key, c.key)?.v || 0, label: c.label }));
    if (cc.rest.length) list.push({ c: COLORS[3], v: cc.rest.reduce((a, c) => a + (cellOf(row.key, c.key)?.v || 0), 0), label: t('rep.other_cols', { n: cc.rest.length }) });
    return list;
};
const maxV = computed(() => Math.max(1, ...chartRows.value.map((r) => r.total?.v || 0)));

// Line through time.
const LW = 640; const LH = 200; const PL = 40; const PR = 12; const PT = 12; const PB = 26;
const line = computed(() => {
    const pts = chartRows.value.map((r) => r.total?.v || 0);
    const max = Math.max(1, ...pts);
    const nice = max <= 5 ? 5 : Math.ceil(max / (10 ** Math.floor(Math.log10(max)))) * (10 ** Math.floor(Math.log10(max)));
    const step = (LW - PL - PR) / Math.max(1, pts.length - 1);
    const x = (i) => (pts.length === 1 ? (LW - PL - PR) / 2 + PL : PL + i * step);
    const y = (v) => PT + (LH - PT - PB) * (1 - v / nice);
    return { pts: pts.map((v, i) => ({ x: x(i), y: y(v), v, label: chartRows.value[i].label })), grid: [0, 0.25, 0.5, 0.75, 1].map((k) => ({ y: y(nice * k), v: nice * k })) };
});

// ── Excel · PDF · Open these people · Save ────────────────────────
const exportUrl = (format) => route(`${area.value}.reports.export`, { r: JSON.stringify(q), format });
const token = (row = null, col = null) => btoa(unescape(encodeURIComponent(JSON.stringify({ p: q, row, col })))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
const peopleUrl = (row = null, col = null) => route('app.cv-bank.index', { report: token(row, col) });
const canOpen = computed(() => !props.admin && props.can_open_people);

const saving = ref(false);
const saveForm = useForm({ name: '', params: null });
const openSave = () => { saveForm.reset(); saveForm.clearErrors(); saving.value = true; };
const doSave = () => { saveForm.params = clone(q); saveForm.post(route('app.reports.save'), { preserveScroll: true, onSuccess: () => { saving.value = false; } }); };
const openSaved = (s) => { Object.assign(q, clone(s.params)); send(true); };
const renaming = ref(null);
const renameForm = useForm({ name: '' });
const openRename = (s) => { renameForm.name = s.name; renameForm.clearErrors(); renaming.value = s; };
const doRename = () => renameForm.patch(route('app.reports.rename', renaming.value.id), { preserveScroll: true, onSuccess: () => { renaming.value = null; } });
const forgetting = ref(null);
const doForget = () => router.delete(route('app.reports.forget', forgetting.value.id), { preserveScroll: true, onFinish: () => { forgetting.value = null; } });

const RANGES = computed(() => props.options.ranges.filter((r) => r !== 'custom'));
const measureTitle = computed(() => t(`rep.m_${R.value.measure}`));
</script>

<template>
    <div class="page-head">
        <div>
            <div class="page-eyebrow">{{ t('nav.insights_group') }}</div>
            <h1 class="page-title">{{ t('nav.reports') }}</h1>
            <div class="page-sub">{{ admin ? t('rep.sub_admin') : t('rep.sub') }}</div>
        </div>
    </div>

    <div class="rep-grid">
        <!-- ── Filters ─────────────────────────────────────────── -->
        <aside class="panel filters-panel">
            <div class="panel-h">
                <h3><AppIcon name="search" :size="15" />{{ t('rep.filters') }}<span v-if="filtersOn" class="badge navy ms">{{ filtersOn }}</span></h3>
                <button v-if="filtersOn" type="button" class="linkbtn small" @click="reset">{{ t('rep.clear') }}</button>
            </div>
            <p class="xsmall mute">{{ t('rep.filters_hint') }}</p>

            <div class="fb">
                <div class="fl">{{ t('rep.d_occupation') }}</div>
                <div v-if="occChips.length" class="chipset">
                    <span v-for="o in occChips" :key="o.value" class="tag teal">{{ STANDARD_LABELS[o.standard] || o.standard }} <bdi dir="ltr">{{ o.code }}</bdi><template v-if="o.title"> · {{ o.title }}</template><span class="x" role="button" :aria-label="t('rep.remove')" @click="dropOcc(o.value)">×</span></span>
                </div>
                <div class="occf">
                    <input v-model="occQ" type="search" class="inp" :placeholder="t('rep.occ_ph')" @focus="occOpen = true" @blur="closeLater">
                    <div v-if="occOpen && occResults.length" class="occlist">
                        <button v-for="o in occResults" :key="o.value" type="button" @mousedown.prevent="pickOcc(o)">
                            <span class="tag plain">{{ STANDARD_LABELS[o.standard] }}</span> <b><bdi dir="ltr">{{ o.code }}</bdi></b> {{ o.title }}
                        </button>
                    </div>
                </div>
                <div v-if="q.f.occ.length > 1" class="xsmall mute mt-1">{{ t('rep.occ_any') }}</div>
            </div>

            <div class="fb">
                <div class="fl">{{ t('rep.d_industry') }}</div>
                <div v-if="q.f.industry.length" class="chipset">
                    <span v-for="c in q.f.industry" :key="c" class="tag navy">{{ sectorName(c) }}<span class="x" role="button" @click="toggleIn(q.f.industry, c)">×</span></span>
                </div>
                <select class="inp" :aria-label="t('rep.d_industry')" @change="addFrom('industry', $event)">
                    <option value="">{{ t('rep.add_industry') }}</option>
                    <optgroup v-for="s in options.sectors" :key="s.code" :label="sname(s)">
                        <option :value="s.code">{{ sname(s) }} — {{ t('rep.whole_sector') }}</option>
                        <option v-for="x in s.subs" :key="x.code" :value="x.code">{{ sname(x) }}</option>
                    </optgroup>
                </select>
                <div class="xsmall mute mt-1">{{ t('rep.industry_hint') }}</div>
            </div>

            <div class="fb">
                <div class="fl">{{ t('rep.d_gender') }}</div>
                <div class="seg full" role="group">
                    <button type="button" :aria-pressed="!q.f.gender" @click="set('f.gender', '')">{{ t('rep.both') }}</button>
                    <button type="button" :aria-pressed="q.f.gender === 'female'" @click="set('f.gender', 'female')">{{ t('rep.women') }}</button>
                    <button type="button" :aria-pressed="q.f.gender === 'male'" @click="set('f.gender', 'male')">{{ t('rep.men') }}</button>
                </div>
            </div>

            <div v-for="k in ['age', 'exp', 'salary']" :key="k" class="fb">
                <div class="fl">{{ t(`rep.f_${k}`) }}</div>
                <div class="pair">
                    <input type="number" min="0" class="inp" :value="q.f[k][0]" :placeholder="t('rep.from')" :aria-label="t(`rep.f_${k}`) + ' ' + t('rep.from')" @change="setPair(k, 0, $event.target.value)">
                    <span class="mute">–</span>
                    <input type="number" min="0" class="inp" :value="q.f[k][1]" :placeholder="t('rep.to')" :aria-label="t(`rep.f_${k}`) + ' ' + t('rep.to')" @change="setPair(k, 1, $event.target.value)">
                </div>
            </div>

            <div class="fb">
                <div class="fl">{{ t('rep.d_governorate') }}</div>
                <div v-if="q.f.gov.length" class="chipset">
                    <span v-for="g in q.f.gov" :key="g" class="tag plain">{{ t(`gov.${g}`) }}<span class="x" role="button" @click="toggleIn(q.f.gov, g)">×</span></span>
                </div>
                <select class="inp" :aria-label="t('rep.d_governorate')" @change="addFrom('gov', $event)">
                    <option value="">{{ t('rep.add_governorate') }}</option>
                    <option v-for="g in options.governorates" :key="g" :value="g">{{ t(`gov.${g}`) }}</option>
                </select>
            </div>

            <div class="fb">
                <div class="fl">{{ t('rep.d_education') }}</div>
                <div v-if="q.f.edu.length" class="chipset">
                    <span v-for="e in q.f.edu" :key="e" class="tag plain">{{ t(`ben.edu_${e}`) }}<span class="x" role="button" @click="toggleIn(q.f.edu, e)">×</span></span>
                </div>
                <select class="inp" :aria-label="t('rep.d_education')" @change="addFrom('edu', $event)">
                    <option value="">{{ t('rep.add_education') }}</option>
                    <option v-for="e in options.education_levels" :key="e" :value="e">{{ t(`ben.edu_${e}`) }}</option>
                </select>
            </div>

            <div class="fb">
                <div class="fl">{{ t('rep.d_stage') }}</div>
                <select :value="q.f.stage" class="inp" :aria-label="t('rep.d_stage')" @change="set('f.stage', $event.target.value)">
                    <option value="">{{ t('rep.any_stage') }}</option>
                    <option v-for="s in options.stages" :key="s" :value="s">{{ t(`rep.sf_${s}`) }}</option>
                </select>
                <div v-if="q.f.stage === 'placed'" class="xsmall mute mt-1">{{ t('rep.placed_hint') }}</div>
            </div>

            <!-- Saved reports (the workspace's team) -->
            <template v-if="!admin">
                <div class="fl mt-4">{{ t('rep.saved') }}</div>
                <div v-if="!saved.length" class="xsmall mute">{{ t('rep.saved_none') }}</div>
                <div v-for="s in saved" :key="s.id" class="saved">
                    <button type="button" class="linkbtn" @click="openSaved(s)">{{ s.name }}</button>
                    <span class="xsmall mute">{{ s.by }}</span>
                    <span v-if="s.can_change" class="sacts">
                        <button type="button" class="linkbtn xsmall" @click="openRename(s)">{{ t('rep.rename') }}</button>
                        <button type="button" class="linkbtn xsmall c-danger" @click="forgetting = s">{{ t('common.delete') }}</button>
                    </span>
                </div>
            </template>
        </aside>

        <!-- ── Show, split, range, result ──────────────────────── -->
        <section class="min0">
            <div class="panel showbar">
                <label class="fld-inline">
                    <span>{{ t('rep.show') }}</span>
                    <select :value="q.measure" class="inp" @change="set('measure', $event.target.value)">
                        <option v-for="m in options.measures" :key="m" :value="m">{{ t(`rep.m_${m}`) }}</option>
                    </select>
                </label>
                <label class="fld-inline">
                    <span>{{ t('rep.split_by') }}</span>
                    <select :value="q.rows" class="inp" @change="set('rows', $event.target.value)">
                        <option v-for="d in options.dimensions" :key="d" :value="d">{{ t(`rep.d_${d}`) }}</option>
                    </select>
                </label>
                <label class="fld-inline">
                    <span>{{ t('rep.and_by') }}</span>
                    <select :value="q.cols" class="inp" @change="set('cols', $event.target.value)">
                        <option value="">—</option>
                        <option v-for="d in options.dimensions.filter((x) => x !== q.rows)" :key="d" :value="d">{{ t(`rep.d_${d}`) }}</option>
                    </select>
                </label>
                <label v-if="q.rows === 'occupation' || q.cols === 'occupation'" class="fld-inline">
                    <span>{{ t('rep.occ_level') }}</span>
                    <select :value="q.occ_level" class="inp" @change="set('occ_level', $event.target.value)">
                        <option v-for="l in options.occ_levels" :key="l" :value="l">{{ t(`rep.ol_${l}`) }}</option>
                    </select>
                </label>
                <label v-if="q.rows === 'industry' || q.cols === 'industry'" class="fld-inline">
                    <span>{{ t('rep.ind_level') }}</span>
                    <select :value="q.ind_level" class="inp" @change="set('ind_level', $event.target.value)">
                        <option v-for="l in options.ind_levels" :key="l" :value="l">{{ t(`rep.il_${l}`) }}</option>
                    </select>
                </label>
                <div class="rangebar">
                    <div class="seg" role="group" :aria-label="t('rep.range')">
                        <button v-for="r in RANGES" :key="r" type="button" :aria-pressed="q.range.preset === r" @click="q.range = { preset: r, from: null, to: null }; send(true)">{{ t(`rep.r_${r}`) }}</button>
                        <button type="button" :aria-pressed="q.range.preset === 'custom'" @click="q.range = { preset: 'custom', from: q.range.from, to: q.range.to }">{{ t('rep.r_custom') }}</button>
                    </div>
                    <template v-if="q.range.preset === 'custom'">
                        <input type="date" class="inp dt" :value="q.range.from" :aria-label="t('rep.from')" @change="q.range.from = $event.target.value || null; send()">
                        <input type="date" class="inp dt" :value="q.range.to" :aria-label="t('rep.to')" @change="q.range.to = $event.target.value || null; send()">
                    </template>
                </div>
            </div>

            <div class="panel mt-4 result" :class="{ busy: loading }">
                <div class="res-head">
                    <div class="min0">
                        <div class="words">{{ summary.join(' · ') }}</div>
                        <div class="big mt-1">
                            <template v-if="R.measure === 'count'"><b class="num">{{ R.total.small ? t('rep.lt5') : nf(R.total.v) }}</b> {{ t('rep.people') }}</template>
                            <template v-else><b class="num">{{ nf(R.total.v, 1) }}</b> {{ measureTitle }} <span class="small mute">· {{ t('rep.based_on', { n: nf(R.total.n) }) }}</span></template>
                        </div>
                    </div>
                    <div class="acts">
                        <a v-if="can('reports.export')" :href="exportUrl('xlsx')" class="btn btn-line sm"><AppIcon name="file" :size="14" />Excel</a>
                        <a v-if="can('reports.export')" :href="exportUrl('pdf')" class="btn btn-line sm"><AppIcon name="file" :size="14" />PDF</a>
                        <button v-if="!admin" type="button" class="btn btn-line sm" @click="openSave"><AppIcon name="plus" :size="14" />{{ t('rep.save') }}</button>
                        <a v-if="canOpen && R.total.v" :href="peopleUrl()" class="btn btn-primary sm"><AppIcon name="users" :size="14" />{{ t('rep.open_people') }}</a>
                    </div>
                </div>
                <div v-if="admin" class="alert mt-3 small"><AppIcon name="shield" :size="14" /><span>{{ t('rep.admin_note') }}</span></div>

                <div v-if="!R.rows.length" class="empty-row mt-4">{{ t('rep.nobody') }}</div>
                <template v-else>
                    <!-- Chart -->
                    <div class="chart-box mt-4">
                        <svg v-if="isTime" class="chart" :viewBox="`0 0 ${LW} ${LH}`" role="img" :aria-label="measureTitle">
                            <g v-for="(g, i) in line.grid" :key="'g' + i"><line class="gl" :x1="PL" :x2="LW - PR" :y1="g.y" :y2="g.y" /><text :x="PL - 6" :y="g.y + 4" text-anchor="end">{{ nf(g.v) }}</text></g>
                            <polyline :points="line.pts.map((p) => `${p.x},${p.y}`).join(' ')" fill="none" stroke="var(--s1)" stroke-width="2" stroke-linejoin="round" />
                            <g v-for="(p, i) in line.pts" :key="i">
                                <circle :cx="p.x" :cy="p.y" r="4" fill="var(--s1)" stroke="var(--ms-bg-card)" stroke-width="2"><title>{{ p.label }}: {{ nf(p.v, dec) }}</title></circle>
                                <text v-if="line.pts.length <= 12 || i % 2 === 0" :x="p.x" :y="LH - 8" text-anchor="middle">{{ p.label }}</text>
                            </g>
                        </svg>
                        <div v-else class="hb">
                            <div v-for="r in chartRows" :key="r.key" class="hr" :title="`${r.label}: ${show(r.total)}`">
                                <span class="hl truncate">{{ r.label }}</span>
                                <span class="trk">
                                    <template v-if="chartCols && r.key !== '_other'">
                                        <i v-for="(s, i) in segs(r)" :key="i" :style="`width:${(s.v / maxV) * 100}%;background:${s.c}`" :title="`${r.label} · ${s.label}: ${nf(s.v)}`" />
                                    </template>
                                    <i v-else :style="`width:${((r.total?.v || 0) / maxV) * 100}%;background:${r.key === '_other' ? 'var(--axis)' : 'var(--s1)'}`" />
                                </span>
                                <span class="hv num">{{ show(r.total) }}</span>
                            </div>
                        </div>
                        <div v-if="chartCols" class="legend">
                            <span v-for="(c, i) in chartCols.top" :key="c.key"><i :style="`background:${COLORS[i]}`" />{{ c.label }}</span>
                            <span v-if="chartCols.rest.length"><i :style="`background:${COLORS[3]}`" />{{ t('rep.other_cols', { n: chartCols.rest.length }) }}</span>
                        </div>
                        <div v-else-if="R.cols" class="xsmall mute mt-2">{{ t('rep.chart_totals') }}</div>
                        <div v-if="!isTime && R.rows.filter((r) => r.key !== '_none').length > 15" class="xsmall mute mt-2">{{ t('rep.top15') }}</div>
                    </div>

                    <!-- Table -->
                    <div class="table-wrap compact mt-4">
                        <table>
                            <thead>
                                <tr>
                                    <th>{{ t(`rep.d_${q.rows}`) }}</th>
                                    <template v-if="R.cols"><th v-for="c in R.cols" :key="c.key" class="n">{{ c.label }}</th></template>
                                    <th v-else class="n">{{ measureTitle }}</th>
                                    <th v-if="R.cols" class="n">{{ t('rep.total') }}</th>
                                    <th v-if="R.measure === 'count' && !R.cols" class="n">{{ t('rep.share') }}</th>
                                    <th v-if="R.measure !== 'count'" class="n">{{ t('rep.based_on_col') }}</th>
                                    <th v-if="R.market" class="n">{{ t('rep.market') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in R.rows" :key="r.key">
                                    <td :class="{ mute: r.key === '_none' }">{{ r.label }}</td>
                                    <template v-if="R.cols">
                                        <td v-for="c in R.cols" :key="c.key" class="n">
                                            <a v-if="canOpen && R.measure === 'count' && cellOf(r.key, c.key)?.v" :href="peopleUrl(r.key, c.key)" :title="t('rep.open_these')">{{ show(cellOf(r.key, c.key)) }}</a>
                                            <span v-else :class="{ mute: !cellOf(r.key, c.key) }">{{ show(cellOf(r.key, c.key)) }}</span>
                                        </td>
                                    </template>
                                    <td class="n">
                                        <a v-if="canOpen && R.row_totals[r.key]?.c !== 0 && R.measure === 'count'" :href="peopleUrl(r.key)" :title="t('rep.open_these')"><b>{{ show(R.row_totals[r.key]) }}</b></a>
                                        <b v-else>{{ show(R.row_totals[r.key]) }}</b>
                                    </td>
                                    <td v-if="R.measure === 'count' && !R.cols" class="n">
                                        <span class="shr"><i :style="`width:${share(R.row_totals[r.key])}%`" /></span> {{ R.row_totals[r.key]?.small ? '' : nf(share(R.row_totals[r.key]), 1) + '%' }}
                                    </td>
                                    <td v-if="R.measure !== 'count'" class="n mute">{{ t('rep.n_people', { n: nf(R.row_totals[r.key]?.n) }) }}</td>
                                    <td v-if="R.market" class="n">{{ R.market[r.key] ? nf(R.market[r.key]) : '—' }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="tot">
                                    <td>{{ t('rep.total') }}</td>
                                    <template v-if="R.cols"><td v-for="c in R.cols" :key="c.key" class="n">{{ show(R.col_totals[c.key]) }}</td></template>
                                    <td class="n">{{ show(R.total) }}</td>
                                    <td v-if="R.measure === 'count' && !R.cols" class="n">100%</td>
                                    <td v-if="R.measure !== 'count'" class="n">{{ t('rep.n_people', { n: nf(R.total.n) }) }}</td>
                                    <td v-if="R.market" />
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p v-if="R.measure !== 'count'" class="xsmall mute mt-2">{{ t('rep.avg_note') }}</p>
                    <p v-if="R.market" class="xsmall mute">{{ t('rep.market_note') }}</p>
                    <p v-if="canOpen && R.measure === 'count'" class="xsmall mute">{{ t('rep.click_hint') }}</p>
                </template>
            </div>
        </section>
    </div>

    <!-- Save / rename / delete -->
    <Modal :show="saving" size="sm" :title="t('rep.save_title')" @close="saving = false">
        <label class="fld" :class="{ 'has-error': saveForm.errors.name }">
            <span class="fl"><span>{{ t('rep.name') }}<span class="req">*</span></span></span>
            <input v-model="saveForm.name" type="text" class="inp" maxlength="120" dir="auto" :placeholder="t('rep.name_ph')" @keyup.enter="doSave">
            <small>{{ t('rep.save_hint') }}</small>
            <span v-if="saveForm.errors.name" class="fld-error">{{ saveForm.errors.name }}</span>
        </label>
        <template #footer>
            <button type="button" class="btn btn-line" @click="saving = false">{{ t('common.cancel') }}</button>
            <button type="button" class="btn btn-primary" :class="{ 'is-loading': saveForm.processing }" :disabled="!saveForm.name.trim() || saveForm.processing" @click="doSave">{{ t('common.save') }}</button>
        </template>
    </Modal>
    <Modal :show="!!renaming" size="sm" :title="t('rep.rename')" @close="renaming = null">
        <label class="fld" :class="{ 'has-error': renameForm.errors.name }">
            <input v-model="renameForm.name" type="text" class="inp" maxlength="120" dir="auto">
            <span v-if="renameForm.errors.name" class="fld-error">{{ renameForm.errors.name }}</span>
        </label>
        <template #footer>
            <button type="button" class="btn btn-line" @click="renaming = null">{{ t('common.cancel') }}</button>
            <button type="button" class="btn btn-primary" :disabled="!renameForm.name.trim() || renameForm.processing" @click="doRename">{{ t('common.save') }}</button>
        </template>
    </Modal>
    <ConfirmDialog :show="!!forgetting" danger :message="t('rep.forget_confirm', { name: forgetting?.name || '' })" :confirm-label="t('common.delete')" @confirm="doForget" @close="forgetting = null" />
</template>

<style scoped>
.rep-grid { display: grid; grid-template-columns: 290px minmax(0, 1fr); gap: 16px; align-items: start; }
.min0 { min-width: 0; }
.ms { margin-inline-start: 6px; }
.filters-panel { position: sticky; top: calc(var(--topbar-h, 60px) + 12px); max-height: calc(100vh - 90px); overflow-y: auto; }
.fb { margin-top: 14px; }
.fl { font-size: 11.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: var(--ms-text-muted); margin-bottom: 6px; }
.chipset { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 6px; }
.chipset .tag { white-space: normal; }
.x { margin-inline-start: 6px; cursor: pointer; font-weight: 800; }
.occf { position: relative; }
.occlist { position: absolute; z-index: 20; inset-inline: 0; top: calc(100% + 4px); background: var(--ms-bg-card); border: 1px solid var(--ms-border); border-radius: 8px; max-height: 280px; overflow-y: auto; box-shadow: 0 8px 24px rgba(0,0,0,.12); min-width: 280px; }
.occlist button { display: block; width: 100%; text-align: start; padding: 7px 10px; border: 0; background: none; font-size: 12.5px; cursor: pointer; color: inherit; }
.occlist button:hover { background: var(--ms-bg-hover); }
.seg.full { display: flex; width: 100%; }
.seg.full button { flex: 1; }
.pair { display: grid; grid-template-columns: 1fr auto 1fr; gap: 6px; align-items: center; }
.saved { display: flex; flex-wrap: wrap; gap: 4px 8px; align-items: baseline; padding: 6px 0; border-top: 1px dashed var(--ms-border); }
.sacts { margin-inline-start: auto; display: flex; gap: 8px; }
.showbar { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: flex-end; }
.fld-inline { display: grid; gap: 4px; font-size: 11.5px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; color: var(--ms-text-muted); }
.fld-inline .inp { min-width: 150px; text-transform: none; letter-spacing: 0; font-weight: 500; }
.rangebar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-inline-start: auto; }
.dt { width: 150px; }
.result.busy { opacity: .6; transition: opacity .15s; }
.res-head { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: flex-start; }
.words { font-size: 13px; color: var(--ms-text-muted); }
.big { font-size: 15px; }
.big b { font-size: 26px; font-weight: 800; margin-inline-end: 4px; }
.acts { display: flex; gap: 8px; flex-wrap: wrap; }
.chart-box { --s1: #2a78d6; --s2: #eb6834; --s3: #1baf7a; --s4: #b0508f; --axis: #5C7999; --grid: rgba(92,121,153,.16); }
:global(html:not(.light)) .chart-box { --s1: #3e82d6; --s2: #d95926; --s3: #199e70; --s4: #c264a8; --axis: #7E93AE; --grid: rgba(126,147,174,.18); }
.chart { width: 100%; display: block; direction: ltr; }
.chart text { fill: var(--axis); font-size: 11px; }
.chart .gl { stroke: var(--grid); }
.hb { display: grid; gap: 4px; }
.hr { display: grid; grid-template-columns: minmax(120px, 1.3fr) 3fr 80px; gap: 10px; align-items: center; font-size: 12.5px; }
.hl { min-width: 0; }
.trk { display: flex; gap: 2px; height: 12px; background: var(--ms-bg-hover); border-radius: 4px; overflow: hidden; }
.trk i { display: block; height: 100%; }
.hv { text-align: end; font-weight: 800; }
.legend { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; color: var(--ms-text-muted); margin-top: 10px; }
.legend i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-inline-end: 6px; vertical-align: -1px; }
td.n, th.n { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
.tot td { font-weight: 800; border-top: 2px solid var(--ms-border); padding: 10px 14px; }
.shr { display: inline-block; width: 50px; height: 6px; border-radius: 3px; background: var(--ms-bg-hover); vertical-align: middle; overflow: hidden; margin-inline-end: 6px; }
.shr i { display: block; height: 100%; background: var(--s1, #2a78d6); }
@media (max-width: 980px) { .rep-grid { grid-template-columns: 1fr; } .filters-panel { position: static; max-height: none; } .rangebar { margin-inline-start: 0; } }
@media (max-width: 560px) { .hr { grid-template-columns: 100px 1fr 60px; } .fld-inline .inp { min-width: 0; width: 100%; } .fld-inline { flex: 1 1 140px; } }
</style>
