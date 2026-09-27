<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — MarketPanel (Egypt labour market figures)
//  Location: resources/js/Components/Backbone/MarketPanel.vue
//
//      <MarketPanel :market="market" />                       ENOC / unit page
//      <MarketPanel :market="market" :inherited="{ code, title }" />  ESCO page
//
//  Shows one ENOC occupation's figures from the CURRENT edition:
//  headline numbers, trends, employment type, wages (always with the
//  "at the time of the survey" note), sectors, regions, top-5
//  knowledge / abilities / skills and skill groups.
//
//  Rules (Scope v2 §1): a missing figure says "No data" — never 0,
//  never an estimate. Figures flagged at import (they cannot be right
//  as published) are shown exactly as published with a warning.
//  Every panel names its source and data period.
//
//  Super Admin screens get the flags and show warnings. For partners
//  the server has already REMOVED flagged figures and lists them in
//  profile.hidden; the panel then says "Under review" in their place
//  (never "No data" — the figure exists, it is being checked).
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';
import { editionName, editionPeriod } from '@/Components/Backbone/occ';

const props = defineProps({
    market: { type: Object, default: null },
    inherited: { type: Object, default: null },
});

const { t, locale } = useTranslations();

const p = computed(() => props.market?.profile ?? null);
const flags = computed(() => p.value?.flags ?? []);
const hidden = computed(() => p.value?.hidden ?? []);
const isHidden = (field) => hidden.value.includes(field);
const EMPLOYMENT_FIELDS = ['pct_formal', 'pct_regular', 'pct_paid', 'pct_public', 'pct_private'];
const employmentHidden = computed(() => EMPLOYMENT_FIELDS.some(isHidden));
const period = computed(() => editionPeriod(props.market?.edition, t));
const edName = computed(() => editionName(props.market?.edition, locale.value));

const fmt = (v, digits = 0) => (v === null || v === undefined)
    ? null
    : Number(v).toLocaleString(locale.value === 'ar' ? 'ar-EG' : 'en-US', { maximumFractionDigits: digits });
const has = (v) => v !== null && v !== undefined;

const employment = computed(() => [
    ['mk.formal', p.value?.pct_formal], ['mk.regular', p.value?.pct_regular], ['mk.paid', p.value?.pct_paid],
    ['mk.public', p.value?.pct_public], ['mk.private', p.value?.pct_private],
].filter(([, v]) => has(v)));

const wages = computed(() => [
    ['mk.wage_male', p.value?.wage_male], ['mk.wage_female', p.value?.wage_female],
    ['mk.wage_public', p.value?.wage_public], ['mk.wage_private', p.value?.wage_private],
]);

const sectors = computed(() => Object.entries(p.value?.sectors ?? {})
    .filter(([, v]) => has(v))
    .sort((a, b) => b[1] - a[1]));

const skillGroups = computed(() => Object.entries(p.value?.skill_groups ?? {})
    .filter(([, v]) => has(v))
    .sort((a, b) => b[1] - a[1]));

const ALL_REGIONS = ['cairo', 'alexandria', 'delta', 'canal', 'north_upper', 'middle_upper', 'south_upper'];
const REGIONS = computed(() => (isHidden('regions_outside_cairo') ? ['cairo'] : ALL_REGIONS));
const hi = (v) => v === 'above' || v === 'much_above';

const trendClass = (v) => ({ much_faster: 'green', faster: 'green', slower: 'orange', much_slower: 'orange', decline: 'danger' }[v] ?? 'plain');
const lists = computed(() => [['mk.knowledge', p.value?.knowledge], ['mk.abilities', p.value?.abilities], ['mk.skills', p.value?.skills]]
    .filter(([, v]) => v?.length));
const bar = (v) => `${Math.max(0, Math.min(100, Number(v)))}%`;
</script>

<template>
    <div class="panel">
        <div class="panel-h">
            <div>
                <h3><AppIcon name="trend" :size="16" />{{ t('mk.title') }}</h3>
                <div v-if="market?.edition" class="sub mb-0">{{ t('mk.source', { edition: edName, period }) }}</div>
            </div>
        </div>

        <div v-if="!market" class="alert info"><AppIcon name="info" :size="16" /><span>{{ t('mk.no_edition') }}</span></div>

        <template v-else>
            <div v-if="inherited" class="note mb-3"><AppIcon name="info" :size="15" /><span>{{ t('mk.inherited', inherited) }}</span></div>

            <div v-if="!p" class="alert info"><AppIcon name="info" :size="16" /><span>{{ t('mk.none') }}</span></div>

            <template v-else>
                <div v-if="hidden.length" class="note mb-3"><AppIcon name="info" :size="15" /><span>{{ t('mk.under_review_note') }}</span></div>

                <!-- Headline figures -->
                <div class="mk">
                    <div>
                        <b>{{ fmt(p.workers) ?? t('mk.no_data') }}</b>
                        <span>{{ t('mk.workers') }}</span>
                        <span v-if="has(p.share_of_employment)" class="xs">{{ t('mk.share', { n: fmt(p.share_of_employment, 2) }) }}</span>
                    </div>
                    <div>
                        <b>{{ has(p.wage_avg) ? `${fmt(p.wage_avg)} ${t('mk.egp')}*` : t('mk.no_data') }}</b>
                        <span>{{ t('mk.wage') }}</span>
                    </div>
                    <div>
                        <b>{{ has(p.pct_women) ? `${fmt(p.pct_women, 1)}%` : t(isHidden('pct_women') ? 'mk.under_review' : 'mk.no_data') }}</b>
                        <span>{{ t('mk.women') }}</span>
                    </div>
                    <div>
                        <b>{{ fmt(p.weekly_hours) ?? t(isHidden('weekly_hours') ? 'mk.under_review' : 'mk.no_data') }}</b>
                        <span>{{ t('mk.hours') }}</span>
                        <span v-if="flags.includes('hours')" class="xs c-danger">{{ t('mk.flag.hours') }}</span>
                    </div>
                </div>
                <div v-if="has(p.wage_avg)" class="src">* {{ t('mk.wage_note', { period }) }}</div>

                <!-- Trends and requirements -->
                <div class="kvs mt-4">
                    <div><span>{{ t('mk.trend_now') }}</span><b v-if="p.workers_trend" :class="`c-${trendClass(p.workers_trend) === 'danger' ? 'danger' : trendClass(p.workers_trend)}`">{{ t(`mk.trend.${p.workers_trend}`) }}</b><b v-else class="mute">{{ t('mk.no_data') }}</b></div>
                    <div><span>{{ t('mk.outlook') }}</span><b v-if="p.outlook_trend" :class="`c-${trendClass(p.outlook_trend) === 'danger' ? 'danger' : trendClass(p.outlook_trend)}`">{{ t(`mk.trend.${p.outlook_trend}`) }}</b><b v-else class="mute">{{ t('mk.no_data') }}</b></div>
                    <div><span>{{ t('mk.jobs_year') }}</span><b v-if="p.outlook_jobs">{{ t(`mk.jobs.${p.outlook_jobs}`) }}</b><b v-else class="mute">{{ t('mk.no_data') }}</b></div>
                    <div><span>{{ t('mk.education') }}</span><b v-if="p.education">{{ t(`mk.edu.${p.education}`) }}</b><b v-else class="mute">{{ t('mk.no_data') }}</b></div>
                    <div><span>{{ t('mk.green') }}</span><b v-if="p.green">{{ t(`mk.greenv.${p.green}`) }}</b><b v-else class="mute">{{ t('mk.no_data') }}</b></div>
                </div>

                <div class="grid-2 mt-4" style="align-items:start">
                    <!-- Employment type + wages -->
                    <div>
                        <div class="kv-label mb-2"><b>{{ t('mk.employment') }}</b></div>
                        <div v-if="flags.includes('public_private') || flags.includes('employment')" class="alert warning mb-2">
                            <AppIcon name="alert" :size="15" /><span>{{ t(flags.includes('employment') ? 'mk.flag.employment' : 'mk.flag.public_private') }}</span>
                        </div>
                        <div v-if="employmentHidden" class="small mute mb-2">{{ t('mk.under_review_part') }}</div>
                        <div v-if="employment.length" class="bars1">
                            <div v-for="[label, v] in employment" :key="label" class="r">
                                <span>{{ t(label) }}</span><span class="trk"><i :style="{ width: bar(v) }"></i></span><span class="v">{{ fmt(v, 1) }}%</span>
                            </div>
                        </div>
                        <div v-else-if="!employmentHidden" class="small mute">{{ t('mk.no_data') }}</div>

                        <div class="kv-label mt-4 mb-2"><b>{{ t('mk.wages') }}</b></div>
                        <div class="kvs">
                            <div v-for="[label, v] in wages" :key="label"><span>{{ t(label) }}</span><b :class="{ mute: !has(v) }">{{ has(v) ? fmt(v) : t('mk.no_data') }}</b></div>
                        </div>
                    </div>

                    <!-- Sectors -->
                    <div>
                        <div class="kv-label mb-2"><b>{{ t('mk.sectors') }}</b></div>
                        <div v-if="flags.includes('sectors')" class="alert warning mb-2"><AppIcon name="alert" :size="15" /><span>{{ t('mk.flag.sectors') }}</span></div>
                        <div v-if="sectors.length" class="bars1">
                            <div v-for="[key, v] in sectors" :key="key" class="r">
                                <span>{{ t(`mk.sector.${key}`) }}</span><span class="trk"><i :style="{ width: bar(v) }"></i></span><span class="v">{{ fmt(v, 1) }}%</span>
                            </div>
                        </div>
                        <div v-else class="small mute">{{ t(isHidden('sectors') ? 'mk.under_review_part' : 'mk.no_data') }}</div>
                    </div>
                </div>

                <!-- Regions -->
                <div class="kv-label mt-4 mb-2"><b>{{ t('mk.regions') }}</b></div>
                <template v-if="p.regions">
                    <div v-if="isHidden('regions_outside_cairo')" class="small mute mb-2">{{ t('mk.regions_under_review') }}</div>
                    <div v-else-if="market.regions_suspect" class="alert warning mb-2"><AppIcon name="alert" :size="15" /><span>{{ t('mk.regions_suspect') }}</span></div>
                    <div class="regions">
                        <div v-for="r in REGIONS" :key="r" :class="{ hi: hi(p.regions[r]), suspect: market.regions_suspect && r !== 'cairo' }">
                            {{ t(`mk.reg.${r}`) }}<b>{{ p.regions[r] ? t(`mk.region.${p.regions[r]}`) : t('mk.no_data') }}</b>
                        </div>
                    </div>
                </template>
                <div v-else class="small mute">{{ t('mk.no_data') }}</div>

                <!-- Knowledge, abilities, skills (Arabic in the source) -->
                <div v-if="lists.length" class="grid-3 mt-4" style="align-items:start">
                    <div v-for="[label, items] in lists" :key="label">
                        <div class="kv-label mb-2"><b>{{ t(label) }}</b></div>
                        <ol class="top5" dir="rtl" lang="ar"><li v-for="i in items" :key="i">{{ i }}</li></ol>
                    </div>
                </div>
                <div v-if="lists.length && locale !== 'ar'" class="src">{{ t('mk.arabic_only') }}</div>

                <!-- Skill groups -->
                <template v-if="skillGroups.length">
                    <div class="kv-label mt-4 mb-2"><b>{{ t('mk.skill_groups') }}</b></div>
                    <div class="sg">
                        <div v-for="[key, v] in skillGroups" :key="key" class="r">
                            <span>{{ t(`mk.sg.${key}`) }}</span><span class="trk"><i :style="{ width: bar(v) }"></i></span><span class="v">{{ fmt(v) }}</span>
                        </div>
                    </div>
                </template>
            </template>
        </template>
    </div>
</template>

<style scoped>
.mk .xs { display: block; font-size: 10.5px; margin-top: 2px; }
.bars1 { display: grid; gap: 5px; }
.bars1 .r { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(60px, 1fr) 48px; gap: 8px; align-items: center; font-size: 12px; }
.bars1 .trk, .sg .trk { height: 6px; border-radius: 3px; background: var(--ms-bg-hover); overflow: hidden; }
.bars1 .trk i { display: block; height: 100%; background: var(--ms-navy); }
.bars1 .v { text-align: end; font-weight: 700; font-variant-numeric: tabular-nums; }
.regions .suspect { opacity: .55; border: 1px dashed var(--ms-orange-border); }
.top5 { margin: 0; padding-inline-start: 20px; font-size: 12.5px; line-height: 1.8; }
@media (max-width: 900px) { .mk { grid-template-columns: repeat(2, 1fr); } .regions { grid-template-columns: repeat(4, 1fr); } }
</style>
