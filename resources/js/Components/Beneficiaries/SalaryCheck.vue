<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — SalaryCheck (expected salary vs the Egypt market)
//  Location: resources/js/Components/Beneficiaries/SalaryCheck.vue
//
//      <SalaryCheck :salary="salary" :market="market" />
//
//  `salary` comes from App\Services\Beneficiaries\SalaryCheck: the
//  expected monthly salary compared with the market average and the
//  private-sector average of the person's occupation (below / close /
//  above, ± the configured percentage).
//
//  The market wages are survey averages from the edition's data
//  period — the note under the result ALWAYS says so, because older
//  figures make most of today's expectations look "above".
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';
import { editionPeriod } from '@/Components/Backbone/occ';
import { money } from '@/Components/Beneficiaries/ben';

const props = defineProps({
    salary: { type: Object, required: true },
    market: { type: Object, default: null },
});

const { t, locale } = useTranslations();
const period = computed(() => editionPeriod(props.market?.edition, t));
const fmt = (v) => money(v, locale.value);
const band = { below: 'navy', close: 'green', above: 'orange' };

// Points on one line: 0 … a little past the largest figure.
const points = computed(() => {
    const s = props.salary;
    const all = [{ key: 'expected', value: s.expected }, ...s.references.map((r) => ({ key: r.key, value: r.value }))];
    const max = Math.max(...all.map((p) => p.value)) * 1.15;
    return all.map((p) => ({ ...p, pos: Math.max(4, Math.min(96, (p.value / max) * 100)) }));
});
const color = { expected: 'var(--ms-teal)', avg: 'var(--ms-navy)', private: 'var(--ms-purple)' };
const sign = (n) => (n > 0 ? `+${n}` : `${n}`);
</script>

<template>
    <div class="panel">
        <h3><AppIcon name="chart" :size="16" />{{ t('ben.salary_title') }}</h3>
        <div class="sub">{{ t('ben.salary_sub') }}</div>

        <div v-if="salary.status === 'no_occupation'" class="empty-row">{{ t('ben.salary_no_occ') }}</div>
        <div v-else-if="salary.status === 'no_expected'" class="empty-row">{{ t('ben.salary_no_expected') }}</div>
        <div v-else-if="salary.status === 'no_wage'" class="empty-row">
            {{ t('ben.salary_expected_is', { n: fmt(salary.expected) }) }} {{ t('ben.salary_no_wage') }}
        </div>

        <template v-else>
            <div class="kvs">
                <div><span>{{ t('ben.salary_expected') }}</span><b>{{ fmt(salary.expected) }} {{ t('mk.egp') }}</b></div>
                <div v-for="r in salary.references" :key="r.key">
                    <span>{{ t(`ben.salary_ref_${r.key}`) }}</span>
                    <b>{{ fmt(r.value) }} {{ t('mk.egp') }}</b>
                    <span class="mt-1"><span class="badge" :class="band[r.band]">{{ t(`ben.band_${r.band}`, { n: sign(r.diff_pct) }) }}</span></span>
                </div>
            </div>

            <div class="cmp" dir="ltr" aria-hidden="true">
                <div class="ln">
                    <span v-for="p in points" :key="p.key" class="pt" :style="{ left: `${p.pos}%`, color: color[p.key] }">{{ fmt(p.value) }}</span>
                </div>
                <div class="legend-row" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
                    <span v-for="p in points" :key="p.key"><i :style="{ background: color[p.key] }"></i>{{ t(p.key === 'expected' ? 'ben.salary_expected' : `ben.salary_ref_${p.key}`) }}</span>
                </div>
            </div>

            <div class="note mt-3"><AppIcon name="info" :size="15" /><span>{{ t('ben.salary_note', { period, pct: salary.close_pct }) }}</span></div>
        </template>
    </div>
</template>

<style scoped>
.kvs .mt-1 { display: block; margin-top: 6px; }
.legend-row { display: flex; gap: 14px; flex-wrap: wrap; font-size: 11.5px; color: var(--ms-text-muted); }
.legend-row i { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-inline-end: 5px; }
</style>
