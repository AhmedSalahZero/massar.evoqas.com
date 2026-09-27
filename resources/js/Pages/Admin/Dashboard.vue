<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Platform Dashboard (Super Admin · Step 15, as the agreed demo)
//  Location: resources/js/Pages/Admin/Dashboard.vue
//  Route: GET /admin/dashboard?range=m1|m3|m12|all (admin.dashboard) → Admin\DashboardController
//  The numbers: App\Services\Dashboard\AdminDashboard ('dash')
//
//  Every partner together, COUNTS ONLY — never a person, a CV or a
//  result (Scope v2 §5): partners, team members, beneficiaries, CVs,
//  placements; new people by month; the occupation backbone; the Public
//  Talent Pool; rule requests waiting; one line per partner.
// ══════════════════════════════════════════════════════════════════

import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DashTooltip from '@/Components/Dashboard/DashTooltip.vue';
import { lines, spark } from '@/Components/Dashboard/charts';
import '@/Components/Dashboard/dash.css';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';

const props = defineProps({
    stats: { type: Object, required: true },
    expiring: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
    range: { type: String, default: 'm12' },
    dash: { type: Object, required: true },
});
const { t, locale } = useTranslations();
const D = computed(() => props.dash);
const K = computed(() => D.value.kpis);
const nf = (v, d = 0) => (v === null || v === undefined ? '—' : new Intl.NumberFormat(locale.value === 'ar' ? 'ar-EG' : 'en-US', { maximumFractionDigits: d, minimumFractionDigits: d }).format(v));
const monthName = (ym, long = false) => new Date(`${ym}-01T00:00:00`).toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-GB', { month: long ? 'long' : 'short', year: long ? 'numeric' : undefined });
const RANGES = ['m1', 'm3', 'm12', 'all'];
const loading = ref(false);
const setRange = (r) => router.get(route('admin.dashboard'), { range: r }, {
    preserveScroll: true, preserveState: true, only: ['dash', 'range'],
    onStart: () => { loading.value = true; }, onFinish: () => { loading.value = false; },
});
const printPage = () => window.print();
const growth = computed(() => lines({ months: D.value.months, series: { people: D.value.growth }, keys: ['people'], colors: ['var(--s1)'], names: [t('db.people')], monthName, nf }));
const totals = computed(() => D.value.partners.reduce((a, p) => ({ people: a.people + p.people, cvs: a.cvs + p.cvs, placed: a.placed + p.placed }), { people: 0, cvs: 0, placed: 0 }));
const pname = (p) => (locale.value === 'ar' && p.name_ar ? p.name_ar : p.name);
</script>

<template>
    <AdminLayout :title="t('nav.dashboard')">
        <div class="dash" :style="loading ? 'opacity:.6;transition:opacity .15s' : ''">
            <div class="page-head">
                <div>
                    <div class="page-eyebrow">{{ t('shell.platform') }}</div>
                    <h1 class="page-title">{{ t('nav.dashboard') }}</h1>
                    <div class="page-sub">{{ t('db.a_sub') }}</div>
                </div>
                <div class="actions">
                    <div class="seg" role="group" :aria-label="t('db.range')">
                        <button v-for="r in RANGES" :key="r" type="button" :aria-pressed="range === r" @click="setRange(r)">{{ t(`db.range_${r}`) }}</button>
                    </div>
                    <button type="button" class="btn btn-line" @click="printPage"><AppIcon name="file" :size="15" />{{ t('db.pdf') }}</button>
                    <Link :href="route('admin.companies.index', { new: 1 })" class="btn btn-primary"><AppIcon name="plus" :size="15" />{{ t('admin.add_partner') }}</Link>
                </div>
            </div>

            <div class="dgrid g5">
                <Link :href="route('admin.companies.index')" class="panel kpi" style="--accent:var(--ms-navy);color:inherit;text-decoration:none">
                    <div class="lbl">{{ t('db.a_partners') }}</div><div class="val num">{{ nf(K.partners) }}</div>
                    <div class="foot">{{ t('db.a_partners_f', { a: nf(K.active), e: nf(K.ending) }) }}</div>
                </Link>
                <Link :href="route('admin.activity.index')" class="panel kpi" style="--accent:var(--ms-teal);color:inherit;text-decoration:none">
                    <div class="lbl">{{ t('db.a_users') }}</div><div class="val num">{{ nf(K.users) }}</div>
                    <div class="foot">{{ t('db.a_users_f', { n: nf(K.today) }) }}</div>
                </Link>
                <div class="panel kpi" style="--accent:var(--ms-navy)">
                    <div class="lbl">{{ t('db.a_people') }}</div><div class="val num">{{ nf(K.people) }}</div>
                    <div class="foot"><span class="delta">▲ {{ nf(K.people_new) }}</span> {{ t('db.k_people_f') }}</div>
                    <div v-html="spark(D.growth.length > 1 ? D.growth : [0, ...D.growth], 'var(--s1)')" />
                </div>
                <div class="panel kpi" style="--accent:var(--ms-teal)">
                    <div class="lbl">{{ t('db.a_cvs') }}</div><div class="val num">{{ nf(K.cvs) }}</div>
                    <div class="foot">{{ K.cvs_auto === null ? t('db.k_cv_none') : t('db.k_cv_f', { p: nf(K.cvs_auto) }) }}</div>
                </div>
                <div class="panel kpi" style="--accent:var(--ms-green)">
                    <div class="lbl">{{ t('db.a_placed') }}</div><div class="val num">{{ nf(K.hired + K.completed) }}</div>
                    <div class="foot">{{ t('db.k_placed_f', { h: nf(K.hired), c: nf(K.completed) }) }}</div>
                </div>
            </div>

            <div class="dgrid g32" style="margin-top:14px">
                <div class="panel"><div class="ph"><h3>{{ t('db.a_growth') }}</h3><Link :href="route('admin.reports.index')" class="small">{{ t('nav.reports') }} →</Link></div><div v-html="growth" /></div>
                <div class="panel">
                    <h3>{{ t('db.a_backbone') }}</h3>
                    <div class="hbars" style="margin-top:6px">
                        <div class="r"><span>ENOC</span><span class="mute small">{{ t('db.a_occupations') }}</span><span class="v">{{ nf(D.backbone.enoc) }}</span></div>
                        <div class="r"><span>ISCO-08</span><span class="mute small">{{ t('db.a_groups') }}</span><span class="v">{{ nf(D.backbone.isco) }}</span></div>
                        <div class="r"><span>ESCO</span><span class="mute small">{{ t('db.a_occupations') }}</span><span class="v">{{ nf(D.backbone.esco) }}</span></div>
                        <div class="r"><span>ESCO</span><span class="mute small">{{ t('db.a_skills') }}</span><span class="v">{{ nf(D.backbone.skills) }}</span></div>
                    </div>
                    <h3 style="margin-top:16px">{{ t('db.a_pool') }}</h3>
                    <div class="two" style="margin-top:8px">
                        <span><b class="num">{{ nf(D.pool.seekers) }}</b><span class="mute">{{ t('db.a_pool_s') }}</span></span>
                        <span style="text-align:end"><b class="num">{{ nf(D.pool.added) }}</b><span class="mute">{{ t('db.a_pool_a') }}</span></span>
                    </div>
                    <div class="att" style="margin-top:14px">
                        <Link :href="route('admin.rule-requests.index')" :class="{ zero: !D.rules }"><span class="ic" :class="D.rules ? 'info' : 'ok'">{{ D.rules ? 'i' : '✓' }}</span>{{ t('db.a_rules') }}<b class="num">{{ nf(D.rules) }}</b></Link>
                    </div>
                </div>
            </div>

            <div class="panel" style="margin-top:14px">
                <div class="ph"><h3>{{ t('db.a_table') }}</h3><Link :href="route('admin.companies.index')" class="small">{{ t('nav.companies') }} →</Link></div>
                <div class="twrap"><table>
                    <thead><tr><th>{{ t('db.c_partner') }}</th><th>{{ t('db.c_type') }}</th><th>{{ t('db.c_seat') }}</th><th class="n">{{ t('db.k_people') }}</th><th class="n">{{ t('db.k_cv') }}</th><th class="n">{{ t('db.k_placed') }}</th><th>{{ t('db.c_ends') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="p in D.partners" :key="p.id">
                            <td><Link :href="route('admin.companies.index')">{{ pname(p) }}</Link><span v-if="!p.is_active" class="badge danger" style="margin-inline-start:6px">{{ t('db.inactive') }}</span></td>
                            <td class="small">{{ p.type ? t(`companies.type_${p.type}`) : '—' }}</td>
                            <td><div class="small num">{{ nf(p.users) }} / {{ nf(p.seats) }}</div><div class="meter"><i :style="`width:${Math.min(100, (p.users / (p.seats || 1)) * 100)}%`" /></div></td>
                            <td class="n">{{ nf(p.people) }}</td>
                            <td class="n">{{ nf(p.cvs) }}</td>
                            <td class="n">{{ nf(p.placed) }}</td>
                            <td><span v-if="p.ending" class="badge orange">⚠ {{ formatDate(p.ends_at, locale) }}</span><span v-else class="small">{{ p.ends_at ? formatDate(p.ends_at, locale) : t('db.no_expiry') }}</span></td>
                        </tr>
                        <tr><td><b>{{ t('db.total') }}</b></td><td /><td /><td class="n"><b>{{ nf(totals.people) }}</b></td><td class="n"><b>{{ nf(totals.cvs) }}</b></td><td class="n"><b>{{ nf(totals.placed) }}</b></td><td /></tr>
                    </tbody>
                </table></div>
            </div>
        </div>
        <DashTooltip />
    </AdminLayout>
</template>
